<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\IncrementalUpdate;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfName;
use IntrAgile\EInvoicing\Pdf\PdfRef;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Pdf\PdfString;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use RuntimeException;

/**
 * Embeds a CII invoice into an existing PDF/A as a Factur-X / ZUGFeRD hybrid document.
 *
 * The PDF is never rewritten: the invoice is added by an incremental update, so the original
 * bytes stay a prefix of the result. The update holds the XML as a PDF/A-3 associated file
 * (catalog /AF and /Names /EmbeddedFiles), XMP metadata with the PDF/A-3 identification and the
 * Factur-X extension schema, an sRGB OutputIntent when the PDF has none, and a copy of the Info
 * dictionary with a synchronised /ModDate.
 *
 * The PDF itself is not converted to PDF/A: an input that declares no PDF/A conformance is
 * embedded into with a warning, or refused in strict mode.
 */
final class FacturXPdfWriter
{
    /** File names under which an invoice may already be embedded, compared case-insensitively. */
    public const array INVOICE_NAMES = ['factur-x.xml', 'xrechnung.xml', 'zugferd-invoice.xml', 'ZUGFeRD-invoice.xml'];

    private const string ICC_PROFILE = __DIR__.'/../../../resources/icc/sRGB2014.icc';

    private const string OUTPUT_CONDITION = 'sRGB IEC61966-2.1';

    /** Bounds on walking an existing /EmbeddedFiles name tree. */
    private const int MAX_TREE_DEPTH = 32;

    private const int MAX_TREE_NODES = 10_000;

    /** PDFDocEncoding code points that differ from ISO-8859-1 (ISO 32000-1, Annex D). */
    private const array PDF_DOC_ENCODING = [
        0x18 => 0x02D8, 0x19 => 0x02C7, 0x1A => 0x02C6, 0x1B => 0x02D9, 0x1C => 0x02DD, 0x1D => 0x02DB, 0x1E => 0x02DA, 0x1F => 0x02DC,
        0x80 => 0x2022, 0x81 => 0x2020, 0x82 => 0x2021, 0x83 => 0x2026, 0x84 => 0x2014, 0x85 => 0x2013, 0x86 => 0x0192, 0x87 => 0x2044,
        0x88 => 0x2039, 0x89 => 0x203A, 0x8A => 0x2212, 0x8B => 0x2030, 0x8C => 0x201E, 0x8D => 0x201C, 0x8E => 0x201D, 0x8F => 0x2018,
        0x90 => 0x2019, 0x91 => 0x201A, 0x92 => 0x2122, 0x93 => 0xFB01, 0x94 => 0xFB02, 0x95 => 0x0141, 0x96 => 0x0152, 0x97 => 0x0160,
        0x98 => 0x0178, 0x99 => 0x017D, 0x9A => 0x0131, 0x9B => 0x0142, 0x9C => 0x0153, 0x9D => 0x0161, 0x9E => 0x017E, 0xA0 => 0x20AC,
    ];

    /**
     * @param  string  $pdf  the PDF/A document to embed into (untrusted bytes)
     * @param  string  $xml  the CII invoice
     * @param  FacturXProfile|null  $profile  the expected profile; null takes the one the XML declares
     * @param  AfRelationship|null  $relationship  null takes the profile's default
     * @param  DateTimeImmutable|null  $modified  the modification date written to the PDF; fixing
     *                                            it makes the output reproducible
     * @param  bool  $strict  refuse an input that declares no PDF/A conformance instead of warning
     *
     * @throws ProfileMismatch when the XML declares another profile than $profile
     * @throws UnsupportedDocument when the XML is not CII or declares no known profile
     * @throws UnsafeXml when the XML or the PDF's XMP metadata carries a DOCTYPE declaration
     * @throws EncryptedPdf when the PDF is encrypted
     * @throws UnreadablePdf when the PDF cannot be read, or its cross-reference index had to be
     *                       reconstructed so an incremental update would be unsafe
     * @throws InvoiceAlreadyEmbedded when the PDF already carries an invoice
     * @throws NotPdfA in strict mode, when the PDF declares no PDF/A conformance
     * @throws UnsupportedPdfAPart when the PDF declares PDF/A-4 or a later part
     */
    public function embed(
        string $pdf,
        string $xml,
        ?FacturXProfile $profile = null,
        ?AfRelationship $relationship = null,
        ?DateTimeImmutable $modified = null,
        bool $strict = false,
    ): EmbedResult {
        $declared = self::declaredProfile($xml);
        if ($profile !== null && $profile !== $declared) {
            throw new ProfileMismatch(sprintf(
                'The XML declares the %s profile (guideline identifier %s), not the requested %s profile.',
                $declared->value,
                $declared->guidelineId(),
                $profile->value,
            ));
        }
        $profile = $declared;
        $relationship ??= $profile->defaultRelationship();
        $modified ??= new DateTimeImmutable;

        $document = PdfDocument::parse($pdf);
        $update = new IncrementalUpdate($document, md5($pdf.$xml.$modified->format(DATE_ATOM), true));
        $catalog = $document->catalog();
        $root = $document->trailer()->get('Root');
        if (! $root instanceof PdfRef) {
            throw UnreadablePdf::malformed('The PDF trailer has no /Root reference.');
        }

        $af = self::listOf($document->resolve($catalog->get('AF')));
        $names = $document->resolve($catalog->get('Names'));
        $names = $names instanceof PdfDictionary ? $names : new PdfDictionary;
        $tree = $document->resolve($names->get('EmbeddedFiles'));
        $tree = $tree instanceof PdfDictionary ? $tree : null;
        $existing = $tree === null ? [] : self::collectNames($document, $tree);
        self::refuseExistingInvoice($document, $existing, $af);

        $existingXmp = self::existingXmp($document, $catalog);
        $warnings = self::warnings($document, $catalog, Xmp::declaredPart($existingXmp), $strict);

        $info = $document->resolve($document->trailer()->get('Info'));
        $info = $info instanceof PdfDictionary ? $info : null;
        $pdfDate = new PdfString(self::pdfDate($modified));
        $name = $profile->attachmentName();

        $file = $update->add(new PdfStream(new PdfDictionary([
            'Type' => new PdfName('EmbeddedFile'),
            'Subtype' => new PdfName('text/xml'),
            'Params' => new PdfDictionary(['ModDate' => $pdfDate, 'Size' => strlen($xml)]),
            'Filter' => new PdfName('FlateDecode'),
        ]), self::deflate($xml)));
        $filespec = $update->add(new PdfDictionary([
            'Type' => new PdfName('Filespec'),
            'F' => new PdfString($name),
            'UF' => new PdfString($name),
            'Desc' => new PdfString($profile === FacturXProfile::XRechnung ? 'XRechnung Invoice' : 'Factur-X Invoice'),
            'AFRelationship' => new PdfName($relationship->value),
            'EF' => new PdfDictionary(['F' => $file, 'UF' => $file]),
        ]));
        $metadata = $update->add(new PdfStream(new PdfDictionary([
            'Type' => new PdfName('Metadata'),
            'Subtype' => new PdfName('XML'),
        ]), Xmp::merge($existingXmp, $profile, $name, $modified, $info === null ? [] : self::infoText($info))));

        $catalog = $catalog
            ->with('AF', [...$af, $filespec])
            ->with('Names', $names->with('EmbeddedFiles', $this->mergedTree($update, $tree, $existing, new PdfString($name), $filespec)))
            ->with('Metadata', $metadata);
        if (! $catalog->has('OutputIntents')) {
            $catalog = $catalog->with('OutputIntents', [$this->outputIntent($update)]);
        }
        $version = $catalog->get('Version');
        $effective = $version instanceof PdfName && version_compare($version->name, $document->version(), '>') ? $version->name : $document->version();
        if (version_compare($effective, '1.7', '<')) {
            $catalog = $catalog->with('Version', new PdfName('1.7'));
        }
        $update->replace($root->number, $catalog);

        $extra = new PdfDictionary;
        if ($info !== null) {
            $extra = $extra->with('Info', $update->add($info->with('ModDate', $pdfDate)));
        }

        return new EmbedResult($update->toBytes($extra), $profile, $relationship, $warnings);
    }

    private static function declaredProfile(string $xml): FacturXProfile
    {
        $id = (new CiiDocument(SafeXml::load($xml)))->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        if ($id === null) {
            throw new UnsupportedDocument('The XML has no guideline identifier (BT-24), so its Factur-X profile is unknown.');
        }

        return FacturXProfile::fromGuidelineId($id)
            ?? throw new UnsupportedDocument(sprintf('The guideline identifier "%s" (BT-24) is not a supported Factur-X / ZUGFeRD profile.', trim($id)));
    }

    /**
     * @param  list<array{PdfString, mixed}>  $existing
     * @param  list<mixed>  $af
     */
    private static function refuseExistingInvoice(PdfDocument $document, array $existing, array $af): void
    {
        $candidates = [];
        foreach ($existing as [$key, $value]) {
            $candidates[] = self::text($key);
            $candidates = [...$candidates, ...self::filespecNames($document->resolve($value))];
        }
        foreach ($af as $value) {
            $candidates = [...$candidates, ...self::filespecNames($document->resolve($value))];
        }
        $invoiceNames = array_map(strtolower(...), self::INVOICE_NAMES);
        foreach ($candidates as $candidate) {
            if (in_array(strtolower(trim($candidate)), $invoiceNames, true)) {
                throw new InvoiceAlreadyEmbedded(sprintf('The PDF already carries an embedded invoice named "%s"; a second one is not added.', $candidate));
            }
        }
    }

    /** @return list<string> */
    private static function filespecNames(mixed $filespec): array
    {
        if (! $filespec instanceof PdfDictionary) {
            return [];
        }
        $names = [];
        foreach (['UF', 'F'] as $key) {
            $value = $filespec->get($key);
            if ($value instanceof PdfString) {
                $names[] = self::text($value);
            }
        }

        return $names;
    }

    /**
     * Every key and value of a name tree, depth-first, with bounded depth and node count.
     *
     * @return list<array{PdfString, mixed}>
     */
    private static function collectNames(PdfDocument $document, PdfDictionary $root): array
    {
        $pairs = [];
        $nodes = 0;
        $stack = [[$root, 0]];
        $visited = [];
        while ($stack !== []) {
            [$node, $depth] = array_pop($stack);
            if (++$nodes > self::MAX_TREE_NODES || $depth > self::MAX_TREE_DEPTH) {
                throw UnreadablePdf::refused('The /EmbeddedFiles name tree is too large or too deep.');
            }
            $names = self::listOf($document->resolve($node->get('Names')));
            for ($i = 0; $i + 1 < count($names); $i += 2) {
                if ($names[$i] instanceof PdfString) {
                    $pairs[] = [$names[$i], $names[$i + 1]];
                }
            }
            $kids = self::listOf($document->resolve($node->get('Kids')));
            foreach (array_reverse($kids) as $kid) {
                if ($kid instanceof PdfRef) {
                    if (isset($visited[$kid->number])) {
                        throw UnreadablePdf::refused('The /EmbeddedFiles name tree contains a cycle.');
                    }
                    $visited[$kid->number] = true;
                }
                $child = $document->resolve($kid);
                if ($child instanceof PdfDictionary) {
                    $stack[] = [$child, $depth + 1];
                }
            }
        }

        return $pairs;
    }

    /**
     * The new /EmbeddedFiles root. A flat tree stays flat, with the new entry merged in name
     * order. A tree with /Kids keeps its nodes: the old root becomes one kid and a leaf with the
     * new entry the other; when the new name falls inside the old tree's key range, which kids
     * may not overlap, the tree is flattened instead.
     *
     * @param  list<array{PdfString, mixed}>  $existing
     */
    private function mergedTree(IncrementalUpdate $update, ?PdfDictionary $tree, array $existing, PdfString $name, PdfRef $filespec): PdfDictionary
    {
        $pairs = [...$existing, [$name, $filespec]];
        usort($pairs, static fn (array $a, array $b): int => strcmp($a[0]->bytes, $b[0]->bytes));
        $flat = new PdfDictionary(['Names' => array_merge(...array_map(static fn (array $pair): array => [$pair[0], $pair[1]], $pairs))]);

        if ($tree === null || self::listOf($tree->get('Kids')) === [] || $existing === []) {
            return $flat;
        }
        $keys = array_map(static fn (array $pair): string => $pair[0]->bytes, $existing);
        sort($keys, SORT_STRING);
        $low = $keys[0];
        $high = $keys[count($keys) - 1];
        if (strcmp($name->bytes, $low) >= 0 && strcmp($name->bytes, $high) <= 0) {
            return $flat;
        }

        $old = $update->add($tree->with('Limits', [new PdfString($low), new PdfString($high)]));
        $leaf = $update->add(new PdfDictionary(['Limits' => [$name, $name], 'Names' => [$name, $filespec]]));

        return new PdfDictionary(['Kids' => strcmp($name->bytes, $low) < 0 ? [$leaf, $old] : [$old, $leaf]]);
    }

    private function outputIntent(IncrementalUpdate $update): PdfRef
    {
        $icc = @file_get_contents(self::ICC_PROFILE);
        if ($icc === false) {
            throw new RuntimeException('The bundled sRGB ICC profile is missing: '.self::ICC_PROFILE);
        }
        $profile = $update->add(new PdfStream(new PdfDictionary(['N' => 3, 'Filter' => new PdfName('FlateDecode')]), self::deflate($icc)));

        return $update->add(new PdfDictionary([
            'Type' => new PdfName('OutputIntent'),
            'S' => new PdfName('GTS_PDFA1'),
            'OutputConditionIdentifier' => new PdfString(self::OUTPUT_CONDITION),
            'Info' => new PdfString(self::OUTPUT_CONDITION),
            'RegistryName' => new PdfString('http://www.color.org'),
            'DestOutputProfile' => $profile,
        ]));
    }

    private static function existingXmp(PdfDocument $document, PdfDictionary $catalog): ?string
    {
        $metadata = $document->resolve($catalog->get('Metadata'));

        return $metadata instanceof PdfStream ? $document->decodedStream($metadata) : null;
    }

    /**
     * @return list<string>
     *
     * @throws NotPdfA
     * @throws UnsupportedPdfAPart
     */
    private static function warnings(PdfDocument $document, PdfDictionary $catalog, ?int $part, bool $strict): array
    {
        $warnings = [];
        if ($part === null) {
            $message = 'The input PDF declares no PDF/A conformance (its XMP metadata has no pdfaid:part). Factur-X requires '
                .'PDF/A-3; the declaration was added, but the PDF was not converted and will not pass PDF/A validation unless '
                .'its content already conforms.';
            if ($strict) {
                throw new NotPdfA('The input PDF declares no PDF/A conformance (no pdfaid:part in its XMP metadata); supply a PDF/A document.');
            }
            $warnings[] = $message;
        } elseif ($part > 3) {
            throw new UnsupportedPdfAPart(sprintf(
                'The input PDF declares PDF/A-%d. A PDF/A-4 or later document cannot be lowered to PDF/A-3, which Factur-X requires; supply a PDF/A-3 (or PDF/A-1 or PDF/A-2) document.',
                $part,
            ));
        } elseif ($part !== 3) {
            $warnings[] = sprintf('The input PDF declared PDF/A-%d; the declaration was changed to PDF/A-3, which embedded files require. Validate the result.', $part);
        }

        $form = $document->resolve($catalog->get('AcroForm'));
        $flags = $form instanceof PdfDictionary ? $document->resolve($form->get('SigFlags')) : null;
        if ($catalog->has('Perms') || (is_int($flags) && $flags >= 1)) {
            $warnings[] = 'The input PDF is signed. The incremental update leaves the signed bytes unchanged, but a validator may '
                .'report the document as changed after signing; sign after embedding the invoice where possible.';
        }

        return $warnings;
    }

    /** @return array<string, string> */
    private static function infoText(PdfDictionary $info): array
    {
        $text = [];
        foreach (['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer'] as $key) {
            $value = $info->get($key);
            if ($value instanceof PdfString) {
                $text[$key] = self::text($value);
            }
        }
        $created = $info->get('CreationDate');
        if ($created instanceof PdfString && ($iso = self::isoDate($created->bytes)) !== null) {
            $text['CreationDate'] = $iso;
        }

        return $text;
    }

    /** A PDF text string as UTF-8: UTF-16BE or UTF-8 with a byte order mark, PDFDocEncoding otherwise. */
    private static function text(PdfString $string): string
    {
        $bytes = $string->bytes;
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return mb_scrub(substr($bytes, 3), 'UTF-8');
        }
        $out = '';
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($bytes[$i]);
            $out .= mb_chr(self::PDF_DOC_ENCODING[$byte] ?? $byte, 'UTF-8');
        }

        return $out;
    }

    /** A PDF date ("D:YYYYMMDDHHmmSSOHH'mm'", trailing parts optional) as an XMP date, or null. */
    private static function isoDate(string $date): ?string
    {
        if (preg_match("/^D:(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?(?:(Z)|([+-])(\d{2})(?:'?(\d{2}))?)?/", $date, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }
        $iso = sprintf('%s-%s-%sT%s:%s:%s', $m[1], $m[2] ?? '01', $m[3] ?? '01', $m[4] ?? '00', $m[5] ?? '00', $m[6] ?? '00');
        if ($m[7] !== null) {
            return $iso.'Z';
        }
        if ($m[8] !== null) {
            return $iso.$m[8].$m[9].':'.($m[10] ?? '00');
        }

        return $iso;
    }

    private static function pdfDate(DateTimeImmutable $date): string
    {
        return 'D:'.$date->format('YmdHis').str_replace(':', "'", $date->format('P'))."'";
    }

    private static function deflate(string $data): string
    {
        $compressed = gzcompress($data);
        if ($compressed === false) {
            throw new RuntimeException('zlib compression failed.');
        }

        return $compressed;
    }

    /** @return list<mixed> */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}

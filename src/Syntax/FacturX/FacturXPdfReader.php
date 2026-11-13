<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;
use IntrAgile\EInvoicing\Pdf\NameTree;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfName;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Pdf\PdfString;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;

/**
 * Extracts the CII invoice embedded in a Factur-X / ZUGFeRD 2.x hybrid PDF and identifies it.
 *
 * The invoice is chosen in this order of precedence:
 * 1. the file name: factur-x.xml, then xrechnung.xml, then zugferd-invoice.xml (compared
 *    case-insensitively);
 * 2. the location: a file specification in the catalog's /AF array before one in the
 *    /EmbeddedFiles name tree;
 * 3. the name entry: a match on /UF before a match on /F;
 * 4. the order within the /AF array or the name tree.
 * ZUGFeRD 1.0 documents use another CII version and are refused.
 */
final class FacturXPdfReader
{
    /** Invoice file names in order of precedence. */
    private const array NAMES = ['factur-x.xml', 'xrechnung.xml', 'zugferd-invoice.xml'];

    /** The ZUGFeRD 1.0 file name, which differs from the 2.x one only in case. */
    private const string ZUGFERD_1_NAME = 'ZUGFeRD-invoice.xml';

    private const string CII_NAMESPACE = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    /**
     * @param  string  $pdf  the hybrid PDF (untrusted bytes)
     *
     * @throws UnsupportedDocument when the PDF carries no Factur-X / ZUGFeRD 2.x invoice, carries a
     *                             ZUGFeRD 1.0 invoice, or the embedded file is not a CII invoice
     * @throws UnsafeXml when the embedded XML carries a DOCTYPE declaration
     * @throws EncryptedPdf when the PDF is encrypted
     * @throws UnreadablePdf when the PDF cannot be read or breaches a safety limit
     * @throws UnsupportedPdfFeature when the embedded file uses a filter other than FlateDecode
     */
    public function read(string $pdf): FacturXDocument
    {
        $document = PdfDocument::parse($pdf);
        $catalog = $document->catalog();
        $filespecs = self::filespecs($document, $catalog);
        [$filespec, $name] = self::invoiceFilespec($filespecs);

        $xml = self::embeddedData($document, $filespec, $name);
        $dom = SafeXml::load($xml);
        $root = $dom->documentElement;
        if ($root === null || $root->localName === 'CrossIndustryDocument' || ($name === self::ZUGFERD_1_NAME && $root->localName !== 'CrossIndustryInvoice')) {
            throw new UnsupportedDocument(sprintf('The embedded invoice "%s" is a ZUGFeRD 1.0 document; ZUGFeRD 1.0 is not supported.', $name));
        }
        if ($root->namespaceURI !== self::CII_NAMESPACE || $root->localName !== 'CrossIndustryInvoice') {
            throw new UnsupportedDocument(sprintf('The embedded file "%s" is not a CII invoice (its root element is {%s}%s).', $name, (string) $root->namespaceURI, $root->localName));
        }

        $warnings = [];
        $id = (new CiiDocument($dom))->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');
        $profile = $id === null ? null : FacturXProfile::fromGuidelineId($id);
        if ($id === null) {
            $warnings[] = 'The embedded XML has no guideline identifier (BT-24), so its profile is unknown.';
        } elseif ($profile === null) {
            $warnings[] = sprintf('The embedded XML declares the guideline identifier "%s" (BT-24), which is not a supported Factur-X / ZUGFeRD profile.', trim($id));
        }

        $relationship = null;
        $value = $document->resolve($filespec->get('AFRelationship'));
        if (! $value instanceof PdfName) {
            $warnings[] = sprintf('The embedded invoice "%s" has no /AFRelationship.', $name);
        } elseif (($relationship = AfRelationship::tryFrom($value->name)) === null) {
            $warnings[] = sprintf('The embedded invoice "%s" has the /AFRelationship %s; Factur-X expects Data, Alternative or Source.', $name, $value->name);
        }

        [$level, $part, $xmpWarnings] = self::xmp($document, $catalog, $profile);

        return new FacturXDocument($xml, $name, $profile, $relationship, $level, $part, [...$warnings, ...$xmpWarnings]);
    }

    /**
     * The file specifications of the catalog's /AF array and of the /EmbeddedFiles name tree,
     * each with the names it is known by (/UF, /F, and the tree key).
     *
     * @return array{list<array{PdfDictionary, array{UF: ?string, F: ?string, key: ?string}}>, list<array{PdfDictionary, array{UF: ?string, F: ?string, key: ?string}}>}
     */
    private static function filespecs(PdfDocument $document, PdfDictionary $catalog): array
    {
        $af = [];
        $list = $document->resolve($catalog->get('AF'));
        foreach (is_array($list) ? $list : [] as $value) {
            $filespec = $document->resolve($value);
            if ($filespec instanceof PdfDictionary) {
                $af[] = [$filespec, self::namesOf($document, $filespec, null)];
            }
        }
        $inTree = [];
        $names = $document->resolve($catalog->get('Names'));
        $tree = $names instanceof PdfDictionary ? $document->resolve($names->get('EmbeddedFiles')) : null;
        if ($tree instanceof PdfDictionary) {
            foreach (NameTree::entries($document, $tree) as [$key, $value]) {
                $filespec = $document->resolve($value);
                if ($filespec instanceof PdfDictionary) {
                    $inTree[] = [$filespec, self::namesOf($document, $filespec, $key->text())];
                }
            }
        }

        return [$af, $inTree];
    }

    /** @return array{UF: ?string, F: ?string, key: ?string} */
    private static function namesOf(PdfDocument $document, PdfDictionary $filespec, ?string $key): array
    {
        $names = ['UF' => null, 'F' => null, 'key' => $key];
        foreach (['UF', 'F'] as $entry) {
            $value = $document->resolve($filespec->get($entry));
            if ($value instanceof PdfString) {
                $names[$entry] = trim($value->text());
            }
        }

        return $names;
    }

    /**
     * @param  array{list<array{PdfDictionary, array{UF: ?string, F: ?string, key: ?string}}>, list<array{PdfDictionary, array{UF: ?string, F: ?string, key: ?string}}>}  $locations
     * @return array{PdfDictionary, string}
     *
     * @throws UnsupportedDocument
     */
    private static function invoiceFilespec(array $locations): array
    {
        foreach (self::NAMES as $wanted) {
            foreach ($locations as $filespecs) {
                foreach (['UF', 'F'] as $entry) {
                    foreach ($filespecs as [$filespec, $names]) {
                        if ($names[$entry] !== null && strtolower($names[$entry]) === $wanted) {
                            return [$filespec, $names[$entry]];
                        }
                    }
                }
            }
        }

        $seen = [];
        foreach ([...$locations[0], ...$locations[1]] as [, $names]) {
            foreach ($names as $name) {
                if ($name !== null && $name !== '') {
                    $seen[$name] = true;
                }
            }
        }
        if ($seen === []) {
            throw new UnsupportedDocument('The PDF has no embedded files, so it carries no Factur-X / ZUGFeRD invoice.');
        }

        throw new UnsupportedDocument(sprintf(
            'The PDF carries no Factur-X / ZUGFeRD invoice (an embedded file named %s); its embedded files are %s.',
            implode(', ', self::NAMES),
            implode(', ', array_map(static fn (string $name): string => '"'.$name.'"', array_keys($seen))),
        ));
    }

    /**
     * @throws UnsupportedDocument when the file specification holds no embedded file stream
     */
    private static function embeddedData(PdfDocument $document, PdfDictionary $filespec, string $name): string
    {
        $files = $document->resolve($filespec->get('EF'));
        if ($files instanceof PdfDictionary) {
            foreach (['UF', 'F'] as $entry) {
                $stream = $document->resolve($files->get($entry));
                if ($stream instanceof PdfStream) {
                    return $document->decodedStream($stream);
                }
            }
        }

        throw new UnsupportedDocument(sprintf('The file specification of "%s" holds no embedded file stream.', $name));
    }

    /**
     * The XMP conformance level and PDF/A part, with the warnings about them. Damaged or unsafe
     * metadata is reported as a warning, since the invoice does not depend on it.
     *
     * @return array{?string, ?int, list<string>}
     *
     * @throws UnreadablePdf when reading the metadata breaches a safety limit
     */
    private static function xmp(PdfDocument $document, PdfDictionary $catalog, ?FacturXProfile $profile): array
    {
        try {
            $metadata = $document->resolve($catalog->get('Metadata'));
            if (! $metadata instanceof PdfStream) {
                return [null, null, ['The PDF has no XMP metadata, so it declares neither its PDF/A conformance nor a Factur-X conformance level.']];
            }
            $xmp = $document->decodedStream($metadata);
            $level = Xmp::declaredConformanceLevel($xmp);
            $part = Xmp::declaredPart($xmp);
        } catch (UnsafeXml|UnsupportedPdfFeature $e) {
            return [null, null, ['The XMP metadata was ignored: '.$e->getMessage()]];
        } catch (UnreadablePdf $e) {
            // A breached safety limit is never tolerated; damaged metadata only loses its data.
            if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                throw $e;
            }

            return [null, null, ['The XMP metadata was ignored because it cannot be read: '.$e->getMessage()]];
        }

        $warnings = [];
        if ($part !== 3) {
            $warnings[] = $part === null
                ? 'The XMP metadata declares no PDF/A part; Factur-X requires PDF/A-3.'
                : sprintf('The XMP metadata declares PDF/A-%d; Factur-X requires PDF/A-3.', $part);
        }
        if ($level === null) {
            $warnings[] = 'The XMP metadata declares no fx:ConformanceLevel.';
        } elseif ($profile !== null && $level !== $profile->value) {
            $warnings[] = sprintf(
                'The XMP metadata declares the conformance level "%s", but the embedded XML declares the %s profile (guideline identifier %s); the XML is authoritative.',
                $level,
                $profile->value,
                $profile->guidelineId(),
            );
        }

        return [$level, $part, $warnings];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use DateTimeImmutable;
use ErrorException;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfName;
use IntrAgile\EInvoicing\Pdf\PdfRef;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Pdf\PdfString;
use IntrAgile\EInvoicing\Syntax\FacturX\AfRelationship;
use IntrAgile\EInvoicing\Syntax\FacturX\EmbedResult;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\InvoiceAlreadyEmbedded;
use IntrAgile\EInvoicing\Syntax\FacturX\NotPdfA;
use IntrAgile\EInvoicing\Syntax\FacturX\ProfileMismatch;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use PHPUnit\Framework\TestCase;

final class FacturXPdfWriterTest extends TestCase
{
    private const string XMP_PDFA3 = '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        .'<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        .'<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"><pdfaid:part>3</pdfaid:part><pdfaid:conformance>A</pdfaid:conformance></rdf:Description>'
        .'<rdf:Description rdf:about="" xmlns:custom="urn:example:custom"><custom:Thing>kept</custom:Thing></rdf:Description>'
        .'</rdf:RDF></x:xmpmeta><?xpacket end="w"?>';

    private const string XMP_PDFA1 = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        .'<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"><pdfaid:part>1</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance></rdf:Description>'
        .'</rdf:RDF></x:xmpmeta>';

    private const string XMP_PLAIN = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        .'<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:format>application/pdf</dc:format></rdf:Description>'
        .'</rdf:RDF></x:xmpmeta>';

    /** @var array<string, string> */
    private static array $xml = [];

    protected function setUp(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_attachment_name_and_relationship_per_profile(): void
    {
        $cases = [
            [FacturXProfile::En16931, null, 'factur-x.xml', 'Alternative', 'Factur-X Invoice'],
            [FacturXProfile::Minimum, null, 'factur-x.xml', 'Data', 'Factur-X Invoice'],
            [FacturXProfile::XRechnung, null, 'xrechnung.xml', 'Alternative', 'XRechnung Invoice'],
            [FacturXProfile::En16931, AfRelationship::Source, 'factur-x.xml', 'Source', 'Factur-X Invoice'],
        ];
        foreach ($cases as [$profile, $relationship, $name, $expectedRelationship, $description]) {
            $pdf = $this->basePdf();
            $xml = self::xml($profile);

            $result = (new FacturXPdfWriter)->embed($pdf, $xml, relationship: $relationship, modified: $this->modified());

            self::assertSame($profile, $result->profile);
            self::assertSame($expectedRelationship, $result->relationship->value);
            $out = $this->reparse($pdf, $result);
            $catalog = $out->catalog();
            $af = $catalog->get('AF');
            self::assertIsArray($af);
            self::assertCount(1, $af);
            $filespec = $out->resolve($af[0]);
            self::assertInstanceOf(PdfDictionary::class, $filespec);
            self::assertEquals(new PdfName('Filespec'), $filespec->get('Type'));
            self::assertEquals(new PdfString($name), $filespec->get('F'));
            self::assertEquals(new PdfString($name), $filespec->get('UF'));
            self::assertEquals(new PdfString($description), $filespec->get('Desc'));
            self::assertEquals(new PdfName($expectedRelationship), $filespec->get('AFRelationship'));

            $ef = $out->resolve($filespec->get('EF'));
            self::assertInstanceOf(PdfDictionary::class, $ef);
            self::assertEquals($ef->get('F'), $ef->get('UF'));
            $file = $out->resolve($ef->get('F'));
            self::assertInstanceOf(PdfStream::class, $file);
            self::assertEquals(new PdfName('EmbeddedFile'), $file->dictionary->get('Type'));
            self::assertEquals(new PdfName('text/xml'), $file->dictionary->get('Subtype'));
            self::assertEquals(new PdfName('FlateDecode'), $file->dictionary->get('Filter'));
            self::assertSame($xml, $out->decodedStream($file));
            $params = $out->resolve($file->dictionary->get('Params'));
            self::assertInstanceOf(PdfDictionary::class, $params);
            self::assertSame(strlen($xml), $params->get('Size'));
            self::assertEquals(new PdfString("D:20261113102030+01'00'"), $params->get('ModDate'));

            self::assertEquals([[$name, $af[0]]], $this->embeddedFiles($out));
            self::assertStringContainsString('/Subtype /text#2Fxml', substr($result->pdf, strlen($pdf)));
        }
    }

    public function test_output_intent_added_only_when_missing(): void
    {
        $pdf = $this->basePdf();
        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));
        $intents = $out->catalog()->get('OutputIntents');
        self::assertIsArray($intents);
        self::assertCount(1, $intents);
        $intent = $out->resolve($intents[0]);
        self::assertInstanceOf(PdfDictionary::class, $intent);
        self::assertEquals(new PdfName('OutputIntent'), $intent->get('Type'));
        self::assertEquals(new PdfName('GTS_PDFA1'), $intent->get('S'));
        $profile = $out->resolve($intent->get('DestOutputProfile'));
        self::assertInstanceOf(PdfStream::class, $profile);
        self::assertSame(3, $profile->dictionary->get('N'));
        self::assertSame(
            hash_file('sha256', dirname(__DIR__, 3).'/resources/icc/sRGB2014.icc'),
            hash('sha256', $out->decodedStream($profile)),
        );

        $existing = $this->basePdf(catalog: '/OutputIntents [20 0 R]', objects: [20 => '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Custom) >>']);
        $result = (new FacturXPdfWriter)->embed($existing, self::xml(FacturXProfile::En16931), modified: $this->modified());
        $out = $this->reparse($existing, $result);
        self::assertEquals([new PdfRef(20, 0)], $out->catalog()->get('OutputIntents'));
        self::assertStringNotContainsString('GTS_PDFA1', substr($result->pdf, strlen($existing)));
    }

    public function test_existing_attachment_is_kept(): void
    {
        $pdf = $this->basePdf(
            catalog: '/AF [20 0 R] /Names << /EmbeddedFiles << /Names [(zzz.txt) 20 0 R] >> >>',
            objects: [20 => '<< /Type /Filespec /F (zzz.txt) /UF (zzz.txt) /AFRelationship /Supplement >>'],
        );

        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));

        $af = $out->catalog()->get('AF');
        self::assertIsArray($af);
        self::assertCount(2, $af);
        self::assertEquals(new PdfRef(20, 0), $af[0]);
        self::assertEquals([['factur-x.xml', $af[1]], ['zzz.txt', new PdfRef(20, 0)]], $this->embeddedFiles($out));
        self::assertNull($this->embeddedFilesNode($out)->get('Kids'), 'a flat name tree stays flat');
    }

    public function test_existing_name_tree_with_kids_gets_a_new_root(): void
    {
        $pdf = $this->basePdf(
            catalog: '/Names << /EmbeddedFiles 21 0 R >>',
            objects: [
                20 => '<< /Type /Filespec /F (a.txt) >>',
                21 => '<< /Kids [22 0 R] >>',
                22 => '<< /Limits [(a.txt) (b.txt)] /Names [(a.txt) 20 0 R (b.txt) 20 0 R] >>',
            ],
        );

        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));

        $root = $this->embeddedFilesNode($out);
        $kids = $root->get('Kids');
        self::assertIsArray($kids);
        self::assertCount(2, $kids);
        self::assertSame(['a.txt', 'b.txt', 'factur-x.xml'], array_column($this->embeddedFiles($out), 0));
        $old = $out->resolve($kids[0]);
        self::assertInstanceOf(PdfDictionary::class, $old);
        self::assertEquals([new PdfString('a.txt'), new PdfString('b.txt')], $old->get('Limits'));
        self::assertEquals([new PdfRef(22, 0)], $old->get('Kids'));
        $leaf = $out->resolve($kids[1]);
        self::assertInstanceOf(PdfDictionary::class, $leaf);
        self::assertEquals([new PdfString('factur-x.xml'), new PdfString('factur-x.xml')], $leaf->get('Limits'));
    }

    public function test_name_tree_whose_range_covers_the_new_name_is_flattened(): void
    {
        $pdf = $this->basePdf(
            catalog: '/Names << /EmbeddedFiles 21 0 R >>',
            objects: [
                20 => '<< /Type /Filespec /F (a.txt) >>',
                21 => '<< /Kids [22 0 R] >>',
                22 => '<< /Limits [(a.txt) (z.txt)] /Names [(a.txt) 20 0 R (z.txt) 20 0 R] >>',
            ],
        );

        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));

        self::assertNull($this->embeddedFilesNode($out)->get('Kids'));
        self::assertSame(['a.txt', 'factur-x.xml', 'z.txt'], array_column($this->embeddedFiles($out), 0));
    }

    public function test_names_siblings_are_kept(): void
    {
        $pdf = $this->basePdf(
            catalog: '/Names 23 0 R /PageMode /UseAttachments /Lang (de)',
            objects: [
                20 => '<< /D [3 0 R /Fit] >>',
                23 => '<< /Dests << /Names [(chapter1) 20 0 R] >> /JavaScript 24 0 R >>',
                24 => '<< /Names [] >>',
            ],
        );

        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));

        $catalog = $out->catalog();
        self::assertEquals(new PdfName('UseAttachments'), $catalog->get('PageMode'));
        self::assertEquals(new PdfString('de'), $catalog->get('Lang'));
        $names = $out->resolve($catalog->get('Names'));
        self::assertInstanceOf(PdfDictionary::class, $names);
        self::assertEquals(new PdfDictionary(['Names' => [new PdfString('chapter1'), new PdfRef(20, 0)]]), $names->get('Dests'));
        self::assertEquals(new PdfRef(24, 0), $names->get('JavaScript'));
        self::assertSame(['factur-x.xml'], array_column($this->embeddedFiles($out), 0));
    }

    public function test_existing_xmp_properties_are_kept(): void
    {
        $pdf = $this->basePdf();

        $out = $this->reparse($pdf, (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified()));

        $metadataRef = $out->catalog()->get('Metadata');
        self::assertInstanceOf(PdfRef::class, $metadataRef);
        self::assertNotSame(5, $metadataRef->number, 'a new Metadata stream is written');
        $metadata = $out->resolve($metadataRef);
        self::assertInstanceOf(PdfStream::class, $metadata);
        self::assertEquals(new PdfName('Metadata'), $metadata->dictionary->get('Type'));
        self::assertEquals(new PdfName('XML'), $metadata->dictionary->get('Subtype'));
        self::assertFalse($metadata->dictionary->has('Filter'), 'the XMP stays uncompressed');
        $xmp = $out->decodedStream($metadata);
        self::assertStringContainsString('<custom:Thing>kept</custom:Thing>', $xmp);
        self::assertStringContainsString('<pdfaid:conformance>A</pdfaid:conformance>', $xmp);
        self::assertStringContainsString('<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $xmp);
        self::assertStringContainsString('<dc:title>', $xmp);
        self::assertStringContainsString('Base title', $xmp);
        self::assertStringContainsString('<xmp:ModifyDate>2026-11-13T10:20:30+01:00</xmp:ModifyDate>', $xmp);

        $info = $out->resolve($out->trailer()->get('Info'));
        self::assertInstanceOf(PdfDictionary::class, $info);
        self::assertEquals(new PdfString("D:20261113102030+01'00'"), $info->get('ModDate'));
        self::assertEquals(new PdfString('Base title'), $info->get('Title'));
    }

    public function test_second_invoice_is_refused(): void
    {
        $pdf = $this->basePdf();
        $once = (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());

        try {
            (new FacturXPdfWriter)->embed($once->pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());
            self::fail('A second invoice was embedded.');
        } catch (InvoiceAlreadyEmbedded $e) {
            self::assertStringContainsString('factur-x.xml', $e->getMessage());
        }

        foreach (['ZUGFeRD-invoice.xml', 'zugferd-invoice.xml', 'xrechnung.xml'] as $name) {
            $afOnly = $this->basePdf(catalog: '/AF [20 0 R]', objects: [20 => '<< /Type /Filespec /UF ('.$name.') >>']);
            try {
                (new FacturXPdfWriter)->embed($afOnly, self::xml(FacturXProfile::En16931), modified: $this->modified());
                self::fail('An invoice listed only in /AF was not found: '.$name);
            } catch (InvoiceAlreadyEmbedded $e) {
                self::assertStringContainsString($name, $e->getMessage());
            }
        }
    }

    public function test_encrypted_input_is_refused(): void
    {
        $pdf = $this->basePdf(trailer: '/Encrypt << /Filter /Standard /V 1 /R 2 >>');

        $this->expectException(EncryptedPdf::class);

        (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());
    }

    public function test_signed_input_warns(): void
    {
        foreach (['/Perms << /DocMDP 20 0 R >>', '/AcroForm << /Fields [] /SigFlags 3 >>'] as $catalog) {
            $pdf = $this->basePdf(catalog: $catalog, objects: [20 => '<< /Type /Sig >>']);

            $result = (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());

            self::assertCount(1, $result->warnings, $catalog);
            self::assertStringContainsString('signed', $result->warnings[0]);
            $this->reparse($pdf, $result);
        }

        $unsigned = $this->basePdf(catalog: '/AcroForm << /Fields [] /SigFlags 0 >>');
        self::assertSame([], (new FacturXPdfWriter)->embed($unsigned, self::xml(FacturXProfile::En16931), modified: $this->modified())->warnings);
    }

    public function test_non_pdfa_input_warns_and_strict_throws(): void
    {
        foreach ([self::XMP_PLAIN, null] as $xmp) {
            $pdf = $this->basePdf(xmp: $xmp);

            $result = (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());

            self::assertCount(1, $result->warnings);
            self::assertStringContainsString('pdfaid:part', $result->warnings[0]);
            $this->reparse($pdf, $result);

            try {
                (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified(), strict: true);
                self::fail('Strict mode accepted a PDF that declares no PDF/A part.');
            } catch (NotPdfA $e) {
                self::assertStringContainsString('PDF/A', $e->getMessage());
            }
        }
    }

    public function test_pdfa1_input_warns_that_the_declaration_was_raised(): void
    {
        $pdf = $this->basePdf(xmp: self::XMP_PDFA1);

        $result = (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified(), strict: true);

        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('PDF/A-1', $result->warnings[0]);
        $out = $this->reparse($pdf, $result);
        $metadata = $out->resolve($out->catalog()->get('Metadata'));
        self::assertInstanceOf(PdfStream::class, $metadata);
        self::assertStringContainsString('<pdfaid:part>3</pdfaid:part>', $out->decodedStream($metadata));
    }

    public function test_profile_mismatch_is_refused(): void
    {
        try {
            (new FacturXPdfWriter)->embed($this->basePdf(), self::xml(FacturXProfile::XRechnung), FacturXProfile::En16931, modified: $this->modified());
            self::fail('A profile mismatch was accepted.');
        } catch (ProfileMismatch $e) {
            self::assertStringContainsString('XRECHNUNG', $e->getMessage());
            self::assertStringContainsString('EN 16931', $e->getMessage());
        }

        $result = (new FacturXPdfWriter)->embed($this->basePdf(), self::xml(FacturXProfile::XRechnung), FacturXProfile::XRechnung, modified: $this->modified());
        self::assertSame(FacturXProfile::XRechnung, $result->profile);

        $unknown = str_replace(FacturXProfile::En16931->guidelineId().'<', 'urn:example:unknown<', self::xml(FacturXProfile::En16931));
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('urn:example:unknown');
        (new FacturXPdfWriter)->embed($this->basePdf(), $unknown, modified: $this->modified());
    }

    public function test_deterministic_with_fixed_modified_date(): void
    {
        $pdf = $this->basePdf();
        $xml = self::xml(FacturXProfile::En16931);

        $first = (new FacturXPdfWriter)->embed($pdf, $xml, modified: $this->modified());
        $second = (new FacturXPdfWriter)->embed($pdf, $xml, modified: $this->modified());
        $later = (new FacturXPdfWriter)->embed($pdf, $xml, modified: $this->modified()->modify('+1 second'));

        self::assertSame($first->pdf, $second->pdf);
        self::assertNotSame($first->pdf, $later->pdf);
        $id = PdfDocument::parse($first->pdf)->trailer()->get('ID');
        self::assertIsArray($id);
        self::assertEquals(new PdfString('ORIGINAL-FIRST-ID', true), $id[0]);
        self::assertEquals(new PdfString(md5($pdf.$xml.$this->modified()->format(DATE_ATOM), true), true), $id[1]);
    }

    public function test_xref_stream_input_end_to_end(): void
    {
        $pdf = $this->basePdf(xrefStream: true);

        $result = (new FacturXPdfWriter)->embed($pdf, self::xml(FacturXProfile::En16931), modified: $this->modified());
        $out = $this->reparse($pdf, $result);

        self::assertTrue($out->usesXrefStream());
        self::assertSame(['factur-x.xml'], array_column($this->embeddedFiles($out), 0));
        self::assertEquals(new PdfName('1.7'), $out->catalog()->get('Version'));
    }

    public function test_catalog_version_is_raised_only_below_1_7(): void
    {
        $old = $this->basePdf(version: '1.4');
        $out = $this->reparse($old, (new FacturXPdfWriter)->embed($old, self::xml(FacturXProfile::En16931), modified: $this->modified()));
        self::assertEquals(new PdfName('1.7'), $out->catalog()->get('Version'));

        $current = $this->basePdf(version: '1.7');
        $out = $this->reparse($current, (new FacturXPdfWriter)->embed($current, self::xml(FacturXProfile::En16931), modified: $this->modified()));
        self::assertFalse($out->catalog()->has('Version'));
    }

    private function reparse(string $original, EmbedResult $result): PdfDocument
    {
        self::assertSame($original, substr($result->pdf, 0, strlen($original)), 'the original bytes are a prefix');
        $document = PdfDocument::parse($result->pdf);
        self::assertFalse($document->reconstructed());

        return $document;
    }

    private function embeddedFilesNode(PdfDocument $document): PdfDictionary
    {
        $names = $document->resolve($document->catalog()->get('Names'));
        self::assertInstanceOf(PdfDictionary::class, $names);
        $node = $document->resolve($names->get('EmbeddedFiles'));
        self::assertInstanceOf(PdfDictionary::class, $node);

        return $node;
    }

    /** @return list<array{string, mixed}> */
    private function embeddedFiles(PdfDocument $document, ?PdfDictionary $node = null): array
    {
        $node ??= $this->embeddedFilesNode($document);
        $pairs = [];
        $names = $node->get('Names');
        if (is_array($names)) {
            for ($i = 0; $i + 1 < count($names); $i += 2) {
                self::assertInstanceOf(PdfString::class, $names[$i]);
                $pairs[] = [$names[$i]->bytes, $names[$i + 1]];
            }
        }
        $kids = $node->get('Kids');
        if (is_array($kids)) {
            foreach ($kids as $kid) {
                $child = $document->resolve($kid);
                self::assertInstanceOf(PdfDictionary::class, $child);
                $pairs = [...$pairs, ...$this->embeddedFiles($document, $child)];
            }
        }

        return $pairs;
    }

    /**
     * A one-page PDF: 1 catalog, 2 pages, 3 page, 4 Info, 5 XMP metadata (when given), plus $objects.
     *
     * @param  array<int, string>  $objects
     */
    private function basePdf(
        string $version = '1.4',
        ?string $xmp = self::XMP_PDFA3,
        string $catalog = '',
        array $objects = [],
        string $trailer = '',
        bool $xrefStream = false,
    ): string {
        $builder = (new PdfBuilder($version))
            ->object(1, '<< /Type /Catalog /Pages 2 0 R'.($xmp !== null ? ' /Metadata 5 0 R' : '').' '.$catalog.' >>')
            ->object(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>')
            ->object(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>')
            ->object(4, '<< /Title (Base title) /Producer (Test producer) /CreationDate (D:20260101120000Z) >>');
        if ($xmp !== null) {
            $builder->stream(5, '/Type /Metadata /Subtype /XML', $xmp);
        }
        foreach ($objects as $number => $body) {
            $builder->object($number, $body);
        }
        $builder->trailer('/Root 1 0 R /Info 4 0 R /ID [<4F524947494E414C2D46495253542D4944> <00>] '.$trailer);

        return $xrefStream ? $builder->xrefStream() : $builder->classic();
    }

    private function modified(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13T10:20:30+01:00');
    }

    private static function xml(FacturXProfile $profile): string
    {
        return self::$xml[$profile->value] ??= (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), $profile)->xml;
    }
}

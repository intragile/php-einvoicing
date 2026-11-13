<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\Xmp;
use PHPUnit\Framework\TestCase;

final class XmpTest extends TestCase
{
    private const string EXISTING = <<<'XMP'
        <?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description rdf:about="uuid:1234" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" pdfaid:part="2" pdfaid:conformance="U"/>
            <rdf:Description rdf:about="uuid:1234" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:custom="urn:example:custom">
              <dc:title><rdf:Alt><rdf:li xml:lang="x-default">Existing title</rdf:li></rdf:Alt></dc:title>
              <custom:Thing>kept</custom:Thing>
            </rdf:Description>
            <rdf:Description rdf:about="uuid:1234" xmlns:xmp="http://ns.adobe.com/xap/1.0/">
              <xmp:CreateDate>2020-01-01T00:00:00+00:00</xmp:CreateDate>
              <xmp:ModifyDate>2020-01-02T00:00:00+00:00</xmp:ModifyDate>
            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        <?xpacket end="w"?>
        XMP;

    public function test_xmp_merge_keeps_existing_properties(): void
    {
        $xmp = Xmp::merge(self::EXISTING, FacturXProfile::En16931, 'factur-x.xml', $this->modified(), ['Title' => 'Info title', 'Author' => 'Info author']);
        $x = $this->xpath($xmp);

        self::assertSame('kept', $x->evaluate('string(//custom:Thing)'));
        self::assertSame('Existing title', $x->evaluate('string(//dc:title/rdf:Alt/rdf:li)'), 'an existing dc:title is not overwritten from Info');
        self::assertSame('Info author', $x->evaluate('string(//dc:creator/rdf:Seq/rdf:li)'));
        self::assertSame('2020-01-01T00:00:00+00:00', $x->evaluate('string(//xmp:CreateDate)'));
        self::assertSame('2026-11-13T10:20:30+01:00', $x->evaluate('string(//xmp:ModifyDate)'));
        self::assertSame(1.0, $x->evaluate('count(//xmp:ModifyDate)'));
        self::assertSame('2026-11-13T10:20:30+01:00', $x->evaluate('string(//xmp:MetadataDate)'));
        self::assertSame('3', $this->property($x, 'pdfaid:part'));
        self::assertSame('U', $this->property($x, 'pdfaid:conformance'), 'an existing conformance level is kept');
        self::assertSame(1.0, $x->evaluate('count(//@pdfaid:part | //pdfaid:part)'));
        // Every description carries the same rdf:about as the existing ones.
        self::assertSame(0.0, $x->evaluate('count(//rdf:Description[@rdf:about != "uuid:1234"])'));
    }

    public function test_xmp_has_extension_schema_and_fx_values(): void
    {
        $cases = [
            [FacturXProfile::En16931, 'factur-x.xml', '1.0', 'EN 16931'],
            [FacturXProfile::XRechnung, 'xrechnung.xml', '3.0', 'XRECHNUNG'],
        ];
        foreach ($cases as [$profile, $name, $version, $level]) {
            $xmp = Xmp::merge(null, $profile, $profile->attachmentName(), $this->modified(), []);
            $x = $this->xpath($xmp);

            self::assertSame('INVOICE', $x->evaluate('string(//fx:DocumentType)'));
            self::assertSame($name, $x->evaluate('string(//fx:DocumentFileName)'));
            self::assertSame($version, $x->evaluate('string(//fx:Version)'));
            self::assertSame($level, $x->evaluate('string(//fx:ConformanceLevel)'));
            self::assertSame('3', $this->property($x, 'pdfaid:part'));
            self::assertSame('B', $this->property($x, 'pdfaid:conformance'));

            $schema = '//pdfaExtension:schemas/rdf:Bag/rdf:li[pdfaSchema:namespaceURI = "urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#"]';
            self::assertSame(1.0, $x->evaluate('count('.$schema.')'));
            self::assertSame('fx', $x->evaluate('string('.$schema.'/pdfaSchema:prefix)'));
            self::assertSame('Factur-X PDFA Extension Schema', $x->evaluate('string('.$schema.'/pdfaSchema:schema)'));
            $properties = $schema.'/pdfaSchema:property/rdf:Seq/rdf:li';
            self::assertSame(4.0, $x->evaluate('count('.$properties.')'));
            self::assertSame(4.0, $x->evaluate('count('.$properties.'[pdfaProperty:valueType = "Text" and pdfaProperty:category = "external"])'));
            foreach (['DocumentFileName', 'DocumentType', 'Version', 'ConformanceLevel'] as $property) {
                self::assertSame(1.0, $x->evaluate('count('.$properties.'[pdfaProperty:name = "'.$property.'"])'));
            }
            self::assertSame(
                'The type of the hybrid document in capital letters, e.g. INVOICE or ORDER',
                $x->evaluate('string('.$properties.'[pdfaProperty:name = "DocumentType"]/pdfaProperty:description)'),
            );
        }
    }

    public function test_packet_wrapper(): void
    {
        $xmp = Xmp::merge(null, FacturXProfile::Minimum, 'factur-x.xml', $this->modified(), []);

        self::assertStringStartsWith("<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>", $xmp);
        self::assertStringEndsWith('<?xpacket end="w"?>', $xmp);
        self::assertStringNotContainsString('<?xml', $xmp);
    }

    public function test_existing_fx_values_and_schema_are_replaced_not_duplicated(): void
    {
        $first = Xmp::merge(null, FacturXProfile::Basic, 'factur-x.xml', $this->modified(), []);
        $second = Xmp::merge($first, FacturXProfile::En16931, 'factur-x.xml', $this->modified(), []);
        $x = $this->xpath($second);

        self::assertSame(1.0, $x->evaluate('count(//fx:ConformanceLevel)'));
        self::assertSame('EN 16931', $x->evaluate('string(//fx:ConformanceLevel)'));
        self::assertSame(1.0, $x->evaluate('count(//pdfaExtension:schemas/rdf:Bag/rdf:li)'));
    }

    public function test_info_entries_fill_missing_properties(): void
    {
        $xmp = Xmp::merge(null, FacturXProfile::En16931, 'factur-x.xml', $this->modified(), [
            'Title' => 'Invoice <1> & co',
            'Author' => 'Seller Kft.',
            'Subject' => 'Invoice 1',
            'Keywords' => 'invoice',
            'Creator' => 'Writer app',
            'Producer' => 'Producer lib',
            'CreationDate' => '2026-01-02T03:04:05+00:00',
        ]);
        $x = $this->xpath($xmp);

        self::assertSame('Invoice <1> & co', $x->evaluate('string(//dc:title/rdf:Alt/rdf:li[@xml:lang="x-default"])'));
        self::assertSame('Seller Kft.', $x->evaluate('string(//dc:creator/rdf:Seq/rdf:li)'));
        self::assertSame('Invoice 1', $x->evaluate('string(//dc:description/rdf:Alt/rdf:li[@xml:lang="x-default"])'));
        self::assertSame('invoice', $x->evaluate('string(//pdf:Keywords)'));
        self::assertSame('Writer app', $x->evaluate('string(//xmp:CreatorTool)'));
        self::assertSame('Producer lib', $x->evaluate('string(//pdf:Producer)'));
        self::assertSame('2026-01-02T03:04:05+00:00', $x->evaluate('string(//xmp:CreateDate)'));
    }

    public function test_declared_part(): void
    {
        self::assertSame(2, Xmp::declaredPart(self::EXISTING));
        self::assertSame(3, Xmp::declaredPart(Xmp::merge(null, FacturXProfile::Minimum, 'factur-x.xml', $this->modified(), [])));
        self::assertNull(Xmp::declaredPart(null));
        self::assertNull(Xmp::declaredPart('<x:xmpmeta xmlns:x="adobe:ns:meta/"/>'));
    }

    public function test_doctype_in_existing_xmp_is_refused(): void
    {
        $this->expectException(UnsafeXml::class);

        Xmp::merge('<!DOCTYPE x [<!ENTITY a "b">]><x:xmpmeta xmlns:x="adobe:ns:meta/"/>', FacturXProfile::Minimum, 'factur-x.xml', $this->modified(), []);
    }

    public function test_deterministic(): void
    {
        self::assertSame(
            Xmp::merge(self::EXISTING, FacturXProfile::En16931, 'factur-x.xml', $this->modified(), ['Producer' => 'p']),
            Xmp::merge(self::EXISTING, FacturXProfile::En16931, 'factur-x.xml', $this->modified(), ['Producer' => 'p']),
        );
    }

    private function modified(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13T10:20:30+01:00');
    }

    private function property(DOMXPath $x, string $name): string
    {
        $value = $x->evaluate('string(//'.$name.' | //@'.$name.')');
        self::assertIsString($value);

        return $value;
    }

    private function xpath(string $xmp): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xmp, LIBXML_NONET));
        $x = new DOMXPath($document);
        foreach ([
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'xmp' => 'http://ns.adobe.com/xap/1.0/',
            'pdf' => 'http://ns.adobe.com/pdf/1.3/',
            'pdfaid' => 'http://www.aiim.org/pdfa/ns/id/',
            'fx' => 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#',
            'pdfaExtension' => 'http://www.aiim.org/pdfa/ns/extension/',
            'pdfaSchema' => 'http://www.aiim.org/pdfa/ns/schema#',
            'pdfaProperty' => 'http://www.aiim.org/pdfa/ns/property#',
            'custom' => 'urn:example:custom',
        ] as $prefix => $uri) {
            $x->registerNamespace($prefix, $uri);
        }

        return $x;
    }
}

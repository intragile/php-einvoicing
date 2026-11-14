<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Sbdh;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\Sbdh\InboundXml;
use PHPUnit\Framework\TestCase;

final class InboundXmlTest extends TestCase
{
    public function test_plain_document_is_kept_as_received(): void
    {
        $xml = self::fixtureXml('inbound-document.json');

        $document = InboundXml::document(self::reference(), $xml);

        self::assertSame($xml, $document->xml);
        self::assertNull($document->envelope);
        self::assertSame('doc_01', $document->reference->documentId);
    }

    public function test_enveloped_document_is_unwrapped(): void
    {
        $document = InboundXml::document(self::reference(), self::fixtureXml('inbound-document-sbdh.json'));

        self::assertNotNull($document->envelope);
        self::assertSame('0208:0987654321', $document->envelope->sender->toString());
        self::assertStringNotContainsString('StandardBusinessDocument', $document->xml);
        self::assertStringContainsString('<Invoice', $document->xml);
    }

    public function test_doctype_is_refused(): void
    {
        $this->expectException(UnsafeXml::class);

        InboundXml::document(self::reference(), '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "b">]><x>&a;</x>');
    }

    public function test_malformed_xml_is_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);

        InboundXml::document(self::reference(), '<Invoice>');
    }

    private static function reference(): InboundReference
    {
        return new InboundReference('doc_01', null, null, null, new DateTimeImmutable('2026-11-12T08:00:00Z'));
    }

    private static function fixtureXml(string $name): string
    {
        $data = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/recommand/'.$name), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['document']);
        self::assertIsString($data['document']['xml']);

        return $data['document']['xml'];
    }
}

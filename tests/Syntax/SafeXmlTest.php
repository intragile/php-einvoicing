<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeXmlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return HostileXml::cases();
    }

    #[DataProvider('hostile')]
    public function test_refuses_hostile_xml_quickly(string $xml): void
    {
        $started = hrtime(true);

        try {
            SafeXml::load($xml);
            self::fail('UnsafeXml was not thrown.');
        } catch (UnsafeXml $e) {
            self::assertStringContainsString('DOCTYPE', $e->getMessage());
        }

        self::assertLessThan(100, (hrtime(true) - $started) / 1e6, 'Refusal took 100 ms or longer.');
    }

    public function test_loads_plain_xml(): void
    {
        $document = SafeXml::load("<?xml version=\"1.0\"?>\n<a>\n  <b> x &amp; y </b>\n</a>");

        self::assertSame('a', $document->documentElement?->localName);
        self::assertSame(' x & y ', $document->documentElement->firstChild?->textContent);
    }

    public function test_refuses_malformed_xml(): void
    {
        $this->expectException(UnsupportedDocument::class);

        SafeXml::load('<a><b></a>');
    }

    public function test_refuses_empty_input(): void
    {
        $this->expectException(UnsupportedDocument::class);

        SafeXml::load('');
    }
}

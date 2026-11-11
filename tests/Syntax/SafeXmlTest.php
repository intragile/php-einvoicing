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
     * @return array<string, array{string, bool}>
     */
    public static function hostileForBothLoaders(): array
    {
        $cases = [];
        foreach (HostileXml::cases() as $name => [$xml]) {
            $cases["{$name}, blanks dropped"] = [$xml, false];
            $cases["{$name}, whitespace kept"] = [$xml, true];
        }

        return $cases;
    }

    #[DataProvider('hostileForBothLoaders')]
    public function test_refuses_hostile_xml_quickly(string $xml, bool $keepWhitespace): void
    {
        $started = hrtime(true);

        try {
            $keepWhitespace ? SafeXml::loadPreservingWhitespace($xml) : SafeXml::load($xml);
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

    public function test_drops_whitespace_only_text_next_to_a_comment(): void
    {
        $document = SafeXml::load('<a><b> <!--x-->O</b></a>');

        self::assertSame('O', $document->documentElement?->firstChild?->textContent);
    }

    public function test_keeps_whitespace_only_text_when_asked(): void
    {
        $document = SafeXml::loadPreservingWhitespace("<a>\n  <b> <!--x-->O</b>\n</a>");

        self::assertSame(' O', $document->getElementsByTagName('b')->item(0)?->textContent);
        self::assertSame("\n  ", $document->documentElement?->firstChild?->textContent);
    }

    public function test_refuses_malformed_xml_when_keeping_whitespace(): void
    {
        $this->expectException(UnsupportedDocument::class);

        SafeXml::loadPreservingWhitespace('<a><b></a>');
    }

    public function test_refuses_empty_input_when_keeping_whitespace(): void
    {
        $this->expectException(UnsupportedDocument::class);

        SafeXml::loadPreservingWhitespace(" \n");
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

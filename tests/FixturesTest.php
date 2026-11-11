<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    public function test_official_examples_present(): void
    {
        foreach (['ubl', 'cii'] as $syntax) {
            $files = Fixtures::official($syntax);

            self::assertNotEmpty($files, "No official {$syntax} examples found.");

            foreach ($files as $file) {
                self::assertFileIsReadable($file);
                $document = new DOMDocument;
                self::assertTrue($document->load($file, LIBXML_NONET), "{$file} is not well-formed XML.");
                self::assertNotNull($document->documentElement);
            }
        }
    }

    public function test_official_validation_stylesheets_present(): void
    {
        self::assertFileIsReadable(Fixtures::officialXslt('ubl'));
        self::assertFileIsReadable(Fixtures::officialXslt('cii'));
    }

    public function test_preprocessed_schematron_present(): void
    {
        foreach (['ubl', 'cii'] as $syntax) {
            $file = Fixtures::schematron($syntax);

            self::assertFileIsReadable($file);
            $document = new DOMDocument;
            self::assertTrue($document->load($file, LIBXML_NONET), "{$file} is not well-formed XML.");
            self::assertSame('http://purl.oclc.org/dsdl/schematron', $document->documentElement?->namespaceURI);
            self::assertSame('schema', $document->documentElement->localName);
        }
    }

    public function test_unit_test_corpus_present(): void
    {
        $documents = Fixtures::unitTests();

        self::assertGreaterThanOrEqual(300, count($documents));

        $names = [];
        $syntaxes = [];
        foreach ($documents as $document) {
            $names[] = $document->name();
            $syntaxes[$document->syntax] = true;

            $root = SafeXml::load($document->xml)->documentElement;
            self::assertNotNull($root, "{$document->name()} has no document element.");
            self::assertContains($root->localName, ['Invoice', 'CreditNote', 'CrossIndustryInvoice'], $document->name());
        }

        self::assertArrayHasKey('ubl', $syntaxes);
        self::assertArrayHasKey('cii', $syntaxes);
        self::assertSame($names, array_values(array_unique($names)), 'Unit-test document names are not unique.');
    }
}

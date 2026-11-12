<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
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

    public function test_peppol_schematron_matches_the_pinned_sources(): void
    {
        $sources = PeppolSources::all();

        self::assertSame(['cen-ubl', 'cen-cii', 'peppol-ubl', 'peppol-cii', 'envelope'], array_keys($sources));

        foreach ($sources as $key => $source) {
            $file = Fixtures::peppolSchematron($key);

            self::assertSame($source['file'], basename($file));
            self::assertFileIsReadable($file);
            self::assertSame($source['sha256'], hash_file('sha256', $file), "{$file} does not match its pinned checksum.");
        }
    }

    public function test_peppol_unit_test_corpus_present(): void
    {
        $documents = Fixtures::peppolUnitTests();

        self::assertGreaterThanOrEqual(250, count($documents));

        $names = [];
        $syntaxes = [];
        foreach ($documents as $document) {
            $names[] = $document->name();
            $syntaxes[$document->syntax] = true;

            self::assertStringStartsWith('peppol/unit-', $document->name());
            $root = SafeXml::load($document->xml)->documentElement;
            self::assertNotNull($root, "{$document->name()} has no document element.");
            // PEPPOL-COMMON-R041.xml tests the syntax-independent rules on UBL Order documents.
            self::assertContains($root->localName, ['Invoice', 'CreditNote', 'CrossIndustryInvoice', 'Order'], $document->name());
        }

        self::assertArrayHasKey('ubl', $syntaxes);
        self::assertArrayHasKey('cii', $syntaxes);
        self::assertSame($names, array_values(array_unique($names)), 'Peppol unit-test document names are not unique.');
    }

    public function test_peppol_examples_present(): void
    {
        $examples = Fixtures::peppolExamples();

        self::assertGreaterThanOrEqual(20, count($examples));
        self::assertArrayHasKey('examples/base-example.xml', $examples);

        foreach ($examples as $name => $xml) {
            self::assertNotNull(SafeXml::load($xml)->documentElement, "{$name} has no document element.");
        }
    }

    public function test_peppol_fixtures_are_git_ignored(): void
    {
        $root = dirname(__DIR__);
        $command = 'git -C '.escapeshellarg($root).' check-ignore -q tests/fixtures/peppol-'.PeppolSources::VERSION.'/sch/PEPPOL-EN16931-UBL.sch';
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, 'The Peppol fixtures must never be committed.');
    }

    public function test_iso_schematron_skeleton_present(): void
    {
        $file = Fixtures::isoSkeleton();

        self::assertFileIsReadable($file);
        self::assertFileIsReadable(dirname($file).'/iso_schematron_skeleton_for_saxon.xsl');
        self::assertFileIsReadable(dirname($file).'/LICENSE');
        self::assertSame('http://www.w3.org/1999/XSL/Transform', SafeXml::load((string) file_get_contents($file))->documentElement?->namespaceURI);
    }
}

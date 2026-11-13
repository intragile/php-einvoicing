<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use FilesystemIterator;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    public function test_xrechnung_fixtures_match_pinned_hashes(): void
    {
        $directory = __DIR__.'/fixtures/official/xrechnung';
        $lines = file(__DIR__.'/fixtures/official/xrechnung.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lines);

        $listed = [];
        foreach ($lines as $line) {
            if (preg_match('/^([0-9a-f]{64})  ([^\t]+)(?:\t(doctype|unparseable))?$/', $line, $match) !== 1) {
                self::fail("Malformed manifest line: {$line}");
            }
            $listed[$match[2]] = true;
            self::assertSame($match[1], hash_file('sha256', $directory.'/'.$match[2]), "{$match[2]} does not match its manifest entry.");
        }

        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $found[substr($file->getPathname(), strlen($directory) + 1)] = true;
        }

        self::assertEqualsCanonicalizing(array_keys($found), array_keys($listed));
    }

    public function test_xrechnung_corpus_size(): void
    {
        $corpus = Fixtures::xrechnungCorpus();

        self::assertGreaterThanOrEqual(500, count($corpus));
        self::assertSame(array_keys($corpus), array_values(array_unique(array_keys($corpus))));
        $sorted = array_keys($corpus);
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, array_keys($corpus), 'The corpus is not sorted by name.');

        foreach ($corpus as $name => $path) {
            self::assertFileIsReadable($path);
            self::assertNotNull(SafeXml::loadPreservingWhitespace((string) file_get_contents($path))->documentElement, $name);
        }

        foreach (Fixtures::xrechnungRejectedCorpus() as $name => $path) {
            self::assertArrayNotHasKey($name, $corpus);
            try {
                SafeXml::loadPreservingWhitespace((string) file_get_contents($path));
                self::fail("{$name} is flagged in the manifest but loads.");
            } catch (UnsafeXml|UnsupportedDocument) {
                // Expected: the file carries a DOCTYPE or is not well-formed.
            }
        }
    }

    public function test_xrechnung_schematron_and_configuration_present(): void
    {
        foreach (['ubl', 'cii'] as $syntax) {
            self::assertFileIsReadable(Fixtures::xrechnungSchematron($syntax));
        }
        self::assertFileIsReadable(Fixtures::xrechnungConfiguration().'/scenarios.xml');
        self::assertFileIsReadable(Fixtures::xrechnungConfiguration().'/LICENSE');
    }

    public function test_cen_1316_sources_present(): void
    {
        $source = (string) file_get_contents(__DIR__.'/fixtures/official/SOURCE.md');

        foreach (['ubl', 'cii'] as $syntax) {
            $file = Fixtures::cen1316($syntax);

            self::assertFileIsReadable($file);
            self::assertStringContainsString('`'.basename($file).'`', $source);
            self::assertStringContainsString('`'.hash_file('sha256', $file).'`', $source, "{$file} does not match the value in SOURCE.md.");
        }

        self::assertFileIsReadable(dirname(__DIR__).'/tests/fixtures/official/cen-1.3.16/LICENSE.txt');
        self::assertGreaterThanOrEqual(20, count(Fixtures::cen1316Examples()));
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

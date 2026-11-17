<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXSources;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
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

        self::assertEqualsCanonicalizing(Fixtures::filesBelow($directory), array_keys($listed));
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

    public function test_facturx_fixtures_match_pinned_hashes(): void
    {
        $directory = __DIR__.'/fixtures/official/facturx-'.FacturXSources::VERSION;
        $lines = file(__DIR__.'/fixtures/official/facturx.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lines);

        $listed = [];
        foreach ($lines as $line) {
            if (preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $match) !== 1) {
                self::fail("Malformed manifest line: {$line}");
            }
            $listed[$match[2]] = $match[1];
            self::assertSame($match[1], hash_file('sha256', $directory.'/'.$match[2]), "{$match[2]} does not match its manifest entry.");
        }

        self::assertEqualsCanonicalizing(Fixtures::filesBelow($directory), array_keys($listed));

        // Same value as `find . -type f -print0 | LC_ALL=C sort -z | xargs -0 shasum -a 256 | shasum -a 256`
        // run inside each upstream ZF_250 folder at the pinned commit.
        $expected = [
            'schema' => [20, 'a66ac39854336a759c27a9289319146c4889c1e4acfb5d3e33ea1cc0bcfeba55'],
            'schematron' => [10, '84b15df7dbddde67134d073dcc0bfc69ed96b11c97997da5f542f44039899184'],
            'xslt' => [10, '2f81544c9b84b19b0fb53f17c0e514ebe22db78fd99a542055c7bfbd33c3459f'],
        ];
        self::assertSame(array_map(static fn (array $pin): string => $pin[1], $expected), FacturXSources::AGGREGATES);

        foreach ($expected as $folder => [$count, $aggregate]) {
            $sums = [];
            foreach ($listed as $name => $sum) {
                if (str_starts_with($name, $folder.'/')) {
                    $sums[substr($name, strlen($folder) + 1)] = $sum;
                }
            }
            ksort($sums, SORT_STRING);
            $listing = implode('', array_map(static fn (string $name, string $sum): string => "{$sum}  ./{$name}\n", array_keys($sums), $sums));

            self::assertCount($count, $sums, "Unexpected file count in {$folder}/.");
            self::assertSame($aggregate, hash('sha256', $listing), "The {$folder}/ folder does not match the pinned aggregate.");
        }

        self::assertSame(FacturXSources::LICENCE_FILES['LICENSE'], $listed['LICENSE'] ?? null);
        self::assertSame(FacturXSources::LICENCE_FILES['NOTICE'], $listed['NOTICE'] ?? null);
    }

    public function test_facturx_profiles_come_from_rule_files(): void
    {
        self::assertSame(RuleFiles::profiles(), Fixtures::facturxProfiles());
    }

    public function test_hidden_files_are_ignored(): void
    {
        $copy = BuildDir::create('fixtures-hidden');
        try {
            $source = dirname(Fixtures::isoSkeleton());
            foreach (Fixtures::filesBelow($source) as $name) {
                self::assertTrue(copy($source.'/'.$name, $copy.'/'.$name));
            }
            $before = Fixtures::filesBelow($copy);
            mkdir($copy.'/nested');
            file_put_contents($copy.'/.DS_Store', 'Finder metadata');
            file_put_contents($copy.'/nested/.DS_Store', 'Finder metadata');

            self::assertSame(Fixtures::filesBelow($source), $before);
            self::assertCount(4, $before);
            self::assertSame($before, Fixtures::filesBelow($copy));
        } finally {
            BuildDir::remove($copy);
        }
    }

    public function test_facturx_rule_sources_present(): void
    {
        foreach (Fixtures::facturxProfiles() as $profile) {
            $schematron = Fixtures::facturxSchematron($profile);
            self::assertSame("FACTUR-X_{$profile}.sch", basename($schematron));
            self::assertSame('http://purl.oclc.org/dsdl/schematron', SafeXml::load((string) file_get_contents($schematron))->documentElement?->namespaceURI);
            self::assertFileIsReadable(dirname($schematron)."/FACTUR-X_{$profile}_codedb.xml");

            $xslt = Fixtures::facturxXslt($profile);
            self::assertSame("FACTUR-X_{$profile}.xslt", basename($xslt));
            self::assertSame('http://www.w3.org/1999/XSL/Transform', SafeXml::load((string) file_get_contents($xslt))->documentElement?->namespaceURI);
            self::assertFileIsReadable(dirname($xslt)."/FACTUR-X_{$profile}_codedb.xml");

            // The BASIC WL schema files are named without the hyphen.
            $schema = Fixtures::facturxSchemaDir().'/'.$profile.'/FACTUR-X_'.str_replace('-', '', $profile).'.xsd';
            self::assertSame('http://www.w3.org/2001/XMLSchema', SafeXml::load((string) file_get_contents($schema))->documentElement?->namespaceURI);
        }

        self::assertFileIsReadable(dirname(Fixtures::facturxSchemaDir()).'/LICENSE');
        self::assertFileIsReadable(dirname(Fixtures::facturxSchemaDir()).'/NOTICE');
    }

    public function test_facturx_example_corpus(): void
    {
        $xml = Fixtures::facturxExampleXml();
        $pdfs = Fixtures::facturxExamplePdfs();
        $ferd = static fn (string $name): bool => str_starts_with($name, 'library/ZF252_Beispiele/');

        self::assertCount(61, array_filter(array_keys($xml), $ferd));
        self::assertCount(61, $xml);
        self::assertCount(70, array_filter(array_keys($pdfs), $ferd));
        self::assertCount(32, array_filter(array_keys($pdfs), static fn (string $name): bool => ! $ferd($name)));

        $manifest = FacturXSources::examples(__DIR__.'/fixtures/facturx-examples.sha256');
        self::assertCount(165, $manifest);

        $directory = Fixtures::facturxExamplesDir();
        foreach ($manifest as $path => $sum) {
            self::assertSame($sum, hash_file('sha256', $directory.'/'.$path), "{$path} does not match its manifest entry.");
        }
        foreach ([...$xml, ...$pdfs] as $name => $file) {
            self::assertArrayHasKey(substr($file, strlen($directory) + 1), $manifest, "{$name} is not listed in the manifest.");
        }
    }

    public function test_facturx_examples_are_git_ignored(): void
    {
        $command = 'git -C '.escapeshellarg(dirname(__DIR__)).' check-ignore -q tests/fixtures/facturx-examples/library/src/test/resources/zugferd_invoice.pdf';
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, 'The Factur-X example files must never be committed.');
    }

    public function test_france_corpus_matches_manifest(): void
    {
        $root = Fixtures::franceRfeRoot();

        foreach (['rules', 'schema', 'oracle', 'example'] as $kind) {
            foreach (FranceSources::files($kind) as $path => $sha256) {
                self::assertFileExists($root.'/'.$path);
                self::assertSame($sha256, hash_file('sha256', $root.'/'.$path), $path);
            }
        }

        self::assertNotEmpty(Fixtures::franceExamples('CII/EXTENDED-CTC-FR'));
    }
}

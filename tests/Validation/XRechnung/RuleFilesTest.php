<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\XRechnung;

use DOMDocument;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\XRechnung\ScenarioTableCompiler;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungSources;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleFilesTest extends TestCase
{
    public function test_versions_match_the_pinned_sources(): void
    {
        self::assertSame('2.6.0', RuleFiles::VERSION);
        self::assertSame('2026-08-31', RuleFiles::CONFIGURATION);
        self::assertSame(XRechnungSources::SCHEMATRON_VERSION, RuleFiles::VERSION);
        self::assertSame(XRechnungSources::CONFIGURATION_VERSION, RuleFiles::CONFIGURATION);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function ruleSets(): iterable
    {
        yield 'ubl' => ['ubl', 77];
        yield 'cii' => ['cii', 74];
    }

    #[DataProvider('ruleSets')]
    public function test_rule_files_exist_and_load(string $syntax, int $asserts): void
    {
        $path = $syntax === 'ubl' ? RuleFiles::ubl() : RuleFiles::cii();
        self::assertFileExists($path);
        self::assertSame("xrechnung-{$syntax}-2.6.0.php", basename($path));

        $rules = RuleSet::fromFile($path);

        self::assertSame("xrechnung-{$syntax}", $rules->id());
        self::assertSame('2.6.0', $rules->version());
        self::assertSame(hash_file('sha256', Fixtures::xrechnungSchematron($syntax)), $rules->sourceSha256());
        self::assertSame($asserts, self::countAsserts($rules));
    }

    public function test_scenario_table_is_the_compiled_configuration(): void
    {
        self::assertSame('scenarios-2026-08-31.php', basename(RuleFiles::scenarios()));
        $xml = file_get_contents(Fixtures::xrechnungConfiguration().'/scenarios.xml');
        self::assertIsString($xml);

        self::assertSame(ScenarioTableCompiler::compile($xml), require RuleFiles::scenarios());
    }

    public function test_every_schema_in_table_exists(): void
    {
        $schemaDir = RuleFiles::schemaDir();
        self::assertDirectoryExists($schemaDir);

        foreach (self::table()['scenarios'] as $scenario) {
            $schema = $schemaDir.'/'.$scenario['schema'];
            self::assertFileExists($schema, $scenario['name']);
            self::assertFileEquals(Fixtures::xrechnungConfiguration().'/resources/'.$scenario['schema'], $schema);
        }
    }

    /**
     * Every shipped schema file is a byte-for-byte copy of the configuration's file at the same
     * path below resources/.
     */
    public function test_schemas_are_unmodified_copies(): void
    {
        $files = self::schemaFiles();
        self::assertCount(19, $files);

        foreach ($files as $relative) {
            self::assertFileEquals(Fixtures::xrechnungConfiguration().'/resources/'.$relative, RuleFiles::schemaDir().'/'.$relative, $relative);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function instances(): iterable
    {
        $dir = dirname(__DIR__, 2).'/fixtures/official/xrechnung';

        yield 'UBL Invoice' => [$dir.'/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml', 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd'];
        yield 'UBL CreditNote' => [$dir.'/configuration-tests-2026-08-31/instances/processing-valid/ubl008.xml', 'ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd'];
        yield 'CII' => [$dir.'/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_uncefact.xml', 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd'];
    }

    /**
     * The copied schema set is closed: every import and include resolves inside schemas/, with
     * network access disabled.
     */
    #[DataProvider('instances')]
    public function test_shipped_schemas_validate_an_instance_offline(string $instance, string $schema): void
    {
        self::assertFileExists($instance);
        $document = new DOMDocument;
        self::assertTrue($document->load($instance, LIBXML_NONET));

        $previous = libxml_use_internal_errors(true);
        try {
            $valid = $document->schemaValidate(RuleFiles::schemaDir().'/'.$schema, LIBXML_NONET);
            $errors = array_map(static fn (\LibXMLError $error): string => trim($error->message), libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        self::assertSame([], $errors);
        self::assertTrue($valid);
    }

    /**
     * @return array{format: int, configuration: string, scenarios: list<array{name: string, schema: string}>}
     */
    private static function table(): array
    {
        /** @var array{format: int, configuration: string, scenarios: list<array{name: string, schema: string}>} $table */
        $table = require RuleFiles::scenarios();

        return $table;
    }

    /**
     * @return list<string> paths relative to schemas/, sorted
     */
    private static function schemaFiles(): array
    {
        $root = RuleFiles::schemaDir();
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            self::assertInstanceOf(\SplFileInfo::class, $file);
            $files[] = substr($file->getPathname(), strlen($root) + 1);
        }
        sort($files);

        return $files;
    }

    private static function countAsserts(RuleSet $rules): int
    {
        $count = 0;
        foreach ($rules->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                $count += count($rule['asserts']);
            }
        }

        return $count;
    }
}

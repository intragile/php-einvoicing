<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class FranceRulesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('france-rules-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_missing_france_rules_name_the_install_command(): void
    {
        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage("are not installed in \"{$this->dir}\". Run: vendor/bin/einvoicing-rules install france --dir=\"{$this->dir}\"");

        FranceRules::load($this->dir, 'flux2-cii');
    }

    public function test_unknown_rule_set_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('flux3');

        FranceRules::load($this->dir, 'flux3');
    }

    public function test_missing_schema_tree_names_the_install_command(): void
    {
        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage("Run: vendor/bin/einvoicing-rules install france --dir=\"{$this->dir}\"");

        FranceRules::schema($this->dir, 'flux10', 'ereporting.xsd');
    }

    public function test_schema_outside_the_manifest_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FranceRules::schema($this->dir, 'flux10', '../../CII/1xsd-CII_D22B_uncoupled/x.xsd');
    }

    #[Group('france-rules')]
    public function test_installed_schema_path_points_into_the_tree(): void
    {
        $dir = InstalledFranceRules::dir();
        $file = self::firstSchemaFile('flux10');

        $path = FranceRules::schema($dir, 'flux10', $file);

        self::assertSame("{$dir}/france-rfe-1.4.0.04/FNFE_RFE_INVOICE/Flux10/xsd/{$file}", $path);
        self::assertFileExists($path);
    }

    #[Group('france-rules')]
    public function test_missing_schema_tree_is_not_installed(): void
    {
        $dir = InstalledFranceRules::dir();
        // A rules folder with the compiled files but without the schema tree.
        foreach (FranceSources::ruleSources() as $source) {
            copy(FranceRules::path($dir, $source['id']), FranceRules::path($this->dir, $source['id']));
        }

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage("Run: vendor/bin/einvoicing-rules install france --dir=\"{$this->dir}\"");

        FranceRules::schema($this->dir, 'cii', self::firstSchemaFile('cii'));
    }

    #[Group('france-rules')]
    public function test_tampered_compiled_file_asks_to_reinstall(): void
    {
        $dir = InstalledFranceRules::dir();
        // Flux2 CII content under the Flux2 UBL name.
        copy(FranceRules::path($dir, 'fr-flux2-cii'), FranceRules::path($this->dir, 'fr-flux2-ubl'));

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('reinstall');

        FranceRules::load($this->dir, 'flux2-ubl');
    }

    #[Group('france-rules')]
    public function test_each_installed_rule_set_loads(): void
    {
        foreach (FranceSources::ruleSources() as $key => $source) {
            $ruleSet = FranceRules::load(InstalledFranceRules::dir(), $key);

            self::assertSame($source['id'], $ruleSet->id());
            self::assertSame(FranceSources::VERSION, $ruleSet->version());
        }
    }

    private static function firstSchemaFile(string $tree): string
    {
        $root = FranceSources::schemaRoot($tree).'/';
        foreach (array_keys(FranceSources::files('schema')) as $path) {
            if (str_starts_with($path, $root)) {
                return substr($path, strlen($root));
            }
        }

        self::fail("No schema file below {$root}");
    }
}

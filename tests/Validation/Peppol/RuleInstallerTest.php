<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolRules;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use IntrAgile\EInvoicing\Validation\Peppol\RuleInstaller;
use PHPUnit\Framework\TestCase;

final class RuleInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('rule-installer-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_installs_the_four_rule_files_from_a_local_folder(): void
    {
        $target = $this->dir.'/rules';

        $written = (new RuleInstaller)->install($target, self::sourceDir());

        $expected = array_values(array_map(
            static fn (array $source): string => "{$target}/{$source['id']}-3.0.22.php",
            PeppolSources::all(),
        ));
        self::assertSame($expected, $written);
        foreach ($written as $path) {
            // Loaded without RuleSet::fromFile, which would keep each copy for the whole run.
            $ruleSet = RuleSet::fromArray(require $path);
            self::assertSame(basename($path, '-3.0.22.php'), $ruleSet->id());
            self::assertSame('3.0.22', $ruleSet->version());
            $header = (string) file_get_contents($path, length: 600);
            self::assertStringContainsString('do not redistribute', $header);
            self::assertStringContainsString('OpenPeppol AISBL holds the copyright', $header);
        }
        self::assertSame([], glob($target.'/*.tmp*') ?: [], 'No temporary file may be left behind.');
    }

    public function test_installed_rules_load_per_syntax_cen_layer_first(): void
    {
        $rules = PeppolRules::load(InstalledRules::dir());

        $ids = array_map(static fn (array $sets): array => array_map(static fn (RuleSet $set): string => $set->id(), $sets), $rules);
        self::assertSame(['ubl' => ['peppol-cen-ubl', 'peppol-ubl'], 'cii' => ['peppol-cen-cii', 'peppol-cii']], $ids);
    }

    public function test_checksum_mismatch_writes_nothing(): void
    {
        $source = $this->dir.'/source';
        mkdir($source);
        foreach (PeppolSources::all() as $file) {
            copy(self::sourceDir().'/'.$file['file'], $source.'/'.$file['file']);
        }
        // The last file is altered, so a check that ran only while writing would already have
        // written the first three.
        $altered = $source.'/PEPPOL-EN16931-CII.sch';
        $bytes = (string) file_get_contents($altered);
        $bytes[100] = $bytes[100] === 'a' ? 'b' : 'a';
        file_put_contents($altered, $bytes);
        $target = $this->dir.'/rules';

        try {
            (new RuleInstaller)->install($target, $source);
            self::fail('A changed source file must be refused.');
        } catch (RuleSourceMismatch $exception) {
            self::assertStringContainsString('PEPPOL-EN16931-CII.sch', $exception->getMessage());
            self::assertStringContainsString(PeppolSources::all()['peppol-cii']['sha256'], $exception->getMessage());
            self::assertStringContainsString(hash('sha256', $bytes), $exception->getMessage());
        }

        self::assertSame([], is_dir($target) ? array_diff(scandir($target) ?: [], ['.', '..']) : []);
    }

    public function test_missing_rules_name_the_install_command(): void
    {
        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage("Peppol BIS 3.0.22 rules are not installed in \"{$this->dir}\". Run: vendor/bin/einvoicing-rules install peppol --dir=\"{$this->dir}\"");

        PeppolRules::load($this->dir);
    }

    public function test_old_format_asks_to_recompile(): void
    {
        (new RuleInstaller)->install($this->dir, self::sourceDir());
        // The first file PeppolRules::load() reads, so no rule set of this folder gets cached.
        $path = $this->dir.'/peppol-cen-ubl-3.0.22.php';
        $php = (string) file_get_contents($path);
        $old = str_replace("'format' => ".RuleSet::FORMAT.',', "'format' => 1,", $php);
        self::assertNotSame($php, $old);
        file_put_contents($path, $old);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('Recompile');

        PeppolRules::load($this->dir);
    }

    public function test_renamed_rule_file_is_refused(): void
    {
        (new RuleInstaller)->install($this->dir, self::sourceDir());
        // CEN CII content under the CEN UBL name; the first file PeppolRules::load() reads.
        copy($this->dir.'/peppol-cen-cii-3.0.22.php', $this->dir.'/peppol-cen-ubl-3.0.22.php');

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('reinstall');

        PeppolRules::load($this->dir);
    }

    public function test_failed_move_after_some_files_reports_the_written_paths(): void
    {
        $target = $this->dir.'/rules';
        // A non-empty folder under the third file's name makes its rename fail after two succeeded.
        mkdir($target.'/peppol-ubl-3.0.22.php/blocker', 0775, true);

        try {
            (new RuleInstaller)->install($target, self::sourceDir());
            self::fail('The blocked rename must fail.');
        } catch (PartialInstall $exception) {
            self::assertSame(
                ["{$target}/peppol-cen-ubl-3.0.22.php", "{$target}/peppol-cen-cii-3.0.22.php"],
                $exception->writtenPaths,
            );
            self::assertStringContainsString('peppol-ubl-3.0.22.php', $exception->getMessage());
        }
        self::assertSame([], glob($target.'/*.tmp*') ?: [], 'No temporary file may be left behind.');
    }

    private static function sourceDir(): string
    {
        return InstalledRules::sourceDir();
    }
}

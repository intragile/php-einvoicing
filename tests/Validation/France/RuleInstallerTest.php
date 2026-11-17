<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\France\RuleInstaller;
use IntrAgile\EInvoicing\Validation\Severity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

#[Group('france-rules')]
final class RuleInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('france-rule-installer-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_install_from_local_checkout_writes_seven_rule_sets_and_schemas(): void
    {
        $dir = InstalledFranceRules::dir();

        foreach (FranceSources::ruleSources() as $key => $source) {
            $path = "{$dir}/{$source['id']}-1.4.0.04.php";
            self::assertSame($path, FranceRules::path($dir, $source['id']));
            $ruleSet = RuleSet::fromFile($path);
            self::assertSame($source['id'], $ruleSet->id(), $key);
            self::assertSame('1.4.0.04', $ruleSet->version());
            self::assertSame($source['sha256'], $ruleSet->sourceSha256());
            $header = (string) file_get_contents($path, length: 800);
            self::assertStringContainsString("FNFE-MPE France_RFE 1.4.0.04 ({$source['file']}), compiled locally", $header);
            self::assertStringContainsString('licence unclear — do not redistribute', $header);
            self::assertStringContainsString('vendor/bin/einvoicing-rules install france', $header);
        }

        $schemas = FranceSources::files('schema');
        self::assertCount(163, $schemas);
        foreach ($schemas as $path => $sha256) {
            self::assertSame($sha256, hash_file('sha256', "{$dir}/france-rfe-1.4.0.04/{$path}"), $path);
        }
        self::assertCount(163, self::filesBelow("{$dir}/france-rfe-1.4.0.04"));
        self::assertSame([], glob($dir.'/*.tmp-*') ?: [], 'No temporary file may be left behind.');
        self::assertSame([], glob($dir.'/*.src-*') ?: [], 'No companion folder may be left behind.');
    }

    public function test_extended_ctc_fr_cii_has_exactly_one_defaulted_assert(): void
    {
        $source = FranceSources::ruleSources()['extended-ctc-fr-cii'];
        $document = new DOMDocument;
        self::assertTrue($document->load(Fixtures::franceRfeRoot().'/'.$source['file']));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sch', 'http://purl.oclc.org/dsdl/schematron');
        // The one assert without an id; every assert of this file carries a flag.
        self::assertSame(1.0, $xpath->evaluate('count(//sch:assert[not(@id)] | //sch:report[not(@id)])'));
        self::assertSame(0.0, $xpath->evaluate('count(//sch:assert[not(@flag)] | //sch:report[not(@flag)])'));

        $asserts = self::asserts(FranceRules::load(InstalledFranceRules::dir(), 'extended-ctc-fr-cii'));

        $defaulted = array_values(array_filter($asserts, static fn (array $assert): bool => $assert['id'] === 'BR-FXEXT-04'));
        self::assertCount(1, $defaulted);
        self::assertSame(Severity::Warning, $defaulted[0]['severity']);
        self::assertSame([], array_filter(array_column($asserts, 'id'), static fn (string $id): bool => str_starts_with($id, 'FR-REPORT-')));
    }

    public function test_flux10_ids_come_from_message_codes(): void
    {
        $asserts = self::asserts(FranceRules::load(InstalledFranceRules::dir(), 'flux10'));

        self::assertCount(150, $asserts);
        $hashed = array_values(array_filter(
            array_column($asserts, 'id'),
            static fn (string $id): bool => preg_match('/^FR10-[0-9a-f]{10}$/', $id) === 1,
        ));
        self::assertSame([], $hashed, 'Every Flux10 assert takes its id from its message code.');
        self::assertCount(150, array_unique(array_column($asserts, 'id')));
    }

    public function test_warning_variants_are_not_installed(): void
    {
        $dir = InstalledFranceRules::dir();

        $compiled = array_map('basename', glob($dir.'/*.php') ?: []);
        sort($compiled);
        $expected = array_values(array_map(static fn (array $source): string => "{$source['id']}-1.4.0.04.php", FranceSources::ruleSources()));
        sort($expected);
        self::assertSame($expected, $compiled);
        foreach (FranceSources::ruleSources() as $source) {
            self::assertStringNotContainsStringIgnoringCase('warning', $source['file']);
        }
        self::assertSame([], array_filter(self::filesBelow($dir), static fn (string $path): bool => stripos($path, 'warning') !== false));
    }

    public function test_checksum_mismatch_writes_nothing(): void
    {
        $source = $this->copyCheckout();
        // The last rule source is altered, so a check that ran only while writing would already
        // have written the others.
        self::alter($source.'/'.FranceSources::ruleSources()['facturx-extended']['file']);
        $target = $this->dir.'/rules';

        $exception = self::capture(fn () => (new RuleInstaller)->install($target, $source));

        self::assertInstanceOf(RuleSourceMismatch::class, $exception);
        self::assertStringContainsString('FACTUR-X_EXTENDED.sch', $exception->getMessage());
        self::assertStringContainsString(FranceSources::ruleSources()['facturx-extended']['sha256'], $exception->getMessage());
        self::assertDirectoryDoesNotExist($target);
    }

    public function test_tampered_schema_file_writes_nothing(): void
    {
        $source = $this->copyCheckout();
        $schemas = FranceSources::files('schema');
        $last = array_key_last($schemas) ?? self::fail('The manifest lists no schema file.');
        self::alter($source.'/'.$last);
        $target = $this->dir.'/rules';

        $exception = self::capture(fn () => (new RuleInstaller)->install($target, $source));

        self::assertInstanceOf(RuleSourceMismatch::class, $exception);
        self::assertStringContainsString($last, $exception->getMessage());
        self::assertStringContainsString($schemas[$last], $exception->getMessage());
        self::assertDirectoryDoesNotExist($target);
    }

    public function test_tampered_companion_file_writes_nothing(): void
    {
        $source = $this->copyCheckout();
        self::alter($source.'/FNFE_RFE_INVOICE/Factur-X/EXTENDED/schematron/FACTUR-X_EXTENDED_codedb.xml');
        $target = $this->dir.'/rules';

        $exception = self::capture(fn () => (new RuleInstaller)->install($target, $source));

        self::assertInstanceOf(RuleSourceMismatch::class, $exception);
        self::assertStringContainsString('FACTUR-X_EXTENDED_codedb.xml', $exception->getMessage());
        self::assertDirectoryDoesNotExist($target);
    }

    public function test_failed_tree_swap_restores_previous_tree(): void
    {
        $target = $this->dir.'/rules';
        self::copyTree(InstalledFranceRules::dir(), $target);
        $marker = "{$target}/france-rfe-1.4.0.04/".array_key_first(FranceSources::files('schema'));
        file_put_contents($marker, "\n<!-- installed earlier -->\n", FILE_APPEND);
        $compiled = FranceRules::path($target, 'fr-flux10');
        file_put_contents($compiled, "\n// installed earlier\n", FILE_APPEND);
        $markerBefore = (string) file_get_contents($marker);
        $compiledBefore = (string) file_get_contents($compiled);
        // The new schema tree fails to move into place.
        $rename = static fn (string $from, string $to): bool => $to === "{$target}/france-rfe-1.4.0.04" && str_contains($from, '.tmp-')
            ? false
            : rename($from, $to);

        $exception = self::capture(fn () => (new RuleInstaller(rename: $rename))->install($target, Fixtures::franceRfeRoot()));

        self::assertNotNull($exception);
        self::assertNotInstanceOf(PartialInstall::class, $exception);
        self::assertSame(RuntimeException::class, $exception::class, $exception->getMessage());
        self::assertStringContainsString('nothing was changed', $exception->getMessage());
        self::assertSame($markerBefore, file_get_contents($marker));
        self::assertSame($compiledBefore, file_get_contents($compiled));
        self::assertCount(163, self::filesBelow("{$target}/france-rfe-1.4.0.04"));
        self::assertSame([], glob($target.'/*.bak-*') ?: [], 'No backup may be left behind.');
        self::assertSame([], glob($target.'/*.tmp-*') ?: [], 'No temporary file may be left behind.');
        self::assertSame([], glob($target.'/*.src-*') ?: [], 'No companion folder may be left behind.');
    }

    public function test_failed_rule_move_after_the_tree_swap_restores_previous_tree(): void
    {
        $target = $this->dir.'/rules';
        self::copyTree(InstalledFranceRules::dir(), $target);
        $marker = "{$target}/france-rfe-1.4.0.04/".array_key_first(FranceSources::files('schema'));
        file_put_contents($marker, "\n<!-- installed earlier -->\n", FILE_APPEND);
        $markerBefore = (string) file_get_contents($marker);
        $moves = 0;
        // The third compiled file fails to move into place, after the tree was swapped in.
        $rename = static function (string $from, string $to) use (&$moves): bool {
            return str_ends_with($to, '.php') && str_contains($from, '.tmp-') && ++$moves === 3 ? false : rename($from, $to);
        };

        $exception = self::capture(fn () => (new RuleInstaller(rename: $rename))->install($target, Fixtures::franceRfeRoot()));

        self::assertNotNull($exception);
        self::assertSame(RuntimeException::class, $exception::class, $exception->getMessage());
        self::assertStringContainsString('nothing was changed', $exception->getMessage());
        self::assertSame($markerBefore, file_get_contents($marker));
        self::assertCount(163, self::filesBelow("{$target}/france-rfe-1.4.0.04"));
        self::assertSame([], glob($target.'/*.bak-*') ?: [], 'No backup may be left behind.');
        self::assertSame([], glob($target.'/*.tmp-*') ?: [], 'No temporary file may be left behind.');
    }

    public function test_reinstall_replaces_the_tree_and_leaves_no_backup(): void
    {
        $target = $this->dir.'/rules';
        self::copyTree(InstalledFranceRules::dir(), $target);
        $stray = "{$target}/france-rfe-1.4.0.04/stray.xsd";
        file_put_contents($stray, '<stray/>');

        $written = (new RuleInstaller)->install($target, Fixtures::franceRfeRoot());

        self::assertCount(7, $written);
        self::assertFileDoesNotExist($stray);
        self::assertCount(163, self::filesBelow("{$target}/france-rfe-1.4.0.04"));
        self::assertSame([], glob($target.'/*.bak-*') ?: [], 'No backup may be left behind.');
        self::assertSame([], glob($target.'/*.tmp-*') ?: [], 'No temporary file may be left behind.');
    }

    /**
     * @return list<array{id: string, severity: Severity}>
     */
    private static function asserts(RuleSet $ruleSet): array
    {
        $asserts = [];
        foreach ($ruleSet->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                foreach ($rule['asserts'] as $assert) {
                    $asserts[] = ['id' => $assert['id'], 'severity' => $assert['severity']];
                }
            }
        }

        return $asserts;
    }

    /**
     * Copies the rule and schema files of the France_RFE checkout into the test folder.
     */
    private function copyCheckout(): string
    {
        $root = Fixtures::franceRfeRoot();
        $copy = $this->dir.'/source';
        foreach ([...array_keys(FranceSources::files('rules')), ...array_keys(FranceSources::files('schema'))] as $path) {
            if (! is_dir(dirname("{$copy}/{$path}"))) {
                mkdir(dirname("{$copy}/{$path}"), 0775, true);
            }
            copy("{$root}/{$path}", "{$copy}/{$path}");
        }

        return $copy;
    }

    private static function copyTree(string $from, string $to): void
    {
        mkdir($to, 0775, true);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            if (! $entry instanceof SplFileInfo) {
                continue;
            }
            $target = $to.substr($entry->getPathname(), strlen($from));
            $entry->isDir() ? mkdir($target) : copy($entry->getPathname(), $target);
        }
    }

    private static function alter(string $path): void
    {
        $bytes = (string) file_get_contents($path);
        $bytes[100] = $bytes[100] === 'a' ? 'b' : 'a';
        file_put_contents($path, $bytes);
    }

    /**
     * @return list<string>
     */
    private static function filesBelow(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function capture(callable $action): ?Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\Process;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RulesCommandTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../../bin/einvoicing-rules';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('france-rules-command-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    #[Group('france-rules')]
    public function test_check_fails_until_install_succeeds(): void
    {
        [$exit, , $stderr] = self::runScript('check', 'france', "--dir={$this->dir}");
        self::assertSame(1, $exit);
        self::assertStringContainsString("vendor/bin/einvoicing-rules install france --dir=\"{$this->dir}\"", $stderr);

        [$exit, $stdout, $stderr] = self::runScript('install', 'france', "--dir={$this->dir}", '--from='.Fixtures::franceRfeRoot());
        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString("{$this->dir}/fr-flux10-1.4.0.04.php", $stdout);
        self::assertStringContainsString('163 schema files', $stdout);
        self::assertStringContainsString('do not redistribute', $stdout);

        [$exit, $stdout, $stderr] = self::runScript('check', 'france', "--dir={$this->dir}");
        self::assertSame(0, $exit, $stderr);
        self::assertSame(7, substr_count($stdout, ' 1.4.0.04'), $stdout);
        self::assertStringContainsString('fr-facturx-extended 1.4.0.04', $stdout);
        self::assertStringContainsString('163 schema files', $stdout);
    }

    #[Group('france-rules')]
    public function test_check_fails_on_a_tampered_schema_file(): void
    {
        [$exit, , $stderr] = self::runScript('install', 'france', "--dir={$this->dir}", '--from='.Fixtures::franceRfeRoot());
        self::assertSame(0, $exit, $stderr);
        $schema = array_key_last(FranceSources::files('schema')) ?? self::fail('The manifest lists no schema file.');
        file_put_contents("{$this->dir}/france-rfe-1.4.0.04/{$schema}", "\n", FILE_APPEND);

        [$exit, $stdout, $stderr] = self::runScript('check', 'france', "--dir={$this->dir}");

        self::assertSame(1, $exit);
        self::assertSame('', $stdout);
        self::assertStringContainsString($schema, $stderr);
        self::assertStringContainsString('vendor/bin/einvoicing-rules install france', $stderr);
    }

    public function test_install_reports_a_bad_source_folder(): void
    {
        [$exit, , $stderr] = self::runScript('install', 'france', "--dir={$this->dir}/rules", "--from={$this->dir}/missing");

        self::assertSame(1, $exit);
        self::assertStringContainsString('Nothing was installed', $stderr);
        self::assertStringContainsString("{$this->dir}/missing", $stderr);
        self::assertDirectoryDoesNotExist("{$this->dir}/rules");
    }

    public function test_usage_names_the_france_target(): void
    {
        foreach ([['install', 'france'], ['check', 'france', '--dir=x', '--from=y'], ['install', 'francia', '--dir=x']] as $arguments) {
            [$exit, $stdout, $stderr] = self::runScript(...$arguments);

            self::assertSame(1, $exit, implode(' ', $arguments));
            self::assertSame('', $stdout);
            self::assertStringContainsString('install france --dir=DIR [--from=DIR]', $stderr);
            self::assertStringContainsString('check france --dir=DIR', $stderr);
            self::assertStringContainsString('install peppol --dir=DIR [--from=DIR]', $stderr);
        }
    }

    /**
     * @return array{int, string, string} exit code, standard output, standard error
     */
    private static function runScript(string ...$arguments): array
    {
        $result = Process::run([PHP_BINARY, self::SCRIPT, ...array_values($arguments)]);

        return [$result['status'], $result['stdout'], $result['stderr']];
    }
}

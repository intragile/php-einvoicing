<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Spain;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\Process;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RulesCommandTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../../bin/einvoicing-rules';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('spain-rules-command-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    #[Group('verifactu-official')]
    public function test_check_fails_until_install_succeeds(): void
    {
        [$exit, , $stderr] = self::runScript('check', 'spain', "--dir={$this->dir}/rules");
        self::assertSame(1, $exit);
        self::assertStringContainsString('SuministroLR.xsd', $stderr);
        self::assertStringContainsString("vendor/bin/einvoicing-rules install spain --dir=\"{$this->dir}/rules\"", $stderr);

        $from = dirname(Fixtures::verifactuSchemas()->informationXsd());
        [$exit, $stdout, $stderr] = self::runScript('install', 'spain', "--dir={$this->dir}/rules", "--from={$from}");
        self::assertSame(0, $exit, $stderr);
        self::assertSame(8, substr_count($stdout, "Wrote {$this->dir}/rules/".VerifactuSchemas::DIR.'/'), $stdout);
        self::assertStringContainsString('Downloaded for this machine only: AEAT publishes no licence for these files, do not redistribute them.', $stdout);

        [$exit, $stdout, $stderr] = self::runScript('check', 'spain', "--dir={$this->dir}/rules");
        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('8 VERI*FACTU files', $stdout);
    }

    public function test_install_reports_a_bad_source_folder(): void
    {
        [$exit, , $stderr] = self::runScript('install', 'spain', "--dir={$this->dir}/rules", "--from={$this->dir}/missing");

        self::assertSame(1, $exit);
        self::assertStringContainsString('Nothing was installed', $stderr);
        self::assertStringContainsString("{$this->dir}/missing", $stderr);
        self::assertDirectoryDoesNotExist("{$this->dir}/rules");
    }

    public function test_usage_names_the_spain_target(): void
    {
        [$exit, $stdout, $stderr] = self::runScript('check', 'spain', '--dir=x', '--from=y');

        self::assertSame(1, $exit);
        self::assertSame('', $stdout);
        self::assertStringContainsString('install spain --dir=DIR [--from=DIR]', $stderr);
        self::assertStringContainsString('check spain --dir=DIR', $stderr);
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

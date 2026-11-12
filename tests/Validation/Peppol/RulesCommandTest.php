<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RulesCommandTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../../bin/einvoicing-rules';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('rules-command-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_check_fails_until_install_succeeds(): void
    {
        [$exit, , $stderr] = self::runScript('check', 'peppol', "--dir={$this->dir}");
        self::assertSame(1, $exit);
        self::assertStringContainsString('vendor/bin/einvoicing-rules install peppol', $stderr);

        $source = dirname(Fixtures::peppolSchematron('cen-ubl'));
        [$exit, $stdout, $stderr] = self::runScript('install', 'peppol', "--dir={$this->dir}", "--from={$source}");
        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString("{$this->dir}/peppol-ubl-3.0.22.php", $stdout);
        self::assertStringContainsString('do not redistribute', $stdout);

        [$exit, $stdout, $stderr] = self::runScript('check', 'peppol', "--dir={$this->dir}");
        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('peppol-cen-cii 3.0.22', $stdout);
    }

    public function test_install_reports_a_bad_source_folder(): void
    {
        [$exit, , $stderr] = self::runScript('install', 'peppol', "--dir={$this->dir}/rules", "--from={$this->dir}/missing");

        self::assertSame(1, $exit);
        self::assertStringContainsString("{$this->dir}/missing", $stderr);
        self::assertDirectoryDoesNotExist("{$this->dir}/rules");
    }

    public function test_unknown_commands_print_the_usage(): void
    {
        foreach ([[], ['update', 'peppol', '--dir=x'], ['install', 'xrechnung', '--dir=x'], ['install', 'peppol'], ['check', 'peppol', '--dir=x', '--from=y']] as $arguments) {
            [$exit, $stdout, $stderr] = self::runScript(...$arguments);

            self::assertSame(1, $exit, implode(' ', $arguments));
            self::assertSame('', $stdout);
            self::assertStringContainsString('install peppol --dir=DIR [--from=DIR]', $stderr);
        }
    }

    public function test_failed_install_reports_the_rollback(): void
    {
        mkdir($this->dir.'/peppol-ubl-3.0.22.php/blocker', 0775, true);
        $source = dirname(Fixtures::peppolSchematron('cen-ubl'));

        [$exit, , $stderr] = self::runScript('install', 'peppol', "--dir={$this->dir}", "--from={$source}");

        self::assertSame(1, $exit);
        self::assertStringContainsString('Nothing was installed', $stderr);
        self::assertStringContainsString('nothing was changed', $stderr);
        self::assertStringNotContainsString('Installation incomplete', $stderr);
        self::assertFileDoesNotExist("{$this->dir}/peppol-cen-ubl-3.0.22.php");
    }

    /**
     * @return array{int, string, string} exit code, standard output, standard error
     */
    private static function runScript(string ...$arguments): array
    {
        $command = [PHP_BINARY, self::SCRIPT, ...array_values($arguments)];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if ($process === false) {
            throw new RuntimeException('Cannot start bin/einvoicing-rules.');
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessTest extends TestCase
{
    public function test_returns_status_and_both_streams(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(3);']);

        self::assertSame(['status' => 3, 'stdout' => 'out', 'stderr' => 'err'], $result);
    }

    public function test_passes_standard_input(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'echo strtoupper(stream_get_contents(STDIN));'], stdin: 'hello');

        self::assertSame(0, $result['status']);
        self::assertSame('HELLO', $result['stdout']);
    }

    public function test_large_stderr_does_not_deadlock(): void
    {
        $script = 'fwrite(STDERR, str_repeat("e", 1 << 20)); fwrite(STDOUT, str_repeat("o", 1 << 20));';
        $started = hrtime(true);

        $result = Process::run([PHP_BINARY, '-r', $script], 10.0);

        self::assertLessThan(10.0, (hrtime(true) - $started) / 1e9);
        self::assertSame(0, $result['status']);
        self::assertSame(1 << 20, strlen($result['stderr']));
        self::assertSame(1 << 20, strlen($result['stdout']));
    }

    public function test_timeout_kills_the_child(): void
    {
        $started = hrtime(true);
        try {
            Process::run(['sleep', '30'], 1.0);
            self::fail('A child that outlives its timeout must make the call throw.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('sleep 30', $exception->getMessage());
            self::assertStringContainsString('timed out', $exception->getMessage());
        }

        self::assertLessThan(3.0, (hrtime(true) - $started) / 1e9);
    }

    /**
     * Where proc_open() spawns with posix_spawn() the start itself fails; where it forks first, the
     * child cannot exec and exits with 127, as a shell does.
     */
    public function test_unknown_program_fails_loudly(): void
    {
        try {
            $result = Process::run(['/nonexistent/einvoicing-test-command']);
            self::assertSame(127, $result['status']);
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Cannot start "/nonexistent/einvoicing-test-command"', $exception->getMessage());
        }
    }

    public function test_reports_a_signal_as_128_plus_its_number(): void
    {
        $result = Process::run(['sh', '-c', 'kill -TERM $$']);

        self::assertSame(128 + 15, $result['status']);
    }
}

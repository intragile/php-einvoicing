<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use RuntimeException;

/**
 * Runs a child process for a test and collects its exit status and both output streams.
 *
 * Both pipes are drained together with stream_select(), so a child that fills one of them (for
 * example a megabyte of standard error before any standard output) never blocks on a full pipe
 * while the parent waits on the other. A child that outlives its timeout is terminated, and the
 * call throws instead of hanging the test run.
 */
final class Process
{
    /** Read size per stream_select() round, in bytes. */
    private const CHUNK = 65536;

    /** Seconds a terminated child gets to exit before it is killed. */
    private const KILL_GRACE = 1.0;

    /**
     * Runs $command without a shell: the first element is the program, found on the PATH when it
     * has no slash, and every other element is passed as one argument, unquoted.
     *
     * @param  list<string>  $command  program and arguments
     * @param  float  $timeoutSeconds  wall-clock bound for the whole run
     * @param  string|null  $stdin  bytes written to the child's standard input, which is then
     *                              closed; null closes it at once
     * @return array{status: int, stdout: string, stderr: string}
     *
     * @throws RuntimeException when the process cannot be started, or runs longer than
     *                          $timeoutSeconds (the child is terminated first)
     */
    public static function run(array $command, float $timeoutSeconds = 120.0, ?string $stdin = null): array
    {
        if ($command === []) {
            throw new RuntimeException('No command to run.');
        }
        $display = implode(' ', $command);

        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException("Cannot start \"{$display}\": ".(error_get_last()['message'] ?? 'unknown error').'.');
        }

        $input = $stdin ?? '';
        if ($input === '') {
            fclose($pipes[0]);
        } else {
            stream_set_blocking($pipes[0], false);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = [1 => '', 2 => ''];
        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $deadline = hrtime(true) / 1e9 + $timeoutSeconds;

        while ($open !== [] || $input !== '') {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                self::terminate($process, $pipes, $input !== '');

                throw new RuntimeException(sprintf('"%s" timed out after %.1f s and was terminated.', $display, $timeoutSeconds));
            }

            $read = array_values($open);
            $write = $input !== '' ? [$pipes[0]] : [];
            $except = null;
            $seconds = (int) $remaining;
            $ready = @stream_select($read, $write, $except, $seconds, (int) (($remaining - $seconds) * 1e6));
            if ($ready === false) {
                // Interrupted by a signal; the deadline check above still bounds the loop.
                continue;
            }

            if ($write !== []) {
                $written = @fwrite($pipes[0], $input);
                if ($written === false || $written === 0) {
                    // The child closed its standard input; the rest cannot be delivered.
                    $input = '';
                } else {
                    $input = substr($input, $written);
                }
                if ($input === '') {
                    fclose($pipes[0]);
                }
            }

            foreach ($read as $stream) {
                $index = array_search($stream, $open, true);
                if ($index === false) {
                    continue;
                }
                $chunk = fread($stream, self::CHUNK);
                if ($chunk !== false && $chunk !== '') {
                    $output[$index] .= $chunk;
                } elseif (feof($stream)) {
                    fclose($stream);
                    unset($open[$index]);
                }
            }
        }

        // The child may close its output before it exits, so its exit is awaited under the same
        // deadline. proc_get_status() reports the exit code only once, on the first call that
        // finds the child stopped, and proc_close() cannot return it afterwards.
        while (($status = proc_get_status($process))['running']) {
            if (hrtime(true) / 1e9 >= $deadline) {
                self::terminate($process, $pipes, false);

                throw new RuntimeException(sprintf('"%s" timed out after %.1f s and was terminated.', $display, $timeoutSeconds));
            }
            usleep(5_000);
        }
        proc_close($process);
        $exitCode = $status['signaled'] ? 128 + $status['termsig'] : $status['exitcode'];

        return ['status' => $exitCode, 'stdout' => $output[1], 'stderr' => $output[2]];
    }

    /**
     * Sends SIGTERM, then SIGKILL when the child has not exited within KILL_GRACE, and releases
     * the process handle.
     *
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     */
    private static function terminate($process, array $pipes, bool $stdinOpen): void
    {
        proc_terminate($process);
        $deadline = hrtime(true) / 1e9 + self::KILL_GRACE;
        while (proc_get_status($process)['running'] && hrtime(true) / 1e9 < $deadline) {
            usleep(10_000);
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }

        if ($stdinOpen) {
            fclose($pipes[0]);
        }
        foreach ([1, 2] as $index) {
            if (is_resource($pipes[$index])) {
                fclose($pipes[$index]);
            }
        }
        proc_close($process);
    }
}

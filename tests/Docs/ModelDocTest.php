<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Docs;

use PHPUnit\Framework\TestCase;

final class ModelDocTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../bin/generate-model-doc.php';

    public function test_generator_prints_the_term_table(): void
    {
        [$status, $output] = $this->runScript(['--stdout']);

        self::assertSame(0, $status);
        self::assertStringContainsString('| BT-1 | `Invoice` | `$number` |', $output);
        self::assertStringContainsString('BT-153', $output);
        self::assertStringContainsString('BG-23', $output);
        self::assertStringContainsString('https://intragile.eu', $output);
    }

    public function test_generator_is_deterministic(): void
    {
        self::assertSame($this->runScript(['--stdout']), $this->runScript(['--stdout']));
    }

    public function test_committed_model_doc_is_up_to_date(): void
    {
        [$status, $output] = $this->runScript(['--check']);

        self::assertSame(0, $status, $output);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{int, string}
     */
    private function runScript(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, self::SCRIPT], $arguments);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}

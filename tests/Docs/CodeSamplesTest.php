<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Docs;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Keeps the PHP samples of the documentation working.
 *
 * Every fenced php block in the READMEs, docs/*.md and the rule packages' READMEs must compile
 * (php -l). A block whose fence is preceded by the line <!-- sample: run --> is also executed in
 * a child process with the Composer autoloader loaded and a fresh working directory that holds a
 * valid EN 16931 UBL invoice as invoice.xml. It must exit 0 and write nothing to standard error.
 */
final class CodeSamplesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    private const RUN_MARKER = '<!-- sample: run -->';

    private const BUILD_DIR = self::ROOT.'/build/docs-samples';

    /** Minimum number of run-marked samples per document. */
    private const REQUIRED_RUNNABLE = [
        'README.md' => 3,
        'README.hu.md' => 3,
        'docs/FACTURX.md' => 1,
        'docs/TRANSPORT.md' => 1,
    ];

    /**
     * @return iterable<string, array{string, int, string, bool}>
     */
    public static function samples(): iterable
    {
        foreach (self::documents() as $document) {
            foreach (self::extract($document) as [$line, $code, $run]) {
                yield "{$document}:{$line}" => [$document, $line, $code, $run];
            }
        }
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function runnableSamples(): iterable
    {
        foreach (self::samples() as $name => [$document, $line, $code, $run]) {
            if ($run) {
                yield $name => [$document, $line, $code];
            }
        }
    }

    #[DataProvider('samples')]
    public function test_sample_compiles(string $document, int $line, string $code, bool $run): void
    {
        $file = self::scratchFile($document, $line, 'lint');
        file_put_contents($file, str_starts_with(ltrim($code), '<?php') ? $code : "<?php\n".$code);

        $result = Process::run([PHP_BINARY, '-l', $file], 60.0);

        self::assertSame(0, $result['status'], "{$document}:{$line} does not compile:\n".$result['stdout'].$result['stderr']);
    }

    #[DataProvider('runnableSamples')]
    public function test_runnable_sample_runs(string $document, int $line, string $code): void
    {
        $workDir = self::BUILD_DIR.'/work/'.self::slug($document).'-'.$line;
        self::emptyDirectory($workDir);
        file_put_contents($workDir.'/invoice.xml', (new UblWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat())));

        $file = self::scratchFile($document, $line, 'run');
        file_put_contents($file, self::runnable($code, $workDir));

        // Notices, warnings and deprecations go to standard error, which must stay empty.
        $result = Process::run([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=-1', $file], 60.0);

        $output = "{$document}:{$line}\n--- stdout\n{$result['stdout']}\n--- stderr\n{$result['stderr']}";
        self::assertSame(0, $result['status'], $output);
        self::assertSame('', $result['stderr'], $output);
    }

    public function test_required_samples_are_marked_runnable(): void
    {
        foreach (self::REQUIRED_RUNNABLE as $document => $minimum) {
            $runnable = array_filter(self::extract($document), static fn (array $sample): bool => $sample[2]);
            self::assertGreaterThanOrEqual($minimum, count($runnable), "{$document} has fewer run-marked samples than expected.");
        }
    }

    public function test_every_run_marker_precedes_a_php_block(): void
    {
        foreach (self::documents() as $document) {
            $lines = self::lines($document);
            foreach ($lines as $index => $text) {
                if (trim($text) !== self::RUN_MARKER) {
                    continue;
                }
                $next = $index + 1;
                while (isset($lines[$next]) && trim($lines[$next]) === '') {
                    $next++;
                }
                self::assertSame('```php', trim($lines[$next] ?? ''), "{$document}:".($index + 1).' marks no php block.');
            }
        }
    }

    /**
     * @return list<string> paths relative to the repository root
     */
    private static function documents(): array
    {
        $paths = [
            ...(glob(self::ROOT.'/README*.md') ?: []),
            ...(glob(self::ROOT.'/docs/*.md') ?: []),
            ...(glob(self::ROOT.'/packages/*/README.md') ?: []),
        ];
        $prefix = realpath(self::ROOT).'/';

        return array_map(static fn (string $path): string => substr((string) realpath($path), strlen($prefix)), $paths);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $document): array
    {
        $content = file_get_contents(self::ROOT.'/'.$document);
        if ($content === false) {
            throw new RuntimeException("Cannot read {$document}.");
        }

        return explode("\n", $content);
    }

    /**
     * Returns the fenced php blocks of a document: the line number of the opening fence, the code
     * and whether the block is marked to run.
     *
     * @return list<array{int, string, bool}>
     */
    private static function extract(string $document): array
    {
        $lines = self::lines($document);
        $samples = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            if (trim($lines[$index]) !== '```php') {
                continue;
            }
            $previous = $index - 1;
            while ($previous >= 0 && trim($lines[$previous]) === '') {
                $previous--;
            }
            $run = $previous >= 0 && trim($lines[$previous]) === self::RUN_MARKER;

            $start = $index + 1;
            $end = $start;
            while ($end < $count && trim($lines[$end]) !== '```') {
                $end++;
            }
            if ($end === $count) {
                throw new RuntimeException("{$document}:".($index + 1).' has an unterminated php block.');
            }
            $samples[] = [$index + 1, implode("\n", array_slice($lines, $start, $end - $start))."\n", $run];
            $index = $end;
        }

        return $samples;
    }

    /**
     * Prepends the autoloader and a change into the working directory, after the opening tag and
     * a leading strict_types declaration, which must stay first.
     */
    private static function runnable(string $code, string $workDir): string
    {
        $body = ltrim($code);
        if (str_starts_with($body, '<?php')) {
            $body = ltrim(substr($body, 5));
        }
        $declare = '';
        if (preg_match('/\Adeclare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $body, $match) === 1) {
            $declare = $match[0]."\n";
            $body = ltrim(substr($body, strlen($match[0])));
        }

        return "<?php\n".$declare
            .'require '.var_export((string) realpath(self::ROOT.'/vendor/autoload.php'), true).";\n"
            .'chdir('.var_export($workDir, true).");\n"
            .$body;
    }

    private static function scratchFile(string $document, int $line, string $kind): string
    {
        $directory = self::BUILD_DIR.'/'.$kind;
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }

        return $directory.'/'.self::slug($document).'-'.$line.'.php';
    }

    private static function slug(string $document): string
    {
        return (string) preg_replace('/[^A-Za-z0-9]+/', '-', $document);
    }

    private static function emptyDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            foreach (scandir($directory) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..' && is_file($directory.'/'.$entry)) {
                    unlink($directory.'/'.$entry);
                }
            }
        } elseif (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }
    }
}

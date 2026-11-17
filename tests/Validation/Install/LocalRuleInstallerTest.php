<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Install;

use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Install\LocalRuleInstaller;
use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use IntrAgile\EInvoicing\Validation\Peppol\StreamOpener;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class LocalRuleInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('local-rule-installer-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_failed_tree_write_leaves_no_temporary_tree(): void
    {
        $installed = "{$this->dir}/tree/old.xsd";
        mkdir(dirname($installed));
        file_put_contents($installed, '<old/>');
        // The second file needs a folder where the first one is a file, so writing the tree fails
        // after a file was already written.
        $files = ['a.xsd' => '<a/>', 'a.xsd/b.xsd' => '<b/>'];
        $verbatim = array_map(static fn (string $contents): string => hash('sha256', $contents), $files);
        $installer = new LocalRuleInstaller(new Downloader(opener: self::opener($files)));

        $exception = null;
        try {
            $installer->install($this->dir, [], 'https://example.com/', null, '%s', 'command', $verbatim, 'tree');
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertStringContainsString('a.xsd', $exception->getMessage());
        self::assertSame([], glob($this->dir.'/*.tmp-*') ?: [], 'No temporary tree may be left behind.');
        self::assertSame(['old.xsd'], array_values(array_diff(scandir(dirname($installed)) ?: [], ['.', '..'])));
        self::assertSame('<old/>', file_get_contents($installed));
    }

    public function test_installed_tree_that_cannot_be_moved_aside_is_left_unchanged(): void
    {
        $installed = "{$this->dir}/tree/old.xsd";
        mkdir(dirname($installed));
        file_put_contents($installed, '<old/>');
        $files = ['a.xsd' => '<a/>'];
        $verbatim = array_map(static fn (string $contents): string => hash('sha256', $contents), $files);
        $rename = fn (string $from, string $to): bool => $from === "{$this->dir}/tree" ? false : rename($from, $to);
        $installer = new LocalRuleInstaller(new Downloader(opener: self::opener($files)), $rename);

        $exception = null;
        try {
            $installer->install($this->dir, [], 'https://example.com/', null, '%s', 'command', $verbatim, 'tree');
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertStringContainsString('nothing was changed', $exception->getMessage());
        self::assertStringNotContainsString('restored', $exception->getMessage());
        self::assertSame('<old/>', file_get_contents($installed));
        self::assertSame([], glob($this->dir.'/*.tmp-*') ?: [], 'No temporary tree may be left behind.');
    }

    /**
     * Serves each file at "https://example.com/<path>".
     *
     * @param  array<string, string>  $files
     */
    private static function opener(array $files): StreamOpener
    {
        return new class($files) implements StreamOpener
        {
            /** @param  array<string, string>  $files */
            public function __construct(private readonly array $files) {}

            public function open(string $url, int $timeoutSeconds): array|false
            {
                $path = rawurldecode(substr($url, strlen('https://example.com/')));
                $stream = fopen('php://memory', 'w+b');
                if ($stream === false || ! isset($this->files[$path])) {
                    return false;
                }
                fwrite($stream, $this->files[$path]);
                rewind($stream);

                return [['HTTP/1.1 200 OK'], $stream];
            }
        };
    }
}

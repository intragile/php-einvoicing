<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the source against constructs that newer PHP versions deprecate, so a deprecation shows
 * up on every supported version and not only on the newest one.
 */
final class PhpCompatibilityTest extends TestCase
{
    public function test_no_class_constant_is_named_namespace(): void
    {
        // PHP 8.6 deprecates class constants called "namespace" (case-insensitive).
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/\bconst\s+(?:[\\\\\w|?]+\s+)?namespace\s*=/i', $source) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        self::assertSame([], $offenders);
    }
}

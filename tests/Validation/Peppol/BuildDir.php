<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use RuntimeException;

/**
 * Throwaway directories under the git-ignored build/ folder, for compiled Peppol rules that must
 * never end up in the repository.
 */
final class BuildDir
{
    public static function create(string $prefix): string
    {
        $dir = dirname(__DIR__, 3).'/build/'.$prefix.'-'.bin2hex(random_bytes(6));
        if (! mkdir($dir, 0775, true)) {
            throw new RuntimeException("Cannot create {$dir}");
        }

        return $dir;
    }

    public static function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) && ! is_link($path) ? self::remove($path) : unlink($path);
        }
        rmdir($dir);
    }
}

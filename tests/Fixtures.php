<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use InvalidArgumentException;

/**
 * Locates the official CEN EN 16931 artefacts (validation-1.3.17) used as test fixtures.
 */
final class Fixtures
{
    private const OFFICIAL_DIR = __DIR__.'/fixtures/official/cen-1.3.17';

    private const STYLESHEETS = [
        'ubl' => 'EN16931-UBL-validation.xslt',
        'cii' => 'EN16931-CII-validation.xslt',
    ];

    /**
     * Absolute paths of the official example invoices for a syntax, sorted by file name.
     *
     * @return list<string>
     */
    public static function official(string $syntax): array
    {
        $files = array_filter(
            glob(self::syntaxDir($syntax).'/examples/*') ?: [],
            static fn (string $file): bool => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml',
        );
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Absolute path of the official compiled Schematron (XSLT) for a syntax.
     */
    public static function officialXslt(string $syntax): string
    {
        return self::syntaxDir($syntax).'/xslt/'.self::STYLESHEETS[$syntax];
    }

    private static function syntaxDir(string $syntax): string
    {
        if (! isset(self::STYLESHEETS[$syntax])) {
            throw new InvalidArgumentException("Unknown syntax \"{$syntax}\"; expected \"ubl\" or \"cii\".");
        }

        return self::OFFICIAL_DIR.'/'.$syntax;
    }
}

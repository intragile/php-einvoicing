<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Rules\En16931;

/**
 * Locates the compiled CEN EN 16931 rule sets. Each file returns the compiled array of one syntax.
 */
final class RuleFiles
{
    public const VERSION = '1.3.17';

    /**
     * Absolute path of the compiled UBL rule set.
     */
    public static function ubl(): string
    {
        return self::path('ubl');
    }

    /**
     * Absolute path of the compiled CII rule set.
     */
    public static function cii(): string
    {
        return self::path('cii');
    }

    private static function path(string $syntax): string
    {
        return dirname(__DIR__).'/rules/en16931-'.$syntax.'-'.self::VERSION.'.php';
    }
}

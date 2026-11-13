<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Rules\En16931;

use InvalidArgumentException;

/**
 * Locates the compiled CEN EN 16931 rule sets. Each file returns the compiled array of one syntax
 * in one release of the validation artefacts.
 */
final class RuleFiles
{
    /**
     * The release used when no version is given.
     */
    public const VERSION = '1.3.17';

    /**
     * Every release shipped in this package, oldest first. 1.3.16 is the release the KoSIT
     * XRechnung configuration is built on.
     */
    public const VERSIONS = ['1.3.16', '1.3.17'];

    /**
     * Absolute path of the compiled UBL rule set.
     *
     * @throws InvalidArgumentException when the version is not one of VERSIONS
     */
    public static function ubl(string $version = self::VERSION): string
    {
        return self::path('ubl', $version);
    }

    /**
     * Absolute path of the compiled CII rule set.
     *
     * @throws InvalidArgumentException when the version is not one of VERSIONS
     */
    public static function cii(string $version = self::VERSION): string
    {
        return self::path('cii', $version);
    }

    private static function path(string $syntax, string $version): string
    {
        if (! in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown EN 16931 rule version "%s"; available versions: %s.',
                $version,
                implode(', ', self::VERSIONS),
            ));
        }

        return dirname(__DIR__).'/rules/en16931-'.$syntax.'-'.$version.'.php';
    }
}

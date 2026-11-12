<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\Peppol\RuleInstaller;

/**
 * One Peppol rules folder per test run, compiled from the downloaded fixtures. RuleSet::fromFile
 * keeps every loaded file for the life of the process, so tests that load rule sets share this
 * folder instead of each loading their own copy.
 */
final class InstalledRules
{
    private static ?string $dir = null;

    public static function dir(): string
    {
        if (self::$dir === null) {
            $source = self::sourceDir();
            $dir = BuildDir::create('peppol-rules-test');
            register_shutdown_function(static fn () => BuildDir::remove($dir));
            (new RuleInstaller)->install($dir, $source);
            self::$dir = $dir;
        }

        return self::$dir;
    }

    /**
     * The folder of the downloaded Peppol Schematron files.
     */
    public static function sourceDir(): string
    {
        return dirname(Fixtures::peppolSchematron('cen-ubl'));
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\France\RuleInstaller;

/**
 * One French rules folder per test run, compiled from the downloaded France_RFE checkout.
 * RuleSet::fromFile keeps every loaded file for the life of the process, so tests that load rule
 * sets share this folder instead of each loading their own copy.
 */
final class InstalledFranceRules
{
    private static ?string $dir = null;

    public static function dir(): string
    {
        if (self::$dir === null) {
            $source = Fixtures::franceRfeRoot();
            $dir = BuildDir::create('france-rules');
            register_shutdown_function(static fn () => BuildDir::remove($dir));
            (new RuleInstaller)->install($dir, $source);
            self::$dir = $dir;
        }

        return self::$dir;
    }
}

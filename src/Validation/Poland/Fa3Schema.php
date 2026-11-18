<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Poland;

use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;

/**
 * The bundled FA(3) XML Schema (version 1-0E) of the Polish Ministry of Finance.
 *
 * The files are byte-identical to the published ones (see resources/poland/fa3-1-0E/SOURCE.md).
 * The main schema imports the shared type definitions by an absolute http location, which
 * localPrefixes() maps onto the bundle folder so that validation stays offline.
 */
final class Fa3Schema
{
    private const MAIN = 'schemat_FA(3)_v1-0E.xsd';

    private const FILES = [
        self::MAIN => 'b646b6b525f51adf1bb2545f111fc8ca6e7aa6dd2f98948f1667d3695c06d958',
        'StrukturyDanych_v10-0E.xsd' => 'cb08348374598e1e716e086c40d740390fb9e1bfa3aba1f4ec4cba0e1ef6d60f',
        'ElementarneTypyDanych_v10-0E.xsd' => '8daf4d3771de200b26b697294cc906a2add3de9acfbbd97f4b1bd4fc0e5ecb2f',
        'KodyKrajow_v10-0E.xsd' => '48be2a9f181d7ff80f185c62491ba12604c5cacbbe21af8e2aaaf2c585bbd214',
        'LICENSE.txt' => 'f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b',
    ];

    private function __construct() {}

    /**
     * Absolute path of the main schema file.
     */
    public static function path(): string
    {
        return self::dir().'/'.self::MAIN;
    }

    /**
     * The URI prefix map for SchemaValidator: the namespace folder of the shared type definitions
     * resolves to the bundle folder.
     *
     * @return array<string, string>
     */
    public static function localPrefixes(): array
    {
        return [Fa3Format::ETD_NS => self::dir()];
    }

    /**
     * The bundled files with their pinned SHA-256 sums, keyed by file name.
     *
     * @return array<string, string>
     */
    public static function files(): array
    {
        return self::FILES;
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 3).'/resources/poland/fa3-'.Fa3Format::SCHEMA_VERSION;
    }
}

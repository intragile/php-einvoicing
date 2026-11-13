<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Rules\XRechnung;

/**
 * Locates the compiled XRechnung rule sets, the scenario table compiled from the KoSIT validator
 * configuration, and the XML Schemas that table names.
 */
final class RuleFiles
{
    /**
     * Release of the KoSIT XRechnung Schematron the rule sets are compiled from.
     */
    public const VERSION = '2.6.0';

    /**
     * Release of the KoSIT validator configuration the scenario table and schemas come from.
     */
    public const CONFIGURATION = '2026-08-31';

    /**
     * Absolute path of the compiled XRechnung UBL rule set.
     */
    public static function ubl(): string
    {
        return self::root().'/rules/xrechnung-ubl-'.self::VERSION.'.php';
    }

    /**
     * Absolute path of the compiled XRechnung CII rule set.
     */
    public static function cii(): string
    {
        return self::root().'/rules/xrechnung-cii-'.self::VERSION.'.php';
    }

    /**
     * Absolute path of the scenario table. The file returns an array with the keys format,
     * configuration and scenarios; each scenario names its match expression, schema (relative to
     * schemaDir()), rule sets in step order and custom levels.
     */
    public static function scenarios(): string
    {
        return self::root().'/rules/scenarios-'.self::CONFIGURATION.'.php';
    }

    /**
     * Absolute path of the folder holding the XML Schemas of the scenario table, without a
     * trailing slash.
     */
    public static function schemaDir(): string
    {
        return self::root().'/schemas';
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }
}

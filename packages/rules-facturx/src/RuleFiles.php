<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Rules\FacturX;

use InvalidArgumentException;

/**
 * Locates the compiled Factur-X rule sets and the XML Schemas of the five profiles.
 */
final class RuleFiles
{
    /**
     * Release of the Factur-X technical artefacts the rule sets are compiled from.
     */
    public const VERSION = '1.09.2';

    private const PROFILES = ['MINIMUM', 'BASIC-WL', 'BASIC', 'EN16931', 'EXTENDED'];

    /**
     * The profiles that have a rule set and a schema, from the smallest to the largest.
     *
     * @return list<string>
     */
    public static function profiles(): array
    {
        return self::PROFILES;
    }

    /**
     * Absolute path of the compiled rule set of a profile.
     *
     * @throws InvalidArgumentException for a profile that has no rule set
     */
    public static function rules(string $profile): string
    {
        return self::root().'/rules/facturx-'.strtolower(str_replace('-', '', self::checked($profile))).'-'.self::VERSION.'.php';
    }

    /**
     * Absolute path of the profile's top-level XML Schema, FACTUR-X_<profile>.xsd. The schema
     * files it imports sit next to it.
     *
     * @throws InvalidArgumentException for a profile that has no schema
     */
    public static function schema(string $profile): string
    {
        return self::root()."/schemas/{$profile}/FACTUR-X_".str_replace('-', '', self::checked($profile)).'.xsd';
    }

    private static function checked(string $profile): string
    {
        if (! in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException("Unknown Factur-X profile \"{$profile}\"; expected one of ".implode(', ', self::PROFILES).'.');
        }

        return $profile;
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }
}

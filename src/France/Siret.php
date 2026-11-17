<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\Model\Identifier;
use InvalidArgumentException;

/**
 * SIRET, the fourteen digit French establishment number (ISO 6523 scheme 0009).
 *
 * Only the format is checked; no checksum is verified.
 */
final class Siret
{
    public const SCHEME = '0009';

    private function __construct() {}

    /**
     * @throws InvalidArgumentException When the value is not exactly fourteen ASCII digits.
     */
    public static function identifier(string $siret): Identifier
    {
        self::assertValid($siret);

        return new Identifier($siret, self::SCHEME);
    }

    public static function isValid(string $siret): bool
    {
        return preg_match('/\A[0-9]{14}\z/', $siret) === 1;
    }

    /**
     * Returns the SIREN of the legal entity, the first nine digits of the SIRET.
     *
     * @throws InvalidArgumentException When the value is not exactly fourteen ASCII digits.
     */
    public static function siren(string $siret): string
    {
        self::assertValid($siret);

        return substr($siret, 0, 9);
    }

    private static function assertValid(string $siret): void
    {
        if (! self::isValid($siret)) {
            throw new InvalidArgumentException(sprintf('Invalid SIRET "%s": expected exactly 14 digits.', $siret));
        }
    }
}

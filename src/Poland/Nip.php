<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * NIP, the ten digit Polish tax identification number.
 *
 * isWellFormed() applies the TNrNIP pattern of the FA(3) schema. isValid() also verifies the
 * check digit (weights 6, 5, 7, 2, 3, 4, 5, 6, 7, modulo 11); the legal source of the checksum
 * has not been verified offline, so writers and validators rely on the pattern alone.
 */
final class Nip
{
    /** The TNrNIP pattern of ElementarneTypyDanych_v10-0E.xsd. */
    private const PATTERN = '/\A[1-9]((\d[1-9])|([1-9]\d))\d{7}\z/';

    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    private function __construct() {}

    /**
     * Removes spaces, hyphens and a leading "PL" country prefix (in either case).
     */
    public static function normalize(string $value): string
    {
        $value = str_replace([' ', '-'], '', $value);

        return preg_replace('/\APL/i', '', $value) ?? $value;
    }

    public static function isWellFormed(string $nip): bool
    {
        return preg_match(self::PATTERN, $nip) === 1;
    }

    public static function isValid(string $nip): bool
    {
        if (! self::isWellFormed($nip)) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += $weight * (int) $nip[$i];
        }

        // A remainder of 10 cannot be expressed as a check digit, so such a NIP is never issued.
        return $sum % 11 === (int) $nip[9];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

/**
 * The SHA-256 fingerprint (Huella) of a VERI*FACTU billing record.
 *
 * AEAT hash specification v0.1.2 §5: the hashed fields are written as "name=value" pairs joined
 * by "&", without URL encoding, a missing value as "Name=", and the UTF-8 bytes are hashed with
 * SHA-256 and written as upper-case hexadecimal.
 */
final class Fingerprint
{
    private function __construct() {}

    public static function of(BillingRecord $record): string
    {
        return self::ofInput(self::input($record));
    }

    /** The exact string that is hashed for the record. */
    public static function input(BillingRecord $record): string
    {
        return implode('&', array_map(
            static fn (array $field): string => $field[0].'='.$field[1],
            $record->hashFields(),
        ));
    }

    public static function ofInput(string $input): string
    {
        return strtoupper(hash('sha256', $input));
    }
}

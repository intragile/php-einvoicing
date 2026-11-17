<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\Model\Identifier;
use InvalidArgumentException;

/**
 * SIREN, the nine digit French legal entity number (ISO 6523 scheme 0002).
 *
 * Only the format is checked; no checksum is verified.
 */
final class Siren
{
    public const SCHEME = '0002';

    private function __construct() {}

    /**
     * @throws InvalidArgumentException When the value is not exactly nine ASCII digits.
     */
    public static function identifier(string $siren): Identifier
    {
        if (! self::isValid($siren)) {
            throw new InvalidArgumentException(sprintf('Invalid SIREN "%s": expected exactly 9 digits.', $siren));
        }

        return new Identifier($siren, self::SCHEME);
    }

    public static function isValid(string $siren): bool
    {
        return preg_match('/\A[0-9]{9}\z/', $siren) === 1;
    }
}

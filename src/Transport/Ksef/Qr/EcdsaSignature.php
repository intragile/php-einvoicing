<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use RuntimeException;

/**
 * ECDSA signature encodings: OpenSSL produces ASN.1 DER (RFC 3279), KSeF recommends IEEE P1363.
 *
 * @internal
 */
final class EcdsaSignature
{
    private function __construct() {}

    /**
     * Converts an ASN.1 DER ECDSA-Sig-Value (SEQUENCE of two INTEGERs) to IEEE P1363 r‖s, each
     * a big-endian unsigned integer padded to the field size (32 bytes for P-256).
     *
     * @throws RuntimeException when the input is not a well-formed ECDSA-Sig-Value or a value
     *                          exceeds the field size
     */
    public static function derToP1363(string $der, int $fieldBytes): string
    {
        $offset = 0;
        if (self::byte($der, $offset++) !== 0x30) {
            throw new RuntimeException('The ECDSA signature is not a DER SEQUENCE.');
        }
        $length = self::derLength($der, $offset);
        if ($offset + $length !== strlen($der)) {
            throw new RuntimeException('The ECDSA signature has an inconsistent DER length.');
        }

        $result = '';
        for ($i = 0; $i < 2; $i++) {
            if (self::byte($der, $offset++) !== 0x02) {
                throw new RuntimeException('The ECDSA signature does not hold two DER INTEGERs.');
            }
            $intLength = self::derLength($der, $offset);
            if ($intLength === 0 || $offset + $intLength > strlen($der)) {
                throw new RuntimeException('The ECDSA signature is truncated.');
            }
            $integer = ltrim(substr($der, $offset, $intLength), "\x00");
            $offset += $intLength;
            if (strlen($integer) > $fieldBytes) {
                throw new RuntimeException('The ECDSA signature value is longer than the curve field.');
            }
            $result .= str_pad($integer, $fieldBytes, "\x00", STR_PAD_LEFT);
        }

        if ($offset !== strlen($der)) {
            throw new RuntimeException('The ECDSA signature has trailing bytes.');
        }

        return $result;
    }

    private static function derLength(string $der, int &$offset): int
    {
        $first = self::byte($der, $offset++);
        if ($first < 0x80) {
            return $first;
        }

        $length = 0;
        for ($count = $first & 0x7F; $count > 0; $count--) {
            $length = ($length << 8) | self::byte($der, $offset++);
        }

        return $length;
    }

    private static function byte(string $der, int $offset): int
    {
        if ($offset >= strlen($der)) {
            throw new RuntimeException('The ECDSA signature is truncated.');
        }

        return ord($der[$offset]);
    }
}

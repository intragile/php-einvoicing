<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The number KSeF assigns to an accepted invoice: "<issuer>-<YYYYMMDD>-<12 hex>-<CRC-8>".
 *
 * The number is exactly 35 characters long. The issuer segment follows the TNumerKSeF pattern
 * of the FA(3) schema (a NIP, "M" and nine digits, or three letters and seven digits), the
 * hexadecimal parts are upper case, and the last two characters are the CRC-8 (polynomial 0x07,
 * initial value 0x00) of the first 32. The 36-character form with a hyphen inside the technical
 * part, which the schema pattern also admits, is refused.
 */
final readonly class KsefNumber
{
    public const LENGTH = 35;

    private const ISSUER = '/\A([1-9]((\d[1-9])|([1-9]\d))\d{7}|M\d{9}|[A-Z]{3}\d{7})\z/';

    private function __construct(
        public string $value,
        public string $issuerIdentifier,
        public DateTimeImmutable $acceptedOn,
        public string $technicalPart,
    ) {}

    /**
     * @throws InvalidArgumentException naming the first defect found: length, segment layout,
     *                                  issuer, date, hexadecimal case or checksum
     */
    public static function parse(string $value): self
    {
        $fail = static fn (string $defect): InvalidArgumentException => new InvalidArgumentException(
            sprintf('Invalid KSeF number "%s": %s.', $value, $defect),
        );

        if (strlen($value) !== self::LENGTH) {
            throw $fail(sprintf('expected exactly %d characters, got %d', self::LENGTH, strlen($value)));
        }

        if (preg_match('/\A([0-9A-Z]{10})-(\d{8})-([0-9A-Fa-f]{12})-([0-9A-Fa-f]{2})\z/', $value, $m) !== 1) {
            throw $fail('expected the segment layout <issuer>-<YYYYMMDD>-<12 hex digits>-<2 hex digits>');
        }
        [, $issuer, $date, $technical, $crc] = $m;

        if (preg_match(self::ISSUER, $issuer) !== 1) {
            throw $fail(sprintf('issuer identifier "%s" is neither a NIP, "M" and 9 digits, nor 3 letters and 7 digits', $issuer));
        }

        $acceptedOn = self::acceptanceDate($date);
        if ($acceptedOn === null) {
            throw $fail(sprintf('acceptance date "%s" is not a calendar date in 2020 or later', $date));
        }

        if (strtoupper($technical.$crc) !== $technical.$crc) {
            throw $fail('hexadecimal digits must be upper-case');
        }

        $expected = self::checksum(substr($value, 0, 32));
        if ($crc !== $expected) {
            throw $fail(sprintf('checksum "%s" does not match the computed CRC-8 "%s"', $crc, $expected));
        }

        return new self($value, $issuer, $acceptedOn, $technical);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * CRC-8 (polynomial 0x07, initial value 0x00, no reflection, no final XOR) of the first 32
     * characters, as two upper-case hexadecimal digits.
     *
     * @throws InvalidArgumentException when the input is not exactly 32 characters long
     */
    public static function checksum(string $first32): string
    {
        if (strlen($first32) !== 32) {
            throw new InvalidArgumentException(sprintf('The KSeF checksum covers exactly 32 characters, got %d.', strlen($first32)));
        }

        $crc = 0x00;
        foreach (str_split($first32) as $char) {
            $crc ^= ord($char);
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x80) !== 0 ? (($crc << 1) ^ 0x07) & 0xFF : ($crc << 1) & 0xFF;
            }
        }

        return sprintf('%02X', $crc);
    }

    /**
     * The date in the Polish time zone, at midnight; null when it is no calendar date or falls
     * outside the year range of the schema pattern (2020 onwards).
     */
    private static function acceptanceDate(string $yyyymmdd): ?DateTimeImmutable
    {
        $year = (int) substr($yyyymmdd, 0, 4);
        $month = (int) substr($yyyymmdd, 4, 2);
        $day = (int) substr($yyyymmdd, 6, 2);

        if ($year < 2020 || ! checkdate($month, $day, $year)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Ymd', $yyyymmdd, new DateTimeZone('Europe/Warsaw'));

        return $date === false ? null : $date;
    }
}

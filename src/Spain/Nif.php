<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

use InvalidArgumentException;
use Stringable;

/**
 * NIF, the Spanish tax identification number: DNI, NIE, the K/L/M special personal forms and CIF.
 *
 * parse() is strict (no case folding, no stripping); normalize() is the lenient input helper.
 */
final readonly class Nif implements Stringable
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const CIF_LETTERS = 'ABCDEFGHJNPQRSUVW';

    /** Organisation types whose control character is always a digit. */
    private const CIF_DIGIT_ONLY = 'ABEH';

    /** Organisation types whose control character is always a letter. */
    private const CIF_LETTER_ONLY = 'KPQSNW';

    private const CIF_CONTROL_LETTERS = 'JABCDEFGHI';

    private function __construct(public string $value) {}

    /**
     * @throws InvalidArgumentException naming the defect
     */
    public static function parse(string $value): self
    {
        $defect = self::defect($value);
        if ($defect !== null) {
            throw new InvalidArgumentException(sprintf('Invalid NIF "%s": %s.', $value, $defect));
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return self::defect($value) === null;
    }

    /**
     * Drops spaces, hyphens and a leading "ES" country prefix and upper-cases the rest.
     */
    public static function normalize(string $value): string
    {
        $value = strtoupper(str_replace([' ', '-'], '', $value));

        return preg_replace('/\AES/', '', $value) ?? $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function defect(string $value): ?string
    {
        if (preg_match('/\A\d{8}[A-Z]\z/', $value) === 1) {
            return self::dniLetter((int) substr($value, 0, 8)) === $value[8] ? null : 'the check letter does not match';
        }

        if (preg_match('/\A[XYZ]\d{7}[A-Z]\z/', $value) === 1) {
            $number = (int) (strpos('XYZ', $value[0]).substr($value, 1, 7));

            return self::dniLetter($number) === $value[8] ? null : 'the check letter does not match';
        }

        if (preg_match('/\A[KLM]\d{7}[A-Z]\z/', $value) === 1) {
            return self::dniLetter((int) substr($value, 1, 7)) === $value[8] ? null : 'the check letter does not match';
        }

        if (preg_match('/\A['.self::CIF_LETTERS.']\d{7}[0-9A-J]\z/', $value) === 1) {
            return self::cifControlMatches($value) ? null : 'the control character does not match';
        }

        return 'the format is none of DNI, NIE, K/L/M or CIF';
    }

    private static function dniLetter(int $number): string
    {
        return self::DNI_LETTERS[$number % 23];
    }

    private static function cifControlMatches(string $value): bool
    {
        $sum = 0;
        for ($i = 1; $i <= 7; $i++) {
            $digit = (int) $value[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                $digit = intdiv($digit, 10) + $digit % 10;
            }
            $sum += $digit;
        }

        $control = (10 - $sum % 10) % 10;
        $given = $value[8];
        $digitForm = (string) $control;
        $letterForm = self::CIF_CONTROL_LETTERS[$control];

        if (str_contains(self::CIF_DIGIT_ONLY, $value[0])) {
            return $given === $digitForm;
        }
        if (str_contains(self::CIF_LETTER_ONLY, $value[0])) {
            return $given === $letterForm;
        }

        return $given === $digitForm || $given === $letterForm;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use InvalidArgumentException;

/**
 * Writes PDF values in PDF syntax: the inverse of the parser's value model.
 *
 * Output is deterministic: dictionary entries keep their order, names are escaped with #xx for
 * every byte outside the regular printable range, and strings keep their literal or hex form.
 */
final class Serializer
{
    private const string NAME_DELIMITERS = '()<>[]{}/%#';

    /**
     * The value in PDF syntax.
     *
     * @throws InvalidArgumentException for a PHP value that is not a PDF value, or a stream,
     *                                  which only object() can write
     */
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::real($value),
            $value instanceof PdfName => self::name($value->name),
            $value instanceof PdfString => self::string($value),
            $value instanceof PdfRef => $value->number.' '.$value->generation.' R',
            $value instanceof PdfDictionary => self::dictionary($value),
            is_array($value) && array_is_list($value) => '['.implode(' ', array_map(self::value(...), $value)).']',
            default => throw new InvalidArgumentException('Not a serialisable PDF value: '.get_debug_type($value).'.'),
        };
    }

    /**
     * An indirect object, "N G obj ... endobj", followed by a newline. A stream's /Length is set
     * to the length of its data.
     */
    public static function object(int $number, int $generation, mixed $value): string
    {
        if ($value instanceof PdfStream) {
            $dictionary = $value->dictionary->with('Length', strlen($value->raw));
            $body = self::dictionary($dictionary)."\nstream\n".$value->raw."\nendstream";
        } else {
            $body = self::value($value);
        }

        return $number.' '.$generation." obj\n".$body."\nendobj\n";
    }

    public static function name(string $name): string
    {
        $out = '/';
        $length = strlen($name);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($name[$i]);
            $out .= $byte < 0x21 || $byte > 0x7E || str_contains(self::NAME_DELIMITERS, $name[$i])
                ? sprintf('#%02X', $byte)
                : $name[$i];
        }

        return $out;
    }

    private static function string(PdfString $string): string
    {
        if ($string->hex) {
            return '<'.strtoupper(bin2hex($string->bytes)).'>';
        }

        return '('.strtr($string->bytes, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r']).')';
    }

    private static function dictionary(PdfDictionary $dictionary): string
    {
        $out = '<<';
        foreach ($dictionary->entries() as $key => $value) {
            // PHP turns numeric-looking keys such as "1" into ints.
            $out .= ' '.self::name((string) $key).' '.self::value($value);
        }

        return $out.' >>';
    }

    private static function real(float $value): string
    {
        if (! is_finite($value)) {
            throw new InvalidArgumentException('A PDF real must be finite.');
        }
        // PDF has no exponent notation.
        $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}

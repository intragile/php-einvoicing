<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\EInvoiceBe;

use JsonException;
use stdClass;
use UnexpectedValueException;

/**
 * The canonical JSON string e-invoice.be signs a webhook notification over: the output of Python's
 * json.dumps(payload, sort_keys=True).
 *
 * Object keys are sorted by code point at every level; items are separated by ", " and keys from
 * values by ": "; '"' and '\' are escaped with a backslash; line feed, carriage return, tab,
 * backspace and form feed are written as \n, \r, \t, \b and \f; every other character outside
 * printable ASCII (U+0020 to U+007E) is a lowercase \uXXXX escape, as a surrogate pair above
 * U+FFFF; "/" is not escaped. An empty object stays "{}" and an empty list "[]".
 *
 * Numbers with a fraction or an exponent, and integers outside the PHP integer range, are refused:
 * PHP and Python print such numbers differently, so a signature over them cannot be checked and
 * verification fails closed.
 */
final class CanonicalJson
{
    /** Nesting depth accepted when decoding; a notification is a few levels deep. */
    private const DEPTH = 64;

    /** Characters written as themselves: printable ASCII except '"' and '\'. */
    private const ESCAPED = '/[^\x20\x21\x23-\x5B\x5D-\x7E]/u';

    private const SHORT_ESCAPES = [
        '"' => '\"',
        '\\' => '\\\\',
        "\n" => '\n',
        "\r" => '\r',
        "\t" => '\t',
        "\x08" => '\b',
        "\x0C" => '\f',
    ];

    /**
     * The canonical string of a JSON text.
     *
     * @throws UnexpectedValueException when the text is not valid JSON, nests deeper than 64
     *                                  levels, or contains a number with a fraction or an exponent
     *                                  or an integer outside the PHP integer range
     */
    public static function fromRaw(string $rawBody): string
    {
        try {
            // Objects as stdClass, so that an empty object stays distinct from an empty list.
            $value = json_decode($rawBody, false, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The text is not valid JSON: '.$exception->getMessage(), 0, $exception);
        }

        return self::encode($value);
    }

    /**
     * @throws UnexpectedValueException when the value holds a float
     */
    private static function encode(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value) => (string) $value,
            is_string($value) => self::string($value),
            is_array($value) => '['.implode(', ', array_map(self::encode(...), $value)).']',
            $value instanceof stdClass => self::object($value),
            // json_decode() yields a float for a fraction, an exponent or an out-of-range integer.
            default => throw new UnexpectedValueException('The JSON text contains a number with a fraction or an exponent, or an integer outside the PHP integer range, which has no canonical form here.'),
        };
    }

    private static function object(stdClass $object): string
    {
        $members = get_object_vars($object);
        // Byte order of UTF-8 strings is code point order. Numeric keys come back as integers and
        // are compared as strings.
        ksort($members, SORT_STRING);

        $parts = [];
        foreach ($members as $key => $member) {
            $parts[] = self::string((string) $key).': '.self::encode($member);
        }

        return '{'.implode(', ', $parts).'}';
    }

    private static function string(string $text): string
    {
        $escaped = preg_replace_callback(self::ESCAPED, static function (array $match): string {
            $character = $match[0];
            if (isset(self::SHORT_ESCAPES[$character])) {
                return self::SHORT_ESCAPES[$character];
            }
            $codePoint = mb_ord($character, 'UTF-8');
            if ($codePoint > 0xFFFF) {
                $codePoint -= 0x10000;

                return sprintf('\u%04x\u%04x', 0xD800 | ($codePoint >> 10), 0xDC00 | ($codePoint & 0x3FF));
            }

            return sprintf('\u%04x', $codePoint);
        }, $text);

        // json_decode() returns valid UTF-8 only; this guards against a PCRE failure.
        if ($escaped === null) {
            throw new UnexpectedValueException('A JSON string could not be escaped.');
        }

        return '"'.$escaped.'"';
    }
}

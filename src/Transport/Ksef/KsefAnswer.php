<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef;

use DateTimeImmutable;
use Exception;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;

/**
 * Reads typed fields from a decoded successful KSeF answer.
 *
 * A missing or mistyped field is an UnexpectedResponse that names the field and the request but
 * never quotes the answer, which may carry tokens.
 *
 * @internal
 */
final class KsefAnswer
{
    /** An ISO 8601 date-time with seconds and an offset; KSeF sends up to seven fraction digits. */
    private const DATE_TIME = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})\z/';

    private function __construct() {}

    /**
     * A non-empty string at the given path, for example ['accessToken', 'token'].
     *
     * @param  array<mixed>  $data
     * @param  non-empty-list<string>  $path
     *
     * @throws UnexpectedResponse
     */
    public static function string(array $data, array $path, string $operation): string
    {
        $value = self::at($data, $path);
        if (! is_string($value) || $value === '') {
            throw self::missing($path, $operation, 'a non-empty string');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     * @param  non-empty-list<string>  $path
     *
     * @throws UnexpectedResponse
     */
    public static function int(array $data, array $path, string $operation): int
    {
        $value = self::at($data, $path);
        if (! is_int($value)) {
            throw self::missing($path, $operation, 'an integer');
        }

        return $value;
    }

    /**
     * An ISO 8601 date-time with an offset; fraction digits beyond microseconds are dropped.
     *
     * @param  array<mixed>  $data
     * @param  non-empty-list<string>  $path
     *
     * @throws UnexpectedResponse
     */
    public static function dateTime(array $data, array $path, string $operation): DateTimeImmutable
    {
        $value = self::at($data, $path);
        $date = is_string($value) ? self::parseDateTime($value) : null;
        if ($date === null) {
            throw self::missing($path, $operation, 'an ISO 8601 date-time');
        }

        return $date;
    }

    /**
     * The date-time of an ISO 8601 string with seconds and an offset, or null for anything else.
     */
    public static function parseDateTime(string $value): ?DateTimeImmutable
    {
        if (preg_match(self::DATE_TIME, $value) !== 1) {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $data
     * @param  non-empty-list<string>  $path
     */
    private static function at(array $data, array $path): mixed
    {
        $value = $data;
        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param  non-empty-list<string>  $path
     */
    private static function missing(array $path, string $operation, string $expected): UnexpectedResponse
    {
        return new UnexpectedResponse(sprintf('%s was answered without %s in "%s".', $operation, $expected, implode('.', $path)));
    }
}

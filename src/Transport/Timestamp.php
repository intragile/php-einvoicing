<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DateTimeImmutable;

/**
 * Reads the ISO 8601 timestamps of provider API responses.
 *
 * @internal
 */
final class Timestamp
{
    /** ISO 8601 with a fraction of up to six digits, as Python and some other APIs write it. */
    private const MICROSECONDS = 'Y-m-d\TH:i:s.uP';

    /**
     * The time of an ISO 8601 timestamp with a time zone, in the DATE_ATOM form
     * (2026-11-12T08:02:00+00:00, also with "Z") or with milliseconds (2026-11-12T07:45:10.000Z);
     * null for any other value, including a date that does not exist.
     *
     * @param  bool  $microseconds  also read a fraction of up to six digits
     *                              (2026-11-12T07:45:10.123456Z), for providers that write one
     */
    public static function parse(mixed $value, bool $microseconds = false): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $formats = $microseconds ? [DATE_ATOM, DATE_RFC3339_EXTENDED, self::MICROSECONDS] : [DATE_ATOM, DATE_RFC3339_EXTENDED];
        foreach ($formats as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $time;
            }
        }

        return null;
    }
}

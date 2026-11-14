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
    /**
     * The time of an ISO 8601 timestamp with a time zone, in the DATE_ATOM form
     * (2026-11-12T08:02:00+00:00, also with "Z") or with milliseconds (2026-11-12T07:45:10.000Z);
     * null for any other value, including a date that does not exist.
     */
    public static function parse(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        foreach ([DATE_ATOM, DATE_RFC3339_EXTENDED] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $time;
            }
        }

        return null;
    }
}

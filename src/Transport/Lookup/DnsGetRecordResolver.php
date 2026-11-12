<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * Resolves NAPTR records with PHP's dns_get_record() and the system resolver.
 *
 * dns_get_record() has no timeout of its own (the system resolver's applies) and reports a failed
 * lookup as false with a warning; the warning is suppressed and the failure returned as null. On
 * a platform without NAPTR support every lookup is a failure.
 */
final class DnsGetRecordResolver implements NaptrResolver
{
    public function naptr(string $host): ?array
    {
        if (! defined('DNS_NAPTR')) {
            return null;
        }

        set_error_handler(static fn (): bool => true);
        try {
            $rows = dns_get_record($host, (int) constant('DNS_NAPTR'));
        } catch (\ValueError) {
            return null;
        } finally {
            restore_error_handler();
        }

        return $rows === false ? null : self::toRecords($rows);
    }

    /**
     * Maps dns_get_record() rows to records, skipping rows of other types.
     *
     * @internal
     *
     * @param  array<array-key, mixed>  $rows
     * @return list<NaptrRecord>
     */
    public static function toRecords(array $rows): array
    {
        $records = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['type'] ?? null) !== 'NAPTR') {
                continue;
            }
            $records[] = new NaptrRecord(
                self::int($row['order'] ?? null),
                self::int($row['pref'] ?? null),
                self::string($row['flags'] ?? null),
                self::string($row['services'] ?? null),
                self::string($row['regex'] ?? null),
                self::string($row['replacement'] ?? null),
            );
        }

        return $records;
    }

    private static function int(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : PHP_INT_MAX);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}

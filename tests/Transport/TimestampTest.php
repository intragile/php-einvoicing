<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function accepted(): iterable
    {
        yield 'offset' => ['2026-11-12T08:02:00+01:00'];
        yield 'Z' => ['2026-11-12T08:02:00Z'];
        yield 'milliseconds' => ['2026-11-12T07:45:10.250Z'];
    }

    #[DataProvider('accepted')]
    public function test_iso_8601_with_a_time_zone_is_read(string $value): void
    {
        self::assertEquals(new DateTimeImmutable($value), Timestamp::parse($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function refused(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'number' => [1_731_398_400];
        yield 'no time zone' => ['2026-11-12T08:02:00'];
        yield 'date only' => ['2026-11-12'];
        yield 'nonexistent date' => ['2026-02-30T08:02:00Z'];
        yield 'free text' => ['yesterday'];
    }

    #[DataProvider('refused')]
    public function test_other_values_are_null(mixed $value): void
    {
        self::assertNull(Timestamp::parse($value));
    }
}

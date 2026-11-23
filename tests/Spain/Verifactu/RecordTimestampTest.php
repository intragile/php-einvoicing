<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use PHPUnit\Framework\TestCase;

final class RecordTimestampTest extends TestCase
{
    public function test_timestamp_in_madrid(): void
    {
        $zone = new DateTimeZone('Europe/Madrid');

        $winter = RecordTimestamp::of(new DateTimeImmutable('2024-01-01T18:20:30.123456Z'), $zone);
        $this->assertSame('2024-01-01T19:20:30+01:00', $winter->text);

        $summer = RecordTimestamp::of(new DateTimeImmutable('2026-07-01T10:00:00Z'), $zone);
        $this->assertSame('2026-07-01T12:00:00+02:00', $summer->text);
        $this->assertSame('2026-07-01T10:00:00+00:00', $summer->value->setTimezone(new DateTimeZone('UTC'))->format('c'));
    }

    public function test_timestamp_literal_is_kept(): void
    {
        $this->assertSame('2025-02-03T14:30:00+01:00', RecordTimestamp::fromLiteral('2025-02-03T14:30:00+01:00')->text);
        $this->assertSame('2025-02-03T14:30:00.5Z', RecordTimestamp::fromLiteral(' 2025-02-03T14:30:00.5Z ')->text);
        $this->assertSame('2025-02-03T14:30:00.1234567Z', RecordTimestamp::fromLiteral('2025-02-03T14:30:00.1234567Z')->text);
    }

    public function test_timestamp_literal_outside_pattern_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        RecordTimestamp::fromLiteral('2025-02-03 14:30:00');
    }

    public function test_impossible_date_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        RecordTimestamp::fromLiteral('2025-13-45T14:30:00Z');
    }
}

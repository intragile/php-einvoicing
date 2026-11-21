<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KsefLiveWindowTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function warsawTimes(): iterable
    {
        yield '15:54 runs' => ['2026-11-23 15:54:59', false];
        yield '15:55 refused, too close to the window' => ['2026-11-23 15:55:00', true];
        yield '16:00 refused' => ['2026-11-23 16:00:00', true];
        yield '17:59 refused' => ['2026-11-23 17:59:59', true];
        yield '18:00 runs' => ['2026-11-23 18:00:00', false];
        yield 'midnight runs' => ['2026-11-23 00:00:00', false];
    }

    #[DataProvider('warsawTimes')]
    public function test_maintenance_window_boundaries(string $warsaw, bool $refused): void
    {
        $now = new DateTimeImmutable($warsaw, new DateTimeZone('Europe/Warsaw'));

        self::assertSame($refused, KsefLive::maintenanceReason($now) !== null);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function utcTimesAcrossDst(): iterable
    {
        // Winter time (CET, UTC+1) the day before summer time starts: 14:30 UTC is 15:30.
        yield 'before DST start, 14:30 UTC = 15:30 CET' => ['2026-03-28T14:30:00+00:00', false];
        // Summer time (CEST, UTC+2): 14:30 UTC is 16:30.
        yield 'after DST start, 14:30 UTC = 16:30 CEST' => ['2026-03-29T14:30:00+00:00', true];
        // Summer time on the last day of it: 16:30 UTC is 18:30.
        yield 'before DST end, 16:30 UTC = 18:30 CEST' => ['2026-10-24T16:30:00+00:00', false];
        // Winter time again: 16:30 UTC is 17:30.
        yield 'after DST end, 16:30 UTC = 17:30 CET' => ['2026-10-25T16:30:00+00:00', true];
    }

    #[DataProvider('utcTimesAcrossDst')]
    public function test_utc_input_is_judged_in_warsaw_time_across_dst(string $utc, bool $refused): void
    {
        self::assertSame($refused, KsefLive::maintenanceReason(new DateTimeImmutable($utc)) !== null);
    }
}

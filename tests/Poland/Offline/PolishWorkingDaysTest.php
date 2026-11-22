<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland\Offline;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Poland\Offline\PolishWorkingDays;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolishWorkingDaysTest extends TestCase
{
    public function test_holidays_2026(): void
    {
        $dates = array_map(
            static fn (DateTimeImmutable $day): string => $day->format('Y-m-d'),
            PolishWorkingDays::holidays(2026),
        );

        self::assertSame([
            '2026-01-01',
            '2026-01-06',
            '2026-04-05',
            '2026-04-06',
            '2026-05-01',
            '2026-05-03',
            '2026-05-24',
            '2026-06-04',
            '2026-08-15',
            '2026-11-01',
            '2026-11-11',
            '2026-12-24',
            '2026-12-25',
            '2026-12-26',
        ], $dates);

        foreach (PolishWorkingDays::holidays(2026) as $holiday) {
            self::assertSame('Europe/Warsaw', $holiday->getTimezone()->getName());
            self::assertSame('00:00:00', $holiday->format('H:i:s'));
        }
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function easterDates(): iterable
    {
        yield '2025' => [2025, '2025-04-20'];
        yield '2026' => [2026, '2026-04-05'];
        yield '2027' => [2027, '2027-03-28'];
        yield '2028' => [2028, '2028-04-16'];
        yield '2029' => [2029, '2029-04-01'];
        yield '2030' => [2030, '2030-04-21'];
    }

    #[DataProvider('easterDates')]
    public function test_easter_dates_2026_2030(int $year, string $easterSunday): void
    {
        $dates = array_map(
            static fn (DateTimeImmutable $day): string => $day->format('Y-m-d'),
            PolishWorkingDays::holidays($year),
        );

        self::assertContains($easterSunday, $dates);
        self::assertContains(
            (new DateTimeImmutable($easterSunday))->modify('+1 day')->format('Y-m-d'),
            $dates,
        );
    }

    public function test_easter_monday_and_christmas_eve_are_not_working_days(): void
    {
        self::assertFalse(PolishWorkingDays::isWorkingDay(self::day('2026-04-06')));
        self::assertFalse(PolishWorkingDays::isWorkingDay(self::day('2026-12-24')));
        self::assertFalse(PolishWorkingDays::isWorkingDay(self::day('2025-12-24')));
        self::assertTrue(PolishWorkingDays::isWorkingDay(self::day('2026-04-07')));
        self::assertTrue(PolishWorkingDays::isWorkingDay(self::day('2026-12-23')));
    }

    public function test_weekend_is_not_working_day(): void
    {
        self::assertFalse(PolishWorkingDays::isWorkingDay(self::day('2026-11-07')));
        self::assertFalse(PolishWorkingDays::isWorkingDay(self::day('2026-11-08')));
        self::assertTrue(PolishWorkingDays::isWorkingDay(self::day('2026-11-06')));
        self::assertTrue(PolishWorkingDays::isWorkingDay(self::day('2026-11-09')));
    }

    public function test_day_is_taken_in_warsaw(): void
    {
        // Friday 23:30 UTC is already Saturday in Warsaw.
        $instant = new DateTimeImmutable('2026-11-06 23:30:00', new DateTimeZone('UTC'));

        self::assertFalse(PolishWorkingDays::isWorkingDay($instant));
    }

    public function test_add_working_days_counts_strictly_after_the_day(): void
    {
        self::assertSame('2026-11-09', PolishWorkingDays::addWorkingDays(self::day('2026-11-06'), 1)->format('Y-m-d'));
        self::assertSame('2026-11-09', PolishWorkingDays::addWorkingDays(self::day('2026-11-07'), 1)->format('Y-m-d'));
        self::assertSame('2026-12-30', PolishWorkingDays::addWorkingDays(self::day('2026-12-17'), 7)->format('Y-m-d'));
        self::assertSame('2027-01-13', PolishWorkingDays::addWorkingDays(self::day('2026-12-31'), 7)->format('Y-m-d'));
    }

    public function test_add_working_days_rejects_non_positive_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PolishWorkingDays::addWorkingDays(self::day('2026-11-06'), 0);
    }

    public function test_year_out_of_range_throws(): void
    {
        foreach ([
            static fn (): mixed => PolishWorkingDays::holidays(2024),
            static fn (): mixed => PolishWorkingDays::holidays(2100),
            static fn (): mixed => PolishWorkingDays::isWorkingDay(self::day('2024-12-31')),
            static fn (): mixed => PolishWorkingDays::isWorkingDay(self::day('2100-01-04')),
            static fn (): mixed => PolishWorkingDays::addWorkingDays(self::day('2099-12-31'), 1),
        ] as $index => $call) {
            try {
                $call();
                self::fail(sprintf('Call #%d did not throw.', $index));
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('2025-2099', $e->getMessage());
            }
        }

        self::assertCount(14, PolishWorkingDays::holidays(2025));
        self::assertCount(14, PolishWorkingDays::holidays(2099));
    }

    private static function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Europe/Warsaw'));
    }
}

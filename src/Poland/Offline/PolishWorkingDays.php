<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Polish working days: Monday to Friday, except the statutory public holidays.
 *
 * The holidays follow art. 1 pkt 1 of the Act of 18 January 1951 on public holidays (ustawa
 * o dniach wolnych od pracy): 1 January, 6 January, Easter Sunday and Monday, 1 May, 3 May,
 * Pentecost Sunday, Corpus Christi, 15 August, 1 November, 11 November, 24, 25 and 26 December.
 * 24 December (Christmas Eve) was added by the Act of 6 December 2024 amending the Act on public
 * holidays and certain other acts (Dz.U. 2024 poz. 1965), in force since 1 February 2025, so it
 * is a holiday in every supported year.
 *
 * Easter Sunday is computed with the Gregorian computus (the anonymous Gregorian algorithm).
 * Days are taken in Europe/Warsaw: an instant is first converted to Warsaw time and its calendar
 * date is used. Supported years are 2025 to 2099; any other year throws, because a later change
 * of the holiday law cannot be known here.
 */
final class PolishWorkingDays
{
    public const FIRST_YEAR = 2025;

    public const LAST_YEAR = 2099;

    private const TIMEZONE = 'Europe/Warsaw';

    /** @var array<int, array<string, true>> */
    private static array $holidaySets = [];

    /**
     * @throws InvalidArgumentException when the day's year (in Warsaw) is outside 2025-2099
     */
    public static function isWorkingDay(DateTimeImmutable $day): bool
    {
        $day = self::startOfDay($day);
        $year = (int) $day->format('Y');

        if ((int) $day->format('N') >= 6) {
            self::assertSupportedYear($year);

            return false;
        }

        return ! isset(self::holidaySet($year)[$day->format('Y-m-d')]);
    }

    /**
     * The statutory holidays of a year in calendar order, each at midnight Europe/Warsaw.
     *
     * @return list<DateTimeImmutable>
     *
     * @throws InvalidArgumentException when the year is outside 2025-2099
     */
    public static function holidays(int $year): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);

        return array_map(
            static fn (string $date): DateTimeImmutable => new DateTimeImmutable($date.' 00:00:00', $timezone),
            array_keys(self::holidaySet($year)),
        );
    }

    /**
     * The n-th working day strictly after the given day, at midnight Europe/Warsaw.
     *
     * The given day itself is never counted, whether or not it is a working day, so
     * addWorkingDays($day, 1) is the first working day after $day.
     *
     * @throws InvalidArgumentException when $n is less than 1, or a day visited lies outside 2025-2099
     */
    public static function addWorkingDays(DateTimeImmutable $day, int $n): DateTimeImmutable
    {
        if ($n < 1) {
            throw new InvalidArgumentException(sprintf('The number of working days must be at least 1, %d given.', $n));
        }

        $current = self::startOfDay($day);

        while ($n > 0) {
            $current = $current->modify('+1 day');

            if (self::isWorkingDay($current)) {
                $n--;
            }
        }

        return $current;
    }

    private static function startOfDay(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->setTimezone(new DateTimeZone(self::TIMEZONE))->setTime(0, 0);
    }

    /**
     * @return array<string, true> holiday dates (Y-m-d) in calendar order
     */
    private static function holidaySet(int $year): array
    {
        self::assertSupportedYear($year);

        if (isset(self::$holidaySets[$year])) {
            return self::$holidaySets[$year];
        }

        $easter = self::easterSunday($year);
        $dates = [
            sprintf('%d-01-01', $year),
            sprintf('%d-01-06', $year),
            $easter->format('Y-m-d'),
            $easter->modify('+1 day')->format('Y-m-d'),
            sprintf('%d-05-01', $year),
            sprintf('%d-05-03', $year),
            $easter->modify('+49 days')->format('Y-m-d'),
            $easter->modify('+60 days')->format('Y-m-d'),
            sprintf('%d-08-15', $year),
            sprintf('%d-11-01', $year),
            sprintf('%d-11-11', $year),
            sprintf('%d-12-24', $year),
            sprintf('%d-12-25', $year),
            sprintf('%d-12-26', $year),
        ];
        sort($dates);

        return self::$holidaySets[$year] = array_fill_keys($dates, true);
    }

    /**
     * Easter Sunday of a Gregorian year (anonymous Gregorian algorithm, Meeus/Jones/Butcher).
     */
    private static function easterSunday(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $dayOfMonth = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $dayOfMonth),
            new DateTimeZone(self::TIMEZONE),
        );
    }

    /**
     * @throws InvalidArgumentException when the year is outside 2025-2099
     */
    public static function assertSupportedYear(int $year): void
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'Polish working days are supported for the years %d-%d only, %d given.',
                self::FIRST_YEAR,
                self::LAST_YEAR,
                $year,
            ));
        }
    }
}

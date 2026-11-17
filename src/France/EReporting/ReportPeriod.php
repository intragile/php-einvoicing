<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Period a Flux 10 transmission covers (TG-7, TG-33), written as dates.
 *
 * The e-reporting rhythm depends on the declarant's VAT regime: a decade (ten-day period, the
 * third one running to the end of the month), a month, or two months starting on an odd month.
 */
final readonly class ReportPeriod
{
    /**
     * @throws InvalidArgumentException when the end is not after the start
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException(sprintf(
                'The report period end (%s) must be after its start (%s).',
                $end->format('Y-m-d'),
                $start->format('Y-m-d'),
            ));
        }
    }

    /**
     * One of the three decades of a month: 1 is days 1 to 10, 2 is days 11 to 20, 3 is day 21 to
     * the end of the month.
     *
     * @throws InvalidArgumentException when the month or the decade is out of range
     */
    public static function decade(int $year, int $month, int $decade): self
    {
        if ($decade < 1 || $decade > 3) {
            throw new InvalidArgumentException(sprintf('The decade must be 1, 2 or 3, %d given.', $decade));
        }
        $first = self::firstDay($year, $month);
        $startDay = ($decade - 1) * 10 + 1;
        $endDay = $decade === 3 ? (int) $first->format('t') : $decade * 10;

        return new self($first->setDate($year, $month, $startDay), $first->setDate($year, $month, $endDay));
    }

    /**
     * @throws InvalidArgumentException when the month is out of range
     */
    public static function month(int $year, int $month): self
    {
        $first = self::firstDay($year, $month);

        return new self($first, $first->modify('last day of this month'));
    }

    /**
     * Two months starting on January, March, May, July, September or November.
     *
     * @throws InvalidArgumentException when the first month is out of range or even
     */
    public static function bimester(int $year, int $firstMonth): self
    {
        $first = self::firstDay($year, $firstMonth);
        if ($firstMonth % 2 === 0) {
            throw new InvalidArgumentException(sprintf('A two-month period starts on an odd month, %d given.', $firstMonth));
        }

        return new self($first, $first->modify('last day of next month'));
    }

    private static function firstDay(int $year, int $month): DateTimeImmutable
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf('The month must be between 1 and 12, %d given.', $month));
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    }
}

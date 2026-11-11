<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

/**
 * An xs:date value with an optional timezone offset in minutes east of UTC.
 */
final readonly class DateValue implements AtomicValue
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
        public ?int $timezone,
    ) {}

    public function type(): string
    {
        return 'xs:date';
    }

    public function lexical(): string
    {
        $year = sprintf('%04d', abs($this->year));

        return sprintf('%s%s-%02d-%02d', $this->year < 0 ? '-' : '', $year, $this->month, $this->day).$this->timezoneLexical();
    }

    /**
     * Days since 1970-01-01 in the proleptic Gregorian calendar (year 0 exists, as in XSD 1.1).
     */
    public function epochDay(): int
    {
        $year = $this->month <= 2 ? $this->year - 1 : $this->year;
        $era = intdiv($year >= 0 ? $year : $year - 399, 400);
        $yearOfEra = $year - $era * 400;
        $dayOfYear = intdiv(153 * ($this->month + ($this->month > 2 ? -3 : 9)) + 2, 5) + $this->day - 1;
        $dayOfEra = $yearOfEra * 365 + intdiv($yearOfEra, 4) - intdiv($yearOfEra, 100) + $dayOfYear;

        return $era * 146097 + $dayOfEra - 719468;
    }

    private function timezoneLexical(): string
    {
        if ($this->timezone === null) {
            return '';
        }
        if ($this->timezone === 0) {
            return 'Z';
        }

        $minutes = abs($this->timezone);

        return sprintf('%s%02d:%02d', $this->timezone < 0 ? '-' : '+', intdiv($minutes, 60), $minutes % 60);
    }
}

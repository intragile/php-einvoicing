<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Charges and deductions that change the amount to pay without changing the tax base
 * (Rozliczenie).
 */
final readonly class Settlement
{
    public const MAX_ENTRIES = 100;

    /**
     * @param  list<SettlementEntry>  $charges  Charges (Obciazenia), at most 100.
     * @param  list<SettlementEntry>  $deductions  Deductions (Odliczenia), at most 100.
     *
     * @throws InvalidArgumentException when either list holds more than 100 entries
     */
    public function __construct(
        public array $charges = [],
        public array $deductions = [],
    ) {
        Limit::count($charges, 'Obciazenia', self::MAX_ENTRIES);
        Limit::count($deductions, 'Odliczenia', self::MAX_ENTRIES);
    }

    /** Sum of the charges (SumaObciazen). */
    public function totalCharges(): BigDecimal
    {
        return self::sum($this->charges);
    }

    /** Sum of the deductions (SumaOdliczen). */
    public function totalDeductions(): BigDecimal
    {
        return self::sum($this->deductions);
    }

    /**
     * The invoice total (P_15) plus the charges minus the deductions: positive is the amount to
     * pay (DoZaplaty), negative the amount to settle (DoRozliczenia).
     */
    public function balance(BigDecimal $p15): BigDecimal
    {
        return $p15->plus($this->totalCharges())->minus($this->totalDeductions());
    }

    /**
     * @param  list<SettlementEntry>  $entries
     */
    private static function sum(array $entries): BigDecimal
    {
        return array_reduce(
            $entries,
            static fn (BigDecimal $sum, SettlementEntry $entry): BigDecimal => $sum->plus($entry->amount),
            BigDecimal::zero(),
        );
    }
}

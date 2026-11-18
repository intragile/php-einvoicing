<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * One charge (Obciazenia) or deduction (Odliczenia) of the settlement block.
 */
final readonly class SettlementEntry
{
    /** Amount (Kwota). */
    public BigDecimal $amount;

    /**
     * @param  BigDecimal|numeric-string|int  $amount
     */
    public function __construct(
        BigDecimal|string|int|float $amount,
        /** Reason (Powod). */
        public string $reason,
    ) {
        $this->amount = Decimal::of($amount, 'Kwota');
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Amount collected at one VAT rate, as reported with status 212 (Encaissée) and in the e-reporting
 * of payments. The amount is negative for a refund.
 */
final readonly class CollectedAmount
{
    /** Amount collected, VAT included. */
    public BigDecimal $amount;

    /** VAT rate as a percentage. */
    public BigDecimal $vatRate;

    /**
     * @param  BigDecimal|numeric-string|int  $amount
     * @param  BigDecimal|numeric-string|int  $vatRate
     */
    public function __construct(BigDecimal|string|int|float $amount, BigDecimal|string|int|float $vatRate)
    {
        $this->amount = Decimal::of($amount, 'Collected amount');
        $this->vatRate = Decimal::of($vatRate, 'Collected VAT rate');
    }
}

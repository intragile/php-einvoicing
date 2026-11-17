<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * Aggregated amounts of one VAT rate in a day of transactions (TG-32).
 */
final readonly class VatSubtotal
{
    /** VAT rate (TT-86). */
    public BigDecimal $rate;

    /** Taxable amount (TT-87). */
    public BigDecimal $taxable;

    /** VAT amount (TT-88). */
    public BigDecimal $tax;

    /**
     * @throws InvalidArgumentException when an amount is a float or not a number
     */
    public function __construct(BigDecimal|string|int|float $rate, BigDecimal|string|int|float $taxable, BigDecimal|string|int|float $tax)
    {
        $this->rate = Decimal::of($rate, 'TT-86');
        $this->taxable = Decimal::of($taxable, 'TT-87');
        $this->tax = Decimal::of($tax, 'TT-88');
    }
}

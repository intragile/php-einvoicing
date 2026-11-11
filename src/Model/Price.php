<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Price details (BG-29).
 */
final readonly class Price
{
    /** Item net price (BT-146), per base quantity. */
    public BigDecimal $netPrice;

    /** Item price discount (BT-147): gross price minus net price. */
    public ?BigDecimal $discount;

    /** Item gross price (BT-148). */
    public ?BigDecimal $grossPrice;

    /** Item price base quantity (BT-149); null means 1. */
    public ?BigDecimal $baseQuantity;

    /** Item price base quantity unit of measure code (BT-150). */
    public ?UnitCode $baseQuantityUnit;

    /**
     * @param  BigDecimal|numeric-string|int  $netPrice
     * @param  BigDecimal|numeric-string|int|null  $discount
     * @param  BigDecimal|numeric-string|int|null  $grossPrice
     * @param  BigDecimal|numeric-string|int|null  $baseQuantity
     */
    public function __construct(
        BigDecimal|string|int|float $netPrice,
        BigDecimal|string|int|float|null $discount = null,
        BigDecimal|string|int|float|null $grossPrice = null,
        BigDecimal|string|int|float|null $baseQuantity = null,
        ?UnitCode $baseQuantityUnit = null,
    ) {
        $this->netPrice = Decimal::of($netPrice, 'BT-146');
        $this->discount = Decimal::ofNullable($discount, 'BT-147');
        $this->grossPrice = Decimal::ofNullable($grossPrice, 'BT-148');
        $this->baseQuantity = Decimal::ofNullable($baseQuantity, 'BT-149');
        $this->baseQuantityUnit = $baseQuantityUnit;
    }
}

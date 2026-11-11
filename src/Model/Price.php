<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\UnitCode;

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

    public function __construct(
        BigDecimal|string|int $netPrice,
        BigDecimal|string|int|null $discount = null,
        BigDecimal|string|int|null $grossPrice = null,
        BigDecimal|string|int|null $baseQuantity = null,
        ?UnitCode $baseQuantityUnit = null,
    ) {
        $this->netPrice = BigDecimal::of($netPrice);
        $this->discount = $discount === null ? null : BigDecimal::of($discount);
        $this->grossPrice = $grossPrice === null ? null : BigDecimal::of($grossPrice);
        $this->baseQuantity = $baseQuantity === null ? null : BigDecimal::of($baseQuantity);
        $this->baseQuantityUnit = $baseQuantityUnit;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Line VAT information (BG-30).
 */
final readonly class LineVat
{
    /** Invoiced item VAT category code (BT-151). */
    public VatCategory $category;

    /** Invoiced item VAT rate (BT-152) as a percentage, e.g. 27 for 27 %. */
    public ?BigDecimal $rate;

    /**
     * @param  BigDecimal|numeric-string|int|null  $rate
     */
    public function __construct(VatCategory $category, BigDecimal|string|int|float|null $rate = null)
    {
        $this->category = $category;
        $this->rate = Decimal::ofNullable($rate, 'BT-152');
    }
}

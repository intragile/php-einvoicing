<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * VAT breakdown (BG-23), one per VAT category and rate.
 */
final readonly class VatBreakdown
{
    /** VAT category taxable amount (BT-116). */
    public BigDecimal $taxableAmount;

    /** VAT category tax amount (BT-117). */
    public BigDecimal $taxAmount;

    /** VAT category code (BT-118). */
    public VatCategory $category;

    /** VAT category rate (BT-119) as a percentage. */
    public ?BigDecimal $rate;

    /** VAT exemption reason text (BT-120). */
    public ?string $exemptionReason;

    /** VAT exemption reason code (BT-121). */
    public ?VatExemptionReason $exemptionReasonCode;

    /**
     * @param  BigDecimal|numeric-string|int  $taxableAmount
     * @param  BigDecimal|numeric-string|int  $taxAmount
     * @param  BigDecimal|numeric-string|int|null  $rate
     */
    public function __construct(
        BigDecimal|string|int|float $taxableAmount,
        BigDecimal|string|int|float $taxAmount,
        VatCategory $category,
        BigDecimal|string|int|float|null $rate = null,
        ?string $exemptionReason = null,
        ?VatExemptionReason $exemptionReasonCode = null,
    ) {
        $this->taxableAmount = Decimal::of($taxableAmount, 'BT-116');
        $this->taxAmount = Decimal::of($taxAmount, 'BT-117');
        $this->category = $category;
        $this->rate = Decimal::ofNullable($rate, 'BT-119');
        $this->exemptionReason = $exemptionReason;
        $this->exemptionReasonCode = $exemptionReasonCode;
    }
}

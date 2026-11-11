<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;

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

    public function __construct(
        BigDecimal|string|int $taxableAmount,
        BigDecimal|string|int $taxAmount,
        VatCategory $category,
        BigDecimal|string|int|null $rate = null,
        ?string $exemptionReason = null,
        ?VatExemptionReason $exemptionReasonCode = null,
    ) {
        $this->taxableAmount = BigDecimal::of($taxableAmount);
        $this->taxAmount = BigDecimal::of($taxAmount);
        $this->category = $category;
        $this->rate = $rate === null ? null : BigDecimal::of($rate);
        $this->exemptionReason = $exemptionReason;
        $this->exemptionReasonCode = $exemptionReasonCode;
    }
}

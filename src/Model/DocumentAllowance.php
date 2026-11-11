<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;

/**
 * Document level allowance (BG-20).
 */
final readonly class DocumentAllowance
{
    /** Document level allowance amount (BT-92), without VAT. */
    public BigDecimal $amount;

    /** Document level allowance VAT category code (BT-95). */
    public VatCategory $vatCategory;

    /** Document level allowance base amount (BT-93). */
    public ?BigDecimal $baseAmount;

    /** Document level allowance percentage (BT-94). */
    public ?BigDecimal $percentage;

    /** Document level allowance VAT rate (BT-96). */
    public ?BigDecimal $vatRate;

    /** Document level allowance reason (BT-97). */
    public ?string $reason;

    /** Document level allowance reason code (BT-98). */
    public ?AllowanceReasonCode $reasonCode;

    public function __construct(
        BigDecimal|string|int $amount,
        VatCategory $vatCategory,
        BigDecimal|string|int|null $vatRate = null,
        BigDecimal|string|int|null $baseAmount = null,
        BigDecimal|string|int|null $percentage = null,
        ?string $reason = null,
        ?AllowanceReasonCode $reasonCode = null,
    ) {
        $this->amount = BigDecimal::of($amount);
        $this->vatCategory = $vatCategory;
        $this->vatRate = $vatRate === null ? null : BigDecimal::of($vatRate);
        $this->baseAmount = $baseAmount === null ? null : BigDecimal::of($baseAmount);
        $this->percentage = $percentage === null ? null : BigDecimal::of($percentage);
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

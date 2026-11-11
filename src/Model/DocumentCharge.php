<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;

/**
 * Document level charge (BG-21).
 */
final readonly class DocumentCharge
{
    /** Document level charge amount (BT-99), without VAT. */
    public BigDecimal $amount;

    /** Document level charge VAT category code (BT-102). */
    public VatCategory $vatCategory;

    /** Document level charge base amount (BT-100). */
    public ?BigDecimal $baseAmount;

    /** Document level charge percentage (BT-101). */
    public ?BigDecimal $percentage;

    /** Document level charge VAT rate (BT-103). */
    public ?BigDecimal $vatRate;

    /** Document level charge reason (BT-104). */
    public ?string $reason;

    /** Document level charge reason code (BT-105). */
    public ?ChargeReasonCode $reasonCode;

    public function __construct(
        BigDecimal|string|int $amount,
        VatCategory $vatCategory,
        BigDecimal|string|int|null $vatRate = null,
        BigDecimal|string|int|null $baseAmount = null,
        BigDecimal|string|int|null $percentage = null,
        ?string $reason = null,
        ?ChargeReasonCode $reasonCode = null,
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

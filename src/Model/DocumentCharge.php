<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Support\Decimal;

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

    /**
     * @param  BigDecimal|numeric-string|int  $amount
     * @param  BigDecimal|numeric-string|int|null  $vatRate
     * @param  BigDecimal|numeric-string|int|null  $baseAmount
     * @param  BigDecimal|numeric-string|int|null  $percentage
     */
    public function __construct(
        BigDecimal|string|int|float $amount,
        VatCategory $vatCategory,
        BigDecimal|string|int|float|null $vatRate = null,
        BigDecimal|string|int|float|null $baseAmount = null,
        BigDecimal|string|int|float|null $percentage = null,
        ?string $reason = null,
        ?ChargeReasonCode $reasonCode = null,
    ) {
        $this->amount = Decimal::of($amount, 'BT-99');
        $this->vatCategory = $vatCategory;
        $this->vatRate = Decimal::ofNullable($vatRate, 'BT-103');
        $this->baseAmount = Decimal::ofNullable($baseAmount, 'BT-100');
        $this->percentage = Decimal::ofNullable($percentage, 'BT-101');
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Support\Decimal;

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
        ?AllowanceReasonCode $reasonCode = null,
    ) {
        $this->amount = Decimal::of($amount, 'BT-92');
        $this->vatCategory = $vatCategory;
        $this->vatRate = Decimal::ofNullable($vatRate, 'BT-96');
        $this->baseAmount = Decimal::ofNullable($baseAmount, 'BT-93');
        $this->percentage = Decimal::ofNullable($percentage, 'BT-94');
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

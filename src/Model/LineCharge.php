<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Invoice line charge (BG-28).
 */
final readonly class LineCharge
{
    /** Invoice line charge amount (BT-141), without VAT. */
    public BigDecimal $amount;

    /** Invoice line charge base amount (BT-142). */
    public ?BigDecimal $baseAmount;

    /** Invoice line charge percentage (BT-143). */
    public ?BigDecimal $percentage;

    /** Invoice line charge reason (BT-144). */
    public ?string $reason;

    /** Invoice line charge reason code (BT-145). */
    public ?ChargeReasonCode $reasonCode;

    /**
     * @param  BigDecimal|numeric-string|int  $amount
     * @param  BigDecimal|numeric-string|int|null  $baseAmount
     * @param  BigDecimal|numeric-string|int|null  $percentage
     */
    public function __construct(
        BigDecimal|string|int|float $amount,
        BigDecimal|string|int|float|null $baseAmount = null,
        BigDecimal|string|int|float|null $percentage = null,
        ?string $reason = null,
        ?ChargeReasonCode $reasonCode = null,
    ) {
        $this->amount = Decimal::of($amount, 'BT-141');
        $this->baseAmount = Decimal::ofNullable($baseAmount, 'BT-142');
        $this->percentage = Decimal::ofNullable($percentage, 'BT-143');
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

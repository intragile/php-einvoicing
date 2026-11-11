<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;

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

    public function __construct(
        BigDecimal|string|int $amount,
        BigDecimal|string|int|null $baseAmount = null,
        BigDecimal|string|int|null $percentage = null,
        ?string $reason = null,
        ?ChargeReasonCode $reasonCode = null,
    ) {
        $this->amount = BigDecimal::of($amount);
        $this->baseAmount = $baseAmount === null ? null : BigDecimal::of($baseAmount);
        $this->percentage = $percentage === null ? null : BigDecimal::of($percentage);
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;

/**
 * Invoice line allowance (BG-27).
 */
final readonly class LineAllowance
{
    /** Invoice line allowance amount (BT-136), without VAT. */
    public BigDecimal $amount;

    /** Invoice line allowance base amount (BT-137). */
    public ?BigDecimal $baseAmount;

    /** Invoice line allowance percentage (BT-138). */
    public ?BigDecimal $percentage;

    /** Invoice line allowance reason (BT-139). */
    public ?string $reason;

    /** Invoice line allowance reason code (BT-140). */
    public ?AllowanceReasonCode $reasonCode;

    public function __construct(
        BigDecimal|string|int $amount,
        BigDecimal|string|int|null $baseAmount = null,
        BigDecimal|string|int|null $percentage = null,
        ?string $reason = null,
        ?AllowanceReasonCode $reasonCode = null,
    ) {
        $this->amount = BigDecimal::of($amount);
        $this->baseAmount = $baseAmount === null ? null : BigDecimal::of($baseAmount);
        $this->percentage = $percentage === null ? null : BigDecimal::of($percentage);
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

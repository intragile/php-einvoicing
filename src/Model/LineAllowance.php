<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;

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
        ?AllowanceReasonCode $reasonCode = null,
    ) {
        $this->amount = Decimal::of($amount, 'BT-136');
        $this->baseAmount = Decimal::ofNullable($baseAmount, 'BT-137');
        $this->percentage = Decimal::ofNullable($percentage, 'BT-138');
        $this->reason = $reason;
        $this->reasonCode = $reasonCode;
    }
}

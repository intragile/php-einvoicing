<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * One partial advance payment an advance invoice covers (ZaliczkaCzesciowa).
 */
final readonly class PartialAdvance
{
    /** Amount received (P_15Z). */
    public BigDecimal $amount;

    /** Exchange rate applied to the payment (KursWalutyZW). */
    public ?BigDecimal $exchangeRate;

    /**
     * @param  BigDecimal|numeric-string|int  $amount
     * @param  BigDecimal|numeric-string|int|null  $exchangeRate
     */
    public function __construct(
        /** Date the payment was received (P_6Z). */
        public DateTimeImmutable $receivedOn,
        BigDecimal|string|int|float $amount,
        BigDecimal|string|int|float|null $exchangeRate = null,
    ) {
        $this->amount = Decimal::of($amount, 'P_15Z');
        $this->exchangeRate = Decimal::ofNullable($exchangeRate, 'KursWalutyZW');
    }
}

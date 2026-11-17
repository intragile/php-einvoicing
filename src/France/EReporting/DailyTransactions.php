<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * Transactions of one day, currency and category reported as totals (TG-31), such as B2C sales.
 */
final readonly class DailyTransactions
{
    /** Total without VAT (TT-82). */
    public BigDecimal $taxExclusive;

    /** Total VAT (TT-83). */
    public BigDecimal $taxTotal;

    /**
     * @param  DateTimeImmutable  $date  day of the transactions (TT-77)
     * @param  CurrencyCode  $currency  currency of the transactions (TT-78)
     * @param  list<VatSubtotal>  $subtotals  amounts per VAT rate (TG-32), at least one
     * @param  int|null  $count  number of transactions (TT-85)
     * @param  string|null  $vatDueDateCode  VAT payment option (TT-80) as a UNTDID 2005 code, as the
     *                                       invoice model holds the VAT point date code (BT-8): 3, 35
     *                                       or 432
     *
     * @throws InvalidArgumentException when there is no subtotal, the count is negative or an
     *                                  amount is a float
     */
    public function __construct(
        public DateTimeImmutable $date,
        public CurrencyCode $currency,
        public TransactionCategory $category,
        BigDecimal|string|int|float $taxExclusive,
        BigDecimal|string|int|float $taxTotal,
        public array $subtotals,
        public ?int $count = null,
        public ?string $vatDueDateCode = null,
    ) {
        if ($subtotals === []) {
            throw new InvalidArgumentException('Daily transactions need at least one VAT subtotal (TG-32).');
        }
        if ($count !== null && $count < 0) {
            throw new InvalidArgumentException(sprintf('The number of transactions (TT-85) cannot be negative, %d given.', $count));
        }
        $this->taxExclusive = Decimal::of($taxExclusive, 'TT-82');
        $this->taxTotal = Decimal::of($taxTotal, 'TT-83');
    }
}

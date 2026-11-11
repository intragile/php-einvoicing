<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Document totals (BG-22).
 *
 * All amounts are in the invoice currency (BT-5) except BT-111, which is in the VAT accounting
 * currency (BT-6).
 */
final readonly class DocumentTotals
{
    /** Sum of invoice line net amount (BT-106). */
    public BigDecimal $sumOfLineNetAmounts;

    /** Invoice total amount without VAT (BT-109). */
    public BigDecimal $totalWithoutVat;

    /** Invoice total amount with VAT (BT-112). */
    public BigDecimal $totalWithVat;

    /** Amount due for payment (BT-115). */
    public BigDecimal $amountDue;

    /** Sum of allowances on document level (BT-107). */
    public ?BigDecimal $sumOfAllowances;

    /** Sum of charges on document level (BT-108). */
    public ?BigDecimal $sumOfCharges;

    /** Invoice total VAT amount (BT-110). */
    public ?BigDecimal $totalVat;

    /** Invoice total VAT amount in accounting currency (BT-111). */
    public ?BigDecimal $totalVatInAccountingCurrency;

    /** Paid amount (BT-113). */
    public ?BigDecimal $paidAmount;

    /** Rounding amount (BT-114). */
    public ?BigDecimal $roundingAmount;

    /**
     * @param  BigDecimal|numeric-string|int  $sumOfLineNetAmounts
     * @param  BigDecimal|numeric-string|int  $totalWithoutVat
     * @param  BigDecimal|numeric-string|int  $totalWithVat
     * @param  BigDecimal|numeric-string|int  $amountDue
     * @param  BigDecimal|numeric-string|int|null  $sumOfAllowances
     * @param  BigDecimal|numeric-string|int|null  $sumOfCharges
     * @param  BigDecimal|numeric-string|int|null  $totalVat
     * @param  BigDecimal|numeric-string|int|null  $totalVatInAccountingCurrency
     * @param  BigDecimal|numeric-string|int|null  $paidAmount
     * @param  BigDecimal|numeric-string|int|null  $roundingAmount
     */
    public function __construct(
        BigDecimal|string|int|float $sumOfLineNetAmounts,
        BigDecimal|string|int|float $totalWithoutVat,
        BigDecimal|string|int|float $totalWithVat,
        BigDecimal|string|int|float $amountDue,
        BigDecimal|string|int|float|null $sumOfAllowances = null,
        BigDecimal|string|int|float|null $sumOfCharges = null,
        BigDecimal|string|int|float|null $totalVat = null,
        BigDecimal|string|int|float|null $totalVatInAccountingCurrency = null,
        BigDecimal|string|int|float|null $paidAmount = null,
        BigDecimal|string|int|float|null $roundingAmount = null,
    ) {
        $this->sumOfLineNetAmounts = Decimal::of($sumOfLineNetAmounts, 'BT-106');
        $this->totalWithoutVat = Decimal::of($totalWithoutVat, 'BT-109');
        $this->totalWithVat = Decimal::of($totalWithVat, 'BT-112');
        $this->amountDue = Decimal::of($amountDue, 'BT-115');
        $this->sumOfAllowances = Decimal::ofNullable($sumOfAllowances, 'BT-107');
        $this->sumOfCharges = Decimal::ofNullable($sumOfCharges, 'BT-108');
        $this->totalVat = Decimal::ofNullable($totalVat, 'BT-110');
        $this->totalVatInAccountingCurrency = Decimal::ofNullable($totalVatInAccountingCurrency, 'BT-111');
        $this->paidAmount = Decimal::ofNullable($paidAmount, 'BT-113');
        $this->roundingAmount = Decimal::ofNullable($roundingAmount, 'BT-114');
    }
}

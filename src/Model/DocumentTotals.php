<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;

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

    public function __construct(
        BigDecimal|string|int $sumOfLineNetAmounts,
        BigDecimal|string|int $totalWithoutVat,
        BigDecimal|string|int $totalWithVat,
        BigDecimal|string|int $amountDue,
        BigDecimal|string|int|null $sumOfAllowances = null,
        BigDecimal|string|int|null $sumOfCharges = null,
        BigDecimal|string|int|null $totalVat = null,
        BigDecimal|string|int|null $totalVatInAccountingCurrency = null,
        BigDecimal|string|int|null $paidAmount = null,
        BigDecimal|string|int|null $roundingAmount = null,
    ) {
        $this->sumOfLineNetAmounts = BigDecimal::of($sumOfLineNetAmounts);
        $this->totalWithoutVat = BigDecimal::of($totalWithoutVat);
        $this->totalWithVat = BigDecimal::of($totalWithVat);
        $this->amountDue = BigDecimal::of($amountDue);
        $this->sumOfAllowances = $sumOfAllowances === null ? null : BigDecimal::of($sumOfAllowances);
        $this->sumOfCharges = $sumOfCharges === null ? null : BigDecimal::of($sumOfCharges);
        $this->totalVat = $totalVat === null ? null : BigDecimal::of($totalVat);
        $this->totalVatInAccountingCurrency = $totalVatInAccountingCurrency === null ? null : BigDecimal::of($totalVatInAccountingCurrency);
        $this->paidAmount = $paidAmount === null ? null : BigDecimal::of($paidAmount);
        $this->roundingAmount = $roundingAmount === null ? null : BigDecimal::of($roundingAmount);
    }
}

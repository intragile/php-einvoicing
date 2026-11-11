<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Calculation;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use InvalidArgumentException;

/**
 * Derives line net amounts, the VAT breakdown and the document totals of an EN 16931 invoice,
 * and checks given totals against the BR-CO calculation rules.
 *
 * All arithmetic is exact; results are rounded half up to two decimals once, at the point where
 * EN 16931 defines the amount (BT-131, BT-116, BT-117 and the BG-22 totals).
 */
final class TotalsCalculator
{
    /**
     * Fills what the caller left empty: BT-131 per line, the BG-23 groups, and BG-22.
     *
     * Values already present are kept as given, even when they disagree with the computation:
     * a line net amount, a VAT breakdown entry with the same category and rate (including its
     * exemption reason BT-120/BT-121), and any total already set. The calculator never invents
     * an exemption reason. BT-111 is filled only when the VAT accounting currency (BT-6) equals
     * the invoice currency (BT-5); otherwise it needs an exchange rate and stays as given.
     */
    public static function complete(Invoice $invoice): Invoice
    {
        $invoice = $invoice->withLines(array_map(
            static fn (InvoiceLine $line): InvoiceLine => $line->netAmount === null
                ? $line->withNetAmount(self::lineNetAmount($line))
                : $line,
            $invoice->lines,
        ));

        $invoice = $invoice->withVatBreakdown(self::mergeBreakdown($invoice->vatBreakdown, self::breakdown($invoice)));

        return $invoice->withTotals(self::mergeTotals($invoice->totals, self::totals($invoice)));
    }

    /**
     * Returns the ids of the BR-CO calculation rules (BR-CO-10 to BR-CO-17) the invoice violates.
     *
     * Each rule is evaluated on the values as given. A line without BT-131 counts with its computed
     * net amount. Without BG-22 only BR-CO-17 is evaluated.
     *
     * @return list<string>
     */
    public static function check(Invoice $invoice): array
    {
        $violations = [];
        $totals = $invoice->totals;

        if ($totals !== null) {
            $zero = BigDecimal::zero();
            $lineSum = $zero;
            foreach ($invoice->lines as $line) {
                $lineSum = $lineSum->plus($line->netAmount ?? self::lineNetAmount($line));
            }
            $allowanceSum = self::allowanceSum($invoice);
            $chargeSum = self::chargeSum($invoice);
            $taxSum = $zero;
            foreach ($invoice->vatBreakdown as $entry) {
                $taxSum = $taxSum->plus($entry->taxAmount);
            }

            // BR-CO-10: BT-106 = sum of BT-131.
            if (! $totals->sumOfLineNetAmounts->isEqualTo(Rounding::amount($lineSum))) {
                $violations[] = 'BR-CO-10';
            }

            // BR-CO-11: BT-107 = sum of BT-92; absent only when there are no document allowances.
            if (! self::matchesSum($totals->sumOfAllowances, $allowanceSum)) {
                $violations[] = 'BR-CO-11';
            }

            // BR-CO-12: BT-108 = sum of BT-99; absent only when there are no document charges.
            if (! self::matchesSum($totals->sumOfCharges, $chargeSum)) {
                $violations[] = 'BR-CO-12';
            }

            // BR-CO-13: BT-109 = BT-106 - BT-107 + BT-108.
            $expectedWithoutVat = $totals->sumOfLineNetAmounts
                ->minus($totals->sumOfAllowances ?? $zero)
                ->plus($totals->sumOfCharges ?? $zero);
            if (! $totals->totalWithoutVat->isEqualTo(Rounding::amount($expectedWithoutVat))) {
                $violations[] = 'BR-CO-13';
            }

            // BR-CO-14: BT-110 = sum of BT-117.
            if ($invoice->vatBreakdown !== [] && ! ($totals->totalVat ?? $zero)->isEqualTo(Rounding::amount($taxSum))) {
                $violations[] = 'BR-CO-14';
            }

            // BR-CO-15: BT-112 = BT-109 + BT-110.
            if (! $totals->totalWithVat->isEqualTo(Rounding::amount($totals->totalWithoutVat->plus($totals->totalVat ?? $zero)))) {
                $violations[] = 'BR-CO-15';
            }

            // BR-CO-16: BT-115 = BT-112 - BT-113 + BT-114.
            $expectedDue = $totals->totalWithVat
                ->minus($totals->paidAmount ?? $zero)
                ->plus($totals->roundingAmount ?? $zero);
            if (! $totals->amountDue->isEqualTo(Rounding::amount($expectedDue))) {
                $violations[] = 'BR-CO-16';
            }
        }

        // BR-CO-17: BT-117 = BT-116 x BT-119 / 100, rounded to two decimals; zero without a rate.
        foreach ($invoice->vatBreakdown as $entry) {
            if (! $entry->taxAmount->isEqualTo(self::taxAmount($entry->taxableAmount, $entry->rate))) {
                $violations[] = 'BR-CO-17';
                break;
            }
        }

        return $violations;
    }

    /**
     * BT-131 = round(BT-129 x BT-146 / BT-149) - sum of BT-136 + sum of BT-141.
     *
     * The base quantity division is exact and rounded only once, so 7 x (10.00 / 3) gives 23.33.
     */
    private static function lineNetAmount(InvoiceLine $line): BigDecimal
    {
        $amount = $line->quantity->multipliedBy($line->price->netPrice);
        $baseQuantity = $line->price->baseQuantity;

        if ($baseQuantity !== null) {
            if ($baseQuantity->isZero()) {
                throw new InvalidArgumentException(sprintf(
                    'Item price base quantity (BT-149) of invoice line %s must not be zero.',
                    $line->id,
                ));
            }
            $amount = $amount->dividedBy($baseQuantity, Rounding::SCALE, Rounding::MODE);
        }

        $net = Rounding::amount($amount);
        foreach ($line->allowances as $allowance) {
            $net = $net->minus($allowance->amount);
        }
        foreach ($line->charges as $charge) {
            $net = $net->plus($charge->amount);
        }

        return Rounding::amount($net);
    }

    /**
     * One BG-23 entry per VAT category and rate, in order of first appearance.
     *
     * BT-116 = sum of BT-131 of the lines in the group - sum of BT-92 + sum of BT-99 of the
     * document allowances and charges in the group (BR-S-08, BR-Z-08, BR-E-08, BR-AE-08,
     * BR-IC-08, BR-O-08 and their counterparts). BT-117 follows BR-CO-17.
     *
     * @return array<string, VatBreakdown>
     */
    private static function breakdown(Invoice $invoice): array
    {
        /** @var array<string, array{VatCategory, ?BigDecimal, BigDecimal}> $groups */
        $groups = [];
        $add = static function (VatCategory $category, ?BigDecimal $rate, BigDecimal $amount) use (&$groups): void {
            $key = self::groupKey($category, $rate);
            $groups[$key] ??= [$category, $rate, BigDecimal::zero()];
            $groups[$key][2] = $groups[$key][2]->plus($amount);
        };

        foreach ($invoice->lines as $line) {
            $add($line->vat->category, $line->vat->rate, $line->netAmount ?? self::lineNetAmount($line));
        }
        foreach ($invoice->allowances as $allowance) {
            $add($allowance->vatCategory, $allowance->vatRate, $allowance->amount->negated());
        }
        foreach ($invoice->charges as $charge) {
            $add($charge->vatCategory, $charge->vatRate, $charge->amount);
        }

        $breakdown = [];
        foreach ($groups as $key => [$category, $rate, $taxable]) {
            $taxable = Rounding::amount($taxable);
            $breakdown[$key] = new VatBreakdown(
                taxableAmount: $taxable,
                taxAmount: self::taxAmount($taxable, $rate),
                category: $category,
                rate: $rate,
            );
        }

        return $breakdown;
    }

    /**
     * Keeps every caller entry; adds computed entries only for groups the caller did not supply.
     *
     * @param  list<VatBreakdown>  $given
     * @param  array<string, VatBreakdown>  $computed
     * @return list<VatBreakdown>
     */
    private static function mergeBreakdown(array $given, array $computed): array
    {
        $remaining = $given;
        $merged = [];

        foreach ($computed as $key => $entry) {
            foreach ($remaining as $index => $candidate) {
                if (self::groupKey($candidate->category, $candidate->rate) === $key) {
                    $entry = $candidate;
                    unset($remaining[$index]);
                    break;
                }
            }
            $merged[] = $entry;
        }

        return [...$merged, ...array_values($remaining)];
    }

    /**
     * BG-22 from the (completed) lines, document allowances and charges, and VAT breakdown.
     *
     * BT-106 = sum of BT-131 (BR-CO-10); BT-107 = sum of BT-92 (BR-CO-11); BT-108 = sum of BT-99
     * (BR-CO-12); BT-109 = BT-106 - BT-107 + BT-108 (BR-CO-13); BT-110 = sum of BT-117 (BR-CO-14);
     * BT-112 = BT-109 + BT-110 (BR-CO-15); BT-115 = BT-112 - BT-113 + BT-114 (BR-CO-16), using
     * BT-113 and BT-114 as already given.
     */
    private static function totals(Invoice $invoice): DocumentTotals
    {
        $zero = BigDecimal::zero();

        $lineSum = $zero;
        foreach ($invoice->lines as $line) {
            $lineSum = $lineSum->plus($line->netAmount ?? self::lineNetAmount($line));
        }
        $lineSum = Rounding::amount($lineSum);

        $allowanceSum = self::allowanceSum($invoice);
        $chargeSum = self::chargeSum($invoice);
        $allowances = $allowanceSum === null ? null : Rounding::amount($allowanceSum);
        $charges = $chargeSum === null ? null : Rounding::amount($chargeSum);

        $withoutVat = Rounding::amount($lineSum->minus($allowances ?? $zero)->plus($charges ?? $zero));

        $vat = $zero;
        foreach ($invoice->vatBreakdown as $entry) {
            $vat = $vat->plus($entry->taxAmount);
        }
        $vat = Rounding::amount($vat);

        $withVat = Rounding::amount($withoutVat->plus($vat));
        $paid = $invoice->totals?->paidAmount;
        $rounding = $invoice->totals?->roundingAmount;
        $due = Rounding::amount($withVat->minus($paid ?? $zero)->plus($rounding ?? $zero));

        $vatInAccountingCurrency = $invoice->taxCurrency !== null && $invoice->taxCurrency === $invoice->currency
            ? $vat
            : null;

        return new DocumentTotals(
            sumOfLineNetAmounts: $lineSum,
            totalWithoutVat: $withoutVat,
            totalWithVat: $withVat,
            amountDue: $due,
            sumOfAllowances: $allowances,
            sumOfCharges: $charges,
            totalVat: $vat,
            totalVatInAccountingCurrency: $vatInAccountingCurrency,
            paidAmount: $paid,
            roundingAmount: $rounding,
        );
    }

    private static function mergeTotals(?DocumentTotals $given, DocumentTotals $computed): DocumentTotals
    {
        if ($given === null) {
            return $computed;
        }

        return new DocumentTotals(
            sumOfLineNetAmounts: $given->sumOfLineNetAmounts,
            totalWithoutVat: $given->totalWithoutVat,
            totalWithVat: $given->totalWithVat,
            amountDue: $given->amountDue,
            sumOfAllowances: $given->sumOfAllowances ?? $computed->sumOfAllowances,
            sumOfCharges: $given->sumOfCharges ?? $computed->sumOfCharges,
            totalVat: $given->totalVat ?? $computed->totalVat,
            totalVatInAccountingCurrency: $given->totalVatInAccountingCurrency ?? $computed->totalVatInAccountingCurrency,
            paidAmount: $given->paidAmount,
            roundingAmount: $given->roundingAmount,
        );
    }

    /**
     * BR-CO-17 with half-up rounding; a category without a rate (O) carries no VAT.
     */
    private static function taxAmount(BigDecimal $taxable, ?BigDecimal $rate): BigDecimal
    {
        if ($rate === null) {
            return BigDecimal::zero()->toScale(Rounding::SCALE);
        }

        return $taxable->multipliedBy($rate)->dividedBy(100, Rounding::SCALE, Rounding::MODE);
    }

    private static function allowanceSum(Invoice $invoice): ?BigDecimal
    {
        if ($invoice->allowances === []) {
            return null;
        }

        $sum = BigDecimal::zero();
        foreach ($invoice->allowances as $allowance) {
            $sum = $sum->plus($allowance->amount);
        }

        return $sum;
    }

    private static function chargeSum(Invoice $invoice): ?BigDecimal
    {
        if ($invoice->charges === []) {
            return null;
        }

        $sum = BigDecimal::zero();
        foreach ($invoice->charges as $charge) {
            $sum = $sum->plus($charge->amount);
        }

        return $sum;
    }

    /**
     * A given BT-107/BT-108 must equal the rounded sum; when nothing is summed it may be absent or zero.
     */
    private static function matchesSum(?BigDecimal $given, ?BigDecimal $sum): bool
    {
        if ($sum === null) {
            return $given === null || $given->isZero();
        }

        return $given !== null && $given->isEqualTo(Rounding::amount($sum));
    }

    private static function groupKey(VatCategory $category, ?BigDecimal $rate): string
    {
        return $category->value.'|'.($rate === null ? '' : (string) $rate->stripTrailingZeros());
    }
}

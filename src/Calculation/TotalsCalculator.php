<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Support\Decimal;
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
     * Fills BT-131 per line and the BG-23 groups the caller left empty, and computes BG-22.
     *
     * Line net amounts and VAT breakdown entries already present are kept as given, even when
     * they disagree with the computation; a breakdown entry is matched by category and rate and
     * keeps its exemption reason (BT-120/BT-121).
     *
     * The derived totals BT-106 to BT-110, BT-112 and BT-115 are always recomputed from the lines,
     * document allowances and charges, and the VAT breakdown, so they agree with BR-CO-10 to
     * BR-CO-16. Only the inputs BT-111, BT-113 and BT-114 are taken over from totals already set.
     * To keep hand-made totals unchanged, do not call complete(); the writers use BG-22 as given.
     *
     * The paid amount (BT-113) and the rounding amount (BT-114) can be passed directly; a value
     * passed here replaces the one in the invoice's totals. BT-115 = BT-112 - BT-113 + BT-114.
     *
     * Two values are the caller's responsibility and are never computed:
     * - VAT exemption reasons (BT-120/BT-121), which E, AE, K, O and other exempt categories
     *   require (BR-E-10, BR-AE-10, BR-IC-10, BR-O-10, ...): supply them as VatBreakdown entries
     *   for the category and rate; such entries are kept and computed entries carry no reason.
     * - The VAT total in accounting currency (BT-111), which needs an exchange rate. Leave it null
     *   when BT-6 is absent or equals BT-5: BT-110 then satisfies BR-53, and a second VAT total in
     *   the invoice currency would break BR-CO-15.
     *
     * @param  BigDecimal|numeric-string|int|null  $paidAmount  Paid amount (BT-113).
     * @param  BigDecimal|numeric-string|int|null  $roundingAmount  Rounding amount (BT-114).
     */
    public static function complete(
        Invoice $invoice,
        BigDecimal|string|int|float|null $paidAmount = null,
        BigDecimal|string|int|float|null $roundingAmount = null,
    ): Invoice {
        $paid = Decimal::ofNullable($paidAmount, 'BT-113') ?? $invoice->totals?->paidAmount;
        $rounding = Decimal::ofNullable($roundingAmount, 'BT-114') ?? $invoice->totals?->roundingAmount;

        $invoice = $invoice->withLines(array_map(
            static fn (InvoiceLine $line): InvoiceLine => $line->netAmount === null
                ? $line->withNetAmount(self::lineNetAmount($line))
                : $line,
            $invoice->lines,
        ));

        $invoice = $invoice->withVatBreakdown(self::mergeBreakdown($invoice->vatBreakdown, self::breakdown($invoice)));

        return $invoice->withTotals(self::totals($invoice, $paid, $rounding));
    }

    /**
     * Returns the ids of the BR-CO calculation rules (BR-CO-10 to BR-CO-17) the invoice violates.
     *
     * Each rule mirrors the executable assertion of the CEN validation artefacts 1.3.17 for UBL
     * (EN16931-UBL-validation.xslt), so check() and the official validator give the same verdict.
     * That includes the artefact's tolerances, which are looser than the rule texts. XPath round()
     * rounds half towards positive infinity, so the artefact's round(x * 100) div 100 is mirrored
     * with RoundingMode::HALF_CEILING. A line without BT-131 counts with its computed net amount.
     * Without BG-22 only BR-CO-17 is evaluated.
     *
     * @return list<string>
     */
    public static function check(Invoice $invoice): array
    {
        $violations = [];
        $totals = $invoice->totals;

        if ($totals !== null) {
            $lineSum = BigDecimal::zero();
            foreach ($invoice->lines as $line) {
                $lineSum = $lineSum->plus($line->netAmount ?? self::lineNetAmount($line));
            }
            $taxSum = BigDecimal::zero();
            foreach ($invoice->vatBreakdown as $entry) {
                $taxSum = $taxSum->plus($entry->taxAmount);
            }

            $rules = [
                'BR-CO-10' => self::brCo10($totals, $lineSum),
                'BR-CO-11' => self::brCo11And12($totals->sumOfAllowances, self::allowanceSum($invoice)),
                'BR-CO-12' => self::brCo11And12($totals->sumOfCharges, self::chargeSum($invoice)),
                'BR-CO-13' => self::brCo13($totals),
                'BR-CO-14' => self::brCo14($totals, $invoice->vatBreakdown !== [], $taxSum),
                'BR-CO-15' => self::brCo15($invoice, $totals),
                'BR-CO-16' => self::brCo16($totals),
            ];
            foreach ($rules as $rule => $satisfied) {
                if (! $satisfied) {
                    $violations[] = $rule;
                }
            }
        }

        foreach ($invoice->vatBreakdown as $entry) {
            if (! self::brCo17($entry)) {
                $violations[] = 'BR-CO-17';
                break;
            }
        }

        return $violations;
    }

    /**
     * BR-CO-10, UBL artefact: BT-106 = round(sum(BT-131) * 100) div 100.
     */
    private static function brCo10(DocumentTotals $totals, BigDecimal $lineSum): bool
    {
        return $totals->sumOfLineNetAmounts->isEqualTo(self::xpathRound2($lineSum));
    }

    /**
     * BR-CO-11 / BR-CO-12, UBL artefact: BT-107 (BT-108) = round(sum(BT-92 (BT-99)) * 100) div 100,
     * or neither the total nor any document allowance (charge) is present. A total of zero without
     * allowances (charges) passes, since the sum of nothing is zero.
     */
    private static function brCo11And12(?BigDecimal $given, ?BigDecimal $sum): bool
    {
        if ($given === null) {
            return $sum === null;
        }

        return $given->isEqualTo(self::xpathRound2($sum ?? BigDecimal::zero()));
    }

    /**
     * BR-CO-13, UBL artefact: BT-109 = round((BT-106 + BT-108 - BT-107) * 100) div 100, with an absent
     * BT-107 or BT-108 left out; with both absent BT-109 = BT-106 exactly.
     */
    private static function brCo13(DocumentTotals $totals): bool
    {
        $allowances = $totals->sumOfAllowances;
        $charges = $totals->sumOfCharges;

        if ($allowances === null && $charges === null) {
            return $totals->totalWithoutVat->isEqualTo($totals->sumOfLineNetAmounts);
        }

        $expected = $totals->sumOfLineNetAmounts
            ->minus($allowances ?? BigDecimal::zero())
            ->plus($charges ?? BigDecimal::zero());

        return $totals->totalWithoutVat->isEqualTo(self::xpathRound2($expected));
    }

    /**
     * BR-CO-14, UBL artefact: BT-110 = round(sum(BT-117) * 100) div 100, or there is no BG-23.
     * An absent BT-110 next to a VAT breakdown fails, as xs:decimal() of nothing equals nothing.
     */
    private static function brCo14(DocumentTotals $totals, bool $hasBreakdown, BigDecimal $taxSum): bool
    {
        if (! $hasBreakdown) {
            return true;
        }

        return $totals->totalVat !== null && $totals->totalVat->isEqualTo(self::xpathRound2($taxSum));
    }

    /**
     * BR-CO-15, UBL artefact: exactly one BT-110 in the invoice currency, and
     * BT-112 = round((BT-109 + BT-110) * 100) div 100.
     *
     * BT-111 is written as a second VAT total in the BT-6 currency, so a BT-111 without BT-6, or
     * with BT-6 equal to BT-5, makes two totals in the invoice currency and fails the count.
     *
     * The CII artefact additionally accepts BT-112 = BT-109 whatever BT-110 is; this mirrors UBL.
     */
    private static function brCo15(Invoice $invoice, DocumentTotals $totals): bool
    {
        if ($totals->totalVat === null) {
            return false;
        }

        if ($totals->totalVatInAccountingCurrency !== null
            && ($invoice->taxCurrency === null || $invoice->taxCurrency === $invoice->currency)) {
            return false;
        }

        return $totals->totalWithVat->isEqualTo(self::xpathRound2($totals->totalWithoutVat->plus($totals->totalVat)));
    }

    /**
     * BR-CO-16, UBL artefact, by presence of BT-113 (paid) and BT-114 (rounding):
     * neither: BT-115 = BT-112 exactly; paid only: BT-115 = round(BT-112 - BT-113);
     * rounding only: round(BT-115 - BT-114) = BT-112; both: round(BT-115 - BT-114) = round(BT-112 - BT-113),
     * each round() to two decimals.
     */
    private static function brCo16(DocumentTotals $totals): bool
    {
        $paid = $totals->paidAmount;
        $rounding = $totals->roundingAmount;
        $due = $totals->amountDue;
        $withVat = $totals->totalWithVat;

        $left = $rounding === null ? $due : self::xpathRound2($due->minus($rounding));
        if ($paid === null) {
            return $left->isEqualTo($withVat);
        }

        return $left->isEqualTo(self::xpathRound2($withVat->minus($paid)));
    }

    /**
     * BR-CO-17, UBL artefact, which is looser than the rule text:
     * - rate absent, or round(BT-119) = 0: round(BT-117) = 0, i.e. -0.50 <= BT-117 < 0.50;
     * - otherwise: | |BT-117| - round(|BT-116| x BT-119 / 100 * 100) div 100 | < 1, with signs ignored.
     *
     * The CII artefact accepts a difference of exactly 1 (<= instead of <); this mirrors UBL.
     */
    private static function brCo17(VatBreakdown $entry): bool
    {
        $rate = $entry->rate;
        if ($rate === null || $rate->toScale(0, RoundingMode::HALF_CEILING)->isZero()) {
            return $entry->taxAmount->toScale(0, RoundingMode::HALF_CEILING)->isZero();
        }

        $product = $entry->taxableAmount->abs()->multipliedBy($rate);
        $expected = self::xpathRound2($product->dividedBy(100, $product->getScale() + 2));
        $tax = $entry->taxAmount->abs();

        return $tax->minus(1)->isLessThan($expected) && $tax->plus(1)->isGreaterThan($expected);
    }

    /**
     * XPath round($x * 10 * 10) div 100: two decimals, half towards positive infinity.
     */
    private static function xpathRound2(BigDecimal $value): BigDecimal
    {
        return $value->toScale(Rounding::SCALE, RoundingMode::HALF_CEILING);
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
     * BT-112 = BT-109 + BT-110 (BR-CO-15); BT-115 = BT-112 - BT-113 + BT-114 (BR-CO-16).
     * BT-111 is taken over from the given totals.
     */
    private static function totals(Invoice $invoice, ?BigDecimal $paid, ?BigDecimal $rounding): DocumentTotals
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
        $due = Rounding::amount($withVat->minus($paid ?? $zero)->plus($rounding ?? $zero));

        return new DocumentTotals(
            sumOfLineNetAmounts: $lineSum,
            totalWithoutVat: $withoutVat,
            totalWithVat: $withVat,
            amountDue: $due,
            sumOfAllowances: $allowances,
            sumOfCharges: $charges,
            totalVat: $vat,
            totalVatInAccountingCurrency: $invoice->totals?->totalVatInAccountingCurrency,
            paidAmount: $paid,
            roundingAmount: $rounding,
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

    private static function groupKey(VatCategory $category, ?BigDecimal $rate): string
    {
        return $category->value.'|'.($rate === null ? '' : (string) $rate->strippedOfTrailingZeros());
    }
}

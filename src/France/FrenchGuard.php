<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Invoice;
use InvalidArgumentException;

/**
 * Checks a French invoice before it is written.
 *
 * Decimal places: amounts take at most 2 decimals, quantities 4, prices 6 and VAT rates 2.
 * Decimals are counted as the model holds them, so trailing zeros count.
 *
 * EXTENDED-CTC-FR lines: French line data (FrenchLine) needs the EXTENDED-CTC-FR profile, also on
 * an invoice without the FrenchInvoice extension, because no other profile carries it. A line
 * order, when given, lists every line identifier once, and each parent line identifier
 * (EXT-FR-FE-162) names another line of the invoice.
 *
 * The other checks apply only to invoices with the FrenchInvoice extension.
 */
final class FrenchGuard
{
    private const AMOUNT = 2;

    private const QUANTITY = 4;

    private const PRICE = 6;

    private const RATE = 2;

    private function __construct() {}

    /**
     * @throws InvalidArgumentException listing every value with too many decimals, or naming the
     *                                  first line structure problem
     */
    public static function check(Invoice $invoice): void
    {
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        self::frenchLinesNeedExtendedProfile($invoice, $french);
        if ($french === null) {
            return;
        }
        self::lineStructure($invoice, $french);
        self::decimals($invoice, $french);
    }

    private static function decimals(Invoice $invoice, FrenchInvoice $french): void
    {
        $violations = [];
        $check = static function (string $term, ?BigDecimal $value, int $max) use (&$violations): void {
            if ($value !== null && $value->getScale() > $max) {
                $violations[] = sprintf('%s %s: at most %d decimals', $term, (string) $value, $max);
            }
        };

        foreach ($invoice->allowances as $allowance) {
            $check('BT-92', $allowance->amount, self::AMOUNT);
            $check('BT-96', $allowance->vatRate, self::RATE);
        }
        foreach ($invoice->charges as $charge) {
            $check('BT-99', $charge->amount, self::AMOUNT);
            $check('BT-103', $charge->vatRate, self::RATE);
        }

        $totals = $invoice->totals;
        if ($totals !== null) {
            $check('BT-106', $totals->sumOfLineNetAmounts, self::AMOUNT);
            $check('BT-107', $totals->sumOfAllowances, self::AMOUNT);
            $check('BT-108', $totals->sumOfCharges, self::AMOUNT);
            $check('BT-109', $totals->totalWithoutVat, self::AMOUNT);
            $check('BT-110', $totals->totalVat, self::AMOUNT);
            $check('BT-111', $totals->totalVatInAccountingCurrency, self::AMOUNT);
            $check('BT-112', $totals->totalWithVat, self::AMOUNT);
            $check('BT-113', $totals->paidAmount, self::AMOUNT);
            $check('BT-114', $totals->roundingAmount, self::AMOUNT);
            $check('BT-115', $totals->amountDue, self::AMOUNT);
        }

        foreach ($invoice->vatBreakdown as $entry) {
            $check('BT-116', $entry->taxableAmount, self::AMOUNT);
            $check('BT-117', $entry->taxAmount, self::AMOUNT);
            $check('BT-119', $entry->rate, self::RATE);
        }

        foreach ($invoice->lines as $line) {
            $check('BT-129', $line->quantity, self::QUANTITY);
            $check('BT-131', $line->netAmount, self::AMOUNT);
            foreach ($line->allowances as $allowance) {
                $check('BT-136', $allowance->amount, self::AMOUNT);
            }
            foreach ($line->charges as $charge) {
                $check('BT-141', $charge->amount, self::AMOUNT);
            }
            $check('BT-146', $line->price->netPrice, self::PRICE);
            $check('BT-147', $line->price->discount, self::PRICE);
            $check('BT-148', $line->price->grossPrice, self::PRICE);
            $check('BT-149', $line->price->baseQuantity, self::QUANTITY);
            $check('BT-152', $line->vat->rate, self::RATE);
            $frenchLine = $line->extensions()->get(FrenchLine::class);
            $check('EXT-FR-FE-181', $frenchLine?->vatAmount, self::AMOUNT);
            $check('EXT-FR-FE-184', $frenchLine?->amountWithVat, self::AMOUNT);
        }

        foreach ($french->informationalLines as $line) {
            $check('BT-129', $line->quantity, self::QUANTITY);
            $check('BT-131', $line->netAmount, self::AMOUNT);
            $check('BT-146', $line->price?->netPrice, self::PRICE);
            $check('BT-147', $line->price?->discount, self::PRICE);
            $check('BT-148', $line->price?->grossPrice, self::PRICE);
            $check('BT-149', $line->price?->baseQuantity, self::QUANTITY);
            $check('BT-152', $line->vat?->rate, self::RATE);
            $check('EXT-FR-FE-181', $line->french->vatAmount, self::AMOUNT);
            $check('EXT-FR-FE-184', $line->french->amountWithVat, self::AMOUNT);
        }

        if ($violations !== []) {
            throw new InvalidArgumentException('The French invoice has values with too many decimals: '.implode('; ', $violations));
        }
    }

    /**
     * @throws InvalidArgumentException when a line has French data and the invoice is not
     *                                  EXTENDED-CTC-FR
     */
    private static function frenchLinesNeedExtendedProfile(Invoice $invoice, ?FrenchInvoice $french): void
    {
        if ($french?->profile === FrenchProfile::ExtendedCtcFr) {
            return;
        }
        foreach ($invoice->lines as $line) {
            if ($line->extensions()->has(FrenchLine::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Invoice line "%s" has EXTENDED-CTC-FR data (FrenchLine), which needs the profile %s.',
                    $line->id,
                    FrenchProfile::ExtendedCtcFr->value,
                ));
            }
        }
    }

    /**
     * @throws InvalidArgumentException when the line order does not list every line once, or a
     *                                  parent line identifier names no other line
     */
    private static function lineStructure(Invoice $invoice, FrenchInvoice $french): void
    {
        /** @var list<array{string, ?string}> $lines identifier and parent line identifier */
        $lines = [];
        foreach ($invoice->lines as $line) {
            $lines[] = [$line->id, $line->extensions()->get(FrenchLine::class)?->parentLineId];
        }
        foreach ($french->informationalLines as $line) {
            $lines[] = [$line->id, $line->french->parentLineId];
        }
        $ids = array_column($lines, 0);

        if ($french->lineOrder === [] && $french->informationalLines !== []) {
            throw new InvalidArgumentException('The French invoice has GROUP or INFORMATION lines but no line order; list every line identifier (BT-126) in document order.');
        }
        if ($french->lineOrder !== []) {
            $expected = $ids;
            $actual = $french->lineOrder;
            sort($expected, SORT_STRING);
            sort($actual, SORT_STRING);
            if ($expected !== $actual) {
                throw new InvalidArgumentException(sprintf(
                    'The French line order must list every line identifier (BT-126) once; the lines are %s, the line order is %s.',
                    implode(', ', $ids),
                    implode(', ', $french->lineOrder),
                ));
            }
        }

        foreach ($lines as [$id, $parent]) {
            if ($parent !== null && ($parent === $id || ! in_array($parent, $ids, true))) {
                throw new InvalidArgumentException(sprintf(
                    'Line "%s" names parent line "%s" (EXT-FR-FE-162), which is not another line of the invoice.',
                    $id,
                    $parent,
                ));
            }
        }
    }
}

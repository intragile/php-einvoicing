<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Invoice;
use InvalidArgumentException;

/**
 * Checks the decimal places the French rules allow before a French invoice is written.
 *
 * Amounts take at most 2 decimals, quantities 4, prices 6 and VAT rates 2. Decimals are counted
 * as the model holds them, so trailing zeros count. Invoices without the FrenchInvoice extension
 * are not checked.
 */
final class FrenchGuard
{
    private const AMOUNT = 2;

    private const QUANTITY = 4;

    private const PRICE = 6;

    private const RATE = 2;

    private function __construct() {}

    /**
     * @throws InvalidArgumentException listing every value with too many decimals
     */
    public static function check(Invoice $invoice): void
    {
        if ($invoice->extensions()->get(FrenchInvoice::class) === null) {
            return;
        }

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
        }

        if ($violations !== []) {
            throw new InvalidArgumentException('The French invoice has values with too many decimals: '.implode('; ', $violations));
        }
    }
}

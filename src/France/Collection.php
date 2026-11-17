<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * Splits a payment over the VAT rates of an invoice.
 *
 * The paid amount is allocated pro rata to the gross amount (taxable amount plus tax amount) of
 * each VAT rate of the VAT breakdown, rounded half up to two decimals; the rounding residual is
 * added to the rate with the largest absolute gross amount, the higher rate on a tie. Breakdowns
 * with the same rate (for example two categories at 0 %) are merged.
 */
final class Collection
{
    private const SCALE = 2;

    private function __construct() {}

    /**
     * @param  BigDecimal|numeric-string|int  $paid  Amount paid, VAT included; negative for a refund. It
     *                                               is rounded half up to two decimals first. Floats are
     *                                               refused.
     * @return non-empty-list<CollectedAmount> One entry per VAT rate, in breakdown order. Rates with
     *                                         a zero gross amount are left out unless every rate has one.
     *
     * @throws InvalidArgumentException When the invoice has no VAT breakdown (BG-23) or the paid amount
     *                                  is a float.
     */
    public static function allocate(Invoice $invoice, BigDecimal|string|int|float $paid): array
    {
        if ($invoice->vatBreakdown === []) {
            throw new InvalidArgumentException('The invoice has no VAT breakdown (BG-23) to allocate the payment over.');
        }

        $paid = Decimal::of($paid, 'Paid amount')->toScale(self::SCALE, RoundingMode::HalfUp);

        /** @var array<string, array{rate: BigDecimal, gross: BigDecimal}> $rates */
        $rates = [];
        foreach ($invoice->vatBreakdown as $breakdown) {
            $rate = $breakdown->rate ?? BigDecimal::zero();
            $key = (string) $rate->strippedOfTrailingZeros();
            $gross = $breakdown->taxableAmount->plus($breakdown->taxAmount);
            $rates[$key] = [
                'rate' => $rates[$key]['rate'] ?? $rate,
                'gross' => isset($rates[$key]) ? $rates[$key]['gross']->plus($gross) : $gross,
            ];
        }

        $nonZero = array_values(array_filter($rates, static fn (array $entry): bool => ! $entry['gross']->isZero()));
        $entries = $nonZero === [] ? array_values($rates) : $nonZero;

        $total = array_reduce(
            $entries,
            static fn (BigDecimal $sum, array $entry): BigDecimal => $sum->plus($entry['gross']),
            BigDecimal::zero(),
        );

        $shares = [];
        $allocated = BigDecimal::zero();
        $largest = 0;
        foreach ($entries as $index => $entry) {
            $share = $total->isZero()
                ? BigDecimal::zero()->toScale(self::SCALE)
                : $paid->multipliedBy($entry['gross'])->dividedBy($total, self::SCALE, RoundingMode::HalfUp);
            $shares[$index] = $share;
            $allocated = $allocated->plus($share);

            $comparison = $entry['gross']->abs()->compareTo($entries[$largest]['gross']->abs());
            if ($comparison > 0 || ($comparison === 0 && $entry['rate']->isGreaterThan($entries[$largest]['rate']))) {
                $largest = $index;
            }
        }

        $shares[$largest] = $shares[$largest]->plus($paid->minus($allocated));

        $amounts = [];
        foreach ($entries as $index => $entry) {
            $amounts[] = new CollectedAmount($shares[$index], $entry['rate']);
        }

        return $amounts;
    }
}

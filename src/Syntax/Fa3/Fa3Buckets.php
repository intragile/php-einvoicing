<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;

/**
 * Maps the VAT breakdown (BG-23) onto the FA(3) summary buckets (P_13_x net, P_14_x VAT).
 *
 * Each breakdown row feeds the bucket of the rate code its lines map to (category and rate,
 * or the Polish rate code override); a row without lines maps by its own category and rate.
 * Rows of one bucket are summed: P_13_x = sum of BT-116, P_14_x = sum of BT-117.
 *
 * @internal
 */
final class Fa3Buckets
{
    /**
     * The net and VAT amount per bucket, in schema order. Callers run Fa3Guard first, which
     * reports every row and line this method would refuse, with its term.
     *
     * @return list<array{VatBucket, BigDecimal, BigDecimal}>
     *
     * @throws InvalidArgumentException when a row cannot be assigned to one bucket
     */
    public static function of(Invoice $invoice): array
    {
        [$buckets, $problems] = self::resolve($invoice);
        if ($problems !== []) {
            throw new InvalidArgumentException(implode('; ', $problems));
        }

        return $buckets;
    }

    /**
     * Why rows cannot be assigned to one bucket, as "BG-23 (<category> <rate>): <reason>".
     * Lines whose own rate code fails are left to the caller to report.
     *
     * @return list<string>
     */
    public static function problems(Invoice $invoice): array
    {
        return self::resolve($invoice)[1];
    }

    /**
     * The buckets the breakdown rows that can be assigned feed, in schema order.
     *
     * @return list<VatBucket>
     */
    public static function written(Invoice $invoice): array
    {
        return array_map(static fn (array $entry): VatBucket => $entry[0], self::resolve($invoice)[0]);
    }

    /**
     * @return array{list<array{VatBucket, BigDecimal, BigDecimal}>, list<string>}
     */
    private static function resolve(Invoice $invoice): array
    {
        $buyerCountry = $invoice->buyer->address?->country;
        /** @var array<string, array{BigDecimal, BigDecimal}> $sums */
        $sums = [];
        $problems = [];

        foreach ($invoice->vatBreakdown as $row) {
            $codes = [];
            $lines = array_filter($invoice->lines, static fn (InvoiceLine $line): bool => self::matches($row, $line));
            foreach ($lines as $line) {
                try {
                    $codes[Fa3LineWriter::rateCode($line, $buyerCountry)->value ?? 'margin'] = Fa3LineWriter::bucket($line, $buyerCountry);
                } catch (InvalidArgumentException) {
                    // Reported per line.
                }
            }
            if ($lines === []) {
                try {
                    $code = VatRateCode::fromModel($row->category, $row->rate, $buyerCountry);
                    $bucket = $code->bucket() ?? throw new InvalidArgumentException(sprintf('rate code "%s" has no P_13 summary bucket in FA(3).', $code->value));
                    $codes[$code->value] = $bucket;
                } catch (InvalidArgumentException $e) {
                    $problems[] = self::label($row).': '.$e->getMessage();
                }
            }
            if (count($codes) > 1) {
                $problems[] = sprintf(
                    '%s: the lines of this VAT breakdown row map to different FA(3) rate codes ("%s"); one row must feed one P_13 bucket.',
                    self::label($row),
                    implode('", "', array_keys($codes)),
                );

                continue;
            }
            if ($codes === []) {
                continue;
            }
            $bucket = reset($codes);
            [$net, $vat] = $sums[$bucket->value] ?? [BigDecimal::zero(), BigDecimal::zero()];
            $sums[$bucket->value] = [$net->plus($row->taxableAmount), $vat->plus($row->taxAmount)];
        }

        $buckets = [];
        foreach (VatBucket::cases() as $bucket) {
            if (isset($sums[$bucket->value])) {
                $buckets[] = [$bucket, ...$sums[$bucket->value]];
            }
        }

        return [$buckets, $problems];
    }

    private static function matches(VatBreakdown $row, InvoiceLine $line): bool
    {
        if ($row->category !== $line->vat->category) {
            return false;
        }
        if ($row->rate === null || $line->vat->rate === null) {
            return $row->rate === $line->vat->rate;
        }

        return $row->rate->isEqualTo($line->vat->rate);
    }

    /**
     * The label of a VAT breakdown row in guard messages, "BG-23 (<category> <rate>)".
     */
    public static function label(VatBreakdown $row): string
    {
        return sprintf('BG-23 (%s %s)', $row->category->value, $row->rate === null ? 'no rate' : $row->rate->__toString());
    }

    private function __construct() {}
}

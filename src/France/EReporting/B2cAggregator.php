<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use InvalidArgumentException;

/**
 * Aggregates sales to consumers (B2C) into daily transactions (TG-31).
 *
 * Invoices are grouped by operation date (the actual delivery date BT-72 when present, else the
 * issue date BT-2), currency and category. Without a classifier the category follows the billing
 * framework (BT-23): goods frameworks (B…) are reported as TLB1, services frameworks (S…) as TPS1;
 * a mixed framework (M…) is refused.
 *
 * With a classifier every line is assigned a category. An invoice whose lines all fall in one
 * category contributes its VAT breakdown (BG-23) unchanged. An invoice with lines in several
 * categories contributes its line net amounts (BT-131) per category and VAT rate, the VAT of each
 * group being taxable amount × rate / 100 rounded half up to two decimals; such an invoice cannot
 * carry document level allowances (BG-20) or charges (BG-21), since they cannot be assigned to a
 * category.
 *
 * Credit notes (381, 261, 396, 502, 503) count negative. Only invoices in euros are accepted, as
 * the report carries no conversion rate. The VAT subtotals of a day are ordered by descending
 * rate; the VAT payment option (TT-80) is the VAT point date code (BT-8) shared by every invoice
 * of the group, or none when they differ.
 */
final class B2cAggregator
{
    private const SCALE = 2;

    /** Invoice types reported with a negative sign. */
    private const CREDIT_NOTES = [
        InvoiceTypeCode::C_381,
        InvoiceTypeCode::C_261,
        InvoiceTypeCode::C_396,
        InvoiceTypeCode::C_502,
        InvoiceTypeCode::C_503,
    ];

    /**
     * @param  (Closure(InvoiceLine, Invoice): TransactionCategory)|null  $classifier  assigns each
     *                                                                                 line a category
     */
    public function __construct(private readonly ?Closure $classifier = null) {}

    /**
     * @param  list<Invoice>  $invoices
     * @return list<DailyTransactions> ordered by date, currency and category
     *
     * @throws InvalidArgumentException when an invoice is not in euros, has no usable billing
     *                                  framework, needs a classifier or cannot be split by category
     */
    public function aggregate(array $invoices): array
    {
        /** @var array<string, array{date: DateTimeImmutable, currency: CurrencyCode, category: TransactionCategory, rates: array<string, array{rate: BigDecimal, taxable: BigDecimal, tax: BigDecimal}>, count: int, codes: non-empty-list<?string>}> $groups */
        $groups = [];

        foreach ($invoices as $invoice) {
            if ($invoice->currency !== CurrencyCode::EUR) {
                throw new InvalidArgumentException(sprintf(
                    'Invoice %s is in %s; consumer sales can only be aggregated in EUR (TT-83 has no conversion rate).',
                    $invoice->number,
                    $invoice->currency->value,
                ));
            }

            $date = ($invoice->delivery->actualDeliveryDate ?? $invoice->issueDate)->format('Y-m-d');
            $negative = in_array($invoice->type, self::CREDIT_NOTES, true);

            foreach ($this->contributions($invoice) as $category => $rates) {
                $category = TransactionCategory::from($category);
                $key = sprintf('%s|%s|%d', $date, $invoice->currency->value, self::categoryOrder($category));
                $groups[$key] ??= [
                    'date' => new DateTimeImmutable($date),
                    'currency' => $invoice->currency,
                    'category' => $category,
                    'rates' => [],
                    'count' => 0,
                    'codes' => [],
                ];
                $groups[$key]['count']++;
                $groups[$key]['codes'][] = $invoice->taxPointDateCode;

                foreach ($rates as $rateKey => $amounts) {
                    $taxable = $negative ? $amounts['taxable']->negated() : $amounts['taxable'];
                    $tax = $negative ? $amounts['tax']->negated() : $amounts['tax'];
                    $existing = $groups[$key]['rates'][$rateKey] ?? null;
                    $groups[$key]['rates'][$rateKey] = [
                        'rate' => $amounts['rate'],
                        'taxable' => $existing === null ? $taxable : $existing['taxable']->plus($taxable),
                        'tax' => $existing === null ? $tax : $existing['tax']->plus($tax),
                    ];
                }
            }
        }

        ksort($groups, SORT_STRING);

        $days = [];
        foreach ($groups as $group) {
            $rates = array_values($group['rates']);
            usort($rates, static fn (array $a, array $b): int => $b['rate']->compareTo($a['rate']));

            $subtotals = [];
            $taxExclusive = BigDecimal::zero()->toScale(self::SCALE);
            $taxTotal = BigDecimal::zero()->toScale(self::SCALE);
            foreach ($rates as $rate) {
                $subtotals[] = new VatSubtotal($rate['rate'], $rate['taxable'], $rate['tax']);
                $taxExclusive = $taxExclusive->plus($rate['taxable']);
                $taxTotal = $taxTotal->plus($rate['tax']);
            }

            $code = $group['codes'][0];
            foreach ($group['codes'] as $other) {
                if ($other !== $code) {
                    $code = null;
                    break;
                }
            }

            $days[] = new DailyTransactions(
                date: $group['date'],
                currency: $group['currency'],
                category: $group['category'],
                taxExclusive: $taxExclusive,
                taxTotal: $taxTotal,
                subtotals: $subtotals,
                count: $group['count'],
                vatDueDateCode: $code,
            );
        }

        return $days;
    }

    /**
     * The taxable and VAT amounts of an invoice per category and rate, unsigned.
     *
     * @return array<string, array<string, array{rate: BigDecimal, taxable: BigDecimal, tax: BigDecimal}>>
     */
    private function contributions(Invoice $invoice): array
    {
        if ($this->classifier === null) {
            return [self::frameworkCategory($invoice)->value => self::breakdownRates($invoice)];
        }

        /** @var array<string, array<string, array{rate: BigDecimal, taxable: BigDecimal}>> $lines */
        $lines = [];
        foreach ($invoice->lines as $line) {
            $category = ($this->classifier)($line, $invoice)->value;
            if ($line->netAmount === null) {
                throw new InvalidArgumentException(sprintf('Line %s of invoice %s has no net amount (BT-131).', $line->id, $invoice->number));
            }
            $rate = $line->vat->rate ?? BigDecimal::zero();
            $rateKey = self::rateKey($rate);
            $existing = $lines[$category][$rateKey] ?? null;
            $lines[$category][$rateKey] = [
                'rate' => $rate->strippedOfTrailingZeros(),
                'taxable' => $existing === null ? $line->netAmount : $existing['taxable']->plus($line->netAmount),
            ];
        }

        if (count($lines) <= 1) {
            $category = array_key_first($lines) ?? self::frameworkCategory($invoice)->value;

            return [$category => self::breakdownRates($invoice)];
        }

        if ($invoice->allowances !== [] || $invoice->charges !== []) {
            throw new InvalidArgumentException(sprintf(
                'Invoice %s has lines in several categories and document level allowances (BG-20) or charges (BG-21), which cannot be assigned to a category.',
                $invoice->number,
            ));
        }

        $contributions = [];
        foreach ($lines as $category => $rates) {
            foreach ($rates as $rateKey => $amounts) {
                $contributions[$category][$rateKey] = [
                    'rate' => $amounts['rate'],
                    'taxable' => $amounts['taxable'],
                    'tax' => $amounts['taxable']->multipliedBy($amounts['rate'])->dividedBy(100, self::SCALE, RoundingMode::HALF_UP),
                ];
            }
        }

        return $contributions;
    }

    /**
     * The VAT breakdown (BG-23) of an invoice per rate.
     *
     * @return array<string, array{rate: BigDecimal, taxable: BigDecimal, tax: BigDecimal}>
     */
    private static function breakdownRates(Invoice $invoice): array
    {
        if ($invoice->vatBreakdown === []) {
            throw new InvalidArgumentException(sprintf('Invoice %s has no VAT breakdown (BG-23).', $invoice->number));
        }

        $rates = [];
        foreach ($invoice->vatBreakdown as $breakdown) {
            $rate = $breakdown->rate ?? BigDecimal::zero();
            $rateKey = self::rateKey($rate);
            $existing = $rates[$rateKey] ?? null;
            $rates[$rateKey] = [
                'rate' => $rate->strippedOfTrailingZeros(),
                'taxable' => $existing === null ? $breakdown->taxableAmount : $existing['taxable']->plus($breakdown->taxableAmount),
                'tax' => $existing === null ? $breakdown->taxAmount : $existing['tax']->plus($breakdown->taxAmount),
            ];
        }

        return $rates;
    }

    private static function frameworkCategory(Invoice $invoice): TransactionCategory
    {
        $value = $invoice->processControl?->businessProcessType;
        $framework = $value === null ? null : BillingFramework::tryFrom($value);
        if ($framework === null) {
            throw new InvalidArgumentException(sprintf(
                'Invoice %s has no French billing framework (BT-23) to derive its category from%s.',
                $invoice->number,
                $value === null ? '' : sprintf(', %s given', $value),
            ));
        }

        return match ($framework->value[0]) {
            'B' => TransactionCategory::Goods,
            'S' => TransactionCategory::Services,
            default => throw new InvalidArgumentException(sprintf(
                'Invoice %s uses the mixed billing framework %s; aggregating it needs a classifier assigning each line a category.',
                $invoice->number,
                $framework->value,
            )),
        };
    }

    private static function rateKey(BigDecimal $rate): string
    {
        return (string) $rate->strippedOfTrailingZeros();
    }

    private static function categoryOrder(TransactionCategory $category): int
    {
        return (int) array_search($category, TransactionCategory::cases(), true);
    }
}

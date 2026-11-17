<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\Collection;
use IntrAgile\EInvoicing\Model\Invoice;
use InvalidArgumentException;

/**
 * Builds the payment blocks of an e-reporting transmission: one invoice's payment split by VAT
 * rate (TG-34), and payments summed per day and VAT rate (TG-37).
 */
final class PaymentAggregator
{
    private function __construct() {}

    /**
     * Sums payments per payment date (the time of day is ignored) and VAT rate.
     *
     * @param  list<InvoicePayment>  $payments
     * @return list<DailyPayments> ordered by date, the amounts of a day by descending rate
     */
    public static function daily(array $payments): array
    {
        /** @var array<string, array<string, array{rate: BigDecimal, amount: BigDecimal}>> $days */
        $days = [];
        foreach ($payments as $payment) {
            $date = $payment->paidOn->format('Y-m-d');
            foreach ($payment->amounts as $collected) {
                $rate = $collected->vatRate->strippedOfTrailingZeros();
                $key = (string) $rate;
                $existing = $days[$date][$key] ?? null;
                $days[$date][$key] = [
                    'rate' => $rate,
                    'amount' => $existing === null ? $collected->amount : $existing['amount']->plus($collected->amount),
                ];
            }
        }

        ksort($days, SORT_STRING);

        $result = [];
        foreach ($days as $date => $rates) {
            $rates = array_values($rates);
            usort($rates, static fn (array $a, array $b): int => $b['rate']->compareTo($a['rate']));
            $result[] = new DailyPayments(
                new DateTimeImmutable((string) $date),
                array_map(static fn (array $entry): CollectedAmount => new CollectedAmount($entry['amount'], $entry['rate']), $rates),
            );
        }

        return $result;
    }

    /**
     * The payment of an invoice, split over its VAT rates by Collection::allocate().
     *
     * @param  BigDecimal|numeric-string  $paid  amount paid, VAT included; negative for a refund
     *
     * @throws InvalidArgumentException when the invoice is not in euros or has no VAT breakdown
     */
    public static function forInvoice(Invoice $invoice, BigDecimal|string $paid, DateTimeImmutable $paidOn): InvoicePayment
    {
        if ($invoice->currency !== CurrencyCode::EUR) {
            throw new InvalidArgumentException(sprintf(
                'Invoice %s is in %s; reported payments are amounts in EUR and the invoice gives no conversion rate.',
                $invoice->number,
                $invoice->currency->value,
            ));
        }

        return new InvoicePayment($invoice->number, $invoice->issueDate, $paidOn, Collection::allocate($invoice, $paid));
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\EReporting;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\EReporting\InvoicePayment;
use IntrAgile\EInvoicing\France\EReporting\PaymentAggregator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentAggregatorTest extends TestCase
{
    public function test_daily_payments_sum_per_rate(): void
    {
        $days = PaymentAggregator::daily([
            self::payment('F-1', '2026-11-20 10:00', [['10.55', '5.5'], ['120.00', '20']]),
            self::payment('F-2', '2026-11-20 18:30', [['60.00', '20.00']]),
            self::payment('F-3', '2026-11-19', [['12.00', '20']]),
            self::payment('F-4', '2026-11-20', [['-5.00', '10']]),
        ]);

        self::assertCount(2, $days);
        self::assertSame('2026-11-19', $days[0]->date->format('Y-m-d'));
        self::assertSame([['12.00', '20']], self::amounts($days[0]->amounts));
        self::assertSame('2026-11-20', $days[1]->date->format('Y-m-d'));
        self::assertSame([['180.00', '20'], ['-5.00', '10'], ['10.55', '5.5']], self::amounts($days[1]->amounts));
    }

    public function test_no_payment_gives_no_day(): void
    {
        self::assertSame([], PaymentAggregator::daily([]));
    }

    public function test_payment_for_invoice_allocates_by_rate(): void
    {
        $invoice = self::twoRateInvoice();
        $paidOn = new DateTimeImmutable('2026-11-25');

        $payment = PaymentAggregator::forInvoice($invoice, '100', $paidOn);

        self::assertSame('F-10', $payment->invoiceNumber);
        self::assertSame('2026-11-05', $payment->invoiceDate->format('Y-m-d'));
        self::assertSame($paidOn, $payment->paidOn);
        // Gross 120.00 at 20 % and 52.75 at 5.5 %: 100 x 120 / 172.75 = 69.46, the rest 30.54.
        self::assertSame([['69.46', '20'], ['30.54', '5.5']], self::amounts($payment->amounts));

        $full = PaymentAggregator::forInvoice($invoice, '172.75', $paidOn);
        self::assertSame([['120.00', '20'], ['52.75', '5.5']], self::amounts($full->amounts));
    }

    public function test_payment_for_non_eur_invoice_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('USD');

        PaymentAggregator::forInvoice(self::twoRateInvoice()->withCurrency(CurrencyCode::USD), '100', new DateTimeImmutable('2026-11-25'));
    }

    private static function twoRateInvoice(): Invoice
    {
        return EReports::consumerSale(BillingFramework::S1, 'F-10', '2026-11-05', [
            EReports::consumerLine('1', 'SERVICE', '100.00', '20'),
            EReports::consumerLine('2', 'BOOK', '50.00', '5.5'),
        ]);
    }

    /**
     * @param  list<array{numeric-string, numeric-string}>  $amounts  amount and rate per collected amount
     */
    private static function payment(string $number, string $paidOn, array $amounts): InvoicePayment
    {
        return new InvoicePayment(
            $number,
            new DateTimeImmutable('2026-11-01'),
            new DateTimeImmutable($paidOn),
            array_map(static fn (array $amount): CollectedAmount => new CollectedAmount($amount[0], $amount[1]), $amounts),
        );
    }

    /**
     * @param  list<CollectedAmount>  $amounts
     * @return list<array{string, string}>
     */
    private static function amounts(array $amounts): array
    {
        return array_map(static fn (CollectedAmount $amount): array => [(string) $amount->amount, (string) $amount->vatRate], $amounts);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\EReporting;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\EReporting\B2cAggregator;
use IntrAgile\EInvoicing\France\EReporting\DailyTransactions;
use IntrAgile\EInvoicing\France\EReporting\TransactionCategory;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class B2cAggregatorTest extends TestCase
{
    public function test_same_day_sales_are_summed_per_rate(): void
    {
        $days = (new B2cAggregator)->aggregate([
            self::sale('T-3', '2026-11-11', [EReports::consumerLine('1', 'GOODS', '12.00', '20')])->withTaxPointDateCode('3'),
            self::sale('T-1', '2026-11-10', [
                EReports::consumerLine('1', 'GOODS', '50.00', '5.5'),
                EReports::consumerLine('2', 'GOODS', '100.00', '20'),
            ])->withTaxPointDateCode('3'),
            self::sale('T-2', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '200.00', '20')])->withTaxPointDateCode('3'),
        ]);

        self::assertCount(2, $days);
        self::assertDay($days[0], '2026-11-10', TransactionCategory::Goods, '350.00', '62.75', [['20', '300.00', '60.00'], ['5.5', '50.00', '2.75']], 2);
        self::assertSame('3', $days[0]->vatDueDateCode);
        self::assertSame(CurrencyCode::EUR, $days[0]->currency);
        self::assertDay($days[1], '2026-11-11', TransactionCategory::Goods, '12.00', '2.40', [['20', '12.00', '2.40']], 1);
    }

    public function test_credit_note_nets_against_same_day_sales(): void
    {
        $days = (new B2cAggregator)->aggregate([
            self::sale('T-1', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '300.00', '20')])->withTaxPointDateCode('3'),
            self::creditNote('A-1', '2026-11-10', InvoiceTypeCode::C_381, '100.00'),
        ]);

        self::assertCount(1, $days);
        self::assertDay($days[0], '2026-11-10', TransactionCategory::Goods, '200.00', '40.00', [['20', '200.00', '40.00']], 2);
        self::assertNull($days[0]->vatDueDateCode);
    }

    public function test_credit_note_only_day_is_negative(): void
    {
        foreach ([InvoiceTypeCode::C_381, InvoiceTypeCode::C_261, InvoiceTypeCode::C_396, InvoiceTypeCode::C_502, InvoiceTypeCode::C_503] as $type) {
            $days = (new B2cAggregator)->aggregate([self::creditNote('A-1', '2026-11-12', $type, '100.00')]);

            self::assertCount(1, $days, $type->value);
            self::assertDay($days[0], '2026-11-12', TransactionCategory::Goods, '-100.00', '-20.00', [['20', '-100.00', '-20.00']], 1);
        }
    }

    public function test_goods_and_services_frameworks_split_into_categories(): void
    {
        $days = (new B2cAggregator)->aggregate([
            EReports::consumerSale(BillingFramework::S1, 'T-1', '2026-11-10', [EReports::consumerLine('1', 'SERVICE', '80.00', '10')]),
            EReports::consumerSale(BillingFramework::B2, 'T-2', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '100.00', '20')]),
            EReports::consumerSale(BillingFramework::S4, 'T-3', '2026-11-10', [EReports::consumerLine('1', 'SERVICE', '20.00', '10')]),
        ]);

        self::assertCount(2, $days);
        self::assertDay($days[0], '2026-11-10', TransactionCategory::Goods, '100.00', '20.00', [['20', '100.00', '20.00']], 1);
        self::assertDay($days[1], '2026-11-10', TransactionCategory::Services, '100.00', '10.00', [['10', '100.00', '10.00']], 2);
    }

    public function test_mixed_framework_without_classifier_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('M1');

        (new B2cAggregator)->aggregate([self::mixedSale()]);
    }

    public function test_mixed_framework_without_lines_is_refused_even_with_a_classifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice T-1 has no lines (BG-25) to classify.');

        (new B2cAggregator(self::classifier()))->aggregate([self::withoutLines(self::mixedSale())]);
    }

    public function test_mixed_framework_with_document_allowance_is_refused(): void
    {
        $sale = EReports::consumerSale(BillingFramework::M1, 'T-1', '2026-11-13', self::mixedLines(), allowances: [
            new DocumentAllowance('10.00', VatCategory::StandardRate, '20'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BG-20');

        (new B2cAggregator(self::classifier()))->aggregate([$sale]);
    }

    public function test_classifier_assigns_tnt1(): void
    {
        $days = (new B2cAggregator(self::classifier()))->aggregate([
            self::mixedSale(),
            self::sale('T-2', '2026-11-13', [EReports::consumerLine('1', 'GOODS', '100.00', '20')]),
        ]);

        self::assertCount(3, $days);
        // The two 5.5 % lines are taxed as one group: 20.10 x 5.5 % = 1.1055, rounded to 1.11.
        self::assertDay($days[0], '2026-11-13', TransactionCategory::Goods, '120.10', '21.11', [['20', '100.00', '20.00'], ['5.5', '20.10', '1.11']], 2);
        self::assertDay($days[1], '2026-11-13', TransactionCategory::Services, '100.00', '20.00', [['20', '100.00', '20.00']], 1);
        self::assertDay($days[2], '2026-11-13', TransactionCategory::NotTaxedInFrance, '40.00', '0.00', [['0', '40.00', '0.00']], 1);
    }

    public function test_classifier_keeps_the_vat_breakdown_of_a_single_category_invoice(): void
    {
        $sale = EReports::consumerSale(BillingFramework::M1, 'T-1', '2026-11-13', [
            EReports::consumerLine('1', 'GOODS', '100.00', '20'),
            EReports::consumerLine('2', 'GOODS', '50.00', '20'),
        ], allowances: [new DocumentAllowance('10.00', VatCategory::StandardRate, '20')]);

        $days = (new B2cAggregator(self::classifier()))->aggregate([$sale]);

        self::assertCount(1, $days);
        self::assertDay($days[0], '2026-11-13', TransactionCategory::Goods, '140.00', '28.00', [['20', '140.00', '28.00']], 1);
    }

    public function test_non_eur_b2c_invoice_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('USD');

        (new B2cAggregator)->aggregate([
            self::sale('T-1', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '100.00', '20')])->withCurrency(CurrencyCode::USD),
        ]);
    }

    public function test_operation_date_prefers_delivery_date(): void
    {
        $days = (new B2cAggregator)->aggregate([
            self::sale('T-1', '2026-11-05', [EReports::consumerLine('1', 'GOODS', '100.00', '20')])
                ->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable('2026-11-03'))),
            self::sale('T-2', '2026-11-03', [EReports::consumerLine('1', 'GOODS', '10.00', '20')]),
            self::sale('T-3', '2026-11-05', [EReports::consumerLine('1', 'GOODS', '1.00', '20')]),
        ]);

        self::assertCount(2, $days);
        self::assertDay($days[0], '2026-11-03', TransactionCategory::Goods, '110.00', '22.00', [['20', '110.00', '22.00']], 2);
        self::assertDay($days[1], '2026-11-05', TransactionCategory::Goods, '1.00', '0.20', [['20', '1.00', '0.20']], 1);
    }

    public function test_invoice_without_billing_framework_is_refused(): void
    {
        $sale = self::sale('T-1', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '100.00', '20')])->withProcessControl(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-23');

        (new B2cAggregator)->aggregate([$sale]);
    }

    public function test_no_invoice_gives_no_day(): void
    {
        self::assertSame([], (new B2cAggregator)->aggregate([]));
    }

    /**
     * @param  list<InvoiceLine>  $lines
     */
    private static function sale(string $number, string $date, array $lines): Invoice
    {
        return EReports::consumerSale(BillingFramework::B1, $number, $date, $lines);
    }

    /**
     * @param  numeric-string  $amount
     */
    private static function creditNote(string $number, string $date, InvoiceTypeCode $type, string $amount): Invoice
    {
        return EReports::consumerSale(BillingFramework::B1, $number, $date, [EReports::consumerLine('1', 'GOODS', $amount, '20')], $type);
    }

    /**
     * A copy of the invoice without lines. The model refuses such an invoice, so the copy is made
     * by reflection to exercise the aggregator's own guard.
     */
    private static function withoutLines(Invoice $invoice): Invoice
    {
        $class = new ReflectionClass(Invoice::class);
        $copy = $class->newInstanceWithoutConstructor();
        foreach ($class->getProperties() as $property) {
            $property->setValue($copy, $property->getName() === 'lines' ? [] : $property->getValue($invoice));
        }

        return $copy;
    }

    private static function mixedSale(): Invoice
    {
        return EReports::consumerSale(BillingFramework::M1, 'T-1', '2026-11-13', self::mixedLines());
    }

    /**
     * @return list<InvoiceLine>
     */
    private static function mixedLines(): array
    {
        return [
            EReports::consumerLine('1', 'GOODS', '10.05', '5.5'),
            EReports::consumerLine('2', 'SERVICE', '100.00', '20'),
            EReports::consumerLine('3', 'GOODS', '10.05', '5.5'),
            EReports::consumerLine('4', 'EXPORT', '40.00', null),
        ];
    }

    /**
     * @return Closure(InvoiceLine, Invoice): TransactionCategory
     */
    private static function classifier(): Closure
    {
        return static fn (InvoiceLine $line, Invoice $invoice): TransactionCategory => match ($line->item->name) {
            'SERVICE' => TransactionCategory::Services,
            'EXPORT' => TransactionCategory::NotTaxedInFrance,
            default => TransactionCategory::Goods,
        };
    }

    /**
     * @param  list<array{string, string, string}>  $subtotals  rate, taxable and tax per subtotal
     */
    private static function assertDay(
        DailyTransactions $day,
        string $date,
        TransactionCategory $category,
        string $taxExclusive,
        string $taxTotal,
        array $subtotals,
        int $count,
    ): void {
        self::assertSame($date, $day->date->format('Y-m-d'));
        self::assertSame($category, $day->category);
        self::assertSame($taxExclusive, (string) $day->taxExclusive);
        self::assertSame($taxTotal, (string) $day->taxTotal);
        self::assertSame($count, $day->count);
        $actual = [];
        foreach ($day->subtotals as $subtotal) {
            $actual[] = [(string) $subtotal->rate, (string) $subtotal->taxable, (string) $subtotal->tax];
        }
        self::assertSame($subtotals, $actual);
    }
}

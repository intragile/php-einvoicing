<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\Collection;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    public function test_allocation_sums_exactly_to_paid_amount(): void
    {
        $amounts = Collection::allocate($this->twoRates(), '1000.01');

        self::assertSame([['891.83', '20'], ['108.18', '5.5']], $this->flatten($amounts));
        self::assertTrue($this->sum($amounts)->isEqualTo('1000.01'));
    }

    public function test_allocation_residual_goes_to_largest_gross(): void
    {
        // Gross 100.00 / 50.00 / 50.00: 0.10 splits into 0.05 / 0.025 / 0.025, rounded 0.05 / 0.03 /
        // 0.03 = 0.11; the -0.01 residual lands on the largest gross.
        $invoice = $this->invoice([
            new VatBreakdown('50.00', '50.00', VatCategory::StandardRate, '100'),
            new VatBreakdown('47.39', '2.61', VatCategory::StandardRate, '5.5'),
            new VatBreakdown('45.45', '4.55', VatCategory::StandardRate, '10'),
        ]);

        $amounts = Collection::allocate($invoice, '0.10');

        self::assertSame([['0.04', '100'], ['0.03', '5.5'], ['0.03', '10']], $this->flatten($amounts));
    }

    public function test_residual_tie_goes_to_higher_rate(): void
    {
        $invoice = $this->invoice([
            new VatBreakdown('0.95', '0.05', VatCategory::StandardRate, '5.5'),
            new VatBreakdown('0.83', '0.17', VatCategory::StandardRate, '20'),
        ]);

        // 0.005 each rounds up to 0.01 + 0.01 = 0.02; the -0.01 residual goes to 20 %.
        $amounts = Collection::allocate($invoice, '0.01');

        self::assertSame([['0.01', '5.5'], ['0.00', '20']], $this->flatten($amounts));
    }

    public function test_full_payment_equals_gross_per_rate(): void
    {
        $amounts = Collection::allocate($this->twoRates(), '1211.00');

        self::assertSame([['1080.00', '20'], ['131.00', '5.5']], $this->flatten($amounts));
    }

    public function test_refund_allocation_is_negative(): void
    {
        $amounts = Collection::allocate($this->twoRates(), BigDecimal::of('-1000.01'));

        self::assertSame([['-891.83', '20'], ['-108.18', '5.5']], $this->flatten($amounts));
    }

    public function test_rates_with_zero_gross_are_omitted(): void
    {
        $invoice = $this->invoice([
            new VatBreakdown('100.00', '20.00', VatCategory::StandardRate, '20'),
            new VatBreakdown('0.00', '0.00', VatCategory::StandardRate, '10'),
        ]);

        self::assertSame([['60.00', '20']], $this->flatten(Collection::allocate($invoice, '60')));
    }

    public function test_all_zero_gross_keeps_every_rate(): void
    {
        $invoice = $this->invoice([
            new VatBreakdown('0.00', '0.00', VatCategory::StandardRate, '20'),
            new VatBreakdown('0.00', '0.00', VatCategory::ZeroRated, '0'),
        ]);

        self::assertSame([['0.00', '20'], ['0.00', '0']], $this->flatten(Collection::allocate($invoice, '0')));
    }

    public function test_breakdowns_with_the_same_rate_are_merged(): void
    {
        $invoice = $this->invoice([
            new VatBreakdown('100.00', '0.00', VatCategory::ZeroRated, '0'),
            new VatBreakdown('100.00', '0.00', VatCategory::Exempt, '0.00'),
        ]);

        self::assertSame([['150.00', '0']], $this->flatten(Collection::allocate($invoice, '150')));
    }

    public function test_invoice_without_vat_breakdown_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Collection::allocate($this->invoice([]), '10');
    }

    public function test_float_paid_amount_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid amount must be given as a string, int or BigDecimal, float given.');

        Collection::allocate($this->twoRates(), 1211.0);
    }

    public function test_int_paid_amount_is_accepted(): void
    {
        self::assertEquals(
            $this->flatten(Collection::allocate($this->twoRates(), '1211.00')),
            $this->flatten(Collection::allocate($this->twoRates(), 1211)),
        );
    }

    private function twoRates(): Invoice
    {
        return $this->invoice([
            new VatBreakdown('900.00', '180.00', VatCategory::StandardRate, '20'),
            new VatBreakdown('124.17', '6.83', VatCategory::StandardRate, '5.5'),
        ]);
    }

    /**
     * @param  list<VatBreakdown>  $breakdown
     */
    private function invoice(array $breakdown): Invoice
    {
        return FrenchInvoices::domesticB2b()->withVatBreakdown($breakdown);
    }

    /**
     * @param  list<CollectedAmount>  $amounts
     * @return list<array{string, string}>
     */
    private function flatten(array $amounts): array
    {
        return array_map(static fn (CollectedAmount $a): array => [(string) $a->amount, (string) $a->vatRate], $amounts);
    }

    /**
     * @param  list<CollectedAmount>  $amounts
     */
    private function sum(array $amounts): BigDecimal
    {
        return array_reduce($amounts, static fn (BigDecimal $carry, CollectedAmount $a): BigDecimal => $carry->plus($a->amount), BigDecimal::zero());
    }
}

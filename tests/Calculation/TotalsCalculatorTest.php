<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Calculation;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\Rounding;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotalsCalculatorTest extends TestCase
{
    /**
     * Figures of CEN guide-example1.xml (validation artefacts 1.3.17), built by hand.
     *
     * Line 20 is a return: the file states quantity 6 with net amount -109.98 at price 18.33;
     * EN 16931 prices cannot be negative (BR-27), so the return is modelled as quantity -6.
     */
    public function test_guide_example1_totals(): void
    {
        $lines = [];
        $data = [
            ['2', '9.95', '6'], ['1', '9.85', '6'], ['1', '8.29', '6'], ['2', '7.23', '6'], ['1', '35.00', '6'],
            ['1', '35.00', '6'], ['1', '10.65', '6'], ['1', '1.55', '6'], ['3', '4.79', '6'], ['1', '8.29', '6'],
            ['2', '8.29', '6'], ['1', '9.95', '6'], ['2', '1.65', '6'], ['1', '10.80', '21'], ['1', '3.90', '6'],
            ['2', '3.80', '21'], ['2', '4.67', '21'], ['1', '18.63', '21'], ['6', '17.02', '6'], ['-6', '18.33', '6'],
        ];
        foreach ($data as $i => [$quantity, $price, $rate]) {
            $lines[] = $this->line((string) ($i + 1), $quantity, $price, VatCategory::StandardRate, $rate, unit: UnitCode::EA);
        }

        $invoice = TotalsCalculator::complete($this->invoice($lines));
        $totals = $invoice->totals;

        self::assertNotNull($totals);
        self::assertSame('229.60', (string) $totals->sumOfLineNetAmounts);
        self::assertSame('229.60', (string) $totals->totalWithoutVat);
        self::assertSame('20.73', (string) $totals->totalVat);
        self::assertSame('250.33', (string) $totals->totalWithVat);
        self::assertSame('250.33', (string) $totals->amountDue);
        self::assertNull($totals->sumOfAllowances);
        self::assertNull($totals->sumOfCharges);
        self::assertNull($totals->totalVatInAccountingCurrency);
        self::assertSame('-109.98', (string) $invoice->lines[19]->netAmount);

        self::assertSame([['S', '6', '183.23', '10.99'], ['S', '21', '46.37', '9.74']], $this->breakdownRows($invoice));
        self::assertSame([], TotalsCalculator::check($invoice));
    }

    public function test_tenth_plus_two_tenths_is_exactly_thirty_cents(): void
    {
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '1', '0.1'),
            $this->line('2', '1', '0.2'),
        ]));

        self::assertSame('0.30', (string) $this->totalsOf($invoice)->sumOfLineNetAmounts);
        self::assertSame('0.30', (string) $this->totalsOf($invoice)->totalWithoutVat);
    }

    public function test_four_decimal_quantity_rounds_the_line_net_half_up(): void
    {
        $invoice = TotalsCalculator::complete($this->invoice([$this->line('1', '1.2345', '9.99')]));

        self::assertSame('12.33', (string) $invoice->lines[0]->netAmount);
    }

    public function test_price_base_quantity_divides_the_net_price(): void
    {
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '3', '250.00', baseQuantity: '100'),
        ]));

        self::assertSame('7.50', (string) $invoice->lines[0]->netAmount);
    }

    public function test_base_quantity_division_is_rounded_once_at_the_end(): void
    {
        // 7 x (10.00 / 3) = 23.333..., not 7 x 3.33 = 23.31.
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '7', '10.00', baseQuantity: '3'),
        ]));

        self::assertSame('23.33', (string) $invoice->lines[0]->netAmount);
    }

    public function test_zero_base_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-149');

        TotalsCalculator::complete($this->invoice([$this->line('1', '1', '5.00', baseQuantity: '0')]));
    }

    public function test_negative_line_on_an_invoice_subtracts(): void
    {
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '2', '50.00', rate: '27'),
            $this->line('2', '-1', '30.00', rate: '27'),
        ]));

        self::assertSame('-30.00', (string) $invoice->lines[1]->netAmount);
        self::assertSame('70.00', (string) $this->totalsOf($invoice)->sumOfLineNetAmounts);
        self::assertSame('18.90', (string) $this->totalsOf($invoice)->totalVat);
        self::assertSame('88.90', (string) $this->totalsOf($invoice)->amountDue);
    }

    public function test_credit_note_totals_are_positive(): void
    {
        $invoice = TotalsCalculator::complete(
            $this->invoice([$this->line('1', '1.5', '19.99', rate: '27')])->withType(InvoiceTypeCode::C_381),
        );

        self::assertSame('29.99', (string) $this->totalsOf($invoice)->sumOfLineNetAmounts);
        self::assertSame('8.10', (string) $this->totalsOf($invoice)->totalVat);
        self::assertSame('38.09', (string) $this->totalsOf($invoice)->amountDue);
    }

    public function test_line_allowances_and_charges_adjust_the_line_net(): void
    {
        $line = $this->line('1', '1', '100.00')
            ->withAllowance(new LineAllowance(amount: '10.00'))
            ->withCharge(new LineCharge(amount: '2.50'));

        $invoice = TotalsCalculator::complete($this->invoice([$line]));

        self::assertSame('92.50', (string) $invoice->lines[0]->netAmount);
    }

    public function test_document_allowances_and_charges_enter_their_own_vat_group(): void
    {
        $invoice = TotalsCalculator::complete($this->invoiceWithAllowanceAndCharge());
        $totals = $invoice->totals;

        self::assertNotNull($totals);
        self::assertSame([['S', '27', '900.00', '243.00'], ['S', '5', '250.00', '12.50']], $this->breakdownRows($invoice));
        self::assertSame('1200.00', (string) $totals->sumOfLineNetAmounts);
        self::assertSame('100.00', (string) $totals->sumOfAllowances);
        self::assertSame('50.00', (string) $totals->sumOfCharges);
        self::assertSame('1150.00', (string) $totals->totalWithoutVat);
        self::assertSame('255.50', (string) $totals->totalVat);
        self::assertSame('1405.50', (string) $totals->totalWithVat);
        self::assertSame('1405.50', (string) $totals->amountDue);
    }

    public function test_vat_is_rounded_per_group_not_summed_from_rounded_line_vat(): void
    {
        // Each line: 0.10 x 25 % = 0.025 -> 0.03, sum 0.09. Group: 0.30 x 25 % = 0.075 -> 0.08.
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '1', '0.10', rate: '25'),
            $this->line('2', '1', '0.10', rate: '25'),
            $this->line('3', '1', '0.10', rate: '25'),
        ]));

        self::assertSame([['S', '25', '0.30', '0.08']], $this->breakdownRows($invoice));
        self::assertSame('0.08', (string) $this->totalsOf($invoice)->totalVat);
    }

    public function test_vat_rounds_half_up_away_from_zero(): void
    {
        $positive = TotalsCalculator::complete($this->invoice([$this->line('1', '1', '0.10', rate: '5')]));
        $negative = TotalsCalculator::complete($this->invoice([$this->line('1', '-1', '0.10', rate: '5')]));

        self::assertSame('0.01', (string) $positive->vatBreakdown[0]->taxAmount);
        self::assertSame('-0.01', (string) $negative->vatBreakdown[0]->taxAmount);
    }

    public function test_never_overwrites_values_the_caller_set(): void
    {
        $presetBreakdown = new VatBreakdown(taxableAmount: '999.00', taxAmount: '1.00', category: VatCategory::StandardRate, rate: '27');
        $presetTotals = new DocumentTotals(sumOfLineNetAmounts: '1', totalWithoutVat: '2', totalWithVat: '3', amountDue: '4');
        $invoice = $this->invoice([
            $this->line('1', '1', '10.00', rate: '27')->withNetAmount('11.11'),
            $this->line('2', '1', '10.00', rate: '5'),
        ])->withVatBreakdown([$presetBreakdown])->withTotals($presetTotals);

        $completed = TotalsCalculator::complete($invoice);

        self::assertSame('11.11', (string) $completed->lines[0]->netAmount);
        self::assertSame('10.00', (string) $completed->lines[1]->netAmount);
        self::assertSame($presetBreakdown, $completed->vatBreakdown[0]);
        self::assertSame([['S', '27', '999.00', '1.00'], ['S', '5', '10.00', '0.50']], $this->breakdownRows($completed));
        self::assertSame('1', (string) $this->totalsOf($completed)->sumOfLineNetAmounts);
        self::assertSame('2', (string) $this->totalsOf($completed)->totalWithoutVat);
        self::assertSame('3', (string) $this->totalsOf($completed)->totalWithVat);
        self::assertSame('4', (string) $this->totalsOf($completed)->amountDue);
        self::assertSame('1.50', (string) $this->totalsOf($completed)->totalVat);
    }

    public function test_completed_invoice_is_left_unchanged_by_a_second_run(): void
    {
        $once = TotalsCalculator::complete($this->invoiceWithAllowanceAndCharge());

        self::assertEquals($once, TotalsCalculator::complete($once));
    }

    public function test_bt111_is_filled_only_when_the_tax_currency_equals_the_invoice_currency(): void
    {
        $base = $this->invoice([$this->line('1', '1', '100.00', rate: '27')]);

        self::assertNull(TotalsCalculator::complete($base)->totals?->totalVatInAccountingCurrency);
        self::assertSame(
            '27.00',
            (string) TotalsCalculator::complete($base->withTaxCurrency(CurrencyCode::EUR))->totals?->totalVatInAccountingCurrency,
        );
        self::assertNull(TotalsCalculator::complete($base->withTaxCurrency(CurrencyCode::HUF))->totals?->totalVatInAccountingCurrency);
    }

    public function test_mixed_vat_invoice_gets_one_breakdown_per_category_and_rate(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        self::assertSame([
            ['S', '27', '1100.00', '297.00'],
            ['S', '5', '50.00', '2.50'],
            ['E', '0', '300.00', '0.00'],
            ['AE', '0', '360.00', '0.00'],
            ['K', '0', '460.00', '0.00'],
            ['Z', '0', '30.00', '0.00'],
        ], $this->breakdownRows($invoice));

        $reasons = array_map(static fn (VatBreakdown $b): ?string => $b->exemptionReason, $invoice->vatBreakdown);
        self::assertSame([
            null,
            null,
            'Exempt under Article 132(1)(i) of Directive 2006/112/EC',
            'Reverse charge',
            'Intra-community supply',
            null,
        ], $reasons);

        self::assertSame('2300.00', (string) $this->totalsOf($invoice)->sumOfLineNetAmounts);
        self::assertSame('299.50', (string) $this->totalsOf($invoice)->totalVat);
        self::assertSame('2599.50', (string) $this->totalsOf($invoice)->amountDue);
        self::assertSame([], TotalsCalculator::check($invoice));
    }

    public function test_not_subject_to_vat_invoice_gets_a_single_rateless_group(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::notSubjectToVat());
        $totals = $this->totalsOf($invoice);

        self::assertSame([['O', '', '216.00', '0.00']], $this->breakdownRows($invoice));
        self::assertSame('Not subject to VAT', $invoice->vatBreakdown[0]->exemptionReason);
        self::assertSame('211.00', (string) $totals->sumOfLineNetAmounts);
        self::assertNull($totals->sumOfAllowances);
        self::assertSame('5.00', (string) $totals->sumOfCharges);
        self::assertSame('216.00', (string) $totals->totalWithoutVat);
        self::assertSame('0.00', (string) $totals->totalVat);
        self::assertSame('216.00', (string) $totals->totalWithVat);
        self::assertSame('216.00', (string) $totals->amountDue);
        self::assertNull($invoice->seller->vatIdentifier);
        self::assertNull($invoice->buyer->vatIdentifier);
        self::assertSame([], TotalsCalculator::check($invoice));
    }

    public function test_calculator_never_invents_an_exemption_reason(): void
    {
        $invoice = TotalsCalculator::complete($this->invoice([
            $this->line('1', '1', '40.00', VatCategory::Exempt, '0'),
            $this->line('2', '1', '60.00', VatCategory::NotSubjectToVat, null),
        ]));

        self::assertSame([['E', '0', '40.00', '0.00'], ['O', '', '60.00', '0.00']], $this->breakdownRows($invoice));
        self::assertNull($invoice->vatBreakdown[0]->exemptionReason);
        self::assertNull($invoice->vatBreakdown[0]->exemptionReasonCode);
        self::assertNull($invoice->vatBreakdown[1]->exemptionReason);
    }

    public function test_check_reports_br_co_16(): void
    {
        $invoice = TotalsCalculator::complete($this->invoiceWithAllowanceAndCharge());

        $broken = $invoice->withTotals($this->totalsWith($invoice, ['amountDue' => '1405.51']));

        self::assertSame(['BR-CO-16'], TotalsCalculator::check($broken));
    }

    /**
     * @param  array<string, string|null>  $override
     * @param  list<string>  $expected
     */
    #[DataProvider('brokenTotals')]
    public function test_check_reports_each_violated_rule(array $override, array $expected): void
    {
        $invoice = TotalsCalculator::complete($this->invoiceWithAllowanceAndCharge());

        self::assertSame($expected, TotalsCalculator::check($invoice->withTotals($this->totalsWith($invoice, $override))));
    }

    /**
     * @return iterable<string, array{array<string, string|null>, list<string>}>
     */
    public static function brokenTotals(): iterable
    {
        yield 'BT-106' => [['sumOfLineNetAmounts' => '1200.01'], ['BR-CO-10', 'BR-CO-13']];
        yield 'BT-107' => [['sumOfAllowances' => '100.01'], ['BR-CO-11', 'BR-CO-13']];
        yield 'BT-107 missing' => [['sumOfAllowances' => null], ['BR-CO-11', 'BR-CO-13']];
        yield 'BT-108' => [['sumOfCharges' => '49.99'], ['BR-CO-12', 'BR-CO-13']];
        yield 'BT-109' => [['totalWithoutVat' => '1150.01'], ['BR-CO-13', 'BR-CO-15']];
        yield 'BT-110' => [['totalVat' => '255.49'], ['BR-CO-14', 'BR-CO-15']];
        yield 'BT-110 missing' => [['totalVat' => null], ['BR-CO-14', 'BR-CO-15']];
        yield 'BT-112' => [['totalWithVat' => '1405.49'], ['BR-CO-15', 'BR-CO-16']];
        yield 'BT-113 without matching BT-115' => [['paidAmount' => '5.50'], ['BR-CO-16']];
        yield 'BT-113 and BT-114 consistent' => [['paidAmount' => '5.50', 'roundingAmount' => '0.30', 'amountDue' => '1400.30'], []];
        yield 'BT-114 only, consistent' => [['roundingAmount' => '0.30', 'amountDue' => '1405.80'], []];
        yield 'BT-114 only, inconsistent' => [['roundingAmount' => '0.30', 'amountDue' => '1405.50'], ['BR-CO-16']];
    }

    public function test_check_reports_br_co_14_but_tolerates_a_one_cent_group_vat_difference(): void
    {
        $invoice = TotalsCalculator::complete($this->invoiceWithAllowanceAndCharge());
        $breakdown = $invoice->vatBreakdown;
        $breakdown[0] = new VatBreakdown(taxableAmount: '900.00', taxAmount: '243.01', category: VatCategory::StandardRate, rate: '27');

        self::assertSame(['BR-CO-14'], TotalsCalculator::check($invoice->withVatBreakdown($breakdown)));
    }

    /**
     * The CEN 1.3.17 UBL artefact accepts |BT-117| within less than 1.00 of
     * round(|BT-116| x BT-119 / 100); 900.00 at 27 % gives 243.00.
     *
     * @param  list<string>  $expected
     */
    #[DataProvider('br17Tolerance')]
    public function test_br_co_17_mirrors_the_official_tolerance(string $taxAmount, ?string $rate, VatCategory $category, array $expected): void
    {
        $invoice = $this->invoice([$this->line('1', '1', '900.00', $category, $rate)])
            ->withVatBreakdown([new VatBreakdown(taxableAmount: '900.00', taxAmount: $taxAmount, category: $category, rate: $rate)]);

        self::assertSame($expected, TotalsCalculator::check($invoice));
    }

    /**
     * @return iterable<string, array{string, string|null, VatCategory, list<string>}>
     */
    public static function br17Tolerance(): iterable
    {
        $s = VatCategory::StandardRate;
        $o = VatCategory::NotSubjectToVat;
        $z = VatCategory::ZeroRated;

        yield 'exact' => ['243.00', '27', $s, []];
        yield '+0.50 tolerated' => ['243.50', '27', $s, []];
        yield '-0.50 tolerated' => ['242.50', '27', $s, []];
        yield '+0.99 tolerated' => ['243.99', '27', $s, []];
        yield '+1.00 reported' => ['244.00', '27', $s, ['BR-CO-17']];
        yield '+1.01 reported' => ['244.01', '27', $s, ['BR-CO-17']];
        yield '-1.01 reported' => ['241.99', '27', $s, ['BR-CO-17']];
        yield 'sign ignored' => ['-243.00', '27', $s, []];
        yield 'no rate, 0.49 tolerated' => ['0.49', null, $o, []];
        yield 'no rate, -0.50 tolerated' => ['-0.50', null, $o, []];
        yield 'no rate, 0.50 reported' => ['0.50', null, $o, ['BR-CO-17']];
        yield 'no rate, 1.00 reported' => ['1.00', null, $o, ['BR-CO-17']];
        yield 'zero rate, 0.49 tolerated' => ['0.49', '0', $z, []];
        yield 'zero rate, 0.50 reported' => ['0.50', '0', $z, ['BR-CO-17']];
    }

    public function test_check_without_totals_only_evaluates_the_breakdown(): void
    {
        self::assertSame([], TotalsCalculator::check($this->invoice([$this->line('1', '1', '1.00')])));
    }

    public function test_rounding_is_half_up_to_two_decimals(): void
    {
        self::assertSame('1.01', (string) Rounding::amount(BigDecimal::of('1.005')));
        self::assertSame('-1.01', (string) Rounding::amount(BigDecimal::of('-1.005')));
        self::assertSame('1.00', (string) Rounding::amount(BigDecimal::of('1.00499999')));
        self::assertSame('2.00', (string) Rounding::amount(BigDecimal::of('2')));
    }

    private function totalsOf(Invoice $invoice): DocumentTotals
    {
        self::assertNotNull($invoice->totals);

        return $invoice->totals;
    }

    private function invoiceWithAllowanceAndCharge(): Invoice
    {
        return $this->invoice([
            $this->line('1', '4', '250.00', rate: '27'),
            $this->line('2', '8', '25.00', rate: '5'),
        ])
            ->withAllowance(new DocumentAllowance(amount: '100.00', vatCategory: VatCategory::StandardRate, vatRate: '27', reason: 'Volume discount'))
            ->withCharge(new DocumentCharge(amount: '50.00', vatCategory: VatCategory::StandardRate, vatRate: '5', reason: 'Packaging'));
    }

    /**
     * @param  array<string, string|null>  $override
     */
    private function totalsWith(Invoice $invoice, array $override): DocumentTotals
    {
        $t = $invoice->totals;
        self::assertNotNull($t);
        $pick = static fn (string $key, ?BigDecimal $current): ?string => array_key_exists($key, $override)
            ? $override[$key]
            : ($current === null ? null : (string) $current);

        return new DocumentTotals(
            sumOfLineNetAmounts: $pick('sumOfLineNetAmounts', $t->sumOfLineNetAmounts) ?? '0',
            totalWithoutVat: $pick('totalWithoutVat', $t->totalWithoutVat) ?? '0',
            totalWithVat: $pick('totalWithVat', $t->totalWithVat) ?? '0',
            amountDue: $pick('amountDue', $t->amountDue) ?? '0',
            sumOfAllowances: $pick('sumOfAllowances', $t->sumOfAllowances),
            sumOfCharges: $pick('sumOfCharges', $t->sumOfCharges),
            totalVat: $pick('totalVat', $t->totalVat),
            totalVatInAccountingCurrency: $pick('totalVatInAccountingCurrency', $t->totalVatInAccountingCurrency),
            paidAmount: $pick('paidAmount', $t->paidAmount),
            roundingAmount: $pick('roundingAmount', $t->roundingAmount),
        );
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    private function breakdownRows(Invoice $invoice): array
    {
        return array_map(static fn (VatBreakdown $b): array => [
            $b->category->value,
            $b->rate === null ? '' : (string) $b->rate,
            (string) $b->taxableAmount,
            (string) $b->taxAmount,
        ], $invoice->vatBreakdown);
    }

    /**
     * @param  list<InvoiceLine>  $lines
     */
    private function invoice(array $lines): Invoice
    {
        return new Invoice(
            number: 'CALC-1',
            issueDate: new DateTimeImmutable('2026-11-11'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party(name: 'Seller BV', vatIdentifier: 'NL123456789B01', address: new PostalAddress(country: CountryCode::NL)),
            buyer: new Party(name: 'Buyer BV', address: new PostalAddress(country: CountryCode::NL)),
            lines: $lines,
        );
    }

    private function line(
        string $id,
        string $quantity,
        string $price,
        VatCategory $category = VatCategory::StandardRate,
        ?string $rate = '21',
        ?string $baseQuantity = null,
        UnitCode $unit = UnitCode::C62,
    ): InvoiceLine {
        return new InvoiceLine(
            id: $id,
            quantity: $quantity,
            unit: $unit,
            price: new Price(netPrice: $price, baseQuantity: $baseQuantity),
            item: new Item(name: 'Item '.$id),
            vat: new LineVat(category: $category, rate: $rate),
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cii;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiVersion;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Xsd;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CiiRoundTripTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/cii';

    private const HOSTILE_TEXT = InvoiceFactory::HOSTILE_TEXT;

    /**
     * @return array<string, array{string}>
     */
    public static function officialExamples(): array
    {
        $cases = [];
        foreach (Fixtures::official('cii') as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    /**
     * Invoices built through the model, completed by the calculator.
     *
     * @return array<string, array{Invoice}>
     */
    public static function modelInvoices(): array
    {
        $mixed = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        return [
            'mixed VAT' => [$mixed],
            'not subject to VAT' => [TotalsCalculator::complete(InvoiceFactory::notSubjectToVat())],
            'mixed VAT as credit note' => [$mixed->withType(InvoiceTypeCode::C_381)],
            'credit note with payment' => [$mixed->withType(InvoiceTypeCode::C_381)->withPayment(
                new PaymentInstructions(meansCode: PaymentMeansCode::C_58, creditTransfers: [new CreditTransfer(accountIdentifier: 'HU42117730161111101800000000')]),
            )],
            'proprietary account and several transfers' => [$mixed->withPayment(new PaymentInstructions(
                meansCode: PaymentMeansCode::C_30,
                meansText: 'Bank transfer',
                remittanceInformation: 'MIX-2026-0001',
                creditTransfers: [
                    new CreditTransfer(accountIdentifier: '11773016-11111018-00000000', accountName: 'Main', serviceProviderIdentifier: 'OTPVHUHB'),
                    new CreditTransfer(accountIdentifier: 'HU42117730161111101800000000'),
                ],
            ))],
            'hostile text' => [InvoiceFactory::hostileText()],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writtenDocuments(): array
    {
        $cases = [];
        foreach (self::officialExamples() as $name => [$file]) {
            $cases[$name] = [(new CiiWriter)->write((new CiiReader)->read(self::contents($file))->invoice)];
        }
        foreach (self::modelInvoices() as $name => [$invoice]) {
            $cases[$name] = [(new CiiWriter)->write($invoice)];
        }
        $cases['all-terms-invoice.xml'] = [(new CiiWriter)->write((new CiiReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'))->invoice)];

        return $cases;
    }

    /**
     * Inbound documents the round trip starts from: the official examples and the self-authored
     * all-terms fixture, which must be schema-valid itself.
     *
     * @return array<string, array{string}>
     */
    public static function inputDocuments(): array
    {
        $cases = [];
        foreach ([...self::officialExamples(), 'all-terms-invoice.xml' => [self::FIXTURES.'/all-terms-invoice.xml']] as $name => [$file]) {
            $cases[$name] = [self::contents($file)];
        }

        return $cases;
    }

    #[DataProvider('officialExamples')]
    public function test_round_trip_official_examples(string $file): void
    {
        $first = (new CiiReader)->read(self::contents($file));

        $second = (new CiiReader)->read((new CiiWriter)->write($first->invoice));

        self::assertEquals($first->invoice, $second->invoice);
        self::assertTrue($first->invoice == $second->invoice);
        self::assertSame([], $second->ignored);
    }

    #[DataProvider('modelInvoices')]
    public function test_round_trip_model_invoices(Invoice $invoice): void
    {
        $read = (new CiiReader)->read((new CiiWriter)->write($invoice));

        self::assertEquals($invoice, $read->invoice);
        self::assertTrue($invoice == $read->invoice);
        self::assertSame([], $read->ignored);
    }

    public function test_round_trip_all_terms_fixture(): void
    {
        $first = (new CiiReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'));
        self::assertSame([], $first->ignored);

        $second = (new CiiReader)->read((new CiiWriter)->write($first->invoice));

        self::assertEquals($first->invoice, $second->invoice);
        self::assertTrue($first->invoice == $second->invoice);
        self::assertSame([], $second->ignored);
    }

    #[DataProvider('writtenDocuments')]
    public function test_output_is_xsd_valid(string $xml): void
    {
        self::assertSame([], Xsd::errors($xml, Fixtures::ciiSchema()));
    }

    #[DataProvider('inputDocuments')]
    public function test_input_documents_are_xsd_valid(string $xml): void
    {
        self::assertSame([], Xsd::errors($xml, Fixtures::ciiSchema()));
    }

    public function test_schema_check_rejects_an_out_of_sequence_element(): void
    {
        $xml = (new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));
        $broken = preg_replace('#(<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>)#', '$1<ram:CreditorReferenceID>X</ram:CreditorReferenceID>', $xml, 1);
        self::assertIsString($broken);

        self::assertNotSame([], Xsd::errors($broken, Fixtures::ciiSchema()));
    }

    public function test_text_content_survives_byte_exact(): void
    {
        $invoice = InvoiceFactory::hostileText();

        $xml = (new CiiWriter)->write($invoice);
        $read = (new CiiReader)->read($xml)->invoice;

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('&amp; Co. &lt;b&gt;', $xml);
        self::assertStringContainsString('őŐűŰ 😀', $xml, 'UTF-8 is written as is, not as character references.');
        self::assertStringNotContainsString('#AAI#', $xml, 'CII carries the subject code in its own element.');

        self::assertSame(self::HOSTILE_TEXT, $read->seller->name);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->tradingName);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->address?->line1);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->contact?->name);
        self::assertSame('a&b@example.com', $read->seller->contact->email);
        self::assertSame(self::HOSTILE_TEXT, $read->buyer->name);
        self::assertSame(self::HOSTILE_TEXT, $read->notes[0]->text);
        self::assertSame(TextSubjectCode::AAI, $read->notes[0]->subjectCode);
        self::assertSame(self::HOSTILE_TEXT, $read->notes[1]->text);
        self::assertNull($read->notes[1]->subjectCode);
        self::assertSame(self::HOSTILE_TEXT, $read->paymentTerms);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->note);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->item->name);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->item->description);
        $exempt = array_values(array_filter($read->vatBreakdown, static fn (VatBreakdown $entry): bool => $entry->category === VatCategory::Exempt));
        self::assertSame(self::HOSTILE_TEXT, $exempt[0]->exemptionReason ?? null);
        self::assertEquals($invoice, $read);
    }

    public function test_mixed_vat_keeps_one_breakdown_per_category_and_rate_with_reasons(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        $read = (new CiiReader)->read((new CiiWriter)->write($invoice))->invoice;

        $summary = array_map(
            static fn (VatBreakdown $entry): string => sprintf(
                '%s %s %s/%s %s %s',
                $entry->category->value,
                $entry->rate === null ? '-' : (string) $entry->rate,
                (string) $entry->taxableAmount,
                (string) $entry->taxAmount,
                $entry->exemptionReasonCode->value ?? '-',
                $entry->exemptionReason ?? '-',
            ),
            $read->vatBreakdown,
        );
        sort($summary);
        $expected = array_map(
            static fn (VatBreakdown $entry): string => sprintf(
                '%s %s %s/%s %s %s',
                $entry->category->value,
                $entry->rate === null ? '-' : (string) $entry->rate,
                (string) $entry->taxableAmount,
                (string) $entry->taxAmount,
                $entry->exemptionReasonCode->value ?? '-',
                $entry->exemptionReason ?? '-',
            ),
            $invoice->vatBreakdown,
        );
        sort($expected);

        self::assertCount(6, $summary);
        self::assertSame($expected, $summary);
    }

    public function test_root_specification_identifier_and_type_codes(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withProcessControl(null);

        $invoiceXml = (new CiiWriter)->write($invoice);
        $creditNoteXml = (new CiiWriter)->write($invoice->withType(InvoiceTypeCode::C_381));
        $customXml = (new CiiWriter)->write($invoice->withProcessControl(new ProcessControl(
            specificationIdentifier: 'urn:cen.eu:en16931:2017#compliant#urn:example:cius',
            businessProcessType: 'urn:example:process',
        )));

        self::assertStringContainsString('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"', $invoiceXml);
        self::assertStringContainsString("<ram:GuidelineSpecifiedDocumentContextParameter>\n      <ram:ID>urn:cen.eu:en16931:2017</ram:ID>", $invoiceXml);
        self::assertSame('urn:cen.eu:en16931:2017', CiiWriter::SPECIFICATION_IDENTIFIER);
        self::assertStringContainsString('<ram:TypeCode>380</ram:TypeCode>', $invoiceXml);
        self::assertStringContainsString('<rsm:CrossIndustryInvoice xmlns:rsm=', $creditNoteXml);
        self::assertStringContainsString('<ram:TypeCode>381</ram:TypeCode>', $creditNoteXml);
        self::assertStringContainsString('<ram:ID>urn:cen.eu:en16931:2017#compliant#urn:example:cius</ram:ID>', $customXml);
        self::assertStringContainsString("<ram:BusinessProcessSpecifiedDocumentContextParameter>\n      <ram:ID>urn:example:process</ram:ID>", $customXml);
    }

    public function test_dates_are_written_in_format_102(): void
    {
        $xml = (new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));

        self::assertStringContainsString('<udt:DateTimeString format="102">20261111</udt:DateTimeString>', $xml);
        self::assertStringNotContainsString('2026-11-11', $xml);
    }

    public function test_tax_point_date_code_is_written_as_untdid_2475(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withTaxPointDate(null);

        foreach ([['3', '5'], ['35', '29'], ['432', '72']] as [$model, $cii]) {
            $withCode = $invoice->withTaxPointDateCode($model);
            $xml = (new CiiWriter)->write($withCode);
            $read = (new CiiReader)->read($xml);

            self::assertSame(1, substr_count($xml, "<ram:DueDateTypeCode>{$cii}</ram:DueDateTypeCode>"));
            self::assertEquals($withCode, $read->invoice);
            self::assertSame([], $read->ignored);
        }
    }

    public function test_unknown_tax_point_date_code_is_refused(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withTaxPointDate(null)->withTaxPointDateCode('999');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-8');

        (new CiiWriter)->write($invoice);
    }

    public function test_tax_point_date_without_vat_breakdown_is_refused(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withTaxPointDate(new DateTimeImmutable('2026-11-10'))->withVatBreakdown([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-7');

        (new CiiWriter)->write($invoice);
    }

    public function test_price_discount_without_gross_price_writes_the_implied_gross_price(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $line = $invoice->lines[0];
        $invoice = $invoice->withLines([
            $line->withPrice(new Price(netPrice: $line->price->netPrice, discount: '10.00', baseQuantity: '1', baseQuantityUnit: $line->unit)),
            ...array_slice($invoice->lines, 1),
        ]);

        $read = (new CiiReader)->read((new CiiWriter)->write($invoice));

        $price = $read->invoice->lines[0]->price;
        self::assertSame((string) $line->price->netPrice->plus('10.00'), (string) $price->grossPrice);
        self::assertSame('10.00', (string) $price->discount);
        self::assertSame((string) $line->price->netPrice, (string) $price->netPrice);
        self::assertSame([], $read->ignored);
    }

    public function test_several_preceding_invoices_are_refused_in_strict_d16b(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withPrecedingInvoices([
            new PrecedingInvoiceReference(number: 'A-1'),
            new PrecedingInvoiceReference(number: 'A-2'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BG-3');

        (new CiiWriter(CiiVersion::D16B))->write($invoice);
    }

    public function test_amounts_keep_their_scale(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $invoice = $invoice->withLines([
            $invoice->lines[0]->withQuantity('10.0000')->withNetAmount('1100.00'),
            ...array_slice($invoice->lines, 1),
        ]);

        $xml = (new CiiWriter)->write($invoice);

        self::assertStringContainsString('<ram:BilledQuantity unitCode="HUR">10.0000</ram:BilledQuantity>', $xml);
        self::assertStringContainsString('<ram:LineTotalAmount>1100.00</ram:LineTotalAmount>', $xml);
        self::assertStringContainsString('<ram:DuePayableAmount>2599.50</ram:DuePayableAmount>', $xml);
        self::assertStringContainsString('<ram:TaxTotalAmount currencyID="EUR">299.50</ram:TaxTotalAmount>', $xml);
        self::assertStringNotContainsString('E+', $xml);
    }

    public function test_missing_totals_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BG-22');

        (new CiiWriter)->write(InvoiceFactory::mixedVat());
    }

    public function test_missing_line_net_amount_is_refused(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $invoice = $invoice->withLines([$invoice->lines[0]->withNetAmount(null), ...array_slice($invoice->lines, 1)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-131');

        (new CiiWriter)->write($invoice);
    }

    /**
     * Unlike UBL, where the breakdown sits inside cac:TaxTotal with its mandatory amount, CII
     * keeps the breakdown apart from ram:TaxTotalAmount, so a missing BT-110 is written as missing
     * (CEN CII example 7 has none).
     */
    public function test_missing_vat_total_with_breakdown_round_trips(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        $invoice = $invoice->withTotals(new DocumentTotals(
            sumOfLineNetAmounts: $totals->sumOfLineNetAmounts,
            totalWithoutVat: $totals->totalWithoutVat,
            totalWithVat: $totals->totalWithVat,
            amountDue: $totals->amountDue,
        ));

        $xml = (new CiiWriter)->write($invoice);

        self::assertStringNotContainsString('TaxTotalAmount', $xml);
        self::assertEquals($invoice, (new CiiReader)->read($xml)->invoice);
    }

    private static function contents(string $file): string
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        return $contents;
    }
}

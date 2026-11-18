<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\KeyValue;
use IntrAgile\EInvoicing\Poland\PaymentForm;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Reader;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Syntax\ReadResult;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Fa3ReaderTest extends TestCase
{
    private const CLOCK = '2026-11-18T09:30:47+01:00';

    public function test_reads_official_template_fields(): void
    {
        $result = self::read(PolishSamples::official('invoice-template-fa-3.xml'));
        $invoice = $result->invoice;
        $polish = self::polish($invoice);

        self::assertSame([], $result->ignored);
        self::assertSame('FA/GRQMB-1/05/2025', $invoice->number);
        self::assertSame('2025-09-01', $invoice->issueDate->format('Y-m-d'));
        self::assertSame(InvoiceTypeCode::C_380, $invoice->type);
        self::assertSame(CurrencyCode::PLN, $invoice->currency);
        self::assertNull($invoice->taxCurrency);

        self::assertSame('PL2222222222', $invoice->seller->vatIdentifier);
        self::assertSame('Elektrownia S.A.', $invoice->seller->name);
        self::assertSame('ul. Kwiatowa 1 m. 2', $invoice->seller->address?->line1);
        self::assertSame('Warszawa', $invoice->seller->address->city);
        self::assertSame('00-001', $invoice->seller->address->postCode);
        self::assertSame(CountryCode::PL, $invoice->seller->address->country);
        self::assertSame('667444555', $invoice->seller->contact?->phone);
        self::assertSame('elektrownia@elektrownia.pl', $invoice->seller->contact->email);
        self::assertSame('PL1111111111', $invoice->buyer->vatIdentifier);
        self::assertSame('F.H.U. Jan Kowalski', $invoice->buyer->name);
        self::assertSame('99999999', $polish->buyer?->customerNumber);
        self::assertFalse($polish->buyerIsJstUnit);
        self::assertFalse($polish->buyerIsGvMember);

        self::assertCount(2, $invoice->lines);
        $line = $invoice->lines[0];
        self::assertSame('1', $line->id);
        self::assertSame('Razem za energię czynną', $line->item->name);
        self::assertSame('20.00', (string) $line->quantity);
        self::assertSame(UnitCode::C62, $line->unit);
        self::assertSame('kWh', $line->extensions()->get(PolishLine::class)?->unitLabel);
        self::assertSame('0.90', (string) $line->price->netPrice);
        self::assertSame('18.00', (string) $line->netAmount);
        self::assertSame(VatCategory::StandardRate, $line->vat->category);
        self::assertSame('23', (string) $line->vat->rate);
        self::assertSame('okres', $invoice->lines[1]->extensions()->get(PolishLine::class)?->unitLabel);

        self::assertSame('2026-01-01', $invoice->delivery?->invoicingPeriod?->start?->format('Y-m-d'));
        self::assertSame('2026-02-28', $invoice->delivery->invoicingPeriod->end?->format('Y-m-d'));
        self::assertCount(1, $invoice->vatBreakdown);
        self::assertSame(VatCategory::StandardRate, $invoice->vatBreakdown[0]->category);
        self::assertSame('23', (string) $invoice->vatBreakdown[0]->rate);
        self::assertSame('43.60', (string) $invoice->vatBreakdown[0]->taxableAmount);
        self::assertSame('10.03', (string) $invoice->vatBreakdown[0]->taxAmount);
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        self::assertSame('43.60', (string) $totals->sumOfLineNetAmounts);
        self::assertSame('43.60', (string) $totals->totalWithoutVat);
        self::assertSame('10.03', (string) $totals->totalVat);
        self::assertSame('53.63', (string) $totals->totalWithVat);
        self::assertSame('53.63', (string) $totals->amountDue);

        self::assertSame('2026-03-15', $invoice->dueDate?->format('Y-m-d'));
        self::assertSame(PaymentMeansCode::C_30, $invoice->payment?->meansCode);
        self::assertNull($polish->paymentForm);
        self::assertSame('73111111111111111111111111', $invoice->payment->creditTransfers[0]->accountIdentifier);
        self::assertSame('PLN', $invoice->payment->creditTransfers[0]->accountName);
        self::assertSame('73111111111111111111111111', $polish->bankAccounts[0]->number);
        self::assertSame('Bank Bankowości Bankowej S. A.', $polish->bankAccounts[0]->bankName);

        self::assertSame(['Kapiał zakładowy 5 000 000'], $polish->footer?->notes);
        $registry = $polish->footer->registries[0];
        self::assertSame(['0000099999', '999999999', '000099999'], [$registry->krs, $registry->regon, $registry->bdo]);

        self::assertSame(InvoiceKind::Vat, $polish->kind);
        self::assertSame('Warszawa', $polish->placeOfIssue);
        self::assertSame('2025-09-01T00:00:00Z', $polish->createdAt?->format('Y-m-d\TH:i:s\Z'));
        self::assertSame('UTC', $polish->createdAt->getTimezone()->getName());
        self::assertSame('SamploFaktur', $polish->systemInfo);
        self::assertNull($polish->annotations->exemption);
        self::assertNull($polish->annotations->margin);
        self::assertEquals(
            [new KeyValue(null, 'Informacja o akcyzie', 'Od 20 kWh energii elektrycznej czynnej naliczono akcyzę w kwocie 0,10 zł')],
            $polish->descriptions,
        );
        $settlement = $polish->settlement;
        self::assertNotNull($settlement);
        self::assertSame(['0.00 Odsetki'], array_map(static fn ($e): string => $e->amount.' '.$e->reason, $settlement->charges));
        self::assertSame(['0.00 Nadpłata'], array_map(static fn ($e): string => $e->amount.' '.$e->reason, $settlement->deductions));
    }

    public function test_reads_jst_template(): void
    {
        $result = self::read(PolishSamples::official('invoice-template-fa-3-with-custom-Subject3.xml'));
        $invoice = $result->invoice;
        $polish = self::polish($invoice);

        self::assertSame([], $result->ignored);
        self::assertTrue($polish->buyerIsJstUnit);
        self::assertCount(1, $polish->thirdParties);
        self::assertSame(ThirdPartyRole::JstRecipient, $polish->thirdParties[0]->role);
        self::assertSame('PL3333333333', $polish->thirdParties[0]->party->vatIdentifier);
        self::assertSame('Szkoła Podstawowa w Bzdziszewie', $polish->thirdParties[0]->party->name);
        self::assertSame('Bzdziszewo', $polish->thirdParties[0]->party->address?->city);

        self::assertSame(['aaaa111133339990', 'aaaa111133339991', 'aaaa111133339992'], array_map(static fn (InvoiceLine $l): string => $l->id, $invoice->lines));
        self::assertSame('2026-01-03', $invoice->lines[0]->extensions()->get(PolishLine::class)?->supplyDate?->format('Y-m-d'));
        self::assertSame('8418 21 91', $invoice->lines[0]->extensions()->get(PolishLine::class)->cn);
        self::assertCount(1, $invoice->lines[1]->allowances);
        self::assertSame('100', (string) $invoice->lines[1]->allowances[0]->amount);
        self::assertSame('P_10', $invoice->lines[1]->allowances[0]->reason);
        self::assertSame([], $invoice->lines[0]->allowances);

        self::assertSame('44343434/2026', $invoice->despatchAdviceReference);
        self::assertSame([], $polish->warehouseDocuments);
        $settlement = $polish->settlement;
        self::assertNotNull($settlement);
        self::assertCount(1, $settlement->deductions);
        self::assertSame('1000', (string) $settlement->deductions[0]->amount);
        self::assertSame('nadwyżka salda nierozliczonych środków', $settlement->deductions[0]->reason);
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        self::assertSame('64279.92', (string) $totals->totalWithVat);
        self::assertSame('63279.92', (string) $totals->amountDue);

        $terms = $polish->transactionTerms;
        self::assertNotNull($terms);
        self::assertCount(1, $terms->transports);
        self::assertSame('Transport', $terms->transports[0]->name);
        self::assertSame(['2312323/2026'], $terms->batchNumbers);
        self::assertSame('CIP', $terms->deliveryTerms);
        self::assertCount(1, $terms->orders);
        self::assertSame('4354343', $terms->orders[0]->number);
        self::assertSame('2026-01-26', $terms->orders[0]->date?->format('Y-m-d'));
        self::assertSame('4354343', $invoice->purchaseOrderReference);

        self::assertSame(PaymentMeansCode::C_30, $invoice->payment?->meansCode);
        self::assertSame([], $invoice->payment->creditTransfers);
        self::assertSame(2, $polish->factorBankAccounts[0]->ownBankAccount ?? null);
    }

    public function test_reads_attachment_template(): void
    {
        $result = self::read(PolishSamples::official('invoice-template-fa-3-with-attachment.xml'));
        $polish = self::polish($result->invoice);

        self::assertSame([], $result->ignored);
        self::assertSame('Zalacznik', $polish->attachment?->name);
        $line = $result->invoice->lines[1];
        self::assertSame('1', (string) $line->quantity);
        self::assertSame(UnitCode::C62, $line->unit);
        self::assertTrue($line->price->netPrice->isEqualTo('25.60'));
        self::assertSame('25.60', (string) $line->netAmount);
    }

    public function test_unknown_element_is_reported_not_dropped(): void
    {
        $xml = str_replace('<RodzajFaktury>VAT</RodzajFaktury>', '<RodzajFaktury>VAT</RodzajFaktury><Foo/>', PolishSamples::official('invoice-template-fa-3.xml'));

        self::assertSame(['/fa:Faktura/fa:Fa/fa:Foo'], self::read($xml)->ignored);
    }

    public function test_before_rows_are_negated(): void
    {
        $source = PolishInvoices::correctionBeforeAfter();
        $result = self::read(self::write($source));
        $invoice = $result->invoice;
        $polish = self::polish($invoice);

        self::assertSame([], $result->ignored);
        self::assertSame(InvoiceTypeCode::C_384, $invoice->type);
        self::assertSame(InvoiceKind::Kor, $polish->kind);
        self::assertSame(['-3', '2', '-5', '3'], array_map(static fn (InvoiceLine $l): string => (string) $l->quantity, $invoice->lines));
        self::assertSame(['-270.00', '180.00', '-600.00', '360.00'], array_map(static fn (InvoiceLine $l): string => (string) $l->netAmount, $invoice->lines));
        self::assertSame([true, false, true, false], array_map(static fn (InvoiceLine $l): bool => $l->extensions()->get(PolishLine::class)?->stateBefore === true, $invoice->lines));
        self::assertSame('90.00', (string) $invoice->lines[0]->price->netPrice);
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        self::assertSame('-330.00', (string) $totals->sumOfLineNetAmounts);
        self::assertSame('-405.90', (string) $totals->totalWithVat);
        self::assertSame(self::CORRECTED_KSEF, $polish->correction?->invoices[0]->ksefNumber?->value);
        self::assertSame('Zwrot towaru', $polish->correction->reason);
        self::assertSame('FV/110/07/2026', $invoice->precedingInvoices[0]->number);
        self::assertSame('2026-07-14', $invoice->precedingInvoices[0]->issueDate?->format('Y-m-d'));
    }

    public function test_before_row_amounts_are_negated_and_restored_on_write(): void
    {
        $xml = str_replace(
            '<P_11>270.00</P_11>',
            '<P_10>5.00</P_10><P_11>270.00</P_11><P_11A>332.10</P_11A><P_11Vat>62.10</P_11Vat>',
            self::write(PolishInvoices::correctionBeforeAfter()),
        );
        $xml = (string) preg_replace('~<P_12>23</P_12>~', '<P_12>23</P_12><KwotaAkcyzy>1.50</KwotaAkcyzy>', $xml, 1);
        $result = self::read($xml);
        $line = $result->invoice->lines[0];
        $polish = $line->extensions()->get(PolishLine::class);

        self::assertSame([], $result->ignored);
        self::assertTrue($polish?->stateBefore);
        self::assertSame('-5.00', (string) $line->allowances[0]->amount);
        self::assertSame('-332.10', (string) $polish->grossAmount);
        self::assertSame('-62.10', (string) $polish->vatAmount);
        self::assertSame('-1.50', (string) $polish->excise);
        self::assertSame('90.00', (string) $line->price->netPrice);
        $compact = static fn (string $x): string => (string) preg_replace('~>\s+<~', '><', $x);
        self::assertSame($compact($xml), $compact(self::write($result->invoice)));
    }

    public function test_vat_in_pln_on_a_pln_invoice_is_reported(): void
    {
        $xml = str_replace('<P_14_1>10.03</P_14_1>', '<P_14_1>10.03</P_14_1><P_14_1W>10.03</P_14_1W>', PolishSamples::official('invoice-template-fa-3.xml'));
        $result = self::read($xml);

        self::assertSame(['/fa:Faktura/fa:Fa/fa:P_14_1W (VAT in PLN on a PLN invoice)'], $result->ignored);
        self::assertNull($result->invoice->totals?->totalVatInAccountingCurrency);
        self::assertSame([], self::polish($result->invoice)->vatInPln);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCreationTimes(): array
    {
        return [
            'month 13' => ['2025-13-01T00:00:00Z'],
            'February 30' => ['2025-02-30T00:00:00Z'],
            'hour 24' => ['2025-09-01T24:00:00Z'],
            'no time' => ['2025-09-01'],
        ];
    }

    #[DataProvider('invalidCreationTimes')]
    public function test_invalid_creation_time_is_reported_not_thrown(string $value): void
    {
        $xml = str_replace('2025-09-01T00:00:00Z', $value, PolishSamples::official('invoice-template-fa-3.xml'));
        $result = self::read($xml);

        self::assertSame(["/fa:Faktura/fa:Naglowek/fa:DataWytworzeniaFa (invalid date-time '{$value}')"], $result->ignored);
        self::assertNull(self::polish($result->invoice)->createdAt);
    }

    public function test_zal_without_lines_gets_bucket_lines(): void
    {
        $result = self::read(self::write(PolishInvoices::advanceInEuro()));
        $invoice = $result->invoice;
        $polish = self::polish($invoice);

        self::assertSame([], $result->ignored);
        self::assertSame(InvoiceTypeCode::C_386, $invoice->type);
        self::assertSame(InvoiceKind::Zal, $polish->kind);
        self::assertCount(1, $invoice->lines);
        $line = $invoice->lines[0];
        self::assertTrue($line->extensions()->get(PolishLine::class)?->bucketLine);
        self::assertSame('P_13_1', $line->item->name);
        self::assertSame('1', (string) $line->quantity);
        self::assertSame('3252.03', (string) $line->netAmount);
        self::assertSame(VatCategory::StandardRate, $line->vat->category);
        self::assertSame('23', (string) $line->vat->rate);
        self::assertCount(3, $polish->partialAdvances);
        self::assertSame('10000.00', (string) $polish->order?->value);
    }

    public function test_foreign_currency_sets_bt6_and_bt111(): void
    {
        $invoice = self::read(self::write(PolishInvoices::advanceInEuro()))->invoice;
        $polish = self::polish($invoice);

        self::assertSame(CurrencyCode::EUR, $invoice->currency);
        self::assertSame(CurrencyCode::PLN, $invoice->taxCurrency);
        self::assertSame('3351.17', (string) $invoice->totals?->totalVatInAccountingCurrency);
        self::assertSame('3351.17', (string) $polish->vatInPln[1]);
    }

    public function test_non_fa3_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);

        self::read('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return HostileXml::cases(Fa3Format::ROOT, Fa3Format::NS);
    }

    #[DataProvider('hostile')]
    public function test_doctype_is_refused(string $xml): void
    {
        $this->expectException(UnsafeXml::class);

        self::read($xml);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function missingRequired(): array
    {
        return [
            'P_2' => ['<P_2>FA/GRQMB-1/05/2025</P_2>', 'P_2'],
            'P_1' => ['<P_1>2025-09-01</P_1>', 'P_1'],
            'KodWaluty' => ['<KodWaluty>PLN</KodWaluty>', 'KodWaluty'],
            'seller NIP' => ['<NIP>2222222222</NIP>', 'NIP'],
        ];
    }

    #[DataProvider('missingRequired')]
    public function test_missing_required_value_is_unsupported(string $element, string $name): void
    {
        $xml = str_replace($element, '', PolishSamples::official('invoice-template-fa-3.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage($name);

        self::read($xml);
    }

    /**
     * @return array<string, array{Invoice}>
     */
    public static function factories(): array
    {
        $invoices = PolishInvoices::all() + PolishInvoices::kinds();

        return array_map(static fn (Invoice $invoice): array => [$invoice], $invoices);
    }

    #[DataProvider('factories')]
    public function test_write_read_write_is_stable(Invoice $invoice): void
    {
        $first = self::write($invoice);
        $result = self::read($first);

        self::assertSame([], $result->ignored);
        self::assertSame($first, self::write($result->invoice));
    }

    public function test_fixed_description_keys_are_read_back_to_their_terms(): void
    {
        $invoice = PolishInvoices::domestic();
        $seller = $invoice->seller;
        $buyer = $invoice->buyer;
        $line = new InvoiceLine(
            id: '1',
            quantity: '2',
            unit: UnitCode::H87,
            price: new Price('50.00'),
            item: new Item('Toner', description: 'Czarny', buyersIdentifier: 'NAB-77', originCountry: CountryCode::DE),
            vat: new LineVat(VatCategory::StandardRate, '23'),
            note: 'Uwaga',
            objectIdentifier: new Identifier('OBJ-1'),
            orderLineReference: '4',
            buyerAccountingReference: '402-01',
            allowances: [new LineAllowance('5.00', baseAmount: '100.00', percentage: '5', reason: 'Rabat', reasonCode: AllowanceReasonCode::C_95)],
        );
        $invoice = TotalsCalculator::complete($invoice
            ->withSeller(new Party(name: $seller->name, tradingName: 'Sprzedawca', vatIdentifier: $seller->vatIdentifier, address: $seller->address, contact: new Contact(name: 'Anna Nowak', email: 'a@sprzedawca.example')))
            ->withBuyer(new Party(name: $buyer->name, tradingName: 'Nabywca', vatIdentifier: $buyer->vatIdentifier, address: $buyer->address, contact: new Contact(name: 'Piotr Wiśniewski')))
            ->withLines([$line])
            ->withVatBreakdown([])
            ->withBuyerReference('b10')
            ->withProjectReference('b11')
            ->withSalesOrderReference('b14')
            ->withReceivingAdviceReference('b15')
            ->withTenderOrLotReference('b17')
            ->withBuyerAccountingReference('b19')
            ->withPaymentTerms('b20')
            ->withNotes([new Note('b22a'), new Note('b22b')])
            ->withContractReference('UM/2026/7')
            ->withPurchaseOrderReference('ZAM/991')
            ->withPayment(new PaymentInstructions(PaymentMeansCode::C_30, remittanceInformation: 'b83')));
        $xml = self::write($invoice);
        $result = self::read($xml);
        $read = $result->invoice;

        self::assertSame([], $result->ignored);
        self::assertSame([], self::polish($read)->descriptions);
        self::assertSame(
            ['b10', 'b11', 'b14', 'b15', 'b17', 'b19', 'b20', 'b83', 'UM/2026/7', 'ZAM/991'],
            [$read->buyerReference, $read->projectReference, $read->salesOrderReference, $read->receivingAdviceReference, $read->tenderOrLotReference, $read->buyerAccountingReference, $read->paymentTerms, $read->payment?->remittanceInformation, $read->contractReference, $read->purchaseOrderReference],
        );
        self::assertSame(['b22a', 'b22b'], array_map(static fn (Note $n): string => $n->text, $read->notes));
        self::assertSame(['Sprzedawca', 'Anna Nowak', 'a@sprzedawca.example'], [$read->seller->tradingName, $read->seller->contact?->name, $read->seller->contact?->email]);
        self::assertSame(['Nabywca', 'Piotr Wiśniewski'], [$read->buyer->tradingName, $read->buyer->contact?->name]);
        $readLine = $read->lines[0];
        self::assertSame(['Uwaga', 'Czarny', 'OBJ-1', '4', '402-01', 'NAB-77', CountryCode::DE], [
            $readLine->note, $readLine->item->description, $readLine->objectIdentifier?->value, $readLine->orderLineReference,
            $readLine->buyerAccountingReference, $readLine->item->buyersIdentifier, $readLine->item->originCountry,
        ]);
        self::assertCount(1, $readLine->allowances);
        $allowance = $readLine->allowances[0];
        self::assertSame(['5.00', '100.00', '5', 'Rabat', AllowanceReasonCode::C_95], [(string) $allowance->amount, (string) $allowance->baseAmount, (string) $allowance->percentage, $allowance->reason, $allowance->reasonCode]);
        self::assertNull(self::polish($read)->transactionTerms);
        self::assertSame($xml, self::write($read));
    }

    public function test_p10_allowance_reason_is_not_written_back_as_a_description(): void
    {
        $source = PolishSamples::official('invoice-template-fa-3-with-custom-Subject3.xml');
        $xml = self::write(self::read($source)->invoice);

        self::assertStringNotContainsString('DodatkowyOpis', $xml);
        self::assertStringContainsString('<P_10>100</P_10>', $xml);
    }

    public function test_non_polish_address_line_2_is_read_as_city(): void
    {
        $result = self::read(self::write(PolishInvoices::euBuyer()));
        $address = $result->invoice->buyer->address;

        self::assertSame([], $result->ignored);
        self::assertSame('DE123456789', $result->invoice->buyer->vatIdentifier);
        self::assertSame(CountryCode::DE, $address?->country);
        self::assertSame(['Hauptstraße 5', null, '10115 Berlin', null], [$address->line1, $address->line2, $address->city, $address->postCode]);
        self::assertSame(CountryCode::PL, self::polish($result->invoice)->seller?->vatPrefix);
        self::assertSame('US-123', self::read(self::write(PolishInvoices::exportBuyer()))->invoice->buyer->vatIdentifier);
    }

    public function test_polish_address_line_2_in_another_layout_is_read_as_city(): void
    {
        $source = str_replace('<AdresL2>60-001 Poznań</AdresL2>', '<AdresL2>Poznań</AdresL2>', self::write(PolishInvoices::domestic()));
        $result = self::read($source);
        $address = $result->invoice->buyer->address;

        self::assertSame([], $result->ignored);
        self::assertSame(['ul. Polna 10', null, 'Poznań', null], [$address?->line1, $address?->line2, $address?->city, $address?->postCode]);
        self::assertSame($source, self::write($result->invoice));
    }

    public function test_rows_without_unit_quantity_and_price_are_written_back_without_them(): void
    {
        $result = self::read(PolishSamples::official('invoice-template-fa-3-with-attachment.xml'));
        $line = $result->invoice->lines[0];
        $polish = $line->extensions()->get(PolishLine::class);

        self::assertSame([true, true, true], [$polish?->unitOmitted, $polish?->quantityOmitted, $polish?->unitPriceOmitted]);
        self::assertSame(['1', '18.00', UnitCode::C62], [(string) $line->quantity, (string) $line->price->netPrice, $line->unit]);
        $xml = self::write($result->invoice);
        foreach (['P_8A', 'P_8B', 'P_9A'] as $name) {
            self::assertStringNotContainsString('<'.$name.'>', $xml);
        }

        // The markers are honoured only while the values still equal those read from the absence.
        $rewrite = static fn (InvoiceLine $changed): string => preg_replace('/>\s+</', '><', self::write(TotalsCalculator::complete($result->invoice
            ->withLines([$changed->withNetAmount(null), ...array_slice($result->invoice->lines, 1)])
            ->withVatBreakdown([])))) ?? '';
        self::assertStringContainsString('<P_7>Razem za energię czynną</P_7><P_8B>2</P_8B><P_11>36.00</P_11>', $rewrite($line->withQuantity('2')));
        self::assertStringContainsString(
            '<P_8A>kWh</P_8A><P_8B>2</P_8B><P_9A>18.00</P_9A><P_10>1.00</P_10><P_11>35.00</P_11>',
            $rewrite($line->withQuantity('2')->withAllowance(new LineAllowance('1.00', reason: 'Rabat'))
                ->withExtension(new PolishLine(unitLabel: 'kWh', unitOmitted: true, quantityOmitted: true, unitPriceOmitted: true))),
        );
    }

    public function test_settlement_invoice_amounts(): void
    {
        $invoice = self::read(self::write(PolishInvoices::settlementOfAdvances()))->invoice;

        self::assertSame(InvoiceKind::Roz, self::polish($invoice)->kind);
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        self::assertSame('12300.00', (string) $totals->totalWithVat);
        self::assertSame('3690.00', (string) $totals->amountDue);
        self::assertSame('8610.00', (string) $totals->paidAmount);
        self::assertSame(['FZ/123/07/2025', 'FZ/133/08/2025'], array_map(static fn ($r): ?string => $r->number, self::polish($invoice)->advanceInvoices));
    }

    public function test_rate_codes_that_differ_from_the_derived_code_are_kept(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[2] = PolishInvoices::line('3', 'Usługa', '1', UnitCode::C62, '100.00', VatCategory::ReverseCharge, '0')
            ->withExtension(new PolishLine(rateCode: VatRateCode::EuServicesArticle100));
        $xml = self::write(TotalsCalculator::complete($invoice->withLines(array_values($lines))->withVatBreakdown([])));
        $read = self::read($xml)->invoice;

        self::assertSame(VatRateCode::EuServicesArticle100, $read->lines[2]->extensions()->get(PolishLine::class)?->rateCode);
        self::assertSame(VatCategory::ReverseCharge, $read->lines[2]->vat->category);
        self::assertNull($read->lines[0]->extensions()->get(PolishLine::class)?->rateCode);
        self::assertSame($xml, self::write($read));
    }

    public function test_other_payment_and_polish_only_forms(): void
    {
        $base = PolishSamples::official('invoice-template-fa-3.xml');
        $other = self::read(str_replace('<FormaPlatnosci>6</FormaPlatnosci>', '<PlatnoscInna>1</PlatnoscInna><OpisPlatnosci>Kompensata</OpisPlatnosci>', $base));
        $voucher = self::read(str_replace('<FormaPlatnosci>6</FormaPlatnosci>', '<FormaPlatnosci>3</FormaPlatnosci>', $base));

        self::assertSame([], $other->ignored);
        self::assertSame(PaymentMeansCode::C_1, $other->invoice->payment?->meansCode);
        self::assertSame('Kompensata', $other->invoice->payment->meansText);
        self::assertSame(PaymentMeansCode::C_1, $voucher->invoice->payment?->meansCode);
        self::assertSame(PaymentForm::Voucher, self::polish($voucher->invoice)->paymentForm);
        self::assertStringContainsString('<FormaPlatnosci>3</FormaPlatnosci>', self::write($voucher->invoice));
        self::assertStringContainsString('<OpisPlatnosci>Kompensata</OpisPlatnosci>', self::write($other->invoice));
    }

    public function test_correction_without_ksef_numbers_is_read_as_preceding_invoices(): void
    {
        $invoice = PolishInvoices::correctionBeforeAfter()
            ->withExtension(new PolishInvoice)
            ->withPrecedingInvoices([new PrecedingInvoiceReference('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))]);
        $xml = self::write($invoice);
        $result = self::read($xml);

        self::assertSame([], $result->ignored);
        self::assertNull(self::polish($result->invoice)->correction);
        self::assertSame('FV/110/07/2026', $result->invoice->precedingInvoices[0]->number);
        self::assertSame($xml, self::write($result->invoice));
    }

    public function test_invalid_ksef_number_is_reported_with_its_entry(): void
    {
        $xml = str_replace(self::CORRECTED_KSEF, '9999999999-20260714-D5FB0C9ED490-9A', self::write(PolishInvoices::correctionBeforeAfter()));
        $result = self::read($xml);

        // Without a readable corrected invoice the rest of the correction block is not mapped either.
        self::assertCount(3, $result->ignored);
        self::assertStringStartsWith('/fa:Faktura/fa:Fa/fa:DaneFaKorygowanej (entry left out: Invalid KSeF number', $result->ignored[0]);
        self::assertSame(['/fa:Faktura/fa:Fa/fa:PrzyczynaKorekty', '/fa:Faktura/fa:Fa/fa:TypKorekty'], array_slice($result->ignored, 1));
        self::assertNull(self::polish($result->invoice)->correction);
        self::assertSame([], $result->invoice->precedingInvoices);
    }

    public function test_settlement_sum_that_differs_from_its_entries_is_reported(): void
    {
        $xml = str_replace('<SumaOdliczen>1000</SumaOdliczen>', '<SumaOdliczen>999</SumaOdliczen>', PolishSamples::official('invoice-template-fa-3-with-custom-Subject3.xml'));

        self::assertSame(
            ['/fa:Faktura/fa:Fa/fa:Rozliczenie/fa:SumaOdliczen (sum 999 differs from the total of its entries 1000, which is used)'],
            self::read($xml)->ignored,
        );
    }

    public function test_descriptions_their_term_cannot_take_stay_descriptions(): void
    {
        $invoice = PolishInvoices::domestic();
        $key = Fa3Format::DESCRIPTION_KEYS;
        $invoice = $invoice->withExtension(new PolishInvoice(descriptions: [
            new KeyValue(null, $key['BT-10'], 'first'),
            new KeyValue(null, $key['BT-10'], 'second'),
            new KeyValue(1, $key['BT-159'], 'XX'),
            new KeyValue(9, $key['BT-127'], 'no such line'),
            new KeyValue(1, $key['BT-139'], 'no P_10 on the line'),
            new KeyValue(1, $key['BT-10'], 'header term on a line'),
        ]))->withPayment(null);
        $xml = self::write($invoice);
        $xml = (string) preg_replace('~<FaWiersz>~', '<DodatkowyOpis><Klucz>'.$key['BT-83'].'</Klucz><Wartosc>no payment</Wartosc></DodatkowyOpis><FaWiersz>', $xml, 1);
        $read = self::read($xml)->invoice;

        self::assertSame('first', $read->buyerReference);
        self::assertNull($read->lines[0]->item->originCountry);
        self::assertNull($read->lines[0]->note);
        self::assertSame([
            '|'.$key['BT-10'].'|second',
            '1|'.$key['BT-159'].'|XX',
            '9|'.$key['BT-127'].'|no such line',
            '1|'.$key['BT-139'].'|no P_10 on the line',
            '1|'.$key['BT-10'].'|header term on a line',
            '|'.$key['BT-83'].'|no payment',
        ], array_map(static fn (KeyValue $kv): string => $kv->lineNumber.'|'.$kv->key.'|'.$kv->value, self::polish($read)->descriptions));
    }

    public function test_several_contacts_are_kept_in_the_polish_party(): void
    {
        $xml = str_replace(
            '<DaneKontaktowe>
			<Email>jan@kowalski.pl</Email>',
            '<DaneKontaktowe><Telefon>111222333</Telefon></DaneKontaktowe><DaneKontaktowe>
			<Email>jan@kowalski.pl</Email>',
            PolishSamples::official('invoice-template-fa-3.xml'),
        );
        $read = self::read($xml)->invoice;

        self::assertSame('111222333', $read->buyer->contact?->phone);
        self::assertNull($read->buyer->contact->email);
        self::assertCount(2, self::polish($read)->buyer->contacts ?? []);
    }

    private const CORRECTED_KSEF = PolishInvoices::CORRECTED_KSEF_NUMBER;

    private static function read(string $xml): ReadResult
    {
        return (new Fa3Reader)->read($xml);
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }

    private static function polish(Invoice $invoice): PolishInvoice
    {
        $polish = $invoice->extensions()->get(PolishInvoice::class);
        self::assertNotNull($polish);

        return $polish;
    }
}

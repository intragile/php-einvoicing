<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\Gtu;
use IntrAgile\EInvoicing\Poland\KeyValue;
use IntrAgile\EInvoicing\Poland\MarginScheme;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\Procedure;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use IntrAgile\EInvoicing\Validation\SchemaError;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Fa3WriterTest extends TestCase
{
    use CapturesThrowable;

    private const CLOCK = '2026-11-18T09:30:47+01:00';

    /**
     * @return array<string, array{Invoice}>
     */
    public static function factories(): array
    {
        return array_map(static fn (Invoice $invoice): array => [$invoice], PolishInvoices::all());
    }

    #[DataProvider('factories')]
    public function test_every_factory_is_schema_valid(Invoice $invoice): void
    {
        self::assertSchemaValid(self::write($invoice));
    }

    public function test_invoice_without_polish_extension_is_written_with_defaults(): void
    {
        $xml = self::write(PolishInvoices::domestic()->withExtensions(Extensions::none()));

        self::assertSchemaValid($xml);
        self::assertSame(['VAT'], self::values($xml, '/fa:Faktura/fa:Fa/fa:RodzajFaktury'));
    }

    public function test_header_is_fixed_and_dated(): void
    {
        $xml = self::write(PolishInvoices::domestic());
        $code = self::nodes($xml, '/fa:Faktura/fa:Naglowek/fa:KodFormularza')[0] ?? null;
        self::assertInstanceOf(DOMElement::class, $code);
        self::assertSame('FA', $code->textContent);
        self::assertSame('FA (3)', $code->getAttribute('kodSystemowy'));
        self::assertSame('1-0E', $code->getAttribute('wersjaSchemy'));
        self::assertSame(['3'], self::values($xml, '/fa:Faktura/fa:Naglowek/fa:WariantFormularza'));
        self::assertSame(['2026-11-18T08:30:47Z'], self::values($xml, '/fa:Faktura/fa:Naglowek/fa:DataWytworzeniaFa'));
        self::assertSame([], self::values($xml, '/fa:Faktura/fa:Naglowek/fa:SystemInfo'));
        self::assertSame(
            ['Naglowek', 'Podmiot1', 'Podmiot2', 'Fa'],
            self::childNames($xml, '/fa:Faktura'),
        );
    }

    public function test_created_at_and_system_info_come_from_the_extension(): void
    {
        $invoice = PolishInvoices::domestic()->withExtension(new PolishInvoice(
            createdAt: new DateTimeImmutable('2026-11-17T23:15:00-02:00'),
            systemInfo: 'Fakturownia testowa',
        ));
        $xml = self::write($invoice);

        self::assertSame(['2026-11-18T01:15:00Z'], self::values($xml, '/fa:Faktura/fa:Naglowek/fa:DataWytworzeniaFa'));
        self::assertSame(['Fakturownia testowa'], self::values($xml, '/fa:Faktura/fa:Naglowek/fa:SystemInfo'));
    }

    public function test_seller_nip_from_vat_identifier(): void
    {
        $xml = self::write(PolishInvoices::domestic());

        self::assertSame(['2222222222'], self::values($xml, '/fa:Faktura/fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP'));
        self::assertSame(['Sprzedawca Sp. z o.o.'], self::values($xml, '/fa:Faktura/fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:Nazwa'));
        self::assertSame(['PL', 'ul. Kwiatowa 1 m. 2', '00-001 Warszawa'], self::values($xml, '/fa:Faktura/fa:Podmiot1/fa:Adres/*'));
        self::assertSame(['faktury@sprzedawca.example', '221234567'], self::values($xml, '/fa:Faktura/fa:Podmiot1/fa:DaneKontaktowe/*'));
    }

    public function test_buyer_identification_variants(): void
    {
        $identification = '/fa:Faktura/fa:Podmiot2/fa:DaneIdentyfikacyjne';

        self::assertSame(['NIP' => ['1111111111']], self::fields(self::write(PolishInvoices::domestic()), $identification, ['NIP', 'KodUE', 'NrVatUE', 'KodKraju', 'NrID', 'BrakID']));
        self::assertSame(['KodUE' => ['DE'], 'NrVatUE' => ['123456789']], self::fields(self::write(PolishInvoices::euBuyer()), $identification, ['NIP', 'KodUE', 'NrVatUE', 'KodKraju', 'NrID', 'BrakID']));
        self::assertSame(['KodKraju' => ['US'], 'NrID' => ['US-123']], self::fields(self::write(PolishInvoices::exportBuyer()), $identification, ['NIP', 'KodUE', 'NrVatUE', 'KodKraju', 'NrID', 'BrakID']));
        self::assertSame(['BrakID' => ['1']], self::fields(self::write(PolishInvoices::consumer()), $identification, ['NIP', 'KodUE', 'NrVatUE', 'KodKraju', 'NrID', 'BrakID']));
    }

    public function test_buckets_sum_rows_by_rate_code(): void
    {
        $invoice = PolishInvoices::domestic();
        $xml = self::write($invoice);

        $row = static function (string $rate) use ($invoice): array {
            foreach ($invoice->vatBreakdown as $entry) {
                if ($entry->rate?->isEqualTo($rate)) {
                    return [(string) $entry->taxableAmount, (string) $entry->taxAmount];
                }
            }
            self::fail('No VAT breakdown row for '.$rate);
        };
        [$net23, $vat23] = $row('23');
        [$net8, $vat8] = $row('8');
        self::assertNotNull($invoice->totals);

        self::assertSame([$net23], self::values($xml, '//fa:Fa/fa:P_13_1'));
        self::assertSame([$vat23], self::values($xml, '//fa:Fa/fa:P_14_1'));
        self::assertSame([$net8], self::values($xml, '//fa:Fa/fa:P_13_2'));
        self::assertSame([$vat8], self::values($xml, '//fa:Fa/fa:P_14_2'));
        self::assertSame([(string) $invoice->totals->totalWithVat], self::values($xml, '//fa:Fa/fa:P_15'));
        self::assertSame(['1550.00', '356.50', '91.80', '7.34', '2005.64'], self::values($xml, '//fa:Fa/*[starts-with(local-name(), "P_13_") or starts-with(local-name(), "P_14_") or local-name() = "P_15"]'));
    }

    public function test_zero_and_exempt_rates_feed_their_buckets(): void
    {
        self::assertSame(['800.00'], self::values(self::write(PolishInvoices::euBuyer()), '//fa:Fa/fa:P_13_6_2'));
        self::assertSame(['25000.00'], self::values(self::write(PolishInvoices::exportBuyer()), '//fa:Fa/fa:P_13_6_3'));
        self::assertSame(['1200.00'], self::values(self::write(PolishInvoices::exempt()), '//fa:Fa/fa:P_13_7'));
        self::assertSame(['1200.00'], self::values(self::write(PolishInvoices::domesticReverseCharge()), '//fa:Fa/fa:P_13_10'));
        self::assertSame(['0 WDT'], self::values(self::write(PolishInvoices::euBuyer()), '//fa:FaWiersz/fa:P_12'));
        self::assertSame(['oo'], self::values(self::write(PolishInvoices::domesticReverseCharge()), '//fa:FaWiersz/fa:P_12'));
    }

    public function test_annotations_default_to_negative_markers(): void
    {
        $leaves = '//fa:Fa/fa:Adnotacje//*[not(*)]';
        $expected = ['P_16=2', 'P_17=2', 'P_18=2', 'P_18A=2', 'P_19N=1', 'P_22N=1', 'P_23=2', 'P_PMarzyN=1'];

        self::assertSame($expected, self::leaves(self::write(PolishInvoices::domestic()), $leaves));
        self::assertSame($expected, self::leaves(PolishSamples::official('invoice-template-fa-3.xml'), $leaves));
    }

    public function test_positive_annotations(): void
    {
        $leaves = '//fa:Fa/fa:Adnotacje//*[not(*)]';

        self::assertSame(
            ['P_16=2', 'P_17=2', 'P_18=1', 'P_18A=2', 'P_19N=1', 'P_22N=1', 'P_23=2', 'P_PMarzyN=1'],
            self::leaves(self::write(PolishInvoices::domesticReverseCharge()), $leaves),
        );
        self::assertSame(
            ['P_16=2', 'P_17=2', 'P_18=2', 'P_18A=2', 'P_19=1', 'P_19A=Art. 43 ust. 1 pkt 37 ustawy', 'P_22N=1', 'P_23=2', 'P_PMarzyN=1'],
            self::leaves(self::write(PolishInvoices::exempt()), $leaves),
        );
    }

    public function test_new_transport_fragment_and_margin_scheme_annotations(): void
    {
        $fragment = new RawFragment('<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<NoweSrodkiTransportu xmlns="'.Fa3Format::NS.'"><P_22>1</P_22><P_42_5>2</P_42_5>'
            .'<NowySrodekTransportu><P_22A>2026-11-01</P_22A><P_NrWierszaNST>1</P_NrWierszaNST><P_22B>1500</P_22B></NowySrodekTransportu>'
            .'</NoweSrodkiTransportu>');
        $invoice = PolishInvoices::domestic()->withExtension(new PolishInvoice(annotations: new Annotations(
            newTransport: $fragment,
            margin: MarginScheme::UsedGoods,
        )));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertStringNotContainsString('<?xml version="1.0" encoding="UTF-8"?>'."\n".'<NoweSrodkiTransportu', $xml);
        self::assertSame(
            ['P_16=2', 'P_17=2', 'P_18=2', 'P_18A=2', 'P_19N=1', 'P_22=1', 'P_42_5=2', 'P_22A=2026-11-01', 'P_NrWierszaNST=1', 'P_22B=1500', 'P_23=2', 'P_PMarzy=1', 'P_PMarzy_3_1=1'],
            self::leaves($xml, '//fa:Fa/fa:Adnotacje//*[not(*)]'),
        );
    }

    public function test_line_mapping(): void
    {
        $line = (new InvoiceLine(
            id: 'A-1',
            quantity: '3.5',
            unit: UnitCode::KGM,
            price: new Price('10.12345678'),
            item: new Item(
                name: 'Kawa ziarnista',
                sellersIdentifier: 'KAWA-01',
                standardIdentifier: new Identifier('05901234123457', IdentifierScheme::C_0160),
            ),
            vat: new LineVat(VatCategory::StandardRate, '23'),
            allowances: [new LineAllowance('2.00'), new LineAllowance('0.50')],
        ))->withExtension(new PolishLine(
            unitLabel: 'kg',
            supplyDate: new DateTimeImmutable('2026-11-10'),
            grossUnitPrice: '12.45185184',
            grossAmount: '40.50',
            vatAmount: '7.57',
            ossRate: '23',
            annex15: true,
            excise: '1.00',
            gtu: Gtu::Gtu12,
            procedure: Procedure::TT_D,
            exchangeRate: '1',
            pkwiu: '10.83.11.0',
            cn: '0901 21 00',
            pkob: '1274',
        ));
        $invoice = TotalsCalculator::complete(PolishInvoices::domestic()->withLines([$line])->withVatBreakdown([]));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame([
            'NrWierszaFa=1',
            'UU_ID=A-1',
            'P_6A=2026-11-10',
            'P_7=Kawa ziarnista',
            'Indeks=KAWA-01',
            'GTIN=05901234123457',
            'PKWiU=10.83.11.0',
            'CN=0901 21 00',
            'PKOB=1274',
            'P_8A=kg',
            'P_8B=3.5',
            'P_9A=10.12345678',
            'P_9B=12.45185184',
            'P_10=2.50',
            'P_11=32.93',
            'P_11A=40.50',
            'P_11Vat=7.57',
            'P_12=23',
            'P_12_XII=23',
            'P_12_Zal_15=1',
            'KwotaAkcyzy=1.00',
            'GTU=GTU_12',
            'Procedura=TT_D',
            'KursWaluty=1',
        ], self::leaves($xml, '//fa:FaWiersz/*'));
    }

    public function test_line_defaults(): void
    {
        $xml = self::write(PolishInvoices::domestic());

        self::assertSame(['1', '2', '3'], self::values($xml, '//fa:FaWiersz/fa:NrWierszaFa'));
        self::assertSame([], self::values($xml, '//fa:FaWiersz/fa:UU_ID'));
        self::assertSame(['HUR', 'H87', 'H87'], self::values($xml, '//fa:FaWiersz/fa:P_8A'));
        self::assertSame(['NrWierszaFa=1', 'P_7=Usługa wdrożeniowa', 'P_8A=HUR', 'P_8B=10', 'P_9A=150.00', 'P_11=1500.00', 'P_12=23'], self::leaves($xml, '//fa:FaWiersz[1]/*'));
    }

    public function test_unmapped_text_terms_go_to_dodatkowy_opis_with_fixed_keys(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[1] = $lines[1]->withNote('Kolor czarny');
        $invoice = $invoice
            ->withLines(array_values($lines))
            ->withBuyerReference('REF-7')
            ->withNote(new Note('Dziękujemy za zakupy'))
            ->withExtension(new PolishInvoice(descriptions: [new KeyValue(null, 'Informacja', 'Tekst własny')]));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame([
            '|Informacja|Tekst własny',
            '|'.Fa3Format::DESCRIPTION_KEYS['BT-10'].'|REF-7',
            '|'.Fa3Format::DESCRIPTION_KEYS['BT-22'].'|Dziękujemy za zakupy',
            '2|'.Fa3Format::DESCRIPTION_KEYS['BT-127'].'|Kolor czarny',
        ], self::descriptions($xml));
        self::assertSame('Referencja nabywcy (BT-10)', Fa3Format::DESCRIPTION_KEYS['BT-10']);
        self::assertSame('Uwagi do pozycji (BT-127)', Fa3Format::DESCRIPTION_KEYS['BT-127']);
    }

    public function test_every_fixed_description_key(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = new InvoiceLine(
            id: '1',
            quantity: '1',
            unit: UnitCode::H87,
            price: new Price('10.00'),
            item: new Item('Towar', description: 'Opis towaru'),
            vat: new LineVat(VatCategory::StandardRate, '23'),
            note: 'Uwaga do pozycji',
        );
        $invoice = TotalsCalculator::complete($invoice
            ->withLines($lines)
            ->withVatBreakdown([])
            ->withBuyerReference('b10')
            ->withProjectReference('b11')
            ->withSalesOrderReference('b14')
            ->withReceivingAdviceReference('b15')
            ->withTenderOrLotReference('b17')
            ->withBuyerAccountingReference('b19')
            ->withPaymentTerms('b20')
            ->withNotes([new Note('b22a'), new Note('b22b')])
            ->withPayment(new PaymentInstructions(PaymentMeansCode::C_30, remittanceInformation: 'b83')));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame([
            '|Referencja nabywcy (BT-10)|b10',
            '|Projekt (BT-11)|b11',
            '|Zamówienie sprzedaży (BT-14)|b14',
            '|Przyjęcie dostawy (BT-15)|b15',
            '|Przetarg lub partia (BT-17)|b17',
            '|Konto księgowe nabywcy (BT-19)|b19',
            '|Warunki płatności (BT-20)|b20',
            '|Uwagi (BT-22)|b22a',
            '|Uwagi (BT-22)|b22b',
            '|Tytuł płatności (BT-83)|b83',
            '1|Uwagi do pozycji (BT-127)|Uwaga do pozycji',
            '1|Opis (BT-154)|Opis towaru',
        ], self::descriptions($xml));
    }

    public function test_contract_and_order_references_go_to_warunki_transakcji(): void
    {
        $xml = self::write(PolishInvoices::domestic()->withContractReference('UM/2026/7')->withPurchaseOrderReference('ZAM/991'));

        self::assertSchemaValid($xml);
        self::assertSame(['NrUmowy=UM/2026/7', 'NrZamowienia=ZAM/991'], self::leaves($xml, '//fa:Fa/fa:WarunkiTransakcji//*[not(*)]'));
        self::assertSame('WarunkiTransakcji', array_slice(self::childNames($xml, '/fa:Faktura/fa:Fa'), -1)[0]);
        self::assertSame([], self::descriptions($xml));
    }

    public function test_party_and_line_text_terms_go_to_dodatkowy_opis(): void
    {
        $invoice = PolishInvoices::domestic();
        $seller = $invoice->seller;
        $buyer = $invoice->buyer;
        $line = new InvoiceLine(
            id: '1',
            quantity: '2',
            unit: UnitCode::H87,
            price: new Price('50.00'),
            item: new Item('Toner', buyersIdentifier: 'NAB-77', originCountry: CountryCode::DE),
            vat: new LineVat(VatCategory::StandardRate, '23'),
            objectIdentifier: new Identifier('OBJ-1'),
            orderLineReference: '4',
            buyerAccountingReference: '402-01',
            allowances: [new LineAllowance('5.00', reason: 'Rabat ilościowy'), new LineAllowance('1.00', reason: 'Promocja')],
        );
        $invoice = TotalsCalculator::complete($invoice
            ->withSeller(new Party(name: $seller->name, tradingName: 'Sprzedawca', vatIdentifier: $seller->vatIdentifier, address: $seller->address, contact: new Contact(name: 'Anna Nowak', email: 'a@sprzedawca.example')))
            ->withBuyer(new Party(name: $buyer->name, tradingName: 'Nabywca', vatIdentifier: $buyer->vatIdentifier, address: $buyer->address, contact: new Contact(name: 'Piotr Wiśniewski')))
            ->withLines([$line])
            ->withVatBreakdown([]));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame([
            '|Nazwa handlowa sprzedawcy (BT-28)|Sprzedawca',
            '|Osoba kontaktowa sprzedawcy (BT-41)|Anna Nowak',
            '|Nazwa handlowa nabywcy (BT-45)|Nabywca',
            '|Osoba kontaktowa nabywcy (BT-56)|Piotr Wiśniewski',
            '1|Identyfikator obiektu (BT-128)|OBJ-1',
            '1|Pozycja zamówienia (BT-132)|4',
            '1|Konto księgowe pozycji (BT-133)|402-01',
            '1|Indeks nabywcy (BT-156)|NAB-77',
            '1|Kraj pochodzenia (BT-159)|DE',
            '1|Powód rabatu (BT-139)|Rabat ilościowy',
            '1|Powód rabatu (BT-139)|Promocja',
        ], self::descriptions($xml));
        self::assertSame([], self::values($xml, '//fa:DaneKontaktowe[not(fa:Email)]/fa:Telefon'));
    }

    public function test_line_allowance_details_go_to_dodatkowy_opis(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[1] = $lines[1]->withAllowance(new LineAllowance('2.50', baseAmount: '50.00', percentage: '5', reason: 'Rabat', reasonCode: AllowanceReasonCode::C_95))
            ->withAllowance(new LineAllowance('1.00', percentage: '2'));
        $xml = self::write(TotalsCalculator::complete($invoice->withLines(array_values($lines))->withVatBreakdown([])));

        self::assertSchemaValid($xml);
        self::assertSame([
            '2|Powód rabatu (BT-139)|Rabat',
            '2|Podstawa rabatu (BT-137)|50.00',
            '2|Procent rabatu (BT-138)|5',
            '2|Kod powodu rabatu (BT-140)|95',
            '2|Procent rabatu (BT-138)|2',
        ], self::descriptions($xml));
        self::assertSame(['3.50'], self::values($xml, '//fa:FaWiersz[2]/fa:P_10'));
    }

    public function test_exemption_reason_is_superseded_by_the_exemption_annotation(): void
    {
        $invoice = PolishInvoices::exempt();
        $row = $invoice->vatBreakdown[0];
        $invoice = $invoice->withVatBreakdown([new VatBreakdown($row->taxableAmount, $row->taxAmount, $row->category, $row->rate, 'Zwolnienie z VAT', VatExemptionReason::VATEX_EU_132)]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertStringNotContainsString('Zwolnienie z VAT', $xml);
        self::assertSame(['Art. 43 ust. 1 pkt 37 ustawy'], self::values($xml, '//fa:Zwolnienie/fa:P_19A'));

        $exception = self::capture(static fn () => self::write($invoice->withExtension(new PolishInvoice)));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('BT-120 (E 0): ', $exception->getMessage());
        self::assertStringContainsString('BT-121 (E 0): ', $exception->getMessage());
        self::assertStringContainsString('Annotations::$exemption', $exception->getMessage());
    }

    public function test_supply_date_or_invoicing_period(): void
    {
        $base = PolishInvoices::domestic();

        $delivered = self::write($base->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable('2026-11-15'))));
        self::assertSame(['2026-11-15'], self::values($delivered, '//fa:Fa/fa:P_6'));

        $taxPoint = self::write($base->withTaxPointDate(new DateTimeImmutable('2026-11-14')));
        self::assertSame(['2026-11-14'], self::values($taxPoint, '//fa:Fa/fa:P_6'));

        $sameDay = self::write($base->withTaxPointDate(new DateTimeImmutable('2026-11-18')));
        self::assertSame([], self::values($sameDay, '//fa:Fa/fa:P_6'));

        $period = self::write($base->withDelivery(new Delivery(invoicingPeriod: new Period(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')))));
        self::assertSchemaValid($period);
        self::assertSame(['2026-10-01', '2026-10-31'], self::values($period, '//fa:Fa/fa:OkresFa/*'));
    }

    public function test_payment_maps_due_date_form_and_account(): void
    {
        $xml = self::write(PolishInvoices::domestic());

        self::assertSame(
            ['Termin=2026-12-02', 'FormaPlatnosci=6', 'NrRB='.PolishInvoices::ACCOUNT],
            self::leaves($xml, '//fa:Fa/fa:Platnosc//*[not(*)]'),
        );
    }

    public function test_payment_without_form_is_written_as_other(): void
    {
        $invoice = PolishInvoices::domestic()->withPayment(new PaymentInstructions(PaymentMeansCode::C_97, meansText: 'Kompensata'));
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['Termin=2026-12-02', 'PlatnoscInna=1', 'OpisPlatnosci=Kompensata'],
            self::leaves($xml, '//fa:Fa/fa:Platnosc//*[not(*)]'),
        );
    }

    public function test_output_is_deterministic(): void
    {
        foreach (PolishInvoices::all() as $name => $invoice) {
            self::assertSame(self::write($invoice), self::write($invoice), $name);
        }
    }

    public function test_jst_buyer_writes_role_eight(): void
    {
        $xml = self::write(PolishInvoices::jstBuyer());

        self::assertSame(['1'], self::values($xml, '/fa:Faktura/fa:Podmiot2/fa:JST'));
        self::assertSame(['2'], self::values($xml, '/fa:Faktura/fa:Podmiot2/fa:GV'));
        self::assertSame([PolishInvoices::JST_UNIT_NIP], self::values($xml, '/fa:Faktura/fa:Podmiot3/fa:DaneIdentyfikacyjne/fa:NIP'));
        self::assertSame(['8'], self::values($xml, '/fa:Faktura/fa:Podmiot3/fa:Rola'));
        self::assertSame(['Naglowek', 'Podmiot1', 'Podmiot2', 'Podmiot3', 'Fa'], self::childNames($xml, '/fa:Faktura'));
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }

    private static function assertSchemaValid(string $xml): void
    {
        $report = Fa3Validator::create()->validate($xml);
        self::assertSame([], array_map(static fn (SchemaError $e): string => $e->message, $report->schemaErrors), $xml);
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('fa', Fa3Format::NS);

        return $xpath;
    }

    /**
     * @return list<DOMElement>
     */
    private static function nodes(string $xml, string $path): array
    {
        $nodes = [];
        foreach (self::xpath($xml)->query($path) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private static function values(string $xml, string $path): array
    {
        $values = [];
        foreach (self::nodes($xml, $path) as $node) {
            $values[] = trim($node->textContent);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function leaves(string $xml, string $path): array
    {
        $values = [];
        foreach (self::nodes($xml, $path) as $node) {
            $values[] = $node->localName.'='.trim($node->textContent);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function childNames(string $xml, string $path): array
    {
        $names = [];
        foreach (self::nodes($xml, $path.'/*') as $node) {
            $names[] = (string) $node->localName;
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    private static function fields(string $xml, string $parent, array $names): array
    {
        $fields = [];
        foreach ($names as $name) {
            $values = self::values($xml, $parent.'/fa:'.$name);
            if ($values !== []) {
                $fields[$name] = $values;
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private static function descriptions(string $xml): array
    {
        $pairs = [];
        foreach (self::nodes($xml, '//fa:Fa/fa:DodatkowyOpis') as $node) {
            $fields = ['NrWiersza' => '', 'Klucz' => '', 'Wartosc' => ''];
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && isset($fields[$child->localName])) {
                    $fields[$child->localName] = trim($child->textContent);
                }
            }
            $pairs[] = implode('|', $fields);
        }

        return $pairs;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\ItemAttribute;
use IntrAgile\EInvoicing\Model\ItemClassification;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\TaxRegistration;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\ContactDetails;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\TransactionTerms;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Guard;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Fa3GuardTest extends TestCase
{
    use CapturesThrowable;

    public function test_factories_pass_the_guard(): void
    {
        foreach ([...PolishInvoices::all(), ...PolishInvoices::kinds()] as $invoice) {
            Fa3Guard::check($invoice);
        }
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function nonPolishSellerIds(): array
    {
        return ['foreign VAT id' => ['DE123456789'], 'malformed NIP' => ['PL12345'], 'none' => [null]];
    }

    #[DataProvider('nonPolishSellerIds')]
    public function test_seller_without_polish_nip_is_refused(?string $vatIdentifier): void
    {
        $invoice = PolishInvoices::domestic();
        $seller = $invoice->seller;
        $invoice = $invoice->withSeller(new Party(name: $seller->name, vatIdentifier: $vatIdentifier, address: $seller->address));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-31: ', $message);
        self::assertStringContainsString('NIP', $message);
    }

    public function test_unmappable_rate_names_the_line(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[2] = PolishInvoices::line('3', 'Lek', '1', UnitCode::H87, '10.00', VatCategory::StandardRate, '4');
        $invoice = TotalsCalculator::complete($invoice->withLines(array_values($lines))->withVatBreakdown([]));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-151 (line "3"): ', $message);
        self::assertStringContainsString('P_13', $message);
    }

    public function test_bucketless_rate_code_override_names_the_line(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withExtension(new PolishLine(rateCode: VatRateCode::Rate4));

        self::assertStringContainsString('P_12 (line "1"): ', self::refusal($invoice->withLines($lines)));
    }

    public function test_rows_whose_lines_map_to_different_codes_are_refused(): void
    {
        $invoice = PolishInvoices::domesticReverseCharge();
        $second = PolishInvoices::line('2', 'Usługa budowlana', '1', UnitCode::C62, '100.00', VatCategory::ReverseCharge, '0')
            ->withExtension(new PolishLine(rateCode: VatRateCode::EuServicesArticle100));
        $invoice = TotalsCalculator::complete($invoice->withLine($second)->withVatBreakdown([]));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BG-23 (AE 0): ', $message);
        self::assertStringContainsString('"oo"', $message);
        self::assertStringContainsString('"np II"', $message);
    }

    public function test_guard_names_every_refusal(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withPrice(new Price('1.123456789'));
        $seller = $invoice->seller;
        $invoice = $invoice
            ->withLines($lines)
            ->withPayee(new Party(name: 'Faktor S.A.'))
            ->withAllowance(new DocumentAllowance('10.00', VatCategory::StandardRate, '23', reason: 'Rabat'))
            ->withSeller(new Party(
                name: $seller->name,
                vatIdentifier: $seller->vatIdentifier,
                electronicAddress: new ElectronicAddress('faktury@sprzedawca.example', ElectronicAddressScheme::EM),
                address: $seller->address,
            ));

        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        $message = $exception->getMessage();
        foreach (['BG-10: ', 'BG-20: ', 'BT-34: ', 'BT-146 (line "1"): '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_every_listed_term_is_refused(): void
    {
        $invoice = PolishInvoices::domestic();
        $buyer = $invoice->buyer;
        $invoice = $invoice
            ->withTaxRepresentative(new Party(name: 'Przedstawiciel'))
            ->withTaxCurrency(CurrencyCode::EUR)
            ->withTaxPointDateCode('3')
            ->withDelivery(new Delivery(partyName: 'Magazyn', address: new PostalAddress(CountryCode::PL, line1: 'ul. Składowa 1')))
            ->withBuyer(new Party(
                name: $buyer->name,
                vatIdentifier: $buyer->vatIdentifier,
                electronicAddress: new ElectronicAddress('zakupy@nabywca.example', ElectronicAddressScheme::EM),
                address: $buyer->address,
            ));
        $message = self::refusal($invoice);

        foreach (['BG-11: ', 'BT-6: ', 'BT-8: ', 'BT-70: ', 'BG-15: ', 'BT-49: '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_terms_without_an_fa3_home_are_refused_together(): void
    {
        $invoice = PolishInvoices::domestic();
        $seller = $invoice->seller;
        $buyer = $invoice->buyer;
        $lines = $invoice->lines;
        $lines[0] = new InvoiceLine(
            id: '1',
            quantity: '1',
            unit: UnitCode::H87,
            price: new Price('100.00', discount: '10.00', grossPrice: '110.00'),
            item: new Item(
                'Drukarka',
                classifications: [new ItemClassification('84433210', 'HS')],
                attributes: [new ItemAttribute('Kolor', 'czarny')],
            ),
            vat: new LineVat(VatCategory::StandardRate, '23'),
            netAmount: '100.00',
            period: new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-30')),
        );
        $invoice = $invoice
            ->withLines($lines)
            ->withNote(new Note('Uwaga', TextSubjectCode::AAI))
            ->withPrecedingInvoice(new PrecedingInvoiceReference('FV/2026/10/001'))
            ->withSeller(new Party(name: $seller->name, vatIdentifier: $seller->vatIdentifier, address: new PostalAddress(CountryCode::PL, line1: 'ul. Kwiatowa 1', subdivision: 'mazowieckie')))
            ->withBuyer(new Party(name: $buyer->name, vatIdentifier: $buyer->vatIdentifier, address: new PostalAddress(CountryCode::PL, line1: 'ul. Polna 10', subdivision: 'wielkopolskie')));

        $message = self::refusal($invoice);
        foreach (['BT-21 (note 1): ', 'BT-39: ', 'BT-54: ', 'BG-3: ', 'BG-26 (line "1"): ', 'BT-147 (line "1"): ', 'BT-148 (line "1"): ', 'BT-158 (line "1"): ', 'BG-32 (line "1"): '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_reference_conflicting_with_transaction_terms_is_refused(): void
    {
        $invoice = PolishInvoices::domestic()
            ->withContractReference('UM/1')
            ->withExtension(new PolishInvoice(transactionTerms: new TransactionTerms(contracts: [new DocumentReference(number: 'UM/2')])));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-12: "UM/1"', $message);
        self::assertStringContainsString('"UM/2"', $message);
    }

    public function test_schemes_and_remaining_unmapped_values_are_refused(): void
    {
        $invoice = PolishInvoices::exportBuyer();
        $buyer = $invoice->buyer;
        $lines = $invoice->lines;
        $line = $lines[0];
        $lines[0] = new InvoiceLine(
            id: $line->id,
            quantity: $line->quantity,
            unit: $line->unit,
            price: new Price($line->price->netPrice, baseQuantityUnit: UnitCode::KGM),
            item: $line->item,
            vat: $line->vat,
            netAmount: $line->netAmount,
            objectIdentifier: new Identifier('OBJ-1', 'AAA'),
        );
        $thirdParty = new ThirdParty(
            party: new Party(name: 'Płatnik', tradingName: 'Płatnik Handel', vatIdentifier: 'PL'.PolishInvoices::JST_UNIT_NIP, contact: new Contact(name: 'Ewa Zielińska')),
            role: ThirdPartyRole::Payer,
        );
        $invoice = $invoice
            ->withLines($lines)
            ->withBuyer(new Party(
                name: $buyer->name,
                legalRegistration: new Identifier('US-123', '0060'),
                taxRegistration: new TaxRegistration('TAX-1'),
                address: $buyer->address,
            ))
            ->withPayment(new PaymentInstructions(PaymentMeansCode::C_30, meansText: 'Przelew'))
            ->withExtension(new PolishInvoice(thirdParties: [$thirdParty]));

        $message = self::refusal($invoice);
        foreach (['BT-47-1: ', 'BT-128-1 (line "1"): ', 'BT-150 (line "1"): ', 'BT-82: ', 'Podmiot2 tax registration: ', 'Podmiot3[1] trading name: ', 'Podmiot3[1] contact name: '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_missing_totals_are_refused(): void
    {
        self::assertStringContainsString('complete the invoice with TotalsCalculator first', self::refusal(PolishInvoices::domestic()->withTotals(null)));
    }

    public function test_decimals_beyond_the_schema_are_refused(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withQuantity('1.1234567')->withNetAmount('10.123');
        $message = self::refusal($invoice->withLines($lines));

        self::assertStringContainsString('BT-129 (line "1"): 1.1234567 has more than 6 decimals', $message);
        self::assertStringContainsString('BT-131 (line "1"): 10.123 has more than 2 decimals', $message);
    }

    public function test_half_open_invoicing_period_is_refused(): void
    {
        $invoice = PolishInvoices::domestic()->withDelivery(new Delivery(invoicingPeriod: new Period(start: new DateTimeImmutable('2026-10-01'))));

        self::assertStringContainsString('BG-14: ', self::refusal($invoice));
    }

    public function test_different_delivery_and_tax_point_dates_are_refused(): void
    {
        $invoice = PolishInvoices::domestic()
            ->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable('2026-11-10')))
            ->withTaxPointDate(new DateTimeImmutable('2026-11-11'));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-72: ', $message);
        self::assertStringContainsString('BT-7', $message);
    }

    public function test_type_code_without_kind_is_refused(): void
    {
        self::assertStringContainsString('BT-3: ', self::refusal(PolishInvoices::domestic()->withType(InvoiceTypeCode::C_389)));
    }

    public function test_misnamed_new_transport_fragment_is_refused(): void
    {
        $fragment = new RawFragment('<Transport xmlns="'.Fa3Format::NS.'"/>');
        $invoice = PolishInvoices::domestic()->withExtension(new PolishInvoice(annotations: new Annotations(newTransport: $fragment)));

        self::assertStringContainsString('NoweSrodkiTransportu: ', self::refusal($invoice));
    }

    public function test_misnamed_pass_through_fragments_are_refused(): void
    {
        $wrong = new RawFragment('<Stopka xmlns="'.Fa3Format::NS.'"/>');
        $invoice = PolishInvoices::domestic()->withExtension(new PolishInvoice(
            transactionTerms: new TransactionTerms(transports: [$wrong]),
            authorizedParty: $wrong,
            attachment: $wrong,
        ));
        $message = self::refusal($invoice);

        foreach (['Transport: ', 'PodmiotUpowazniony: ', 'Zalacznik: '] as $expected) {
            self::assertStringContainsString($expected, $message);
        }
    }

    public function test_occurrence_limits_are_refused_by_name(): void
    {
        $invoice = PolishInvoices::domestic()
            ->withDespatchAdviceReference('WZ/0')
            ->withPayment(new PaymentInstructions(
                PaymentMeansCode::C_30,
                creditTransfers: array_map(static fn (int $i): CreditTransfer => new CreditTransfer(PolishInvoices::ACCOUNT), range(1, 101)),
            ))
            ->withExtension(new PolishInvoice(
                warehouseDocuments: array_map(static fn (int $i): string => 'WZ/'.$i, range(1, 1000)),
                transactionTerms: new TransactionTerms(
                    contracts: array_map(static fn (int $i): DocumentReference => new DocumentReference(number: 'U/'.$i), range(1, 101)),
                    orders: array_map(static fn (int $i): DocumentReference => new DocumentReference(number: 'Z/'.$i), range(1, 101)),
                    batchNumbers: array_map(static fn (int $i): string => 'P/'.$i, range(1, 1001)),
                ),
            ));
        $message = self::refusal($invoice);

        foreach ([
            'WZ: occurs 1001 times; FA(3) allows at most 1000',
            'RachunekBankowy: occurs 101 times; FA(3) allows at most 100',
            'WarunkiTransakcji/Umowy: occurs 101 times',
            'WarunkiTransakcji/Zamowienia: occurs 101 times',
            'WarunkiTransakcji/NrPartiiTowaru: occurs 1001 times',
        ] as $expected) {
            self::assertStringContainsString($expected, $message);
        }
    }

    public function test_third_party_and_contact_limits_are_refused_by_name(): void
    {
        $base = PolishInvoices::jstBuyer();
        $polish = $base->extensions()->get(PolishInvoice::class);
        self::assertInstanceOf(PolishInvoice::class, $polish);
        $contacts = array_map(static fn (int $i): ContactDetails => new ContactDetails(phone: '22000000'.$i), range(1, 4));
        $invoice = $base->withExtension(self::forge($polish, [
            'thirdParties' => array_fill(0, 101, $polish->thirdParties[0]),
            'seller' => self::forge(new PolishParty, ['contacts' => $contacts]),
        ]));
        $message = self::refusal($invoice);

        self::assertStringContainsString('Podmiot3: occurs 101 times; FA(3) allows at most 100', $message);
        self::assertStringContainsString('Podmiot1/DaneKontaktowe: occurs 4 times; FA(3) allows at most 3', $message);
    }

    public function test_share_beyond_six_decimals_is_refused(): void
    {
        $base = PolishInvoices::jstBuyer();
        $thirdParty = $base->extensions()->get(PolishInvoice::class)?->thirdParties[0];
        self::assertInstanceOf(ThirdParty::class, $thirdParty);
        $invoice = $base->withExtension(new PolishInvoice(
            buyerIsJstUnit: true,
            thirdParties: [new ThirdParty($thirdParty->party, ThirdPartyRole::JstRecipient, share: '12.1234567')],
        ));

        self::assertStringContainsString('Podmiot3[1] Udzial: 12.1234567 has more than 6 decimals', self::refusal($invoice));
    }

    public function test_vat_total_in_accounting_currency_must_match(): void
    {
        $invoice = PolishInvoices::advanceInEuro();
        self::assertNotNull($invoice->totals);
        $totals = $invoice->totals;
        $conflict = $invoice->withTotals(new DocumentTotals(
            $totals->sumOfLineNetAmounts,
            $totals->totalWithoutVat,
            $totals->totalWithVat,
            $totals->amountDue,
            totalVat: $totals->totalVat,
            totalVatInAccountingCurrency: '3351.16',
        ));
        self::assertStringContainsString('BT-111: 3351.16 differs from the sum 3351.17', self::refusal($conflict));

        $domestic = PolishInvoices::domestic();
        self::assertNotNull($domestic->totals);
        $pln = $domestic->withTotals(new DocumentTotals(
            $domestic->totals->sumOfLineNetAmounts,
            $domestic->totals->totalWithoutVat,
            $domestic->totals->totalWithVat,
            $domestic->totals->amountDue,
            totalVat: $domestic->totals->totalVat,
            totalVatInAccountingCurrency: '1.00',
        ));
        self::assertStringContainsString('BT-111: 1.00 differs', self::refusal($pln));
    }

    public function test_writer_runs_the_guard(): void
    {
        $exception = self::capture(static fn () => (new Fa3Writer)->write(PolishInvoices::domestic()->withTotals(null)));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('TotalsCalculator', $exception->getMessage());
    }

    /**
     * A copy of a readonly value object with some properties replaced, bypassing its constructor,
     * to reach the guard with values the constructor would refuse.
     *
     * @template T of object
     *
     * @param  T  $object
     * @param  array<string, mixed>  $overrides
     * @return T
     */
    private static function forge(object $object, array $overrides): object
    {
        $class = new ReflectionClass($object);
        $copy = $class->newInstanceWithoutConstructor();
        foreach ($class->getProperties() as $property) {
            $name = $property->getName();
            $property->setValue($copy, array_key_exists($name, $overrides) ? $overrides[$name] : $property->getValue($object));
        }

        return $copy;
    }

    private static function refusal(Invoice $invoice): string
    {
        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        return $exception->getMessage();
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\Footer;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\OrderLine;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;
use IntrAgile\EInvoicing\Poland\TransactionTerms;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use IntrAgile\EInvoicing\Validation\SchemaError;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Fa3WriterKindsTest extends TestCase
{
    use CapturesThrowable;

    private const CLOCK = '2026-11-18T09:30:47+01:00';

    /**
     * @return array<string, array{Invoice}>
     */
    public static function kinds(): array
    {
        return array_map(static fn (Invoice $invoice): array => [$invoice], PolishInvoices::kinds());
    }

    #[DataProvider('kinds')]
    public function test_every_kind_factory_is_schema_valid(Invoice $invoice): void
    {
        self::assertSchemaValid(self::write($invoice));
    }

    public function test_correction_references_the_ksef_number(): void
    {
        $xml = self::write(PolishInvoices::correctionBeforeAfter());

        self::assertSame(['KOR'], self::values($xml, '//fa:Fa/fa:RodzajFaktury'));
        self::assertSame(
            [
                'DataWystFaKorygowanej=2026-07-14',
                'NrFaKorygowanej=FV/110/07/2026',
                'NrKSeF=1',
                'NrKSeFFaKorygowanej='.PolishInvoices::CORRECTED_KSEF_NUMBER,
            ],
            self::leaves($xml, '//fa:Fa/fa:DaneFaKorygowanej/*'),
        );
        self::assertSame(['Zwrot towaru'], self::values($xml, '//fa:Fa/fa:PrzyczynaKorekty'));
        self::assertSame(['2'], self::values($xml, '//fa:Fa/fa:TypKorekty'));
        self::assertSame(
            ['RodzajFaktury', 'PrzyczynaKorekty', 'TypKorekty', 'DaneFaKorygowanej', 'FaWiersz'],
            self::following(self::childNames($xml, '//fa:Fa'), 'RodzajFaktury', 5),
        );
    }

    public function test_before_rows_are_written_positive_with_stan_przed(): void
    {
        $xml = self::write(PolishInvoices::correctionBeforeAfter());

        self::assertSame(
            ['NrWierszaFa=1', 'P_7=Koc polarowy', 'P_8A=szt.', 'P_8B=3', 'P_9A=90.00', 'P_11=270.00', 'P_12=23', 'StanPrzed=1'],
            self::leaves($xml, '//fa:FaWiersz[1]/*'),
        );
        self::assertSame(
            ['NrWierszaFa=2', 'P_7=Koc polarowy', 'P_8A=szt.', 'P_8B=2', 'P_9A=90.00', 'P_11=180.00', 'P_12=23'],
            self::leaves($xml, '//fa:FaWiersz[2]/*'),
        );
        self::assertSame(['5', '3'], self::values($xml, '//fa:FaWiersz[position() > 2]/fa:P_8B'));
        self::assertSame(['600.00', '360.00'], self::values($xml, '//fa:FaWiersz[position() > 2]/fa:P_11'));
        self::assertSame(['1'], self::values($xml, '//fa:FaWiersz[3]/fa:StanPrzed'));
    }

    public function test_correction_buckets_are_differences(): void
    {
        $xml = self::write(PolishInvoices::correctionBeforeAfter());

        self::assertSame(['-330.00'], self::values($xml, '//fa:Fa/fa:P_13_1'));
        self::assertSame(['-75.90'], self::values($xml, '//fa:Fa/fa:P_14_1'));
        self::assertSame(['-405.90'], self::values($xml, '//fa:Fa/fa:P_15'));
    }

    public function test_correction_outside_ksef_writes_nrksefn(): void
    {
        $invoice = self::withPolish(PolishInvoices::correctionBeforeAfter(), static fn (PolishInvoice $polish): array => [
            'correction' => new Correction([new CorrectedInvoice('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))]),
        ]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['DataWystFaKorygowanej=2026-07-14', 'NrFaKorygowanej=FV/110/07/2026', 'NrKSeFN=1'],
            self::leaves($xml, '//fa:Fa/fa:DaneFaKorygowanej/*'),
        );
    }

    public function test_preceding_invoice_reference_becomes_the_corrected_invoice(): void
    {
        $invoice = self::withPolish(PolishInvoices::correctionBeforeAfter(), static fn (): array => ['correction' => null])
            ->withPrecedingInvoices([new PrecedingInvoiceReference('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['DataWystFaKorygowanej=2026-07-14', 'NrFaKorygowanej=FV/110/07/2026', 'NrKSeFN=1'],
            self::leaves($xml, '//fa:Fa/fa:DaneFaKorygowanej/*'),
        );

        $matching = PolishInvoices::correctionBeforeAfter()
            ->withPrecedingInvoices([new PrecedingInvoiceReference('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))]);
        self::assertSame(1, count(self::nodes(self::write($matching), '//fa:Fa/fa:DaneFaKorygowanej')));
    }

    public function test_correction_data_before_the_change_is_written(): void
    {
        $sellerBefore = new Party(
            name: 'Sprzedawca dawniej Sp. z o.o.',
            vatIdentifier: PolishInvoices::SELLER_VAT,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Kwiatowa 1 m. 2', city: 'Warszawa', postCode: '00-001'),
        );
        $buyerBefore = new Party(
            name: 'Nabywca dawniej S.A.',
            vatIdentifier: PolishInvoices::BUYER_VAT,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Stara 1', city: 'Poznań', postCode: '60-002'),
        );
        $invoice = self::withPolish(PolishInvoices::advanceInEuro(), static fn (PolishInvoice $polish): array => [
            'kind' => InvoiceKind::KorZal,
            'correction' => new Correction(
                invoices: [new CorrectedInvoice('FZ/7/09/2026', new DateTimeImmutable('2026-09-20'))],
                period: '2026-09',
                correctedNumber: 'FZ/7/09/2026/A',
                sellerBefore: $sellerBefore,
                buyersBefore: [$buyerBefore],
                amountBefore: '3000.00',
                exchangeRateBefore: '4.4724',
            ),
            'exchangeRate' => '4.5148',
        ]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['DaneFaKorygowanej', 'OkresFaKorygowanej', 'NrFaKorygowany', 'Podmiot1K', 'Podmiot2K', 'P_15ZK', 'KursWalutyZK', 'ZaliczkaCzesciowa'],
            self::following(self::childNames($xml, '//fa:Fa'), 'DaneFaKorygowanej', 8),
        );
        self::assertSame(['2222222222', 'Sprzedawca dawniej Sp. z o.o.'], self::values($xml, '//fa:Podmiot1K/fa:DaneIdentyfikacyjne/*'));
        self::assertSame(['1111111111', 'Nabywca dawniej S.A.'], self::values($xml, '//fa:Podmiot2K/fa:DaneIdentyfikacyjne/*'));
        self::assertSame(['PL', 'ul. Stara 1', '60-002 Poznań'], self::values($xml, '//fa:Podmiot2K/fa:Adres/*'));
        self::assertSame(['3000.00'], self::values($xml, '//fa:Fa/fa:P_15ZK'));
        self::assertSame(['4.5148'], self::values($xml, '//fa:Fa/fa:KursWalutyZ'));
    }

    public function test_partial_advances_and_pln_vat(): void
    {
        $xml = self::write(PolishInvoices::advanceInEuro());

        self::assertSame(['EUR'], self::values($xml, '//fa:Fa/fa:KodWaluty'));
        self::assertSame(['ZAL'], self::values($xml, '//fa:Fa/fa:RodzajFaktury'));
        self::assertSame(
            ['P_13_1=3252.03', 'P_14_1=747.97', 'P_14_1W=3351.17', 'P_15=4000.00'],
            self::leaves($xml, '//fa:Fa/*[starts-with(local-name(), "P_13_") or starts-with(local-name(), "P_14_") or local-name() = "P_15"]'),
        );
        self::assertSame(
            ['P_6Z=2026-09-10', 'P_15Z=500', 'KursWalutyZW=4.4512', 'P_6Z=2026-09-15', 'P_15Z=2500', 'KursWalutyZW=4.4724', 'P_6Z=2026-09-20', 'P_15Z=1000', 'KursWalutyZW=4.5148'],
            self::leaves($xml, '//fa:Fa/fa:ZaliczkaCzesciowa/*'),
        );
        self::assertSame([], self::nodes($xml, '//fa:FaWiersz'));
        self::assertSame(['10000.00'], self::values($xml, '//fa:Fa/fa:Zamowienie/fa:WartoscZamowienia'));
        self::assertSame(
            ['NrWierszaZam=1', 'P_7Z=Linia do pakowania', 'P_8AZ=szt.', 'P_8BZ=1', 'P_9AZ=8130.08', 'P_11NettoZ=8130.08', 'P_11VatZ=1869.92', 'P_12Z=23'],
            self::leaves($xml, '//fa:Fa/fa:Zamowienie/fa:ZamowienieWiersz/*'),
        );
        self::assertSame('Zamowienie', self::childNames($xml, '//fa:Fa')[count(self::childNames($xml, '//fa:Fa')) - 1]);
    }

    public function test_foreign_currency_without_pln_vat_names_each_bucket(): void
    {
        $invoice = PolishInvoices::domestic()->withCurrency(CurrencyCode::EUR);
        $message = self::refusal($invoice);

        self::assertStringContainsString('P_14_1W: ', $message);
        self::assertStringContainsString('P_14_2W: ', $message);
        self::assertStringNotContainsString('P_14_3W', $message);

        $supplied = self::withPolish($invoice, static fn (): array => ['vatInPln' => [1 => '1531.40', 2 => '31.53']]);
        $xml = self::write($supplied);
        self::assertSchemaValid($xml);
        self::assertSame(['1531.40'], self::values($xml, '//fa:Fa/fa:P_14_1W'));
        self::assertSame(['31.53'], self::values($xml, '//fa:Fa/fa:P_14_2W'));

        $extra = self::withPolish($invoice, static fn (): array => ['vatInPln' => [1 => '1531.40', 2 => '31.53', 3 => '1.00']]);
        self::assertStringContainsString('P_14_3W: ', self::refusal($extra));
    }

    public function test_pln_vat_on_pln_invoice_is_refused(): void
    {
        $invoice = self::withPolish(PolishInvoices::domestic(), static fn (): array => ['vatInPln' => [1 => '356.50']]);

        self::assertStringContainsString('P_14_1W: ', self::refusal($invoice));
    }

    public function test_advance_references_outside_ksef(): void
    {
        $xml = self::write(PolishInvoices::settlementOfAdvances());

        self::assertSame(['ROZ'], self::values($xml, '//fa:Fa/fa:RodzajFaktury'));
        self::assertSame(
            ['NrKSeFZN=1', 'NrFaZaliczkowej=FZ/123/07/2025', 'NrKSeFZN=1', 'NrFaZaliczkowej=FZ/133/08/2025'],
            self::leaves($xml, '//fa:Fa/fa:FakturaZaliczkowa/*'),
        );
        self::assertSame(['3690.00'], self::values($xml, '//fa:Fa/fa:P_15'));
        self::assertSame(['10000.00', '2300.00'], self::values($xml, '//fa:Fa/fa:P_13_1 | //fa:Fa/fa:P_14_1'));

        $inKsef = self::withPolish(PolishInvoices::settlementOfAdvances(), static fn (): array => [
            'advanceInvoices' => [new AdvanceInvoiceReference(KsefNumber::parse(PolishInvoices::CORRECTED_KSEF_NUMBER))],
        ]);
        $ksefXml = self::write($inKsef);
        self::assertSchemaValid($ksefXml);
        self::assertSame(['NrKSeFFaZaliczkowej='.PolishInvoices::CORRECTED_KSEF_NUMBER], self::leaves($ksefXml, '//fa:Fa/fa:FakturaZaliczkowa/*'));
    }

    public function test_margin_line_has_no_rate_and_sets_pmarzy(): void
    {
        $xml = self::write(PolishInvoices::usedCarMargin());

        self::assertSame([], self::values($xml, '//fa:FaWiersz/fa:P_12'));
        self::assertSame(['50000.00'], self::values($xml, '//fa:Fa/fa:P_13_11'));
        self::assertSame(['50000.00'], self::values($xml, '//fa:Fa/fa:P_15'));
        self::assertSame(['P_PMarzy=1', 'P_PMarzy_3_1=1'], self::leaves($xml, '//fa:Adnotacje/fa:PMarzy/*'));

        $unannotated = self::withPolish(PolishInvoices::usedCarMargin(), static fn (): array => ['annotations' => new Annotations]);
        self::assertStringContainsString('PMarzy: ', self::refusal($unannotated));
    }

    public function test_split_payment_writes_p18a_and_annex_15(): void
    {
        $xml = self::write(PolishInvoices::splitPayment());

        self::assertSame(['1'], self::values($xml, '//fa:Adnotacje/fa:P_18A'));
        self::assertSame(['1'], self::values($xml, '//fa:FaWiersz/fa:P_12_Zal_15'));
        self::assertSame(['15375.00'], self::values($xml, '//fa:Fa/fa:P_15'));
    }

    public function test_settlement_writes_do_rozliczenia_37(): void
    {
        $xml = self::write(PolishInvoices::withSettlement());

        self::assertSame(['246.00'], self::values($xml, '//fa:Fa/fa:P_15'));
        self::assertSame(
            [
                'Kwota=17.00', 'Powod=Zwrot kosztów - opłata skarbowa', 'SumaObciazen=17.00',
                'Kwota=300.00', 'Powod=Rozliczenie salda klienta', 'SumaOdliczen=300.00',
                'DoRozliczenia=37.00',
            ],
            self::leaves($xml, '//fa:Fa/fa:Rozliczenie//*[not(*)]'),
        );
        self::assertSame(['Rozliczenie', 'Platnosc'], self::following(self::childNames($xml, '//fa:Fa'), 'Rozliczenie', 2));
        self::assertSame(['FaWiersz', 'Rozliczenie'], self::following(self::childNames($xml, '//fa:Fa'), 'FaWiersz', 2));
    }

    public function test_settlement_writes_do_zaplaty_when_positive(): void
    {
        $invoice = self::withPolish(PolishInvoices::withSettlement(), static fn (): array => [
            'settlement' => new Settlement(charges: [new SettlementEntry('17.00', 'Zwrot kosztów - opłata skarbowa')]),
        ]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['Kwota=17.00', 'Powod=Zwrot kosztów - opłata skarbowa', 'SumaObciazen=17.00', 'DoZaplaty=263.00'],
            self::leaves($xml, '//fa:Fa/fa:Rozliczenie//*[not(*)]'),
        );
    }

    public function test_fragments_are_written_in_schema_position(): void
    {
        $authorized = new RawFragment('<PodmiotUpowazniony xmlns="'.Fa3Format::NS.'">'
            .'<DaneIdentyfikacyjne><NIP>'.PolishInvoices::JST_UNIT_NIP.'</NIP><Nazwa>Komornik Sądowy</Nazwa></DaneIdentyfikacyjne>'
            .'<Adres><KodKraju>PL</KodKraju><AdresL1>ul. Sądowa 3</AdresL1></Adres>'
            .'<RolaPU>1</RolaPU></PodmiotUpowazniony>');
        $invoice = self::withPolish(PolishInvoices::withTransportAndAttachment(), static fn (PolishInvoice $polish): array => [
            'authorizedParty' => $authorized,
            'thirdParties' => PolishInvoices::jstBuyer()->extensions()->get(PolishInvoice::class)->thirdParties ?? [],
            'buyerIsJstUnit' => true,
            'footer' => new Footer(notes: ['Dziękujemy']),
        ]);
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(['Naglowek', 'Podmiot1', 'Podmiot2', 'Podmiot3', 'PodmiotUpowazniony', 'Fa', 'Stopka', 'Zalacznik'], self::childNames($xml, '/fa:Faktura'));
        self::assertSame(['Zamowienia', 'NrPartiiTowaru', 'WarunkiDostawy', 'Transport'], self::childNames($xml, '//fa:Fa/fa:WarunkiTransakcji'));
        self::assertSame(['Jan Nowak Transport'], self::values($xml, '//fa:Transport/fa:Przewoznik/fa:DaneIdentyfikacyjne/fa:Nazwa'));
        self::assertSame(3, count(self::nodes($xml, '/fa:Faktura/fa:Zalacznik/fa:BlokDanych/fa:Tabela')));
        self::assertSame(['Komornik Sądowy'], self::values($xml, '/fa:Faktura/fa:PodmiotUpowazniony/fa:DaneIdentyfikacyjne/fa:Nazwa'));
    }

    public function test_transaction_terms_are_written_in_full(): void
    {
        $invoice = self::withPolish(PolishInvoices::domestic(), static fn (): array => [
            'transactionTerms' => new TransactionTerms(
                contracts: [new DocumentReference(new DateTimeImmutable('2026-01-02'), 'U/1/2026')],
                deliveryTerms: 'FCA',
                contractRate: '4.25',
                contractCurrency: CurrencyCode::EUR,
                intermediary: true,
            ),
        ])->withContractReference('U/1/2026')->withPurchaseOrderReference('Z/9');
        $xml = self::write($invoice);

        self::assertSchemaValid($xml);
        self::assertSame(
            ['DataUmowy=2026-01-02', 'NrUmowy=U/1/2026', 'NrZamowienia=Z/9', 'WarunkiDostawy=FCA', 'KursUmowny=4.25', 'WalutaUmowna=EUR', 'PodmiotPosredniczacy=1'],
            self::leaves($xml, '//fa:Fa/fa:WarunkiTransakcji//*[not(*)]'),
        );
    }

    public function test_correction_data_on_vat_invoice_is_refused(): void
    {
        $correction = new Correction([new CorrectedInvoice('FV/1', new DateTimeImmutable('2026-07-14'))]);
        $invoice = self::withPolish(PolishInvoices::domestic(), static fn (): array => ['correction' => $correction]);

        self::assertStringContainsString('DaneFaKorygowanej: ', self::refusal($invoice));
    }

    public function test_before_row_on_vat_invoice_is_refused(): void
    {
        $invoice = PolishInvoices::correctionBeforeAfter()
            ->withType(InvoiceTypeCode::C_380)
            ->withExtension(new PolishInvoice);

        $message = self::refusal($invoice);
        self::assertStringContainsString('StanPrzed (line "1"): ', $message);
        self::assertStringContainsString('StanPrzed (line "3"): ', $message);
    }

    public function test_zal_without_order_is_refused(): void
    {
        $invoice = self::withPolish(PolishInvoices::advanceInEuro(), static fn (): array => ['order' => null]);

        self::assertStringContainsString('Zamowienie: ', self::refusal($invoice));
    }

    public function test_kind_specific_blocks_are_refused_on_other_kinds(): void
    {
        $order = new Order('100.00', [new OrderLine(name: 'x', stateBefore: true)]);
        $invoice = self::withPolish(PolishInvoices::domestic(), static fn (): array => [
            'advanceInvoices' => [new AdvanceInvoiceReference(number: 'FZ/1')],
            'order' => $order,
            'exchangeRate' => '4.1',
        ]);
        $invoice = $invoice->withLines([...$invoice->lines, PolishInvoices::bucketLine('10.00', '23')]);
        $message = self::refusal(TotalsCalculator::complete($invoice->withVatBreakdown([])));

        foreach (['FakturaZaliczkowa: ', 'Zamowienie: ', 'StanPrzedZ (order line 1): ', 'KursWalutyZ: ', 'FaWiersz (line "1"): '] as $expected) {
            self::assertStringContainsString($expected, $message);
        }
    }

    public function test_partial_advances_with_derived_kind_are_checked(): void
    {
        $advances = PolishInvoices::advanceInEuro()->extensions()->get(PolishInvoice::class)->partialAdvances ?? [];
        $invoice = self::withPolish(PolishInvoices::domestic(), static fn (): array => ['partialAdvances' => $advances]);

        self::assertStringContainsString('ZaliczkaCzesciowa: ', self::refusal($invoice));
    }

    public function test_preceding_invoice_conflicting_with_correction_is_refused(): void
    {
        $conflict = PolishInvoices::correctionBeforeAfter()->withPrecedingInvoices([new PrecedingInvoiceReference('FV/999/07/2026')]);
        self::assertStringContainsString('BG-3: ', self::refusal($conflict));

        $undated = self::withPolish(PolishInvoices::correctionBeforeAfter(), static fn (): array => ['correction' => null])
            ->withPrecedingInvoices([new PrecedingInvoiceReference('FV/110/07/2026')]);
        self::assertStringContainsString('BT-26 (BG-3 "FV/110/07/2026"): ', self::refusal($undated));
    }

    public function test_correction_party_data_is_checked(): void
    {
        $invoice = self::withPolish(PolishInvoices::correctionBeforeAfter(), static fn (): array => [
            'correction' => new Correction(
                invoices: [new CorrectedInvoice('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))],
                sellerBefore: new Party(name: 'Bez NIP'),
                buyersBefore: [PolishInvoices::seller()],
                exchangeRateBefore: '4.1',
            ),
        ]);
        $message = self::refusal($invoice);

        self::assertStringContainsString('Podmiot1K: ', $message);
        self::assertStringContainsString('Podmiot2K[1] contact: ', $message);
        self::assertStringContainsString('KursWalutyZK: ', $message);
    }

    /**
     * A copy of the invoice whose Polish extension has the given properties replaced.
     *
     * @param  callable(PolishInvoice): array<string, mixed>  $changes
     */
    private static function withPolish(Invoice $invoice, callable $changes): Invoice
    {
        $polish = $invoice->extensions()->get(PolishInvoice::class) ?? new PolishInvoice;
        $class = new ReflectionClass(PolishInvoice::class);
        $arguments = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $arguments[$parameter->getName()] = $polish->{$parameter->getName()};
        }

        return $invoice->withExtension($class->newInstanceArgs([...$arguments, ...$changes($polish)]));
    }

    /**
     * The given number of names starting at the first occurrence of $name.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function following(array $names, string $name, int $count): array
    {
        $index = array_search($name, $names, true);
        self::assertIsInt($index, $name.' not found in '.implode(', ', $names));

        return array_slice($names, $index, $count);
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }

    private static function refusal(Invoice $invoice): string
    {
        $exception = self::capture(static fn () => self::write($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        return $exception->getMessage();
    }

    private static function assertSchemaValid(string $xml): void
    {
        $report = Fa3Validator::create()->validate($xml);
        self::assertSame([], array_map(static fn (SchemaError $e): string => $e->message, $report->schemaErrors), $xml);
    }

    /**
     * @return list<DOMElement>
     */
    private static function nodes(string $xml, string $path): array
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('fa', Fa3Format::NS);
        $nodes = [];
        foreach ($xpath->query($path) ?: [] as $node) {
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
        return array_map(static fn (DOMElement $node): string => trim($node->textContent), self::nodes($xml, $path));
    }

    /**
     * @return list<string>
     */
    private static function leaves(string $xml, string $path): array
    {
        return array_map(static fn (DOMElement $node): string => $node->localName.'='.trim($node->textContent), self::nodes($xml, $path));
    }

    /**
     * @return list<string>
     */
    private static function childNames(string $xml, string $path): array
    {
        return array_map(static fn (DOMElement $node): string => (string) $node->localName, self::nodes($xml, $path.'/*'));
    }
}

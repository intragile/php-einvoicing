<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\TaxRegistration;
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\EuroAmounts;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\Chainer;
use IntrAgile\EInvoicing\Spain\Verifactu\CorrectionAmounts;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\MappingRefused;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordMapper;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\SpanishInvoices;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RecordMapperTest extends TestCase
{
    public function test_domestic_invoice(): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::domestic());

        self::assertSame('B64829179', $draft->id->issuer->value);
        self::assertSame('PROBE-ES-UBL-0002', $draft->id->number);
        self::assertSame('23-11-2026', $draft->id->dateText());
        self::assertSame('IntrAgile Test Espana S.L.', $draft->issuerName);
        self::assertSame(InvoiceType::F1, $draft->type);
        self::assertNull($draft->correctionType);
        self::assertSame('Factura PROBE-ES-UBL-0002', $draft->description);
        self::assertCount(1, $draft->recipients);
        self::assertSame('Cliente Prueba S.A.', $draft->recipients[0]->name);
        self::assertSame('A58818501', $draft->recipients[0]->nif?->value);
        self::assertCount(1, $draft->breakdown);
        self::assertSame(['01', '01', 'S1', null, '21.00', '100.00', '21.00'], self::describe($draft->breakdown[0]));
        self::assertSame('21.00', $draft->totalTax->text);
        self::assertSame('121.00', $draft->total->text);
        self::assertNull($draft->operationDate);
        self::assertFalse($draft->macrodato);
        self::assertSame([], $draft->corrected);
    }

    /** @return iterable<string, array{VatCategory, numeric-string, list<?string>}> */
    public static function defaults(): iterable
    {
        yield 'S' => [VatCategory::StandardRate, '21', ['01', '01', 'S1', null, '21.00', '100.00', '21.00']];
        yield 'Z' => [VatCategory::ZeroRated, '0', ['01', '01', 'S1', null, '0.00', '100.00', '0.00']];
        yield 'AE' => [VatCategory::ReverseCharge, '0', ['01', '01', 'S2', null, '0.00', '100.00', '0.00']];
        yield 'G' => [VatCategory::ExportOutsideEu, '0', ['01', '02', null, 'E2', null, '100.00', null]];
        yield 'K' => [VatCategory::IntraCommunitySupply, '0', ['01', '01', null, 'E5', null, '100.00', null]];
        yield 'L' => [VatCategory::CanaryIslands, '7', ['03', '01', 'S1', null, '7.00', '100.00', '7.00']];
        yield 'M' => [VatCategory::CeutaAndMelilla, '4', ['02', '01', 'S1', null, '4.00', '100.00', '4.00']];
    }

    /**
     * @param  numeric-string  $rate
     * @param  list<?string>  $expected
     */
    #[DataProvider('defaults')]
    public function test_default_breakdown_table(VatCategory $category, string $rate, array $expected): void
    {
        $invoice = SpanishInvoices::invoice([SpanishInvoices::line('1', '100.00', $category, $rate)]);

        $draft = (new RecordMapper)->map($invoice);

        self::assertCount(1, $draft->breakdown);
        self::assertSame($expected, self::describe($draft->breakdown[0]));
    }

    public function test_intra_community_buyer_becomes_a_vat_number_recipient(): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::intraCommunity());

        self::assertCount(1, $draft->recipients);
        $foreign = $draft->recipients[0]->foreignId;
        self::assertNotNull($foreign);
        self::assertSame(ForeignIdType::VatNumber, $foreign->type);
        self::assertSame('DE123456789', $foreign->id);
        self::assertSame(CountryCode::DE, $foreign->country);
        self::assertNull($draft->recipients[0]->nif);
    }

    public function test_greek_vat_prefix_maps_to_country_gr(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '100.00', VatCategory::IntraCommunitySupply, '0')],
            buyer: new Party(name: 'Agorastis A.E.', vatIdentifier: 'EL123456789'),
        );

        $foreign = (new RecordMapper)->map($invoice)->recipients[0]->foreignId;

        self::assertSame('EL123456789', $foreign?->id);
        self::assertSame(CountryCode::GR, $foreign->country);
    }

    public function test_override_replaces_a_default(): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::exempt(ExemptOperation::E1));

        self::assertSame(['01', '01', null, 'E1', null, '400.00', null], self::describe($draft->breakdown[0]));

        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(breakdown: [
            'S|21' => new BreakdownOverride(
                regime: RegimeKey::Key18,
                equivalenceRate: TaxRate::of('5.20'),
                equivalenceQuota: Amount::of('5.20'),
            ),
        ]));
        $line = (new RecordMapper)->map($invoice)->breakdown[0];

        self::assertSame(['01', '18', 'S1', null, '21.00', '100.00', '21.00'], self::describe($line));
        self::assertSame('5.20', $line->equivalenceRate?->text);
        self::assertSame('5.20', $line->equivalenceQuota?->text);
    }

    /** @return iterable<string, array{ExemptOperation}> */
    public static function exportExemptions(): iterable
    {
        yield 'E2' => [ExemptOperation::E2];
        yield 'E3' => [ExemptOperation::E3];
    }

    /**
     * Regime 01 refuses E2 and E3 (Validaciones §3.1.3.15.5, error 1199), so an exempt group
     * overridden to either defaults to the export regime 02.
     */
    #[DataProvider('exportExemptions')]
    public function test_export_exemption_defaults_to_regime_02(ExemptOperation $article): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::exempt($article));

        self::assertSame(['01', '02', null, $article->value, null, '400.00', null], self::describe($draft->breakdown[0]));
    }

    public function test_explicit_regime_override_is_kept_for_export_exemptions(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '400.00', VatCategory::Exempt, null)],
            spanish: new SpanishInvoice(breakdown: ['E|-' => new BreakdownOverride(regime: RegimeKey::Key01, exemption: ExemptOperation::E3)]),
        );

        $draft = (new RecordMapper)->map($invoice);

        self::assertSame(['01', '01', null, 'E3', null, '400.00', null], self::describe($draft->breakdown[0]));
    }

    public function test_exempt_and_not_subject_need_overrides(): void
    {
        $invoice = SpanishInvoices::invoice([
            SpanishInvoices::line('1', '100.00', VatCategory::Exempt, null),
            SpanishInvoices::line('2', '50.00', VatCategory::NotSubjectToVat, null),
        ]);

        $problems = self::problems($invoice);

        self::assertCount(2, $problems);
        self::assertStringStartsWith('OperacionExenta (E|-):', $problems[0]);
        self::assertStringStartsWith('CalificacionOperacion (O|-):', $problems[1]);
    }

    public function test_not_subject_override_has_no_rate_or_quota(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '50.00', VatCategory::NotSubjectToVat, null)],
            spanish: new SpanishInvoice(breakdown: ['O|-' => new BreakdownOverride(qualification: OperationQualification::N2)]),
        );

        $draft = (new RecordMapper)->map($invoice);

        self::assertSame(['01', '01', 'N2', null, null, '50.00', null], self::describe($draft->breakdown[0]));
    }

    public function test_split_payment_category_is_refused(): void
    {
        $invoice = SpanishInvoices::invoice([SpanishInvoices::line('1', '100.00', VatCategory::TransferredVat, '21')]);

        $problems = self::problems($invoice);

        self::assertCount(1, $problems);
        self::assertStringStartsWith('Desglose (B|21):', $problems[0]);
    }

    public function test_credit_note_needs_type_and_correction_kind(): void
    {
        $invoice = SpanishInvoices::creditNote()->withExtensions(Extensions::none());

        $problems = self::problems($invoice);

        self::assertCount(2, $problems);
        self::assertStringStartsWith('TipoFactura:', $problems[0]);
        self::assertStringStartsWith('TipoRectificativa:', $problems[1]);
    }

    public function test_corrective_type_without_correction_kind_is_refused(): void
    {
        $invoice = SpanishInvoices::creditNote()->withExtension(new SpanishInvoice(type: InvoiceType::R1));

        self::assertSame(['TipoRectificativa'], self::fields(self::problems($invoice)));
    }

    public function test_credit_note_cannot_be_a_non_corrective_type(): void
    {
        $invoice = SpanishInvoices::creditNote()->withExtension(new SpanishInvoice(type: InvoiceType::F1));

        self::assertSame(['TipoFactura'], self::fields(self::problems($invoice)));
    }

    public function test_correction_kind_on_a_derived_non_corrective_type_is_refused(): void
    {
        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(correctionType: CorrectionType::Difference));

        self::assertSame(['TipoRectificativa'], self::fields(self::problems($invoice)));
    }

    public function test_credit_note_by_difference_is_negative(): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::creditNote());

        self::assertSame(InvoiceType::R1, $draft->type);
        self::assertSame(CorrectionType::Difference, $draft->correctionType);
        self::assertCount(1, $draft->corrected);
        self::assertSame('B64829179', $draft->corrected[0]->issuer->value);
        self::assertSame('PROBE-ES-UBL-0002', $draft->corrected[0]->number);
        self::assertSame('23-11-2026', $draft->corrected[0]->dateText());
        self::assertSame(['01', '01', 'S1', null, '21.00', '-100.00', '-21.00'], self::describe($draft->breakdown[0]));
        self::assertSame('-21.00', $draft->totalTax->text);
        self::assertSame('-121.00', $draft->total->text);
        self::assertNull($draft->correctionAmounts);
    }

    public function test_preceding_invoice_without_date_is_refused(): void
    {
        $invoice = SpanishInvoices::creditNote()->withPrecedingInvoices([
            new PrecedingInvoiceReference(SpanishInvoices::NUMBER),
        ]);

        self::assertSame(['FacturasRectificadas (BT-26)'], self::fields(self::problems($invoice)));
    }

    public function test_preceding_invoices_are_not_written_for_a_non_corrective_type(): void
    {
        $invoice = SpanishInvoices::domestic()->withPrecedingInvoice(
            new PrecedingInvoiceReference('PROBE-ES-UBL-0001', new DateTimeImmutable('2026-11-01')),
        );

        self::assertSame([], (new RecordMapper)->map($invoice)->corrected);
    }

    public function test_substitution_needs_correction_amounts(): void
    {
        $refused = SpanishInvoices::creditNote()->withExtension(
            new SpanishInvoice(type: InvoiceType::R1, correctionType: CorrectionType::Substitution),
        );
        self::assertSame(['ImporteRectificacion'], self::fields(self::problems($refused)));

        $amounts = new CorrectionAmounts(Amount::of('100.00'), Amount::of('21.00'), null);
        $invoice = SpanishInvoices::creditNote()->withExtension(new SpanishInvoice(
            type: InvoiceType::R1,
            correctionType: CorrectionType::Substitution,
            correctionAmounts: $amounts,
        ));

        $draft = (new RecordMapper)->map($invoice);

        self::assertSame(CorrectionType::Substitution, $draft->correctionType);
        self::assertSame($amounts, $draft->correctionAmounts);
        self::assertSame(['01', '01', 'S1', null, '21.00', '100.00', '21.00'], self::describe($draft->breakdown[0]));
        self::assertSame('21.00', $draft->totalTax->text);
        self::assertSame('121.00', $draft->total->text);
    }

    public function test_correction_amounts_need_substitution(): void
    {
        $invoice = SpanishInvoices::creditNote()->withExtension(new SpanishInvoice(
            type: InvoiceType::R1,
            correctionType: CorrectionType::Difference,
            correctionAmounts: new CorrectionAmounts(Amount::of('100.00'), Amount::of('21.00'), null),
        ));

        self::assertSame(['ImporteRectificacion'], self::fields(self::problems($invoice)));
    }

    public function test_substituted_invoices_need_type_f3(): void
    {
        $substituted = [new InvoiceId(Nif::parse(SpanishInvoices::SELLER_NIF), 'T-0001', new DateTimeImmutable('2026-11-20'))];

        $refused = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(substituted: $substituted));
        self::assertSame(['FacturasSustituidas'], self::fields(self::problems($refused)));

        $draft = (new RecordMapper)->map(
            SpanishInvoices::domestic()->withExtension(new SpanishInvoice(type: InvoiceType::F3, substituted: $substituted)),
        );
        self::assertSame(InvoiceType::F3, $draft->type);
        self::assertSame($substituted, $draft->substituted);
    }

    public function test_simplified_invoice_has_no_recipients(): void
    {
        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(type: InvoiceType::F2));

        $draft = (new RecordMapper)->map($invoice);

        self::assertSame(InvoiceType::F2, $draft->type);
        self::assertSame([], $draft->recipients);
    }

    public function test_missing_buyer_id_is_refused_for_f1(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '100.00', VatCategory::StandardRate, '21')],
            buyer: new Party(name: 'Consumidor Final'),
        );

        self::assertSame(['Destinatarios (BT-48)'], self::fields(self::problems($invoice)));

        $allowed = $invoice->withExtension(new SpanishInvoice(withoutRecipientArt61d: true));
        $draft = (new RecordMapper)->map($allowed);
        self::assertSame([], $draft->recipients);
        self::assertTrue($draft->withoutRecipientArt61d);
    }

    public function test_recipient_override_is_used_without_a_buyer_vat_id(): void
    {
        $draft = (new RecordMapper)->map(SpanishInvoices::export());

        self::assertCount(1, $draft->recipients);
        self::assertSame(ForeignIdType::OfficialDocument, $draft->recipients[0]->foreignId?->type);
        self::assertSame('123456789', $draft->recipients[0]->foreignId->id);
    }

    public function test_recipient_override_next_to_a_buyer_vat_id_is_refused(): void
    {
        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(recipients: [VerifactuSamples::recipient()]));

        self::assertSame(['Destinatarios'], self::fields(self::problems($invoice)));
    }

    public function test_invalid_spanish_buyer_vat_id_is_refused(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '100.00', VatCategory::StandardRate, '21')],
            buyer: new Party(name: 'Cliente', vatIdentifier: 'ESA58818502'),
        );

        self::assertSame(['Destinatarios (BT-48)'], self::fields(self::problems($invoice)));
    }

    public function test_non_eur_needs_euro_amounts(): void
    {
        self::assertSame(['BT-5'], self::fields(self::problems(SpanishInvoices::usd())));

        $invoice = SpanishInvoices::usd()->withExtension(new SpanishInvoice(euroAmounts: new EuroAmounts(
            Amount::of('19.40'),
            Amount::of('111.79'),
            ['S|21' => ['base' => Amount::of('92.39'), 'quota' => Amount::of('19.40')]],
        )));

        $draft = (new RecordMapper)->map($invoice);

        self::assertSame(['01', '01', 'S1', null, '21.00', '92.39', '19.40'], self::describe($draft->breakdown[0]));
        self::assertSame('19.40', $draft->totalTax->text);
        self::assertSame('111.79', $draft->total->text);
    }

    public function test_euro_amounts_must_cover_every_group(): void
    {
        $invoice = SpanishInvoices::usd()->withExtension(new SpanishInvoice(euroAmounts: new EuroAmounts(
            Amount::of('19.40'),
            Amount::of('111.79'),
            ['S|10' => ['base' => Amount::of('92.39'), 'quota' => Amount::of('19.40')]],
        )));

        self::assertSame(['Desglose (S|21)', 'EuroAmounts (S|10)'], self::fields(self::problems($invoice)));
    }

    public function test_euro_amounts_on_a_eur_invoice_are_refused(): void
    {
        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(euroAmounts: new EuroAmounts(
            Amount::of('21.00'),
            Amount::of('121.00'),
            ['S|21' => ['base' => Amount::of('100.00'), 'quota' => Amount::of('21.00')]],
        )));

        self::assertSame(['EuroAmounts'], self::fields(self::problems($invoice)));
    }

    public function test_override_for_a_missing_group_is_refused(): void
    {
        $invoice = SpanishInvoices::domestic()->withExtension(new SpanishInvoice(breakdown: [
            'S|10' => new BreakdownOverride(regime: RegimeKey::Key18),
        ]));

        self::assertSame(['SpanishInvoice::$breakdown (S|10)'], self::fields(self::problems($invoice)));
    }

    public function test_more_than_twelve_lines_is_refused(): void
    {
        self::assertSame(['Desglose (BG-23)'], self::fields(self::problems(SpanishInvoices::thirteenRates())));
    }

    public function test_incomplete_invoice_is_refused(): void
    {
        $invoice = SpanishInvoices::domestic()->withTotals(null)->withVatBreakdown([]);

        $problems = self::problems($invoice);

        self::assertSame(['BG-22'], self::fields($problems));
        self::assertStringContainsString('TotalsCalculator', $problems[0]);
    }

    public function test_macrodato_threshold(): void
    {
        $at = SpanishInvoices::invoice([SpanishInvoices::line('1', '100000000.00', VatCategory::ZeroRated, '0')]);
        $below = SpanishInvoices::invoice([SpanishInvoices::line('1', '99999999.99', VatCategory::ZeroRated, '0')]);

        self::assertTrue((new RecordMapper)->map($at)->macrodato);
        self::assertFalse((new RecordMapper)->map($below)->macrodato);
    }

    public function test_operation_date_from_delivery(): void
    {
        $mapper = new RecordMapper;
        $base = SpanishInvoices::domestic();

        $delivered = $mapper->map($base->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable('2026-11-20'))));
        self::assertSame('2026-11-20', $delivered->operationDate?->format('Y-m-d'));

        $period = $mapper->map($base->withDelivery(new Delivery(invoicingPeriod: new Period(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31'),
        ))));
        self::assertSame('2026-10-31', $period->operationDate?->format('Y-m-d'));

        $sameDay = $mapper->map($base->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable(SpanishInvoices::ISSUE_DATE))));
        self::assertNull($sameDay->operationDate);
    }

    public function test_description_from_override_or_first_note(): void
    {
        $mapper = new RecordMapper;
        $noted = SpanishInvoices::domestic()->withNotes([new Note('  Consultoría noviembre  '), new Note('Second note')]);

        self::assertSame('Consultoría noviembre', $mapper->map($noted)->description);
        self::assertSame('Servicios', $mapper->map($noted->withExtension(new SpanishInvoice(description: 'Servicios')))->description);

        $tooLong = $noted->withExtension(new SpanishInvoice(description: str_repeat('x', 501)));
        self::assertSame(['DescripcionOperacion'], self::fields(self::problems($tooLong)));
    }

    public function test_long_note_falls_back_to_number(): void
    {
        $invoice = SpanishInvoices::domestic()->withNotes([new Note(str_repeat('n', 501))]);

        self::assertSame('Factura PROBE-ES-UBL-0002', (new RecordMapper)->map($invoice)->description);
    }

    public function test_seller_without_spanish_nif_is_refused(): void
    {
        foreach (['DE123456789', 'ESB64829178'] as $vat) {
            $seller = new Party(name: SpanishInvoices::SELLER_NAME, vatIdentifier: $vat);
            $invoice = SpanishInvoices::invoice(
                [SpanishInvoices::line('1', '100.00', VatCategory::StandardRate, '21')],
                seller: $seller,
            );

            self::assertSame(['IDEmisorFactura (BT-31)'], self::fields(self::problems($invoice)), $vat);
        }
    }

    public function test_seller_tax_registration_is_the_fallback(): void
    {
        $seller = new Party(
            name: SpanishInvoices::SELLER_NAME,
            taxRegistration: new TaxRegistration('B64829179'),
        );
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '100.00', VatCategory::StandardRate, '21')],
            seller: $seller,
        );

        self::assertSame('B64829179', (new RecordMapper)->map($invoice)->id->issuer->value);
    }

    public function test_every_problem_is_reported_at_once(): void
    {
        $invoice = SpanishInvoices::invoice(
            [
                SpanishInvoices::line('1', '100.00', VatCategory::Exempt, null),
                SpanishInvoices::line('2', '100.00', VatCategory::StandardRate, '21'),
            ],
            buyer: new Party(name: 'Consumidor Final'),
            currency: CurrencyCode::USD,
            seller: new Party(name: SpanishInvoices::SELLER_NAME, vatIdentifier: 'DE123456789'),
        );

        self::assertSame(
            ['IDEmisorFactura (BT-31)', 'Destinatarios (BT-48)', 'OperacionExenta (E|-)', 'BT-5'],
            self::fields(self::problems($invoice)),
        );
    }

    /** @return iterable<string, array{Invoice}> */
    public static function mappableSamples(): iterable
    {
        yield 'domestic' => [SpanishInvoices::domestic()];
        yield 'export' => [SpanishInvoices::export()];
        yield 'intraCommunity' => [SpanishInvoices::intraCommunity()];
        yield 'reverseCharge' => [SpanishInvoices::reverseCharge()];
        foreach ([ExemptOperation::E1, ExemptOperation::E3, ExemptOperation::E4, ExemptOperation::E6] as $article) {
            yield 'exempt '.$article->value => [SpanishInvoices::exempt($article)];
        }
        yield 'creditNote' => [SpanishInvoices::creditNote()];
        yield 'igic' => [SpanishInvoices::igic()];
        yield 'usd with EUR amounts' => [SpanishInvoices::usd()->withExtension(new SpanishInvoice(euroAmounts: new EuroAmounts(
            Amount::of('19.40'),
            Amount::of('111.79'),
            ['S|21' => ['base' => Amount::of('92.39'), 'quota' => Amount::of('19.40')]],
        )))];
    }

    #[Group('verifactu-official')]
    #[DataProvider('mappableSamples')]
    public function test_every_mapped_sample_chains_into_a_schema_valid_record(Invoice $invoice): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $draft = (new RecordMapper)->map($invoice);
        [$record] = (new Chainer(VerifactuSamples::system()))->append($draft, null, new DateTimeImmutable('2026-11-23T10:00:00+01:00'));

        $document = SafeXml::load((new RecordWriter)->write($record));
        $errors = (new SchemaValidator)->validate($document, $schemas->informationXsd(), $schemas->localPrefixes());

        self::assertSame([], array_map(static fn ($error): string => $error->message, $errors));
    }

    /** @return list<?string> tax, regime, qualification, exemption, rate, base, quota */
    private static function describe(TaxBreakdownLine $line): array
    {
        return [
            $line->tax?->value,
            $line->regime?->value,
            $line->qualification?->value,
            $line->exemption?->value,
            $line->rate?->text,
            $line->base->text,
            $line->quota?->text,
        ];
    }

    /** @return list<string> */
    private static function problems(Invoice $invoice): array
    {
        try {
            (new RecordMapper)->map($invoice);
        } catch (MappingRefused $e) {
            return $e->problems;
        }

        self::fail('The mapper accepted the invoice.');
    }

    /**
     * The field part of each problem ("<field>: <reason>").
     *
     * @param  list<string>  $problems
     * @return list<string>
     */
    private static function fields(array $problems): array
    {
        return array_map(static fn (string $problem): string => explode(': ', $problem, 2)[0], $problems);
    }
}

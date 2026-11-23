<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Verifactu;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\GeneratedBy;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\AnulacionOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\CorrectionAmounts;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\ForeignId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordWriter;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RecordWriterTest extends TestCase
{
    /** @return iterable<string, array{BillingRecord}> */
    public static function samples(): iterable
    {
        foreach (self::sampleRecords() as $name => $record) {
            yield $name => [$record];
        }
    }

    /** @return array<string, BillingRecord> */
    public static function sampleRecords(): array
    {
        $system = VerifactuSamples::system();
        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
        $second = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
        );
        $cancellation = CancellationRecord::create(
            CancellationDraft::of(VerifactuSamples::invoiceId('12345679/G34', '01-01-2024')),
            ChainLink::after($second->invoiceId(), $second->fingerprint()),
            $system,
            RecordTimestamp::fromLiteral('2024-01-01T19:20:40+01:00'),
        );

        return [
            'vector 1 (first alta)' => $first,
            'vector 2 (second alta)' => $second,
            'vector 3 (anulacion)' => $cancellation,
            'every optional field' => self::fullRecord($cancellation),
            'cancellation with every optional field' => self::fullCancellation($second),
        ];
    }

    #[Group('verifactu-official')]
    #[DataProvider('samples')]
    public function test_every_sample_record_is_schema_valid(BillingRecord $record): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $document = SafeXml::load((new RecordWriter)->write($record));

        $errors = (new SchemaValidator)->validate($document, $schemas->informationXsd(), $schemas->localPrefixes());

        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
    }

    #[DataProvider('samples')]
    public function test_hash_input_is_built_from_the_written_text_nodes(BillingRecord $record): void
    {
        $root = SafeXml::load((new RecordWriter)->write($record))->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);

        $this->assertSame(Fingerprint::input($record), Fingerprint::inputOf($root));
        $this->assertSame($record->fingerprint(), self::text($root, 'Huella'));
    }

    public function test_full_record_writes_its_fields_in_schema_order(): void
    {
        $records = self::sampleRecords();
        $xml = (new RecordWriter)->write($records['every optional field']);
        $root = SafeXml::load($xml)->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);

        $names = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $names[] = $child->localName;
            }
        }
        $this->assertSame([
            'IDVersion', 'IDFactura', 'RefExterna', 'NombreRazonEmisor', 'Subsanacion', 'TipoFactura',
            'TipoRectificativa', 'FacturasRectificadas', 'FacturasSustituidas', 'ImporteRectificacion',
            'FechaOperacion', 'DescripcionOperacion', 'FacturaSimplificadaArt7273',
            'FacturaSinIdentifDestinatarioArt61d', 'Macrodato', 'EmitidaPorTerceroODestinatario', 'Tercero',
            'Destinatarios', 'Cupon', 'Desglose', 'CuotaTotal', 'ImporteTotal', 'Encadenamiento',
            'SistemaInformatico', 'FechaHoraHusoGenRegistro', 'NumRegistroAcuerdoFacturacion',
            'IdAcuerdoSistemaInformatico', 'TipoHuella', 'Huella',
        ], $names);
        $this->assertSame(12, $root->getElementsByTagNameNS(VerifactuFormat::SF_NS, 'DetalleDesglose')->length);
        $this->assertStringContainsString('<sum1:FechaOperacion>31-12-2023</sum1:FechaOperacion>', $xml);
        $this->assertStringContainsString('<sum1:BaseRectificada>-10.00</sum1:BaseRectificada>', $xml);
        $this->assertStringContainsString('<sum1:CodigoPais>FR</sum1:CodigoPais>', $xml);
        $this->assertStringContainsString('<sum1:IDType>02</sum1:IDType>', $xml);
        $this->assertStringContainsString('<sum1:OperacionExenta>E1</sum1:OperacionExenta>', $xml);
        $this->assertStringNotContainsString('Signature', $xml);
    }

    public function test_first_record_writes_primer_registro_s(): void
    {
        $xml = (new RecordWriter)->write(self::sampleRecords()['vector 1 (first alta)']);

        $this->assertStringContainsString('<sum1:PrimerRegistro>S</sum1:PrimerRegistro>', $xml);
        $this->assertStringNotContainsString('RegistroAnterior', $xml);
    }

    public function test_initial_record_omits_n_flags(): void
    {
        $xml = (new RecordWriter)->write(self::sampleRecords()['vector 2 (second alta)']);

        foreach (['Subsanacion', 'RechazoPrevio', 'FacturaSimplificadaArt7273', 'FacturaSinIdentifDestinatarioArt61d', 'Macrodato', 'Cupon', 'RefExterna', 'TipoRectificativa'] as $absent) {
            $this->assertStringNotContainsString('<sum1:'.$absent.'>', $xml, $absent);
        }
        $this->assertStringContainsString('<sum1:TipoUsoPosibleSoloVerifactu>S</sum1:TipoUsoPosibleSoloVerifactu>', $xml);
        $this->assertStringContainsString('<sum1:TipoUsoPosibleMultiOT>N</sum1:TipoUsoPosibleMultiOT>', $xml);
        $this->assertStringContainsString('<sum1:IndicadorMultiplesOT>N</sum1:IndicadorMultiplesOT>', $xml);
        $this->assertStringContainsString('<sum1:CuotaTotal>12.35</sum1:CuotaTotal>', $xml);
        $this->assertStringContainsString('<sum1:TipoImpositivo>10.00</sum1:TipoImpositivo>', $xml);
        $this->assertStringContainsString('<sum1:FechaHoraHusoGenRegistro>2024-01-01T19:20:35+01:00</sum1:FechaHoraHusoGenRegistro>', $xml);
        $this->assertStringContainsString('<sum1:Huella>3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60</sum1:Huella>', $xml);
    }

    public function test_correction_after_rejection_writes_s_and_s(): void
    {
        $record = self::alta(self::draftWith(operation: AltaOperation::CorrectionAfterRejection));
        $xml = (new RecordWriter)->write($record);

        $this->assertStringContainsString("<sum1:Subsanacion>S</sum1:Subsanacion>\n  <sum1:RechazoPrevio>S</sum1:RechazoPrevio>", $xml);
    }

    public function test_after_rejection_writes_s_and_x(): void
    {
        $xml = (new RecordWriter)->write(self::alta(self::draftWith(operation: AltaOperation::AfterRejection)));

        $this->assertStringContainsString('<sum1:Subsanacion>S</sum1:Subsanacion>', $xml);
        $this->assertStringContainsString('<sum1:RechazoPrevio>X</sum1:RechazoPrevio>', $xml);
    }

    public function test_cancellation_writes_its_flags_only_when_set(): void
    {
        $records = self::sampleRecords();
        $plain = (new RecordWriter)->write($records['vector 3 (anulacion)']);
        $full = (new RecordWriter)->write($records['cancellation with every optional field']);

        $this->assertStringStartsWith("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sum1:RegistroAnulacion", $plain);
        $this->assertStringNotContainsString('SinRegistroPrevio', $plain);
        $this->assertStringNotContainsString('RechazoPrevio', $plain);
        $this->assertStringNotContainsString('GeneradoPor', $plain);
        $this->assertStringContainsString('<sum1:NumSerieFacturaAnulada>12345679/G34</sum1:NumSerieFacturaAnulada>', $plain);
        $this->assertStringContainsString('<sum1:SinRegistroPrevio>S</sum1:SinRegistroPrevio>', $full);
        $this->assertStringContainsString('<sum1:RechazoPrevio>S</sum1:RechazoPrevio>', $full);
        $this->assertStringContainsString('<sum1:GeneradoPor>T</sum1:GeneradoPor>', $full);
    }

    public function test_ampersand_is_escaped(): void
    {
        $record = self::alta(self::draftWith(number: '12345678&G33'));
        $xml = (new RecordWriter)->write($record);

        $this->assertStringContainsString('<sum1:NumSerieFactura>12345678&amp;G33</sum1:NumSerieFactura>', $xml);
        $root = SafeXml::load($xml)->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertStringContainsString('NumSerieFactura=12345678&G33&', Fingerprint::inputOf($root));
        $this->assertSame(Fingerprint::input($record), Fingerprint::inputOf($root));
    }

    public function test_blank_optional_texts_are_omitted(): void
    {
        $xml = (new RecordWriter)->write(self::alta(self::draftWith(blankOptionals: true)));

        $this->assertStringNotContainsString('RefExterna', $xml);
        $this->assertStringNotContainsString('NumRegistroAcuerdoFacturacion', $xml);
        $this->assertStringNotContainsString('IdAcuerdoSistemaInformatico', $xml);
    }

    public function test_output_is_deterministic(): void
    {
        $writer = new RecordWriter;
        foreach (self::sampleRecords() as $record) {
            $this->assertSame($writer->write($record), $writer->write($record));
            $this->assertSame($writer->write($record), (new RecordWriter)->write($record));
        }
    }

    public function test_append_to_imports_the_record_under_the_parent(): void
    {
        $document = SafeXml::load('<sum:RegistroFactura xmlns:sum="'.VerifactuFormat::LR_NS.'" xmlns:sum1="'.VerifactuFormat::SF_NS.'"/>');
        $parent = $document->documentElement;
        $this->assertInstanceOf(DOMElement::class, $parent);
        $record = self::sampleRecords()['vector 2 (second alta)'];

        $element = (new RecordWriter)->appendTo($parent, $record);

        $this->assertSame($parent, $element->parentNode);
        $this->assertSame('RegistroAlta', $element->localName);
        $this->assertSame(VerifactuFormat::SF_NS, $element->namespaceURI);
        $this->assertSame(Fingerprint::input($record), Fingerprint::inputOf($element));
    }

    public function test_input_of_refuses_an_element_that_is_no_record(): void
    {
        $root = SafeXml::load('<Other/>')->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);

        $this->expectException(InvalidRecord::class);
        Fingerprint::inputOf($root);
    }

    public function test_writer_with_schemas_throws_on_schema_error(): void
    {
        $dir = BuildDir::create('verifactu-writer-test');
        try {
            mkdir($dir.'/'.VerifactuSchemas::DIR);
            file_put_contents(
                $dir.'/'.VerifactuSchemas::DIR.'/SuministroInformacion.xsd',
                '<?xml version="1.0"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="Other"/></xs:schema>',
            );
            $writer = new RecordWriter(new VerifactuSchemas($dir));

            try {
                $writer->write(self::sampleRecords()['vector 1 (first alta)']);
                $this->fail('The schema error was not reported.');
            } catch (InvalidRecord $e) {
                $this->assertStringContainsString('SuministroInformacion.xsd', $e->getMessage());
                $this->assertStringContainsString('No matching global declaration available for the validation root', $e->getMessage());
            }
        } finally {
            BuildDir::remove($dir);
        }
    }

    #[Group('verifactu-official')]
    public function test_writer_with_installed_schemas_accepts_valid_records(): void
    {
        $writer = new RecordWriter(Fixtures::verifactuSchemas());

        foreach (self::sampleRecords() as $record) {
            $this->assertStringContainsString('<sum1:TipoHuella>01</sum1:TipoHuella>', $writer->write($record));
        }
    }

    private static function text(DOMElement $root, string $name): string
    {
        $value = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $value = $child->textContent;
            }
        }

        return (string) $value;
    }

    private static function alta(RegistrationDraft $draft): RegistrationRecord
    {
        return RegistrationRecord::create(
            $draft,
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
    }

    private static function draftWith(
        string $number = '12345678/G33',
        AltaOperation $operation = AltaOperation::Initial,
        bool $blankOptionals = false,
    ): RegistrationDraft {
        $base = VerifactuSamples::vectorDraft($number, '01-01-2024');

        return new RegistrationDraft(
            id: $base->id,
            issuerName: $base->issuerName,
            type: $base->type,
            correctionType: null,
            corrected: [],
            substituted: [],
            correctionAmounts: null,
            operationDate: null,
            description: $base->description,
            simplifiedArt7273: false,
            withoutRecipientArt61d: false,
            macrodato: false,
            issuedBy: null,
            thirdParty: null,
            recipients: $base->recipients,
            coupon: false,
            breakdown: $base->breakdown,
            totalTax: $base->totalTax,
            total: $base->total,
            externalReference: $blankOptionals ? '  ' : null,
            agreementNumber: $blankOptionals ? '' : null,
            systemAgreementId: $blankOptionals ? ' ' : null,
            operation: $operation,
        );
    }

    /**
     * A record that fills every optional element of RegistroAlta. The combination satisfies the
     * schema only; it is not meant to pass the AEAT business rules.
     */
    private static function fullRecord(BillingRecord $previous): RegistrationRecord
    {
        $issueDate = DateTimeImmutable::createFromFormat('!d-m-Y', '02-01-2024');
        $operationDate = DateTimeImmutable::createFromFormat('!d-m-Y', '31-12-2023');
        self::assertInstanceOf(DateTimeImmutable::class, $issueDate);
        self::assertInstanceOf(DateTimeImmutable::class, $operationDate);

        $lines = [];
        for ($i = 0; $i < 12; $i++) {
            $lines[] = $i % 2 === 0
                ? new TaxBreakdownLine(TaxType::Iva, RegimeKey::Key01, OperationQualification::S1, null, TaxRate::of('21'), Amount::of('10'), Amount::of('9'), Amount::of('2.10'), TaxRate::of('5.2'), Amount::of('0.52'))
                : new TaxBreakdownLine(TaxType::Igic, RegimeKey::Key02, null, ExemptOperation::E1, null, Amount::of('5'), null, null, null, null);
        }

        $draft = new RegistrationDraft(
            id: VerifactuSamples::invoiceId('R-2024/0001', '02-01-2024'),
            issuerName: 'Test Issuer S.L.',
            type: InvoiceType::R1,
            correctionType: CorrectionType::Substitution,
            corrected: [VerifactuSamples::invoiceId('12345678/G33', '01-01-2024')],
            substituted: [VerifactuSamples::invoiceId('12345679/G34', '01-01-2024'), VerifactuSamples::invoiceId('12345680/G35', '01-01-2024')],
            correctionAmounts: new CorrectionAmounts(Amount::of('-10'), Amount::of('-2.10'), Amount::of('-0.52')),
            operationDate: $operationDate,
            description: 'Rectificación por sustitución',
            simplifiedArt7273: true,
            withoutRecipientArt61d: true,
            macrodato: true,
            issuedBy: IssuedBy::ThirdParty,
            thirdParty: new TaxParty('Third Party S.L.', Nif::parse('B64829179'), null),
            recipients: [
                VerifactuSamples::recipient(),
                new TaxParty('Client Français SARL', null, new ForeignId(ForeignIdType::VatNumber, 'FR12345678901', CountryCode::FR)),
            ],
            coupon: true,
            breakdown: $lines,
            totalTax: Amount::of('15.72'),
            total: Amount::of('105.72'),
            externalReference: 'EXT-0001',
            agreementNumber: 'ACU-123',
            systemAgreementId: 'SIS-456',
            operation: AltaOperation::Correction,
        );

        return RegistrationRecord::create(
            $draft,
            ChainLink::after($previous->invoiceId(), $previous->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-02T09:00:00+01:00'),
        );
    }

    private static function fullCancellation(BillingRecord $previous): CancellationRecord
    {
        return CancellationRecord::create(
            CancellationDraft::of(
                VerifactuSamples::invoiceId('12345678/G33', '01-01-2024'),
                AnulacionOperation::WithoutPriorRecordAfterRejection,
                GeneratedBy::ThirdParty,
                new TaxParty('Generator Ltd', null, new ForeignId(ForeignIdType::Passport, 'P1234567', null)),
                'EXT-ANUL',
            ),
            ChainLink::after($previous->invoiceId(), $previous->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-03T10:00:00+01:00'),
        );
    }
}

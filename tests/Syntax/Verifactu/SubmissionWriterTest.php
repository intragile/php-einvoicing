<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Verifactu;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Representative;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\SubmissionWriter;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class SubmissionWriterTest extends TestCase
{
    #[Group('verifactu-official')]
    public function test_submission_is_schema_valid(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $xml = (new SubmissionWriter)->write(self::submission(withRepresentative: true));

        $errors = (new SchemaValidator)->validate(SafeXml::load($xml), $schemas->submissionXsd(), $schemas->localPrefixes());

        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
        $this->assertStringContainsString('<sum1:FechaFinVeriFactu>31-12-2026</sum1:FechaFinVeriFactu>', $xml);
        $this->assertStringContainsString('<sum1:Incidencia>N</sum1:Incidencia>', $xml);
    }

    #[Group('verifactu-official')]
    public function test_writer_with_installed_schemas_accepts_a_minimal_submission(): void
    {
        $xml = (new SubmissionWriter(Fixtures::verifactuSchemas()))->write(self::submission(withRepresentative: false));

        $this->assertStringNotContainsString('Representante', $xml);
        $this->assertStringNotContainsString('RemisionVoluntaria', $xml);
    }

    public function test_submission_writes_header_then_one_registro_factura_per_record(): void
    {
        $submission = self::submission(withRepresentative: true);
        $xml = (new SubmissionWriter)->write($submission);
        $root = SafeXml::load($xml)->documentElement;
        $this->assertInstanceOf(DOMElement::class, $root);

        $this->assertSame(VerifactuFormat::LR_NS, $root->namespaceURI);
        $this->assertSame('sum:RegFactuSistemaFacturacion', $root->tagName);
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child->tagName;
            }
        }
        $this->assertSame(['sum:Cabecera', 'sum:RegistroFactura', 'sum:RegistroFactura'], $children);
        $this->assertStringContainsString(
            "<sum:Cabecera>\n    <sum1:ObligadoEmision>\n      <sum1:NombreRazon>Test Issuer S.L.</sum1:NombreRazon>\n      <sum1:NIF>89890001K</sum1:NIF>\n    </sum1:ObligadoEmision>\n    <sum1:Representante>\n      <sum1:NombreRazon>Asesoría Fiscal S.L.</sum1:NombreRazon>\n      <sum1:NIF>B64829179</sum1:NIF>",
            $xml,
        );

        $records = $root->getElementsByTagNameNS(VerifactuFormat::SF_NS, 'RegistroAlta');
        $this->assertSame(2, $records->length);
        foreach ($submission->records as $i => $record) {
            $element = $records->item($i);
            $this->assertInstanceOf(DOMElement::class, $element);
            $this->assertSame(Fingerprint::input($record), Fingerprint::inputOf($element));
        }
    }

    public function test_submission_output_is_deterministic(): void
    {
        $submission = self::submission(withRepresentative: true);

        $this->assertSame((new SubmissionWriter)->write($submission), (new SubmissionWriter)->write($submission));
    }

    public function test_submission_refuses_foreign_issuer_and_1001_records(): void
    {
        $header = new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.');
        $foreign = RegistrationRecord::create(
            VerifactuSamples::draft(new InvoiceId(Nif::parse('A58818501'), 'X-1', new DateTimeImmutable('2024-01-01'))),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );

        try {
            new Submission($header, [self::records()[0], $foreign]);
            $this->fail('A record of another issuer was accepted.');
        } catch (InvalidRecord $e) {
            $this->assertStringContainsString('A58818501', $e->getMessage());
        }

        $record = self::records()[0];
        try {
            new Submission($header, array_fill(0, 1001, $record));
            $this->fail('1001 records were accepted.');
        } catch (InvalidRecord $e) {
            $this->assertStringContainsString('1001', $e->getMessage());
        }

        $this->expectException(InvalidRecord::class);
        new Submission($header, []);
    }

    public function test_submission_accepts_1000_records(): void
    {
        $header = new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.');

        $this->assertCount(1000, (new Submission($header, array_fill(0, 1000, self::records()[0])))->records);
    }

    public function test_header_refuses_overlong_names(): void
    {
        $this->expectException(InvalidRecord::class);
        new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test', new Representative(str_repeat('a', 121), Nif::parse('B64829179')));
    }

    private static function submission(bool $withRepresentative): Submission
    {
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', '2026-12-31');
        self::assertInstanceOf(DateTimeImmutable::class, $endDate);

        $header = $withRepresentative
            ? new SubmissionHeader(
                Nif::parse(VerifactuSamples::ISSUER_NIF),
                ' Test Issuer S.L. ',
                new Representative('Asesoría Fiscal S.L.', Nif::parse('B64829179')),
                $endDate,
                false,
            )
            : new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.');

        return new Submission($header, self::records());
    }

    /** @return list<BillingRecord> */
    private static function records(): array
    {
        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
        $second = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
        );

        return [$first, $second];
    }
}

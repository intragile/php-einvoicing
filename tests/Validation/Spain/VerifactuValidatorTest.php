<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Spain;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Chainer;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordMapper;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordWriter;
use IntrAgile\EInvoicing\Syntax\Verifactu\SubmissionWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\France\Negative\Mutator;
use IntrAgile\EInvoicing\Tests\Spain\Verifactu\RecordMapperTest;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Tests\Syntax\Verifactu\RecordWriterTest;
use IntrAgile\EInvoicing\Validation\MessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * VerifactuValidator: the schema chosen by the document element, the business rules run on every
 * record of a bare record or a submission, and the valid corpus left without fatal findings.
 */
#[Group('verifactu-official')]
final class VerifactuValidatorTest extends TestCase
{
    private const E3_SAMPLE = 'exempt E3';

    /**
     * @return array<string, array{string}>
     */
    public static function validCorpus(): array
    {
        $documents = [];
        foreach (VerifactuRulesTest::samples() as $name => $xml) {
            $documents['sample/'.$name] = [$xml];
        }
        foreach (Fixtures::verifactuB2Brouter() as $name => $xml) {
            $documents['b2brouter/'.$name] = [$xml];
        }
        foreach (RecordMapperTest::mappableSamples() as $name => [$invoice]) {
            $documents['mapped/'.$name] = [self::mapped($invoice)];
        }

        return $documents;
    }

    #[DataProvider('validCorpus')]
    public function test_valid_corpus_has_no_fatal_findings(string $xml): void
    {
        $report = VerifactuRulesTest::validator()->validate($xml);

        self::assertSame([], VerifactuRulesTest::schemaMessages($report));
        self::assertSame([], VerifactuRulesTest::describe($report->fatal()));
        self::assertSame([], VerifactuRulesTest::describe($report->findings));
        self::assertTrue($report->isValid());
        self::assertSame(MessageType::Verifactu, $report->type);
        self::assertSame(['verifactu-xsd tikeV1.0', 'es-verifactu 1.2.2'], $report->ruleSets);
    }

    public function test_valid_corpus_holds_every_document(): void
    {
        self::assertCount(3 + 4 + 12, self::validCorpus());
    }

    /**
     * Regime 01 refuses E2 and E3 (error 1199), so the mapper writes the E3 exemption under the
     * export regime 02 and the record passes ES-VF-14.
     */
    public function test_mapped_e3_exemption_uses_regime_02(): void
    {
        $samples = iterator_to_array(RecordMapperTest::mappableSamples());
        self::assertArrayHasKey(self::E3_SAMPLE, $samples);
        $xml = self::mapped($samples[self::E3_SAMPLE][0]);

        $report = VerifactuRulesTest::validator()->validate($xml);

        self::assertStringContainsString('<sum1:ClaveRegimen>02</sum1:ClaveRegimen>', $xml);
        self::assertStringContainsString('<sum1:OperacionExenta>E3</sum1:OperacionExenta>', $xml);
        self::assertSame([], $report->verdict());
    }

    public function test_aeat_vectors_dated_2024_fire_only_es_vf_01(): void
    {
        $records = RecordWriterTest::sampleRecords();
        $validator = VerifactuRulesTest::validator();

        $first = $validator->validate((new RecordWriter)->write($records['vector 1 (first alta)']));
        $cancellation = $validator->validate((new RecordWriter)->write($records['vector 3 (anulacion)']));

        self::assertSame([], VerifactuRulesTest::schemaMessages($first));
        self::assertSame(['ES-VF-01 fatal'], $first->verdict());
        self::assertSame('/sum1:RegistroAlta[1]/sum1:IDFactura[1]/sum1:FechaExpedicionFactura[1]', $first->findings[0]->location);
        self::assertSame('Validaciones v1.2.2 §3.1.3.1; AEAT error 1152', $first->findings[0]->test);
        self::assertFalse($first->isValid());
        // The cancellation of a 2024 invoice has no issue date rule (Val. §3.1.4).
        self::assertSame([], $cancellation->verdict());
    }

    public function test_rules_run_when_the_schema_fails(): void
    {
        $xml = VerifactuRulesTest::rehash(Mutator::apply(Fixtures::verifactuB2Brouter()['record-1064493.xml'], [
            ['/sf:RegistroAlta/sf:IDFactura/sf:FechaExpedicionFactura', 'set', '01-01-2024'],
            ['/sf:RegistroAlta/sf:TipoHuella', 'set', 'ZZ'],
        ], VerifactuRulesTest::NAMESPACES));

        $report = VerifactuRulesTest::validator()->validate($xml);

        self::assertCount(1, $report->schemaErrors);
        self::assertStringContainsString('TipoHuella', $report->schemaErrors[0]->message);
        self::assertSame(['ES-VF-01 fatal'], $report->verdict());
    }

    public function test_submission_is_validated_per_record(): void
    {
        $xml = (new SubmissionWriter)->write(self::submission());
        $validator = VerifactuRulesTest::validator();

        $clean = $validator->validate($xml);
        $broken = $validator->validate(VerifactuRulesTest::rehash(Mutator::apply($xml, [
            ['/lr:RegFactuSistemaFacturacion/lr:RegistroFactura[2]/sf:RegistroAlta/sf:CuotaTotal', 'set', '22.36'],
        ], VerifactuRulesTest::NAMESPACES)));

        self::assertSame([], VerifactuRulesTest::schemaMessages($clean));
        self::assertSame([], $clean->verdict());
        self::assertSame([], VerifactuRulesTest::schemaMessages($broken));
        self::assertSame(['ES-VF-21 warning'], $broken->verdict());
        self::assertSame('/sum:RegFactuSistemaFacturacion[1]/sum:RegistroFactura[2]/sum1:RegistroAlta[1]/sum1:CuotaTotal[1]', $broken->findings[0]->location);
    }

    public function test_submission_is_checked_against_the_submission_schema(): void
    {
        $xml = Mutator::apply((new SubmissionWriter)->write(self::submission()), [
            ['/lr:RegFactuSistemaFacturacion/lr:Cabecera', 'remove'],
        ], VerifactuRulesTest::NAMESPACES);

        $report = VerifactuRulesTest::validator()->validate($xml);

        self::assertNotSame([], $report->schemaErrors);
        self::assertSame([], $report->verdict());
    }

    public function test_foreign_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('{urn:example}RegistroAlta');

        VerifactuRulesTest::validator()->validate('<RegistroAlta xmlns="urn:example"/>');
    }

    private static function mapped(Invoice $invoice): string
    {
        $draft = (new RecordMapper)->map($invoice);
        [$record] = (new Chainer(VerifactuSamples::system()))->append($draft, null, new DateTimeImmutable('2026-11-23T10:00:00+01:00'));

        return (new RecordWriter)->write($record);
    }

    private static function submission(): Submission
    {
        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-11-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-11-01T19:20:30+01:00'),
        );
        $second = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-11-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-11-01T19:20:35+01:00'),
        );

        return new Submission(new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.'), [$first, $second]);
    }
}

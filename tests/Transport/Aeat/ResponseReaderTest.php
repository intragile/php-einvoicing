<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Aeat\DuplicateState;
use IntrAgile\EInvoicing\Transport\Aeat\LookupState;
use IntrAgile\EInvoicing\Transport\Aeat\RecordOutcome;
use IntrAgile\EInvoicing\Transport\Aeat\RecordState;
use IntrAgile\EInvoicing\Transport\Aeat\ResponseReader;
use IntrAgile\EInvoicing\Transport\Aeat\SoapEnvelope;
use IntrAgile\EInvoicing\Transport\Aeat\SubmissionState;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use PHPUnit\Framework\TestCase;

final class ResponseReaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/spain/aeat';

    private const HASH_1 = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';

    public function test_correcto_reads_csv_wait_presenter_and_lines(): void
    {
        $response = (new ResponseReader)->submission(self::payload('correcto.xml'));

        $this->assertSame('A1B2C3D4E5F6G7H8', $response->csv);
        $this->assertSame(60, $response->waitSeconds);
        $this->assertSame(SubmissionState::Correcto, $response->state);
        $this->assertSame('89890001K', $response->presenterNif);
        $this->assertSame('2024-01-01T19:21:02+01:00', $response->presentedAt?->format(DATE_ATOM));
        $this->assertCount(2, $response->lines);
        $this->assertSame('12345678/G33', $response->lines[0]->invoiceId->number);
        $this->assertSame('89890001K', $response->lines[0]->invoiceId->issuer->value);
        $this->assertSame('01-01-2024', $response->lines[0]->invoiceId->dateText());
        $this->assertSame('Alta', $response->lines[0]->operation);
        $this->assertNull($response->lines[0]->errorCode);
        $this->assertSame([RecordOutcome::Accepted, RecordOutcome::Accepted], $response->outcomes(self::submission(self::first(), self::second())));
        $this->assertStringStartsWith('<tikR:RespuestaRegFactuSistemaFacturacion', $response->xml);
        $this->assertStringContainsString('<tikR:CSV>A1B2C3D4E5F6G7H8</tikR:CSV>', $response->xml);
    }

    public function test_lines_are_matched_by_invoice_not_position(): void
    {
        $submission = self::submission(self::first(), self::second(), self::third());

        $response = (new ResponseReader)->submission(self::payload('parcialmente-correcto.xml'));

        $this->assertSame(SubmissionState::ParcialmenteCorrecto, $response->state);
        $this->assertSame(
            [RecordOutcome::Rejected, RecordOutcome::AcceptedWithErrors, RecordOutcome::Unconfirmed],
            $response->outcomes($submission),
        );
        $rejected = $response->lineFor(self::first());
        $this->assertNotNull($rejected);
        $this->assertSame(1142, $rejected->errorCode);
        $this->assertSame(RecordState::Incorrecto, $rejected->state);
        $withErrors = $response->lineFor(self::second());
        $this->assertNotNull($withErrors);
        $this->assertSame(2005, $withErrors->errorCode);
        $this->assertSame(RecordState::AceptadoConErrores, $withErrors->state);
        $this->assertNotNull($withErrors->errorDescription);
        $this->assertNull($response->lineFor(self::third()));
        $this->assertSame([], $response->unmatched($submission));
    }

    public function test_outcomes_hold_one_entry_per_record_in_submission_order(): void
    {
        $submission = self::submission(self::first(), self::third(), self::second());

        $outcomes = (new ResponseReader)->submission(self::payload('correcto.xml'))->outcomes($submission);

        $this->assertSame([RecordOutcome::Accepted, RecordOutcome::Unconfirmed, RecordOutcome::Accepted], $outcomes);
    }

    public function test_unmatched_line_is_listed_not_thrown(): void
    {
        $submission = self::submission(self::first());

        $response = (new ResponseReader)->submission(self::payload('correcto.xml'));

        $this->assertSame([RecordOutcome::Accepted], $response->outcomes($submission));
        $unmatched = $response->unmatched($submission);
        $this->assertCount(1, $unmatched);
        $this->assertSame('12345679/G34', $unmatched[0]->invoiceId->number);
        $this->assertSame('A1B2C3D4E5F6G7H8', $response->csv);
    }

    public function test_a_cancellation_is_not_matched_by_an_alta_line(): void
    {
        $cancellation = self::cancellation();

        $response = (new ResponseReader)->submission(self::payload('correcto.xml'));

        $this->assertNull($response->lineFor($cancellation));
        $this->assertSame([RecordOutcome::Unconfirmed], $response->outcomes(self::submission($cancellation)));
    }

    public function test_duplicate_correcta_is_already_registered(): void
    {
        $response = (new ResponseReader)->submission(self::payload('duplicado-correcta.xml'));

        $this->assertNull($response->csv);
        $line = $response->lineFor(self::first());
        $this->assertNotNull($line);
        $this->assertSame(DuplicateState::Correcta, $line->duplicateState);
        $this->assertSame('202401011921020001', $line->duplicateRequestId);
        $this->assertSame(3000, $line->errorCode);
        $this->assertSame(RecordOutcome::AlreadyRegistered, $line->outcome());
        $this->assertSame(DeliveryState::Delivered, $line->outcome()->deliveryState(false));
    }

    public function test_duplicate_anulada_is_already_cancelled(): void
    {
        $response = (new ResponseReader)->submission(self::payload('duplicado-anulada.xml'));

        $line = $response->lineFor(self::cancellation());
        $this->assertNotNull($line);
        $this->assertSame('Anulacion', $line->operation);
        $this->assertSame(DuplicateState::Anulada, $line->duplicateState);
        $this->assertSame(RecordOutcome::AlreadyCancelled, $line->outcome());
        $this->assertSame(DeliveryState::Delivered, $line->outcome()->deliveryState(true));
        $this->assertSame(DeliveryState::Failed, $line->outcome()->deliveryState(false));
        $this->assertNull($response->lineFor(self::second()));
    }

    public function test_incorrecto_has_no_csv(): void
    {
        $response = (new ResponseReader)->submission(self::payload('incorrecto.xml'));

        $this->assertNull($response->csv);
        $this->assertNull($response->presenterNif);
        $this->assertNull($response->presentedAt);
        $this->assertSame(SubmissionState::Incorrecto, $response->state);
        $this->assertSame([RecordOutcome::Rejected], $response->outcomes(self::submission(self::first())));
    }

    public function test_unknown_enum_value_is_unsupported(): void
    {
        $xml = str_replace('<tikR:EstadoEnvio>Correcto<', '<tikR:EstadoEnvio>Raro<', self::read('correcto.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('EstadoEnvio');

        (new ResponseReader)->submission(self::open($xml));
    }

    public function test_missing_mandatory_element_is_unsupported(): void
    {
        $xml = (string) preg_replace('#<tikR:TiempoEsperaEnvio>60</tikR:TiempoEsperaEnvio>#', '', self::read('correcto.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('TiempoEsperaEnvio');

        (new ResponseReader)->submission(self::open($xml));
    }

    public function test_non_numeric_error_code_is_unsupported(): void
    {
        $xml = str_replace('>1142<', '>11x2<', self::read('parcialmente-correcto.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CodigoErrorRegistro');

        (new ResponseReader)->submission(self::open($xml));
    }

    public function test_wrong_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('RespuestaRegFactuSistemaFacturacion');

        (new ResponseReader)->submission(self::payload('consulta-con-datos.xml'));
    }

    public function test_lookup_wrong_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('RespuestaConsultaFactuSistemaFacturacion');

        (new ResponseReader)->lookup(self::payload('correcto.xml'), self::first()->invoiceId());
    }

    public function test_lookup_with_data_matches_the_vector_record(): void
    {
        $record = self::first();

        $status = (new ResponseReader)->lookup(self::payload('consulta-con-datos.xml'), $record->invoiceId());

        $this->assertNotNull($status);
        $this->assertTrue($status->invoiceId->equals($record->invoiceId()));
        $this->assertSame(LookupState::Correcto, $status->state);
        $this->assertSame(self::HASH_1, $status->fingerprint);
        $this->assertSame('2024-01-01T19:21:02+01:00', $status->lastModified?->format(DATE_ATOM));
        $this->assertNull($status->errorCode);
        $this->assertTrue($status->matches($record));
        $this->assertFalse($status->confirmsCancellation());
        $this->assertFalse($status->matches(self::second()));

        $changed = str_replace(self::HASH_1.'</tikLRRC:Huella>', '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F61</tikLRRC:Huella>', self::read('consulta-con-datos.xml'));
        $altered = (new ResponseReader)->lookup(self::open($changed), $record->invoiceId());
        $this->assertNotNull($altered);
        $this->assertFalse($altered->matches($record));
    }

    public function test_lookup_without_data_is_null(): void
    {
        $this->assertNull((new ResponseReader)->lookup(self::payload('consulta-sin-datos.xml'), self::first()->invoiceId()));
    }

    public function test_lookup_without_a_matching_record_is_null(): void
    {
        $this->assertNull((new ResponseReader)->lookup(self::payload('consulta-con-datos.xml'), self::third()->invoiceId()));
    }

    public function test_anulado_confirms_cancellation(): void
    {
        $status = (new ResponseReader)->lookup(self::payload('consulta-con-datos.xml'), self::second()->invoiceId());

        $this->assertNotNull($status);
        $this->assertSame(LookupState::Anulado, $status->state);
        $this->assertTrue($status->confirmsCancellation());
        $this->assertSame('2024-01-01T19:25:40+01:00', $status->lastModified?->format(DATE_ATOM));
    }

    public function test_lookup_unknown_state_is_unsupported(): void
    {
        $xml = str_replace('<tikLRRC:EstadoRegistro>Anulado<', '<tikLRRC:EstadoRegistro>Borrado<', self::read('consulta-con-datos.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('EstadoRegistro');

        (new ResponseReader)->lookup(self::open($xml), self::second()->invoiceId());
    }

    public function test_delivery_state_of_every_outcome(): void
    {
        $expected = [
            [RecordOutcome::Accepted, DeliveryState::Delivered, DeliveryState::Delivered],
            [RecordOutcome::AcceptedWithErrors, DeliveryState::Delivered, DeliveryState::Delivered],
            [RecordOutcome::AlreadyRegistered, DeliveryState::Delivered, DeliveryState::Delivered],
            [RecordOutcome::AlreadyCancelled, DeliveryState::Failed, DeliveryState::Delivered],
            [RecordOutcome::Rejected, DeliveryState::Failed, DeliveryState::Failed],
            [RecordOutcome::Unconfirmed, DeliveryState::Unknown, DeliveryState::Unknown],
        ];

        foreach ($expected as [$outcome, $alta, $cancellation]) {
            $this->assertSame($alta, $outcome->deliveryState(false), $outcome->name);
            $this->assertSame($cancellation, $outcome->deliveryState(true), $outcome->name);
        }
        $this->assertCount(count(RecordOutcome::cases()), $expected);
    }

    private static function read(string $name): string
    {
        return (string) file_get_contents(self::FIXTURES.'/'.$name);
    }

    private static function payload(string $name): DOMElement
    {
        return self::open(self::read($name));
    }

    private static function open(string $xml): DOMElement
    {
        $payload = SoapEnvelope::open($xml);
        self::assertInstanceOf(DOMElement::class, $payload);

        return $payload;
    }

    private static function submission(BillingRecord ...$records): Submission
    {
        return new Submission(
            new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.'),
            array_values($records),
        );
    }

    private static function first(): RegistrationRecord
    {
        return RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
    }

    private static function second(): RegistrationRecord
    {
        $first = self::first();

        return RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
        );
    }

    private static function third(): RegistrationRecord
    {
        $second = self::second();

        return RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345680/G35', '01-01-2024'),
            ChainLink::after($second->invoiceId(), $second->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:38+01:00'),
        );
    }

    private static function cancellation(): CancellationRecord
    {
        $second = self::second();

        return CancellationRecord::create(
            CancellationDraft::of(VerifactuSamples::invoiceId('12345679/G34', '01-01-2024')),
            ChainLink::after($second->invoiceId(), $second->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:40+01:00'),
        );
    }
}

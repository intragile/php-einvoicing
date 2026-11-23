<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\GeneratedBy;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\AnulacionOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use PHPUnit\Framework\TestCase;

final class RecordTest extends TestCase
{
    use CapturesThrowable;

    private const HASH_1 = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';

    private const HASH_2 = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';

    private function at(string $text = '2024-01-01T19:20:30+01:00'): RecordTimestamp
    {
        return RecordTimestamp::fromLiteral($text);
    }

    public function test_restore_keeps_the_given_fingerprint(): void
    {
        $draft = VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024');
        $system = VerifactuSamples::system();

        $registration = RegistrationRecord::restore($draft, ChainLink::first(), $system, $this->at(), self::HASH_2);
        $this->assertSame(self::HASH_2, $registration->fingerprint());
        $this->assertSame(self::HASH_1, Fingerprint::of($registration));

        $cancellation = CancellationRecord::restore(
            CancellationDraft::of($draft->id),
            ChainLink::first(),
            $system,
            $this->at(),
            'not-a-hash',
        );
        $this->assertSame('not-a-hash', $cancellation->fingerprint());
    }

    public function test_records_expose_the_billing_record_contract(): void
    {
        $draft = VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024');
        $system = VerifactuSamples::system();
        $link = ChainLink::first();
        $at = $this->at();

        $record = RegistrationRecord::create($draft, $link, $system, $at);
        $this->assertInstanceOf(BillingRecord::class, $record);
        $this->assertSame($draft, $record->draft);
        $this->assertSame($draft->id, $record->invoiceId());
        $this->assertSame($link, $record->chainLink());
        $this->assertSame($system, $record->system());
        $this->assertSame($at, $record->generatedAt());
        $this->assertSame(AltaOperation::Initial, $record->draft->operation);
        $this->assertSame(
            [
                ['IDEmisorFactura', '89890001K'],
                ['NumSerieFactura', '12345678/G33'],
                ['FechaExpedicionFactura', '01-01-2024'],
                ['TipoFactura', 'F1'],
                ['CuotaTotal', '12.35'],
                ['ImporteTotal', '123.45'],
                ['Huella', ''],
                ['FechaHoraHusoGenRegistro', '2024-01-01T19:20:30+01:00'],
            ],
            $record->hashFields(),
        );

        $cancellation = CancellationRecord::create(CancellationDraft::of($draft->id), $link, $system, $at);
        $this->assertSame($draft->id, $cancellation->invoiceId());
        $this->assertSame(AnulacionOperation::Normal, $cancellation->draft->operation);
        $this->assertNull($cancellation->draft->generatedBy);
        $this->assertSame(
            ['IDEmisorFacturaAnulada', 'NumSerieFacturaAnulada', 'FechaExpedicionFacturaAnulada', 'Huella', 'FechaHoraHusoGenRegistro'],
            array_map(static fn (array $field): string => $field[0], $cancellation->hashFields()),
        );
    }

    public function test_cancellation_draft_keeps_its_options(): void
    {
        $id = VerifactuSamples::invoiceId('A1', '01-01-2024');
        $generator = new TaxParty('Generator S.L.', Nif::parse('B64829179'), null);

        $draft = CancellationDraft::of($id, AnulacionOperation::WithoutPriorRecord, GeneratedBy::ThirdParty, $generator, ' ref-1 ');

        $this->assertSame($id, $draft->annulled);
        $this->assertSame(AnulacionOperation::WithoutPriorRecord, $draft->operation);
        $this->assertSame(GeneratedBy::ThirdParty, $draft->generatedBy);
        $this->assertSame($generator, $draft->generator);
        $this->assertSame('ref-1', $draft->externalReference);

        $error = self::capture(static fn () => CancellationDraft::of($id, externalReference: str_repeat('a', 61)));
        $this->assertInstanceOf(InvalidRecord::class, $error);
        $this->assertStringContainsString('RefExterna', $error->getMessage());
    }

    public function test_draft_cardinalities(): void
    {
        $id = VerifactuSamples::invoiceId('A1', '01-01-2024');
        $line = VerifactuSamples::line();
        $recipient = VerifactuSamples::recipient();

        $cases = [
            'no breakdown line' => [static fn () => VerifactuSamples::draft($id, breakdown: []), 'Desglose'],
            'thirteen breakdown lines' => [static fn () => VerifactuSamples::draft($id, breakdown: array_fill(0, 13, $line)), 'Desglose'],
            '1001 recipients' => [static fn () => VerifactuSamples::draft($id, recipients: array_fill(0, 1001, $recipient)), 'Destinatarios'],
            '501-character description' => [static fn () => VerifactuSamples::draft($id, description: str_repeat('d', 501)), 'DescripcionOperacion'],
        ];

        foreach ($cases as $name => [$build, $field]) {
            $error = self::capture($build);
            $this->assertInstanceOf(InvalidRecord::class, $error, $name);
            $this->assertStringContainsString($field, $error->getMessage(), $name);
        }

        // The bounds themselves are admitted.
        $this->assertCount(12, VerifactuSamples::draft($id, breakdown: array_fill(0, 12, $line))->breakdown);
        $this->assertCount(1000, VerifactuSamples::draft($id, recipients: array_fill(0, 1000, $recipient))->recipients);
        $this->assertCount(0, VerifactuSamples::draft($id, recipients: [])->recipients);
        $this->assertSame(500, mb_strlen(VerifactuSamples::draft($id, description: str_repeat('é', 500))->description));
    }
}

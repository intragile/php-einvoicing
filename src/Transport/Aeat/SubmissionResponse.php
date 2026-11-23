<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;

/**
 * The response to a RegFactuSistemaFacturacion submission (RespuestaRegFactuSistemaFacturacion).
 *
 * Lines are matched to the submitted records by IDFactura and TipoOperacion, never by position:
 * AEAT does not promise to answer in request order, nor to answer every record.
 */
final readonly class SubmissionResponse
{
    /**
     * @param  list<RecordResponse>  $lines
     */
    public function __construct(
        /** The CSV of the submission; AEAT issues none when it refuses the submission. */
        public ?string $csv,
        /** DatosPresentacion/NIFPresentador. */
        public ?string $presenterNif,
        /** DatosPresentacion/TimestampPresentacion. */
        public ?DateTimeImmutable $presentedAt,
        /** TiempoEsperaEnvio: seconds to wait before the next submission. */
        public int $waitSeconds,
        public SubmissionState $state,
        public array $lines,
        /** The SOAP body payload as received. */
        public string $xml,
    ) {}

    /** The line answering the record, or null when the response carries none. */
    public function lineFor(BillingRecord $record): ?RecordResponse
    {
        $operation = self::operationOf($record);
        foreach ($this->lines as $line) {
            if ($line->operation === $operation && $line->invoiceId->equals($record->invoiceId())) {
                return $line;
            }
        }

        return null;
    }

    /**
     * One outcome per submitted record, in submission order; a record without a line is
     * Unconfirmed.
     *
     * @return list<RecordOutcome>
     */
    public function outcomes(Submission $submission): array
    {
        return array_map(
            fn (BillingRecord $record): RecordOutcome => $this->lineFor($record)?->outcome() ?? RecordOutcome::Unconfirmed,
            $submission->records,
        );
    }

    /**
     * The lines that answer no record of the submission. They are reported rather than thrown so
     * that the CSV of a response AEAT has already processed is not lost.
     *
     * @return list<RecordResponse>
     */
    public function unmatched(Submission $submission): array
    {
        $unmatched = [];
        foreach ($this->lines as $line) {
            $matched = false;
            foreach ($submission->records as $record) {
                if ($line->operation === self::operationOf($record) && $line->invoiceId->equals($record->invoiceId())) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $unmatched[] = $line;
            }
        }

        return $unmatched;
    }

    private static function operationOf(BillingRecord $record): string
    {
        return $record instanceof CancellationRecord ? RecordResponse::ANULACION : RecordResponse::ALTA;
    }
}

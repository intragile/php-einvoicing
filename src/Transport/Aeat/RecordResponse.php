<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;

/**
 * One RespuestaLinea of a submission response: AEAT's verdict on one submitted record.
 */
final readonly class RecordResponse
{
    public const ALTA = 'Alta';

    public const ANULACION = 'Anulacion';

    public function __construct(
        public InvoiceId $invoiceId,
        /** TipoOperacion: "Alta" or "Anulacion". */
        public string $operation,
        /** RefExterna, echoed from the record. */
        public ?string $externalReference,
        public RecordState $state,
        /** CodigoErrorRegistro, kept for accepted-with-errors lines as well. */
        public ?int $errorCode,
        public ?string $errorDescription,
        /** EstadoRegistroDuplicado, present only when the record was rejected as a duplicate. */
        public ?DuplicateState $duplicateState,
        /** IdPeticionRegistroDuplicado: the request that registered the duplicate. */
        public ?string $duplicateRequestId,
    ) {}

    /**
     * The duplicate state takes precedence: a duplicate is rejected as a line, yet the record it
     * duplicates is already held by AEAT.
     */
    public function outcome(): RecordOutcome
    {
        return match ($this->duplicateState) {
            DuplicateState::Correcta, DuplicateState::AceptadaConErrores => RecordOutcome::AlreadyRegistered,
            DuplicateState::Anulada => RecordOutcome::AlreadyCancelled,
            null => match ($this->state) {
                RecordState::Correcto => RecordOutcome::Accepted,
                RecordState::AceptadoConErrores => RecordOutcome::AcceptedWithErrors,
                RecordState::Incorrecto => RecordOutcome::Rejected,
            },
        };
    }
}

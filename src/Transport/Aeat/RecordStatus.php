<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;

/**
 * A record as AEAT holds it, returned by a record query (RegistroRespuestaConsultaFactuSistemaFacturacion).
 */
final readonly class RecordStatus
{
    public function __construct(
        public InvoiceId $invoiceId,
        public LookupState $state,
        public ?int $errorCode,
        public ?string $errorDescription,
        /** DatosRegistroFacturacion/Huella of the registered record. */
        public ?string $fingerprint,
        /** EstadoRegistro/TimestampUltimaModificacion. */
        public ?DateTimeImmutable $lastModified,
    ) {}

    /** Whether AEAT holds this very record: the same invoice with the same Huella. */
    public function matches(RegistrationRecord $record): bool
    {
        return $this->fingerprint !== null
            && $this->invoiceId->equals($record->invoiceId())
            && $this->fingerprint === $record->fingerprint();
    }

    public function confirmsCancellation(): bool
    {
        return $this->state === LookupState::Anulado;
    }
}

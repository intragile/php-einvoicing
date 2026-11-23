<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

/**
 * A chained RegistroAlta with its fingerprint.
 */
final readonly class RegistrationRecord implements BillingRecord
{
    private string $fingerprint;

    private function __construct(
        public RegistrationDraft $draft,
        private ChainLink $link,
        private ComputerSystem $system,
        private RecordTimestamp $at,
        ?string $fingerprint,
    ) {
        $this->fingerprint = $fingerprint ?? Fingerprint::of($this);
    }

    /** Builds a new record and computes its fingerprint. */
    public static function create(RegistrationDraft $draft, ChainLink $link, ComputerSystem $system, RecordTimestamp $at): self
    {
        return new self($draft, $link, $system, $at, null);
    }

    /**
     * Rebuilds a record that was read back, keeping the given Huella verbatim even when it does
     * not match the recomputed fingerprint.
     */
    public static function restore(
        RegistrationDraft $draft,
        ChainLink $link,
        ComputerSystem $system,
        RecordTimestamp $at,
        string $fingerprint,
    ): self {
        return new self($draft, $link, $system, $at, $fingerprint);
    }

    public function invoiceId(): InvoiceId
    {
        return $this->draft->id;
    }

    public function chainLink(): ChainLink
    {
        return $this->link;
    }

    public function generatedAt(): RecordTimestamp
    {
        return $this->at;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function system(): ComputerSystem
    {
        return $this->system;
    }

    public function hashFields(): array
    {
        $id = $this->draft->id;

        return [
            ['IDEmisorFactura', $id->issuer->value],
            ['NumSerieFactura', $id->number],
            ['FechaExpedicionFactura', $id->dateText()],
            ['TipoFactura', $this->draft->type->value],
            ['CuotaTotal', $this->draft->totalTax->text],
            ['ImporteTotal', $this->draft->total->text],
            ['Huella', $this->link->previousFingerprint()],
            ['FechaHoraHusoGenRegistro', $this->at->text],
        ];
    }
}

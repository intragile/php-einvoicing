<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

/**
 * A chained VERI*FACTU billing record: a RegistroAlta or a RegistroAnulacion.
 */
interface BillingRecord
{
    /** The invoice the record registers or cancels. */
    public function invoiceId(): InvoiceId;

    public function chainLink(): ChainLink;

    /** FechaHoraHusoGenRegistro. */
    public function generatedAt(): RecordTimestamp;

    /** Huella: the record's own fingerprint, 64 upper-case hexadecimal characters for records built here. */
    public function fingerprint(): string;

    public function system(): ComputerSystem;

    /**
     * The fields that enter the fingerprint, as element name and literal XML text, in hash order.
     *
     * @return list<array{string, string}>
     */
    public function hashFields(): array;
}

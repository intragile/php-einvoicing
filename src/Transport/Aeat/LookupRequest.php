<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;

/**
 * A query for one registered invoice of an issuer (ConsultaFactuSistemaFacturacion).
 */
final readonly class LookupRequest
{
    public function __construct(
        public Nif $issuer,
        public string $issuerName,
        public InvoiceId $invoice,
    ) {}
}

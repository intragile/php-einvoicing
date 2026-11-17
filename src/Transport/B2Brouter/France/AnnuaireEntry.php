<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;

/**
 * The answer of a French directory lookup through B2Brouter.
 *
 * Only the status is set when B2Brouter gave no company record (not listed, or the lookup failed);
 * the other fields are then null and the flag list is empty.
 */
final readonly class AnnuaireEntry
{
    /**
     * @param  string|null  $name  the company name B2Brouter reports
     * @param  string|null  $participantScheme  the scheme of the electronic address invoices are
     *                                          sent to, for example "0225"
     * @param  string|null  $participantValue  the electronic address invoices are sent to
     * @param  string|null  $invoiceDocumentTypeCode  the B2Brouter document type code the recipient
     *                                                receives invoices in, for example
     *                                                "xml.ubl.invoice.frcius.v1"
     * @param  list<string>  $flags  the information flags as reported, for example
     *                               "FR_ASSUJETTI_ACTIVE"
     */
    public function __construct(
        public AnnuaireStatus $status,
        public ?string $name,
        public ?string $participantScheme,
        public ?string $participantValue,
        public ?string $invoiceDocumentTypeCode,
        public array $flags,
    ) {}
}

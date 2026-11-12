<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

/**
 * Document type and process identifiers of Peppol BIS Billing 3.0.
 */
final class PeppolBis3
{
    /** Document type identifier of a BIS Billing 3.0 UBL Invoice. */
    public const INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1';

    /** Document type identifier of a BIS Billing 3.0 UBL CreditNote. */
    public const CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1';

    /** Process identifier of the BIS Billing 3.0 business process. */
    public const PROCESS = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
}

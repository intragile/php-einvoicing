<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;

/**
 * Document type, specification and process identifiers of Peppol BIS Billing 3.0.
 */
final class PeppolBis3
{
    /** Document type identifier of a BIS Billing 3.0 UBL Invoice. */
    public const INVOICE = PeppolDocumentTypes::BIS_INVOICE;

    /** Document type identifier of a BIS Billing 3.0 UBL CreditNote. */
    public const CREDIT_NOTE = PeppolDocumentTypes::BIS_CREDIT_NOTE;

    /** Specification identifier (BT-24, cbc:CustomizationID) of BIS Billing 3.0. */
    public const CUSTOMIZATION = PeppolDocumentTypes::BIS_CUSTOMIZATION;

    /** Process identifier of the BIS Billing 3.0 business process. */
    public const PROCESS = PeppolDocumentTypes::BILLING_PROCESS;
}

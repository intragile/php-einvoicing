<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;

/**
 * Peppol document type identifiers of XRechnung 3.0, as listed in the Peppol Code Lists v9.7.
 *
 * Every XRechnung document type runs under the Peppol billing process. The XRechnung CVD has no
 * Peppol document type, and the XRechnung UBL Credit Note Extension is deprecated there, so
 * neither is listed.
 */
final class XRechnungPeppol
{
    /** XRechnung UBL Invoice V3.0. */
    public const UBL_INVOICE = PeppolDocumentTypes::XRECHNUNG_UBL_INVOICE;

    /** XRechnung UBL Credit Note V3.0. */
    public const UBL_CREDIT_NOTE = PeppolDocumentTypes::XRECHNUNG_UBL_CREDIT_NOTE;

    /** XRechnung CII Invoice V3.0; it also carries CII credit notes, which share the root element. */
    public const CII_INVOICE = PeppolDocumentTypes::XRECHNUNG_CII_INVOICE;

    /** XRechnung UBL Invoice V3.0 Extension. */
    public const UBL_INVOICE_EXTENSION = PeppolDocumentTypes::XRECHNUNG_UBL_INVOICE_EXTENSION;

    /** XRechnung CII Invoice V3.0 Extension. */
    public const CII_INVOICE_EXTENSION = PeppolDocumentTypes::XRECHNUNG_CII_INVOICE_EXTENSION;

    /** Process identifier of every XRechnung document type: Peppol billing. */
    public const PROCESS = PeppolBis3::PROCESS;

    /**
     * The sendable XRechnung documents: "<root namespace>|<root local name>|<specification
     * identifier (BT-24)>" mapped to the Peppol document type identifier. The key is matched
     * exactly, without trimming.
     *
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return PeppolDocumentTypes::xrechnung();
    }
}

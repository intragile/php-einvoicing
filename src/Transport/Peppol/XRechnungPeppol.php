<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

use IntrAgile\EInvoicing\Syntax\XRechnung;

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
    public const UBL_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1';

    /** XRechnung UBL Credit Note V3.0. */
    public const UBL_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1';

    /** XRechnung CII Invoice V3.0; it also carries CII credit notes, which share the root element. */
    public const CII_INVOICE = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::D16B';

    /** XRechnung UBL Invoice V3.0 Extension. */
    public const UBL_INVOICE_EXTENSION = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::2.1';

    /** XRechnung CII Invoice V3.0 Extension. */
    public const CII_INVOICE_EXTENSION = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::D16B';

    /** Process identifier of every XRechnung document type: Peppol billing. */
    public const PROCESS = PeppolBis3::PROCESS;

    private const UBL_INVOICE_ROOT = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2|Invoice|';

    private const UBL_CREDIT_NOTE_ROOT = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2|CreditNote|';

    private const CII_ROOT = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100|CrossIndustryInvoice|';

    /**
     * The sendable XRechnung documents: "<root namespace>|<root local name>|<specification
     * identifier (BT-24)>" mapped to the Peppol document type identifier. The key is matched
     * exactly, without trimming.
     *
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return [
            self::UBL_INVOICE_ROOT.XRechnung::CIUS => self::UBL_INVOICE,
            self::UBL_CREDIT_NOTE_ROOT.XRechnung::CIUS => self::UBL_CREDIT_NOTE,
            self::CII_ROOT.XRechnung::CIUS => self::CII_INVOICE,
            self::UBL_INVOICE_ROOT.XRechnung::EXTENSION => self::UBL_INVOICE_EXTENSION,
            self::CII_ROOT.XRechnung::EXTENSION => self::CII_INVOICE_EXTENSION,
        ];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

/**
 * The B2Brouter document type codes of the French documents B2Brouter accepts as a French
 * plateforme agréée.
 *
 * B2Brouter takes every one of them as application/octet-stream, so no media type is kept here.
 */
enum FrenchDocumentType: string
{
    /** UBL Invoice under the French CIUS (EN 16931). */
    case UblInvoice = 'xml.ubl.invoice.frcius.v1';

    /** UBL CreditNote under the French CIUS (EN 16931). */
    case UblCreditNote = 'xml.ubl.credit_note.frcius.v1';

    /** UBL Invoice under the French extension (EXTENDED-CTC-FR). */
    case UblInvoiceExtended = 'xml.ubl.invoice.fr.extension.v1';

    /** UBL CreditNote under the French extension (EXTENDED-CTC-FR). */
    case UblCreditNoteExtended = 'xml.ubl.credit_note.fr.extension.v1';

    /** CII CrossIndustryInvoice under the French CIUS (EN 16931). */
    case CiiInvoice = 'xml.cii.cross_industry_invoice.frcius.v1';

    /** Factur-X hybrid PDF of the BASIC, EN 16931 or EXTENDED profile. */
    case FacturX = 'xml.cii.cross_industry_invoice.facturx.fr.all_profiles.v1';
}

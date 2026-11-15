<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Peppol;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use LogicException;

/**
 * The Peppol document types this library sends and validates, and how a payload is matched to
 * one: by the exact pair of document element and specification identifier (BT-24), compared as
 * written, without trimming.
 *
 * - Peppol BIS Billing 3.0: a UBL Invoice or CreditNote with BIS_CUSTOMIZATION.
 * - XRechnung 3.0 (Peppol Code Lists v9.7): a UBL Invoice, UBL CreditNote or CII
 *   CrossIndustryInvoice with XRechnung::CIUS, or a UBL Invoice or CII CrossIndustryInvoice with
 *   XRechnung::EXTENSION. The XRechnung CVD has no Peppol document type and the XRechnung UBL
 *   Credit Note Extension is deprecated, so neither is matched.
 *
 * Both the transport (OutboundDocument) and the validation (PeppolPayloadValidator) use this
 * class, so that what is sent and what is validated cannot drift apart. The user-facing names of
 * the document type identifiers are PeppolBis3 and XRechnungPeppol in the transport namespace.
 *
 * @internal Used by the transport and validation layers; not covered by the BC promise.
 */
final class PeppolDocumentTypes
{
    /** Specification identifier (BT-24) of Peppol BIS Billing 3.0. */
    public const BIS_CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    /** Process identifier of Peppol billing, the process of every document type listed here. */
    public const BILLING_PROCESS = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    public const BIS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##'.self::BIS_CUSTOMIZATION.'::2.1';

    public const BIS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##'.self::BIS_CUSTOMIZATION.'::2.1';

    public const XRECHNUNG_UBL_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1';

    public const XRECHNUNG_UBL_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1';

    public const XRECHNUNG_CII_INVOICE = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::D16B';

    public const XRECHNUNG_UBL_INVOICE_EXTENSION = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::2.1';

    public const XRECHNUNG_CII_INVOICE_EXTENSION = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::D16B';

    /** Root key ("<namespace>|<local name>") of a UBL Invoice. */
    public const UBL_INVOICE_ROOT = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2|Invoice';

    /** Root key of a UBL CreditNote. */
    public const UBL_CREDIT_NOTE_ROOT = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2|CreditNote';

    /** Root key of a CII CrossIndustryInvoice. */
    public const CII_ROOT = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100|CrossIndustryInvoice';

    private const UBL_SPECIFICATION = 'cbc:CustomizationID';

    private const CII_SPECIFICATION = 'rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID';

    /**
     * The BIS Billing 3.0 document types: "<root key>|<BT-24>" mapped to the document type.
     *
     * @return array<string, string>
     */
    public static function bis(): array
    {
        return [
            self::UBL_INVOICE_ROOT.'|'.self::BIS_CUSTOMIZATION => self::BIS_INVOICE,
            self::UBL_CREDIT_NOTE_ROOT.'|'.self::BIS_CUSTOMIZATION => self::BIS_CREDIT_NOTE,
        ];
    }

    /**
     * The XRechnung 3.0 document types: "<root key>|<BT-24>" mapped to the document type.
     *
     * @return array<string, string>
     */
    public static function xrechnung(): array
    {
        return [
            self::UBL_INVOICE_ROOT.'|'.XRechnung::CIUS => self::XRECHNUNG_UBL_INVOICE,
            self::UBL_CREDIT_NOTE_ROOT.'|'.XRechnung::CIUS => self::XRECHNUNG_UBL_CREDIT_NOTE,
            self::CII_ROOT.'|'.XRechnung::CIUS => self::XRECHNUNG_CII_INVOICE,
            self::UBL_INVOICE_ROOT.'|'.XRechnung::EXTENSION => self::XRECHNUNG_UBL_INVOICE_EXTENSION,
            self::CII_ROOT.'|'.XRechnung::EXTENSION => self::XRECHNUNG_CII_INVOICE_EXTENSION,
        ];
    }

    /**
     * The root key ("<namespace>|<local name>") of a document element.
     */
    public static function rootKey(DOMElement $root): string
    {
        return $root->namespaceURI.'|'.$root->localName;
    }

    /**
     * True for a UBL Invoice or CreditNote root key.
     */
    public static function isUbl(string $rootKey): bool
    {
        return $rootKey === self::UBL_INVOICE_ROOT || $rootKey === self::UBL_CREDIT_NOTE_ROOT;
    }

    /**
     * The specification identifier (BT-24) of a UBL or CII document, untrimmed: UBL
     * cbc:CustomizationID or CII ram:GuidelineSpecifiedDocumentContextParameter/ram:ID. Null when
     * the document has none or is of another kind.
     */
    public static function specification(DOMElement $root): ?string
    {
        $rootKey = self::rootKey($root);
        if (! self::isUbl($rootKey) && $rootKey !== self::CII_ROOT) {
            return null;
        }
        $xpath = new DOMXPath($root->ownerDocument ?? throw new LogicException('The element has no owner document.'));
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $nodes = $xpath->query(self::isUbl($rootKey) ? self::UBL_SPECIFICATION : self::CII_SPECIFICATION, $root);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? $node->textContent : null;
    }

    /**
     * The Peppol document type of a root key and specification identifier pair, or null when the
     * pair is not sendable.
     */
    public static function resolve(string $rootKey, ?string $specification): ?string
    {
        if ($specification === null) {
            return null;
        }
        $key = $rootKey.'|'.$specification;

        return self::bis()[$key] ?? self::xrechnung()[$key] ?? null;
    }

    /**
     * True when the document type is one of the XRechnung document types.
     */
    public static function isXRechnung(string $documentType): bool
    {
        return in_array($documentType, self::xrechnung(), true);
    }

    /**
     * The sendable pairs, for refusal messages.
     */
    public static function sendable(): string
    {
        return sprintf(
            'a UBL Invoice or CreditNote with %s (Peppol BIS Billing 3.0), a UBL Invoice, UBL CreditNote or CII CrossIndustryInvoice with %s (XRechnung 3.0), and a UBL Invoice or CII CrossIndustryInvoice with %s (XRechnung 3.0 Extension).',
            self::BIS_CUSTOMIZATION,
            XRechnung::CIUS,
            XRechnung::EXTENSION,
        );
    }

    /**
     * Why a known but unsendable pair is refused, as a sentence with a leading space; an empty
     * string when there is no specific reason.
     */
    public static function refusalReason(string $rootKey, string $specification): string
    {
        return match (true) {
            $specification === XRechnung::CVD => ' The XRechnung CVD has no Peppol document type.',
            $specification === XRechnung::EXTENSION && $rootKey === self::UBL_CREDIT_NOTE_ROOT => ' The XRechnung UBL Credit Note Extension document type is deprecated in Peppol Code Lists v9.7; send the credit note as an XRechnung 3.0 Extension CII document or without the extension.',
            $specification === UblWriter::SPECIFICATION_IDENTIFIER => ' Writers use this identifier for an invoice without process control; give the invoice the process control of the specification to send.',
            default => '',
        };
    }
}

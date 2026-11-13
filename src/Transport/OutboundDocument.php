<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use InvalidArgumentException;

/**
 * A business document ready to be sent over Peppol, with the routing metadata the envelope needs:
 * sender and receiver participant, document type, process and the sender's country (C1).
 */
final readonly class OutboundDocument
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const CII = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    /** Specification identifier (BT-24) of Peppol BIS Billing 3.0. */
    private const BIS = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    /** BIS Billing 3.0 document type of each UBL root element, by "<namespace>|<local name>". */
    private const BIS_DOCUMENT_TYPES = [
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2|Invoice' => PeppolBis3::INVOICE,
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2|CreditNote' => PeppolBis3::CREDIT_NOTE,
    ];

    private const CII_ROOT = self::CII.'|CrossIndustryInvoice';

    private const CII_AGREEMENT = 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/';

    public function __construct(
        /** The business document as sent. */
        public string $xml,
        /** Sending participant (C1). */
        public ParticipantId $sender,
        /** Receiving participant (C4). */
        public ParticipantId $receiver,
        public DocumentTypeId $documentType,
        public ProcessId $process,
        /** ISO 3166-1 alpha-2 country of the sender (C1), when known. */
        public ?string $countryC1 = null,
    ) {
        if ($countryC1 !== null) {
            self::assertCountry($countryC1);
        }
    }

    /**
     * Reads the routing metadata from a sendable document. The document is chosen by the exact
     * pair of document element and specification identifier (BT-24):
     *
     * - a UBL Invoice or CreditNote with the Peppol BIS Billing 3.0 identifier;
     * - an XRechnung 3.0 UBL Invoice, UBL CreditNote or CII CrossIndustryInvoice, or an XRechnung
     *   3.0 Extension UBL Invoice or CII CrossIndustryInvoice (XRechnungPeppol::documentTypes()).
     *
     * The sender comes from the seller electronic address (BT-34), the receiver from the buyer
     * electronic address (BT-49), the process from the business process type (BT-23; Peppol
     * billing when absent) and, unless given, the sender's country from the seller country (BT-40).
     * An XRechnung document must use the Peppol billing process, the only process its Peppol
     * document types are registered for.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not a sendable pair, is a self-billing
     *                             document, uses another process than its document type allows, or
     *                             lacks an electronic address
     * @throws InvalidArgumentException when an identifier or the country is malformed
     */
    public static function fromXml(string $xml, ?string $countryC1 = null): self
    {
        $root = SafeXml::load($xml)->documentElement
            ?? throw new UnsupportedDocument('The document has no root element.');
        $rootKey = $root->namespaceURI.'|'.$root->localName;
        $isUbl = isset(self::BIS_DOCUMENT_TYPES[$rootKey]);
        if (! $isUbl && $rootKey !== self::CII_ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document root {%s}%s is not a UBL Invoice or CreditNote or a CII CrossIndustryInvoice.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));
        }

        $xpath = new DOMXPath($root->ownerDocument ?? throw new UnsupportedDocument('The document has no owner.'));
        $xpath->registerNamespace('cbc', self::CBC);
        $xpath->registerNamespace('cac', self::CAC);
        $xpath->registerNamespace('rsm', self::CII);
        $xpath->registerNamespace('ram', self::RAM);

        // Read untrimmed: the document type is chosen by an exact match.
        $specification = self::first($xpath, $root, $isUbl
            ? 'cbc:CustomizationID'
            : 'rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID')->textContent ?? '';
        if (str_contains($specification, 'selfbilling')) {
            throw new UnsupportedDocument(sprintf(
                'The document is a self-billing invoice or credit note (%s); self-billing is not sent over Peppol by this version.',
                $specification,
            ));
        }
        $documentType = self::documentType($root, $rootKey, $specification);
        $isXRechnung = ! in_array($documentType, self::BIS_DOCUMENT_TYPES, true);

        if ($isUbl) {
            $sender = self::endpoint($xpath, $root, 'cac:AccountingSupplierParty/cac:Party/cbc:EndpointID', 'seller', 'BT-34');
            $receiver = self::endpoint($xpath, $root, 'cac:AccountingCustomerParty/cac:Party/cbc:EndpointID', 'buyer', 'BT-49');
            $profile = self::text($xpath, $root, 'cbc:ProfileID');
            $countryC1 ??= self::text($xpath, $root, 'cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode');
        } else {
            $sender = self::endpoint($xpath, $root, self::CII_AGREEMENT.'ram:SellerTradeParty/ram:URIUniversalCommunication/ram:URIID', 'seller', 'BT-34');
            $receiver = self::endpoint($xpath, $root, self::CII_AGREEMENT.'ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID', 'buyer', 'BT-49');
            $profile = self::text($xpath, $root, 'rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID');
            $countryC1 ??= self::text($xpath, $root, self::CII_AGREEMENT.'ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID');
        }

        $process = $profile ?? PeppolBis3::PROCESS;
        if ($isXRechnung && $process !== XRechnungPeppol::PROCESS) {
            throw new UnsupportedDocument(sprintf(
                'The XRechnung document declares the business process (BT-23) "%s"; its Peppol document type is registered only for %s, so set that process.',
                $process,
                XRechnungPeppol::PROCESS,
            ));
        }

        return new self(
            $xml,
            $sender,
            $receiver,
            new DocumentTypeId($documentType),
            new ProcessId($process),
            $countryC1,
        );
    }

    /**
     * Writes the invoice as UBL or CII and reads its routing metadata, as fromXml() does. CII is
     * sent over Peppol only for XRechnung, so Syntax::Cii needs an invoice whose process control
     * is XRechnung (XRechnung::processControl()).
     *
     * @throws UnsupportedDocument when the written document is not sendable (see fromXml()), for
     *                             example because the seller or buyer has no electronic address,
     *                             or when CII is asked for an invoice that is not XRechnung
     * @throws InvalidArgumentException when the invoice cannot be written or an identifier is malformed
     */
    public static function fromInvoice(Invoice $invoice, ?string $countryC1 = null, Syntax $syntax = Syntax::Ubl): self
    {
        if ($syntax === Syntax::Cii && ! str_starts_with($invoice->processControl->specificationIdentifier ?? '', XRechnung::CIUS)) {
            throw new UnsupportedDocument(sprintf(
                'CII is sent over Peppol only for XRechnung; the invoice has the specification identifier (BT-24) "%s". Give it XRechnung::processControl() or send it as UBL.',
                $invoice->processControl->specificationIdentifier ?? '',
            ));
        }

        return self::fromXml(match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter)->write($invoice),
        }, $countryC1);
    }

    /**
     * The Peppol document type of a document element and specification identifier pair.
     *
     * @throws UnsupportedDocument when the pair is not sendable; the message lists the sendable ones
     */
    private static function documentType(DOMElement $root, string $rootKey, string $specification): string
    {
        if ($specification === self::BIS && isset(self::BIS_DOCUMENT_TYPES[$rootKey])) {
            return self::BIS_DOCUMENT_TYPES[$rootKey];
        }
        $xrechnung = XRechnungPeppol::documentTypes()[$rootKey.'|'.$specification] ?? null;
        if ($xrechnung !== null) {
            return $xrechnung;
        }

        $reason = match (true) {
            $specification === XRechnung::CVD => ' The XRechnung CVD has no Peppol document type.',
            $specification === XRechnung::EXTENSION && $rootKey === 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2|CreditNote' => ' The XRechnung UBL Credit Note Extension document type is deprecated in Peppol Code Lists v9.7; send the credit note as an XRechnung 3.0 Extension CII document or without the extension.',
            $specification === UblWriter::SPECIFICATION_IDENTIFIER => ' Writers use this identifier for an invoice without process control; give the invoice the process control of the specification to send.',
            default => '',
        };

        throw new UnsupportedDocument(sprintf(
            'The document {%s}%s with the specification identifier (BT-24) "%s" is not sent over Peppol by this version.%s Sendable are a UBL Invoice or CreditNote with %s (Peppol BIS Billing 3.0), a UBL Invoice, UBL CreditNote or CII CrossIndustryInvoice with %s (XRechnung 3.0), and a UBL Invoice or CII CrossIndustryInvoice with %s (XRechnung 3.0 Extension).',
            (string) $root->namespaceURI,
            (string) $root->localName,
            $specification,
            $reason,
            self::BIS,
            XRechnung::CIUS,
            XRechnung::EXTENSION,
        ));
    }

    private static function endpoint(DOMXPath $xpath, DOMElement $root, string $path, string $role, string $term): ParticipantId
    {
        $endpoint = self::first($xpath, $root, $path);
        $value = $endpoint === null ? '' : trim($endpoint->textContent);
        $scheme = $endpoint === null ? '' : $endpoint->getAttribute('schemeID');
        if ($scheme !== trim($scheme)) {
            throw new UnsupportedDocument(sprintf(
                'The %s electronic address scheme (%s-1) "%s" has surrounding whitespace; the envelope must repeat it unchanged, so correct it in the document.',
                $role,
                $term,
                $scheme,
            ));
        }
        if ($value === '' || $scheme === '') {
            throw new UnsupportedDocument(sprintf(
                'The %s electronic address (%s) with its scheme (%s-1) is required to send the document over Peppol.',
                $role,
                $term,
                $term,
            ));
        }

        return new ParticipantId($scheme, $value);
    }

    private static function text(DOMXPath $xpath, DOMElement $root, string $path): ?string
    {
        $element = self::first($xpath, $root, $path);
        $value = $element === null ? '' : trim($element->textContent);

        return $value === '' ? null : $value;
    }

    private static function first(DOMXPath $xpath, DOMElement $root, string $path): ?DOMElement
    {
        $nodes = $xpath->query($path, $root);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private static function assertCountry(string $country): void
    {
        if (preg_match('/\A[A-Z0-9]{2}\z/', $country) !== 1) {
            throw new InvalidArgumentException(sprintf('The sender country (C1) "%s" is not a two-character upper-case code.', $country));
        }
    }
}

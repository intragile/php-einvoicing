<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\Cii\CiiVersion;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use InvalidArgumentException;

/**
 * A French invoice document as sent to B2Brouter, with its B2Brouter document type and its
 * document number (BT-1).
 *
 * The type is detected from the document element and the specification identifier (BT-24),
 * compared exactly as written: a UBL Invoice or CreditNote under the French CIUS or the French
 * extension (EXTENDED-CTC-FR), a CII CrossIndustryInvoice under the French CIUS, or a Factur-X
 * hybrid PDF of any profile.
 */
final readonly class FrenchDocument
{
    /**
     * @param  string  $bytes  the document as sent, byte for byte
     * @param  string|null  $number  the document number (BT-1), or null when it cannot be read
     */
    public function __construct(
        public string $bytes,
        public FrenchDocumentType $type,
        public ?string $number,
    ) {}

    /**
     * Detects the type of a UBL or CII French invoice.
     *
     * @throws UnsupportedDocument when the XML is not well-formed, is neither a UBL Invoice or
     *                             CreditNote nor a CII CrossIndustryInvoice, carries a BT-24
     *                             that is not a French profile, or is a CII EXTENDED-CTC-FR
     *                             document, which B2Brouter has no document type for
     * @throws UnsafeXml when the XML carries a DOCTYPE declaration or declares entities
     */
    public static function fromXml(string $xml): self
    {
        $root = SafeXml::load($xml)->documentElement;
        if (! $root instanceof DOMElement) {
            throw new UnsupportedDocument('The document has no document element.');
        }
        $rootKey = PeppolDocumentTypes::rootKey($root);
        $isUbl = PeppolDocumentTypes::isUbl($rootKey);
        if (! $isUbl && $rootKey !== PeppolDocumentTypes::CII_ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is neither a UBL Invoice or CreditNote nor a CII CrossIndustryInvoice, so it is not a French invoice B2Brouter accepts.',
                $root->namespaceURI ?? '',
                $root->localName,
            ));
        }

        $specification = PeppolDocumentTypes::specification($root);
        $profile = $specification === null ? null : FrenchProfile::fromSpecificationIdentifier($specification);
        if ($profile === null) {
            throw new UnsupportedDocument(sprintf(
                'The specification identifier (BT-24) %s is not a French profile; B2Brouter accepts French invoices with %s (French CIUS) or %s (EXTENDED-CTC-FR, UBL only). Send Peppol documents such as Peppol BIS Billing 3.0 or XRechnung through B2BrouterTransport.',
                $specification === null ? 'is missing, so the document' : sprintf('"%s"', $specification),
                FrenchProfile::En16931->value,
                FrenchProfile::ExtendedCtcFr->value,
            ));
        }

        $type = match (true) {
            $rootKey === PeppolDocumentTypes::UBL_INVOICE_ROOT => $profile === FrenchProfile::En16931
                ? FrenchDocumentType::UblInvoice
                : FrenchDocumentType::UblInvoiceExtended,
            $isUbl => $profile === FrenchProfile::En16931
                ? FrenchDocumentType::UblCreditNote
                : FrenchDocumentType::UblCreditNoteExtended,
            $profile === FrenchProfile::En16931 => FrenchDocumentType::CiiInvoice,
            default => throw new UnsupportedDocument(sprintf(
                'B2Brouter has no document type for a CII EXTENDED-CTC-FR document (BT-24 "%s"); write the invoice as UBL to send it under the French extension.',
                $profile->value,
            )),
        };

        return new self($xml, $type, DocumentNumber::of($xml));
    }

    /**
     * Wraps a Factur-X hybrid PDF, taking the document number from its embedded XML.
     *
     * The MINIMUM and BASIC WL profiles carry no invoice lines and are not invoices under the
     * French reform, so they are refused; the profile is read from the guideline identifier
     * (BT-24) of the embedded XML.
     *
     * @throws UnsupportedDocument when the PDF carries no Factur-X invoice or its profile is
     *                             MINIMUM or BASIC WL
     * @throws UnsafeXml when the embedded XML carries a DOCTYPE declaration
     * @throws UnreadablePdf when the PDF cannot be read or breaches a safety limit
     * @throws EncryptedPdf when the PDF is encrypted
     * @throws UnsupportedPdfFeature when the embedded file uses a filter other than FlateDecode
     */
    public static function fromFacturX(string $pdf): self
    {
        $document = (new FacturXPdfReader)->read($pdf);
        if ($document->profile?->hasLines() === false) {
            throw new UnsupportedDocument(sprintf(
                'A Factur-X %s profile document carries no invoice lines and is not an invoice under the French reform; send the EN 16931, EXTENDED or BASIC profile.',
                $document->profile->value,
            ));
        }

        return new self($pdf, FrenchDocumentType::FacturX, DocumentNumber::of($document->xml));
    }

    /**
     * Writes the invoice in the syntax, CII as D22B, and detects its type.
     *
     * @throws UnsupportedDocument when the written document is not a French document B2Brouter
     *                             accepts (see fromXml())
     * @throws InvalidArgumentException when the writer refuses the invoice
     */
    public static function fromInvoice(Invoice $invoice, Syntax $syntax = Syntax::Ubl): self
    {
        $xml = match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter(CiiVersion::D22B))->write($invoice),
        };

        return self::fromXml($xml);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
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

    /** Document type of each supported root element, by namespace and local name. */
    private const DOCUMENT_TYPES = [
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' => ['Invoice', PeppolBis3::INVOICE],
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2' => ['CreditNote', PeppolBis3::CREDIT_NOTE],
    ];

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
     * Reads the routing metadata from a UBL Invoice or CreditNote: sender from the seller
     * electronic address (BT-34), receiver from the buyer electronic address (BT-49), process
     * from cbc:ProfileID (BIS Billing 3.0 when absent) and, unless given, the sender's country
     * from the seller country (BT-40).
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not a UBL Invoice or CreditNote or lacks an electronic address
     * @throws InvalidArgumentException when an identifier or the country is malformed
     */
    public static function fromXml(string $xml, ?string $countryC1 = null): self
    {
        $root = SafeXml::load($xml)->documentElement
            ?? throw new UnsupportedDocument('The document has no root element.');

        if ($root->namespaceURI === self::CII) {
            throw new UnsupportedDocument('The document is a UN/CEFACT CII invoice; CII is not sent over Peppol by this version, send UBL instead.');
        }
        [$localName, $documentType] = self::DOCUMENT_TYPES[(string) $root->namespaceURI] ?? [null, null];
        if ($documentType === null || $root->localName !== $localName) {
            throw new UnsupportedDocument(sprintf(
                'The document root {%s}%s is not a UBL Invoice or CreditNote.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));
        }

        $xpath = new DOMXPath($root->ownerDocument ?? throw new UnsupportedDocument('The document has no owner.'));
        $xpath->registerNamespace('cbc', self::CBC);
        $xpath->registerNamespace('cac', self::CAC);

        $sender = self::endpoint($xpath, $root, 'cac:AccountingSupplierParty', 'seller', 'BT-34');
        $receiver = self::endpoint($xpath, $root, 'cac:AccountingCustomerParty', 'buyer', 'BT-49');
        $profile = self::text($xpath, $root, 'cbc:ProfileID');
        $countryC1 ??= self::text($xpath, $root, 'cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode');

        return new self(
            $xml,
            $sender,
            $receiver,
            new DocumentTypeId($documentType),
            new ProcessId($profile ?? PeppolBis3::PROCESS),
            $countryC1,
        );
    }

    /**
     * Writes the invoice as UBL and reads its routing metadata, as fromXml() does.
     *
     * @throws UnsupportedDocument when the seller or buyer has no electronic address
     * @throws InvalidArgumentException when the invoice cannot be written or an identifier is malformed
     */
    public static function fromInvoice(Invoice $invoice, ?string $countryC1 = null): self
    {
        return self::fromXml((new UblWriter)->write($invoice), $countryC1);
    }

    private static function endpoint(DOMXPath $xpath, DOMElement $root, string $party, string $role, string $term): ParticipantId
    {
        $endpoint = self::first($xpath, $root, $party.'/cac:Party/cbc:EndpointID');
        $value = $endpoint === null ? '' : trim($endpoint->textContent);
        $scheme = $endpoint === null ? '' : trim($endpoint->getAttribute('schemeID'));
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
        if (preg_match('/^[A-Z0-9]{2}$/', $country) !== 1) {
            throw new InvalidArgumentException(sprintf('The sender country (C1) "%s" is not a two-character upper-case code.', $country));
        }
    }
}

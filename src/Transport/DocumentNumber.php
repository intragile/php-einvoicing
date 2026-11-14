<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\SafeXml;

/**
 * Reads the document number (BT-1) of a UBL or CII invoice, for messages that tell the user how to
 * find a document at the provider.
 *
 * @internal
 */
final class DocumentNumber
{
    /**
     * The trimmed UBL cbc:ID or CII rsm:ExchangedDocument/ram:ID of the root element; null when
     * the document has none, is of another kind or cannot be parsed safely.
     */
    public static function of(string $xml): ?string
    {
        try {
            $document = SafeXml::load($xml);
        } catch (UnsafeXml|UnsupportedDocument) {
            return null;
        }
        $root = $document->documentElement;
        if (! $root instanceof DOMElement) {
            return null;
        }
        $rootKey = PeppolDocumentTypes::rootKey($root);
        if (PeppolDocumentTypes::isUbl($rootKey)) {
            $path = 'cbc:ID';
        } elseif ($rootKey === PeppolDocumentTypes::CII_ROOT) {
            $path = 'rsm:ExchangedDocument/ram:ID';
        } else {
            return null;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $nodes = $xpath->query($path, $root);
        $node = $nodes === false ? null : $nodes->item(0);
        $number = $node instanceof DOMElement ? trim($node->textContent) : '';

        return $number === '' ? null : $number;
    }
}

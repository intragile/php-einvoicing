<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use RuntimeException;

/**
 * SOAP 1.1 envelope handling for the AEAT web service: wraps a payload document in an envelope
 * and opens a received envelope, returning its payload or the SOAP fault it carries.
 */
final class SoapEnvelope
{
    /**
     * Wraps the payload document element as the only child of an envelope body, with an empty
     * header.
     *
     * @throws UnsafeXml when the payload carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the payload is not well-formed XML
     */
    public static function wrap(string $payloadXml): string
    {
        $payload = SafeXml::load($payloadXml)->documentElement;
        if ($payload === null) {
            throw new UnsupportedDocument('The payload has no document element.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $envelope = $document->createElementNS(AeatFormat::SOAP_NS, AeatFormat::SOAP_PREFIX.':Envelope');
        $document->appendChild($envelope);
        $envelope->appendChild($document->createElementNS(AeatFormat::SOAP_NS, AeatFormat::SOAP_PREFIX.':Header'));
        $body = $document->createElementNS(AeatFormat::SOAP_NS, AeatFormat::SOAP_PREFIX.':Body');
        $envelope->appendChild($body);
        $body->appendChild($document->importNode($payload, true));

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('The SOAP envelope could not be serialised.');
        }

        return $xml;
    }

    /**
     * Opens a received envelope.
     *
     * @return DOMElement|SoapFault the first element child of the body, or the fault when the body
     *                              carries a SOAP fault
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not a SOAP 1.1 envelope or has no body content
     */
    public static function open(string $xml): DOMElement|SoapFault
    {
        $root = SafeXml::load($xml)->documentElement;
        if ($root === null || $root->namespaceURI !== AeatFormat::SOAP_NS || $root->localName !== 'Envelope') {
            throw new UnsupportedDocument(sprintf(
                'Expected a SOAP 1.1 envelope ({%s}Envelope), found {%s}%s.',
                AeatFormat::SOAP_NS,
                (string) $root?->namespaceURI,
                (string) $root?->localName,
            ));
        }

        $body = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === AeatFormat::SOAP_NS && $child->localName === 'Body') {
                $body = $child;
                break;
            }
        }
        if ($body === null) {
            throw new UnsupportedDocument('The SOAP envelope has no {'.AeatFormat::SOAP_NS.'}Body.');
        }

        foreach ($body->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            return $child->namespaceURI === AeatFormat::SOAP_NS && $child->localName === 'Fault'
                ? SoapFault::fromElement($child)
                : $child;
        }

        throw new UnsupportedDocument('The SOAP envelope {'.AeatFormat::SOAP_NS.'}Body is empty.');
    }
}

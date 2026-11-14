<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Sbdh;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;

/**
 * Turns the XML of a received document into an InboundDocument, unwrapping an SBDH envelope when
 * the provider still delivers one.
 *
 * @internal
 */
final class InboundXml
{
    /**
     * The document with any SBDH envelope removed and its metadata kept. The XML is parsed
     * without DTD processing or network access.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its envelope is incomplete
     */
    public static function document(InboundReference $reference, string $xml): InboundDocument
    {
        if (EnvelopeReader::isEnvelopeStream($xml)) {
            // The reader parses the envelope once with SafeXml and serialises the payload from the
            // parsed tree, so the payload needs no second parse.
            [$envelope, $payload] = (new EnvelopeReader)->unwrap($xml);

            return new InboundDocument($reference, $payload, $envelope);
        }

        SafeXml::load($xml);

        return new InboundDocument($reference, $xml);
    }
}

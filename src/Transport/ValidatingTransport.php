<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Exception\InvalidDocument;
use IntrAgile\EInvoicing\Validation\DocumentValidator;

/**
 * Validates every document before handing it to the wrapped transport, so that an invalid
 * document never leaves.
 *
 * For Peppol, PeppolPayloadValidator validates each document with the rules of the specification
 * it declares (Peppol BIS Billing 3.0 or XRechnung 3.0).
 */
final class ValidatingTransport implements Transport
{
    public function __construct(
        private readonly Transport $inner,
        private readonly DocumentValidator $validator,
    ) {}

    /**
     * @throws InvalidDocument when a fatal assert fails or an assert cannot be evaluated; the
     *                         wrapped transport is not called
     */
    public function send(OutboundDocument $document): SendResult
    {
        $report = $this->validator->validate($document->xml);
        if (! $report->isValid()) {
            throw new InvalidDocument($report);
        }

        return $this->inner->send($document);
    }

    public function status(string $documentId): DeliveryStatus
    {
        return $this->inner->status($documentId);
    }
}

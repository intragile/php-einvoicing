<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Exception\InvalidDocument;
use IntrAgile\EInvoicing\Validation\Validator;

/**
 * Validates every document before handing it to the wrapped transport, so that an invalid
 * document never leaves.
 */
final class ValidatingTransport implements Transport
{
    public function __construct(
        private readonly Transport $inner,
        private readonly Validator $validator,
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

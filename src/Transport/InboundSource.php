<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Exception\TransportException;

/**
 * Received business documents waiting to be processed. A document stays pending until it is
 * acknowledged, so a consumer that fails halfway sees it again.
 */
interface InboundSource
{
    /**
     * The documents received and not yet acknowledged.
     *
     * @return list<InboundReference>
     *
     * @throws TransportException when the list could not be retrieved
     */
    public function pending(): array;

    /**
     * @throws TransportException when the document could not be retrieved
     */
    public function fetch(string $documentId): InboundDocument;

    /**
     * Marks the document as processed, so that it is no longer pending.
     *
     * @throws TransportException when the acknowledgement could not be recorded
     */
    public function acknowledge(string $documentId): void;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

/**
 * What the transport answered when it accepted a document.
 */
final readonly class SendResult
{
    public function __construct(
        /** The transport's id of the document, used to query its status. */
        public string $documentId,
        public DeliveryState $state,
        /** The Peppol message id (AS4 MessageId), when known. */
        public ?string $peppolMessageId = null,
        /** The envelope (SBDH) instance identifier, when known. */
        public ?string $envelopeId = null,
        /** Why delivery failed, when the state is Failed. */
        public ?DeliveryFailure $failure = null,
    ) {}
}

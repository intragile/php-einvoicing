<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;

/**
 * A received document as listed by an inbound source, before its content is fetched.
 */
final readonly class InboundReference
{
    public function __construct(
        /** The source's id of the document, used to fetch and acknowledge it. */
        public string $documentId,
        public ?ParticipantId $sender,
        public ?ParticipantId $receiver,
        /** The document type identifier, when the source reports it. */
        public ?string $documentType,
        public DateTimeImmutable $receivedAt,
    ) {}
}

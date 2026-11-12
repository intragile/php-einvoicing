<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Sbdh;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;

/**
 * The routing metadata of a Peppol Business Message Envelope (Standard Business Document Header).
 */
final readonly class Envelope
{
    public function __construct(
        /** Sending participant (C1). */
        public ParticipantId $sender,
        /** Receiving participant (C4). */
        public ParticipantId $receiver,
        /** Document type, carried in the DOCUMENTID scope. */
        public DocumentTypeId $documentType,
        /** Process, carried in the PROCESSID scope. */
        public ProcessId $process,
        /** ISO 3166-1 alpha-2 country of the sender, carried in the COUNTRY_C1 scope. */
        public string $countryC1,
        /** Globally unique identifier of this envelope. */
        public string $instanceIdentifier,
        /** Creation time of the envelope. */
        public DateTimeImmutable $createdAt,
        /**
         * Further scopes, such as MLS_TO or MLS_TYPE: the scope type mapped to its instance
         * identifier.
         *
         * @var array<string, string>
         */
        public array $additionalScopes = [],
    ) {}
}

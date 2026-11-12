<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Sbdh\Envelope;

/**
 * A received business document with the envelope it arrived in, when there was one.
 */
final readonly class InboundDocument
{
    public function __construct(
        public InboundReference $reference,
        /** The bare business document, without the envelope. */
        public string $xml,
        /** The envelope (SBDH) metadata, when the document arrived wrapped in one. */
        public ?Envelope $envelope = null,
    ) {}
}

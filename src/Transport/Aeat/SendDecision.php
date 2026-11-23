<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DateTimeImmutable;

/**
 * Whether a send may happen now, and how many records it may carry.
 */
final readonly class SendDecision
{
    public function __construct(
        public bool $sendNow,
        /** When the next send is allowed; set only when records are pending but must wait. */
        public ?DateTimeImmutable $nextSendAt,
        /** The number of pending records to send now; 0 when nothing may be sent. */
        public int $batchSize,
    ) {}
}

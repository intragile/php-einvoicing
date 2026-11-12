<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use DateTimeImmutable;

/**
 * The delivery state of a sent document at the time it was queried.
 */
final readonly class DeliveryStatus
{
    public function __construct(
        public string $documentId,
        public DeliveryState $state,
        /** Why delivery failed, when the state is Failed. */
        public ?DeliveryFailure $failure = null,
        /** When the state last changed, when known. */
        public ?DateTimeImmutable $changedAt = null,
    ) {}
}

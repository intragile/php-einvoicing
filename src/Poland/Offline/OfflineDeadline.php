<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

use DateTimeImmutable;

/**
 * The deadline for sending an offline invoice to KSeF: the last instant of the deadline day in
 * Europe/Warsaw, or null when there is no known deadline (an ongoing failure) or no duty to send
 * (a total failure).
 */
final readonly class OfflineDeadline
{
    public function __construct(
        public ?DateTimeImmutable $deadline,
        public DeadlineReason $reason,
    ) {}
}

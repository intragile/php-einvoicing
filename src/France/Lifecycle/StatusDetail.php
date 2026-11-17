<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Details of a status: reason, requested action, notes and data items.
 */
final readonly class StatusDetail
{
    /**
     * @param  list<StatusNote>  $notes  Notes.
     * @param  list<StatusCharacteristic>  $characteristics  Data items.
     */
    public function __construct(
        /** Sequence number (MDT-124-2), unique and ascending within a message. */
        public int $sequence,
        /** Reason code (MDT-113). */
        public ?StatusReason $reason = null,
        /** Reason text (MDT-114). */
        public ?string $reasonText = null,
        /** Status these details refer to (MDT-115). */
        public ?LifecycleStatus $status = null,
        /** Requested action code (MDT-121). */
        public ?RequestedAction $action = null,
        /** Requested action text (MDT-122). */
        public ?string $actionText = null,
        public array $notes = [],
        public array $characteristics = [],
        /** Reference date. */
        public ?DateTimeImmutable $referenceDate = null,
    ) {
        if ($sequence < 1) {
            throw new InvalidArgumentException(sprintf('Status detail sequence (MDT-124-2) must be at least 1, %d given.', $sequence));
        }
    }
}

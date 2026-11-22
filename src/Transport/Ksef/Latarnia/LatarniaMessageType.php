<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

/**
 * The type of a Latarnia message: a maintenance announcement carrying its whole window, or the
 * start or end of a failure.
 */
enum LatarniaMessageType: string
{
    case MaintenanceAnnouncement = 'MAINTENANCE_ANNOUNCEMENT';
    case FailureStart = 'FAILURE_START';
    case FailureEnd = 'FAILURE_END';

    /**
     * Whether a message of this type opens an event: a maintenance announcement or a failure start.
     */
    public function opensEvent(): bool
    {
        return $this !== self::FailureEnd;
    }
}

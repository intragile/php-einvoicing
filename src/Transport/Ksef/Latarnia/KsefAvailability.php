<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

/**
 * The current availability of KSeF as Latarnia reports it. When several events are active at
 * once, Latarnia reports the most severe: TOTAL_FAILURE over FAILURE over MAINTENANCE.
 */
enum KsefAvailability: string
{
    case Available = 'AVAILABLE';
    case Maintenance = 'MAINTENANCE';
    case Failure = 'FAILURE';
    case TotalFailure = 'TOTAL_FAILURE';
}

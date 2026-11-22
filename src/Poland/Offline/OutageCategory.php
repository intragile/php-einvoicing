<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

/**
 * The category of an announced KSeF outage, matching the Latarnia message categories
 * MAINTENANCE (a planned unavailability), FAILURE and TOTAL_FAILURE.
 */
enum OutageCategory
{
    case Maintenance;
    case Failure;
    case TotalFailure;
}

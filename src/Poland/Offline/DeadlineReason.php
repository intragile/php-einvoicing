<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

/**
 * The rule that produced an offline sending deadline.
 */
enum DeadlineReason
{
    /** Offline24: the first working day after the issue date. */
    case NextWorkingDay;

    /** Unavailability: the first working day after the day the unavailability ended. */
    case AfterUnavailability;

    /** Failure: the 7th working day after the day the (last) failure ended. */
    case AfterFailure;

    /** A failure that has not ended yet: the deadline is not known. */
    case FailureOngoing;

    /** A total failure removes the duty to send the invoice to KSeF. */
    case TotalFailure;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

/**
 * Type of a Flux 10 transmission (TT-4).
 */
enum ReportType: string
{
    /** First transmission for the period. */
    case Initial = 'IN';

    /** Transmission that replaces an earlier one for the same period. */
    case Corrective = 'RE';
}

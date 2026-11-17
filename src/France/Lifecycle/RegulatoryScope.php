<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Regulatory scope of the exchange a lifecycle message reports on (MDT-2), the closed list of
 * rule BR-FR-CDV-CL-01.
 */
enum RegulatoryScope: string
{
    case Regulated = 'REGULATED';
    case NonRegulated = 'NON_REGULATED';
    case B2c = 'B2C';
    case B2cInt = 'B2CINT';
    case B2bInt = 'B2BINT';
    case OutOfScope = 'OUTOFSCOPE';
}

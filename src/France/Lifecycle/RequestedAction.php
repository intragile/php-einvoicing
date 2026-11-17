<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Action requested from the receiver of a lifecycle message (MDT-121), the closed list of rule
 * BR-FR-CDV-CL-10.
 */
enum RequestedAction: string
{
    case NOA = 'NOA';
    case PIN = 'PIN';
    case NIN = 'NIN';
    case CNF = 'CNF';
    case CNP = 'CNP';
    case CNA = 'CNA';
    case OTH = 'OTH';
}

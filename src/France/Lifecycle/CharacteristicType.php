<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Type of a data item attached to a status (MDT-207), such as MEN for the amount collected per VAT
 * rate of status 212; the closed list of rule BR-FR-CDV-CL-11.
 */
enum CharacteristicType: string
{
    case MEN = 'MEN';
    case MPA = 'MPA';
    case RAP = 'RAP';
    case ESC = 'ESC';
    case RAB = 'RAB';
    case REM = 'REM';
    case MAP = 'MAP';
    case MAPTTC = 'MAPTTC';
    case MNA = 'MNA';
    case MNATTC = 'MNATTC';
    case CBB = 'CBB';
    case DIV = 'DIV';
    case DVA = 'DVA';
    case MAJ = 'MAJ';
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

/**
 * Invoice treatment category, carried as the content of the note with subject code BAR.
 */
enum Treatment: string
{
    case B2B = 'B2B';
    case B2BINT = 'B2BINT';
    case B2C = 'B2C';
    case B2CINT = 'B2CINT';
    case OUTOFSCOPE = 'OUTOFSCOPE';
    case ARCHIVEONLY = 'ARCHIVEONLY';
}

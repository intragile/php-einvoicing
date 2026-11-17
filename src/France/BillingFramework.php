<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

/**
 * French billing framework (cadre de facturation), carried in the business process type (BT-23).
 */
enum BillingFramework: string
{
    case B1 = 'B1';
    case S1 = 'S1';
    case M1 = 'M1';
    case B2 = 'B2';
    case S2 = 'S2';
    case M2 = 'M2';
    case S3 = 'S3';
    case B4 = 'B4';
    case S4 = 'S4';
    case M4 = 'M4';
    case S5 = 'S5';
    case S6 = 'S6';
    case B7 = 'B7';
    case S7 = 'S7';
    case B8 = 'B8';
    case S8 = 'S8';
    case M8 = 'M8';
    case B9 = 'B9';
    case S9 = 'S9';
    case M9 = 'M9';
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) legal basis of a VAT exemption: each case is the name of the element carrying its text.
 */
enum ExemptionBasis: string
{
    /** A provision of the Polish VAT Act. */
    case Act = 'P_19A';
    /** A provision of Directive 2006/112/EC. */
    case Directive = 'P_19B';
    /** Another legal basis. */
    case Other = 'P_19C';
}

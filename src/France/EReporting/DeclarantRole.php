<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

/**
 * Role of the declarant of a Flux 10 transmission (TT-15).
 */
enum DeclarantRole: string
{
    case Seller = 'SE';
    case Buyer = 'BY';
}

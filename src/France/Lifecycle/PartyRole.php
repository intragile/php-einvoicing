<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Role of a party in a lifecycle message (MDT-21, MDT-40, MDT-59, MDT-158), UNTDID 3035 codes as
 * restricted by rules BR-FR-CDV-CL-02, -03, -04 and -08.
 */
enum PartyRole: string
{
    case Buyer = 'BY';
    case BuyerAgent = 'AB';
    case Factor = 'DL';
    case Seller = 'SE';
    case SellerAgent = 'SR';
    /** An approved platform (plateforme agréée) or dematerialisation operator. */
    case Platform = 'WK';
    case Payee = 'PE';
    case Payer = 'PR';
    case Invoicer = 'II';
    case Invoicee = 'IV';
    /** The public invoicing portal (PPF). */
    case PublicPortal = 'DFH';
}

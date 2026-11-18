<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) role of a third party on the invoice (Podmiot3/Rola, XSD type TRolaPodmiotu3).
 */
enum ThirdPartyRole: int
{
    case Factor = 1;
    case Recipient = 2;
    /** The original entity, e.g. after a merger, split or transformation. */
    case OriginalEntity = 3;
    case AdditionalBuyer = 4;
    /** The entity issuing the invoice on behalf of the seller. */
    case Issuer = 5;
    case Payer = 6;
    /** Local government unit issuing the invoice. */
    case JstIssuer = 7;
    /** Local government unit receiving the invoice. */
    case JstRecipient = 8;
    /** VAT group member issuing the invoice. */
    case GvMemberIssuer = 9;
    /** VAT group member receiving the invoice. */
    case GvMemberRecipient = 10;
    case Employee = 11;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) correction type (TypKorekty, XSD type TTypKorekty): the date in which the correction
 * takes effect for the seller.
 */
enum CorrectionEffect: int
{
    /** In the period of the original invoice. */
    case OriginalDate = 1;
    /** In the period of the corrective invoice. */
    case CorrectionDate = 2;
    /** In another period. */
    case OtherDate = 3;
}

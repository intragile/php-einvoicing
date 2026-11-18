<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) correction effect type (TypKorekty, XSD type TTypKorekty): the date on which the correction
 * takes effect in the VAT records.
 */
enum CorrectionEffect: int
{
    /** On the date the original invoice was recorded. */
    case OriginalDate = 1;
    /** On the issue date of the corrective invoice. */
    case CorrectionDate = 2;
    /** On another date, including when the dates differ between lines of the corrective invoice. */
    case OtherDate = 3;
}

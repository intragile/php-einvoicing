<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * ClaveTipoRectificativaType: how a corrective invoice relates to the corrected one.
 */
enum CorrectionType: string
{
    /** Substitution (S): the corrective invoice replaces the original amounts. */
    case Substitution = 'S';

    /** Difference (I): the corrective invoice carries only the difference. */
    case Difference = 'I';
}

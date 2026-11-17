<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

/**
 * Invoice line sub-type (EXT-FR-FE-163) of the EXTENDED-CTC-FR profile.
 *
 * A GROUP line heads the DETAIL lines that name it as their parent; an INFORMATION line carries
 * information only. GROUP and INFORMATION lines are not summed into the document totals, so they
 * are held as InformationalLine outside the invoice lines.
 */
enum LineSubType: string
{
    case Group = 'GROUP';
    case Detail = 'DETAIL';
    case Information = 'INFORMATION';
}

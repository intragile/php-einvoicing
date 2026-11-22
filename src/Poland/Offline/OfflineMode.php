<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

/**
 * The KSeF mode an invoice was issued in outside the online flow.
 *
 * Offline24 is the taxpayer's own choice (art. 106nda of the VAT Act), Unavailability is a
 * KSeF unavailability announced by the Ministry of Finance (art. 106nh), Failure is an announced
 * KSeF failure (art. 106nf) and TotalFailure is an announced total failure, which removes the
 * duty to send the invoice to KSeF.
 */
enum OfflineMode
{
    case Offline24;
    case Unavailability;
    case Failure;
    case TotalFailure;
}

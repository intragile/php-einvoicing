<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * Directory status of a recipient.
 */
enum AnnuaireStatus
{
    /** Listed and able to receive e-invoices. */
    case Active;

    /** Listed but no longer active. */
    case Inactive;

    /** Not listed in the directory. */
    case NotListed;

    /** The status could not be determined, for example because the lookup failed. */
    case Unknown;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * The scope a send throttle applies to.
 */
enum ThrottleScope: string
{
    /** One throttle per installation of the billing software and environment. */
    case Installation = 'installation';
    /** One throttle per issuer, installation and environment. */
    case Issuer = 'issuer';
}

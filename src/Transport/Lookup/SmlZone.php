<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * The DNS zones of the Peppol Service Metadata Locator, operated by OpenPeppol.
 */
enum SmlZone: string
{
    case Production = 'participant.sml.prod.tech.peppol.org';
    case Test = 'participant.sml.test.tech.peppol.org';
}

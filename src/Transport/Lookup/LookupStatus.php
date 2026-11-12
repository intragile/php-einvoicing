<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * Whether a participant is registered in the Peppol network.
 */
enum LookupStatus
{
    /** The SML points to an SMP that knows the participant (and the document type, when asked). */
    case Registered;

    /** The SML has no entry for the participant, or its SMP answered 404. */
    case NotRegistered;

    /** The lookup failed (DNS failure, unusable SMP URL, SMP error or timeout); try again later. */
    case Unknown;
}

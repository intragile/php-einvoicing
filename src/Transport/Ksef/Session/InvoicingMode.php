<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

/**
 * How KSeF classified an accepted invoice (invoicingMode in the KSeF API 2.0).
 *
 * KSeF decides this itself; the offlineMode flag of a send is only one input. An invoice sent
 * without the flag whose issue date (P_1) lies before the day of the send is classified as
 * Offline as well. Use the reported mode, not a local prediction, to decide whether the offline
 * rules (KOD II on the visualisation, the sending deadline) apply.
 */
enum InvoicingMode: string
{
    /** Issued and sent in KSeF on the same day. */
    case Online = 'Online';

    /** Issued outside KSeF and sent later: offline24, unavailability, failure or a technical correction. */
    case Offline = 'Offline';

    /**
     * The mode KSeF reported, or null when the value is absent or not one this version knows.
     */
    public static function fromAnswer(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(trim($value)) : null;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

/**
 * The state of a B2Brouter ledger, the e-reporting transmission B2Brouter sends to the DGFiP.
 *
 * States advance asynchronously as the DGFiP processes the file: Acknowledged stands for the
 * DGFiP status 500, Registered for 300, Refused for 301 (with the DGFiP error code) and Error for
 * 501. A state B2Brouter adds later, such as "replaced" for a file superseded by a corrective
 * transmission of the same period, reads as Unknown.
 */
enum LedgerState: string
{
    case New = 'new';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Deposited = 'deposited';
    case Registered = 'registered';
    case Refused = 'refused';
    case Error = 'error';
    case Unknown = 'unknown';

    /**
     * Whether the state is final: the DGFiP registered or refused the file, or sending it failed.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Registered, self::Refused, self::Error => true,
            default => false,
        };
    }
}

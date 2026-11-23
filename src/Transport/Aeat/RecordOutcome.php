<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Transport\DeliveryState;

/**
 * What a submission did to one billing record, derived from its response line.
 */
enum RecordOutcome: string
{
    /** Registered without errors. */
    case Accepted = 'accepted';
    /** Registered; AEAT reported an error that must be remedied with a correction. */
    case AcceptedWithErrors = 'accepted_with_errors';
    /** Not registered. */
    case Rejected = 'rejected';
    /**
     * AEAT already holds a record for this IDFactura (Correcta or AceptadaConErrores); no new CSV
     * was issued. The held record is not necessarily this one, since AEAT matches the invoice id,
     * not the Huella: unless the submission was a byte-identical resend, confirm it with
     * AeatClient::lookup() and RecordStatus::matches().
     */
    case AlreadyRegistered = 'already_registered';
    /** AEAT already holds the invoice as cancelled. */
    case AlreadyCancelled = 'already_cancelled';
    /** The response carries no line for the record, so its fate is unknown. */
    case Unconfirmed = 'unconfirmed';

    /**
     * The delivery state of the record. An invoice that is already cancelled confirms a
     * cancellation but defeats a registration.
     *
     * @param  bool  $cancellation  whether the record was a RegistroAnulacion
     */
    public function deliveryState(bool $cancellation): DeliveryState
    {
        return match ($this) {
            self::Accepted, self::AcceptedWithErrors, self::AlreadyRegistered => DeliveryState::Delivered,
            self::AlreadyCancelled => $cancellation ? DeliveryState::Delivered : DeliveryState::Failed,
            self::Rejected => DeliveryState::Failed,
            self::Unconfirmed => DeliveryState::Unknown,
        };
    }
}

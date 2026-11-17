<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;

/**
 * Maps B2Brouter invoice states to French lifecycle statuses (CDV codes).
 *
 * B2Brouter emits the lifecycle messages itself from its invoice states; this mapping reads the
 * status back from the state. A state that stands for no status, or one this library does not
 * know, maps to null.
 *
 * @internal
 */
final class LifecycleMapping
{
    /**
     * The status of an invoice the account issued.
     *
     * "accepted" maps to Approved (205): B2Brouter uses the same state for a partial approval
     * (206), which the state alone cannot tell apart. "paid" maps to Collected (212), the status
     * a seller sends once paid; it is a mandatory status only for services.
     */
    public static function forIssued(string $state): ?LifecycleStatus
    {
        return match ($state) {
            'sent' => LifecycleStatus::Deposited,
            'registered' => LifecycleStatus::Received,
            'read' => LifecycleStatus::InProcess,
            'accepted' => LifecycleStatus::Approved,
            'annotated' => LifecycleStatus::Disputed,
            'refused' => LifecycleStatus::Refused,
            'paid' => LifecycleStatus::Collected,
            default => null,
        };
    }

    /**
     * The status of an invoice the account received, as the buyer's response sets it.
     *
     * "paid" maps to PaymentSent (211), the status a buyer sends. "annotated" maps to null: the
     * buyer's platform sends Disputed (207) only when the annotation is a dispute, and the state
     * does not say whether it was one.
     */
    public static function forReceived(string $state): ?LifecycleStatus
    {
        return match ($state) {
            'read' => LifecycleStatus::InProcess,
            'accepted' => LifecycleStatus::Approved,
            'refused' => LifecycleStatus::Refused,
            'paid' => LifecycleStatus::PaymentSent,
            default => null,
        };
    }
}

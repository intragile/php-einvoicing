<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\FailureCategory;

/**
 * Classifies AEAT error codes by scope, failure category and recommended action.
 *
 * The table is our own reading of the code ranges; the AEAT's descriptions are not reproduced,
 * and a response's text is passed through as received.
 */
final class AeatErrors
{
    /** Codes that mean the certificate or the caller's authorisation was refused. */
    public const AUTHENTICATION_CODES = [4108, 4112, 4132];

    /** Submission-level codes caused by a temporary technical failure on the AEAT side. */
    private const TRANSIENT_CODES = [4110, 4111, 4118, 4128, 4134, 3500, 3501];

    /**
     * Classifies a code. A code outside the known ranges is Record scope, category Other,
     * action Investigate.
     */
    public static function classify(int $code): AeatError
    {
        if (self::inRange($code, 4100, 4199) || self::inRange($code, 3500, 3599)) {
            return self::submission($code);
        }

        if (self::inRange($code, 2000, 2099)) {
            return new AeatError($code, ErrorScope::AcceptedWithErrors, FailureCategory::Validation, ErrorAction::CorrectAcceptedRecord);
        }

        if (self::inRange($code, 1100, 1299) || self::inRange($code, 3000, 3099)) {
            return self::record($code);
        }

        return new AeatError($code, ErrorScope::Record, FailureCategory::Other, ErrorAction::Investigate);
    }

    /**
     * The delivery failure for a code: the category from the table, the code as the provider
     * code and the description exactly as the AEAT sent it.
     */
    public static function failure(int $code, ?string $description): DeliveryFailure
    {
        return new DeliveryFailure(self::classify($code)->category, $description, (string) $code);
    }

    /**
     * The AEAT code a transport exception carries, or null when it carries none.
     */
    public static function codeOf(TransportException $exception): ?int
    {
        if ($exception instanceof AuthenticationFailed || $exception instanceof DocumentRejected) {
            return $exception->providerCode;
        }

        return null;
    }

    private static function submission(int $code): AeatError
    {
        if (in_array($code, self::AUTHENTICATION_CODES, true)) {
            return new AeatError($code, ErrorScope::Submission, FailureCategory::Other, ErrorAction::FixConfiguration, true);
        }

        if (in_array($code, self::TRANSIENT_CODES, true)) {
            return new AeatError($code, ErrorScope::Submission, FailureCategory::Transport, ErrorAction::RetrySameBytes);
        }

        return new AeatError($code, ErrorScope::Submission, FailureCategory::Validation, ErrorAction::FixConfiguration);
    }

    private static function record(int $code): AeatError
    {
        return match ($code) {
            3000, 3001 => new AeatError($code, ErrorScope::Record, FailureCategory::Duplicate, ErrorAction::ResolveDuplicate),
            // 3002 is also the code for a cancellation (anulación) of an invoice with no prior record.
            3002 => new AeatError($code, ErrorScope::Record, FailureCategory::Validation, ErrorAction::RegenerateAfterRejection),
            3003, 3004 => new AeatError($code, ErrorScope::Record, FailureCategory::Validation, ErrorAction::FixConfiguration),
            default => new AeatError($code, ErrorScope::Record, FailureCategory::Validation, ErrorAction::RegenerateAfterRejection),
        };
    }

    private static function inRange(int $code, int $from, int $to): bool
    {
        return $code >= $from && $code <= $to;
    }
}

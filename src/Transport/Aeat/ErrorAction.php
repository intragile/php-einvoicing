<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * What the caller should do about an AEAT error code.
 */
enum ErrorAction: string
{
    /** Send the same bytes again later; the failure was temporary and the chain is untouched. */
    case RetrySameBytes = 'retry_same_bytes';
    /** Fix the configuration, certificate or submission data, then send again. */
    case FixConfiguration = 'fix_configuration';
    /** Build a new record from the corrected source data; the refused record never entered the chain. */
    case RegenerateAfterRejection = 'regenerate_after_rejection';
    /** Submit a correction (subsanación) for the record that was accepted. */
    case CorrectAcceptedRecord = 'correct_accepted_record';
    /** The record exists already; look it up and reconcile instead of sending it again. */
    case ResolveDuplicate = 'resolve_duplicate';
    /** The code is not known to this version; inspect the response manually. */
    case Investigate = 'investigate';
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * How much of a submission an AEAT error code affects.
 */
enum ErrorScope: string
{
    /** The whole submission was refused; no record in it was stored. */
    case Submission = 'submission';
    /** One record was refused; other records of the same submission are unaffected. */
    case Record = 'record';
    /** The record was accepted and stored, and must be corrected afterwards. */
    case AcceptedWithErrors = 'accepted_with_errors';
}

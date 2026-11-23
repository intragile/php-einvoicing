<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * The outcome of a cross-check step or of one difference found in it.
 */
enum CheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    /** A documented, accepted divergence between B2Brouter's record and ours; not a failure. */
    case KnownDivergence = 'known_divergence';

    /** The step could not run, for example because an earlier step failed or an input is missing. */
    case Skipped = 'skipped';
}

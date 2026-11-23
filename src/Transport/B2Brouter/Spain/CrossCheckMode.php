<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * How a caller treats a cross-check result: Strict fails on any failed step, Report only records
 * the result and never fails.
 */
enum CrossCheckMode: string
{
    case Strict = 'strict';
    case Report = 'report';
}

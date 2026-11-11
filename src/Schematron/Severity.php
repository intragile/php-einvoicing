<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

/**
 * The flag of a Schematron assert.
 */
enum Severity: string
{
    case Fatal = 'fatal';
    case Warning = 'warning';
    case Information = 'information';
}

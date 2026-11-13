<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Validation\DocumentValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;

/**
 * A validator double that records every document it is given and reports it valid, naming
 * itself as the only rule set ("<name> 1.0").
 */
final class RecordingValidator implements DocumentValidator
{
    /** @var list<string> */
    public array $seen = [];

    public function __construct(private readonly string $name) {}

    public function validate(string $xml): ValidationReport
    {
        $this->seen[] = $xml;

        return new ValidationReport([$this->name.' 1.0'], Syntax::Ubl, [], []);
    }
}

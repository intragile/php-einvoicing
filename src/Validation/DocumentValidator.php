<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * Validates an XML business document and reports the result.
 */
interface DocumentValidator
{
    /**
     * Validates an XML document. A document the validator cannot handle at all, such as one of an
     * unsupported syntax or specification, is refused with an exception instead of a report.
     */
    public function validate(string $xml): ValidationReport;
}

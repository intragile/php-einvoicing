<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;

/**
 * Caller-chosen AEAT classification of one invoice VAT breakdown group.
 *
 * Every unset field falls back to the default derived from the EN 16931 VAT category. A
 * breakdown group is either subject to tax (an operation qualification) or exempt (an exemption
 * cause), never both.
 */
final readonly class BreakdownOverride
{
    public function __construct(
        public ?TaxType $tax = null,
        public ?RegimeKey $regime = null,
        public ?OperationQualification $qualification = null,
        public ?ExemptOperation $exemption = null,
        public ?Amount $costBase = null,
        public ?TaxRate $equivalenceRate = null,
        public ?Amount $equivalenceQuota = null,
    ) {
        if ($qualification !== null && $exemption !== null) {
            throw new InvalidRecord('A breakdown group carries either an operation qualification or an exemption, not both.');
        }
    }
}

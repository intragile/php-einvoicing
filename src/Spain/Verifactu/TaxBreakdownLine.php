<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * One line of the tax breakdown (DetalleDesglose).
 */
final readonly class TaxBreakdownLine
{
    public function __construct(
        public ?TaxType $tax,
        public ?RegimeKey $regime,
        public ?OperationQualification $qualification,
        public ?ExemptOperation $exemption,
        public ?TaxRate $rate,
        public Amount $base,
        public ?Amount $costBase,
        public ?Amount $quota,
        public ?TaxRate $equivalenceRate,
        public ?Amount $equivalenceQuota,
    ) {
        if (($qualification === null) === ($exemption === null)) {
            throw new InvalidRecord('A breakdown line needs exactly one of CalificacionOperacion or OperacionExenta.');
        }
    }

    /** The same line with every amount sign-flipped; rates are unchanged. */
    public function negated(): self
    {
        return new self(
            $this->tax,
            $this->regime,
            $this->qualification,
            $this->exemption,
            $this->rate,
            $this->base->negated(),
            $this->costBase?->negated(),
            $this->quota?->negated(),
            $this->equivalenceRate,
            $this->equivalenceQuota?->negated(),
        );
    }
}

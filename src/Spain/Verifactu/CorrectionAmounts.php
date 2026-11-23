<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

/**
 * The amounts of a corrective invoice issued by substitution (ImporteRectificacion).
 */
final readonly class CorrectionAmounts
{
    public function __construct(
        public Amount $base,
        public Amount $quota,
        public ?Amount $equivalenceQuota,
    ) {}
}

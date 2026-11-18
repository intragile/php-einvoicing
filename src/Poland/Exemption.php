<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * A VAT exemption (P_19 = 1) with its legal basis: the basis selects the element (P_19A, P_19B
 * or P_19C) that carries the text.
 */
final readonly class Exemption
{
    public function __construct(
        public ExemptionBasis $basis,
        /** Text of the legal provision the exemption rests on. */
        public string $text,
    ) {}
}

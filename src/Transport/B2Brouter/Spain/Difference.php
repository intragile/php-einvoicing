<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * One difference found by a cross-check step: what our side expects against what B2Brouter's
 * record or report carries.
 */
final readonly class Difference
{
    /**
     * @param  CheckStatus  $status  Failed or KnownDivergence
     * @param  string  $field  the AEAT element or report field, for example "ImporteTotal" or
     *                         "DetalleDesglose[1].OperacionExenta"
     * @param  string  $ours  the value we expect, '' when there is none
     * @param  string  $theirs  the value B2Brouter's record or report carries, '' when there is none
     */
    public function __construct(
        public CrossCheckStep $step,
        public CheckStatus $status,
        public string $field,
        public string $ours,
        public string $theirs,
        public string $message,
    ) {}
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Process control (BG-2).
 *
 * When an invoice carries no process control, writers use the specification identifier of the
 * syntax they produce.
 */
final readonly class ProcessControl
{
    public function __construct(
        /** Specification identifier (BT-24), e.g. "urn:cen.eu:en16931:2017". */
        public string $specificationIdentifier,
        /** Business process type (BT-23). */
        public ?string $businessProcessType = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

/**
 * A failed assert on one node.
 */
final readonly class Finding
{
    public function __construct(
        public string $ruleId,
        public Severity $severity,
        public string $location,
        public string $message,
        public string $test,
    ) {}
}

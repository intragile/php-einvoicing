<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

/**
 * One rule reported by an official Schematron stylesheet (an SVRL failed-assert or successful-report).
 */
final readonly class FiredRule
{
    /**
     * @param  string  $id  Rule identifier, for example "BR-CO-16".
     * @param  string  $flag  Severity as published in the CEN artefacts: "fatal" or "warning".
     * @param  string  $location  XPath of the context node the rule fired on.
     */
    public function __construct(
        public string $id,
        public string $flag,
        public string $location,
        public string $text = '',
    ) {}

    /**
     * Fails closed: only the explicit non-fatal flags "warning" and "information" are tolerated, so
     * a missing or unknown flag counts as fatal.
     */
    public function isFatal(): bool
    {
        return ! in_array($this->flag, ['warning', 'information'], true);
    }

    public function __toString(): string
    {
        return "{$this->id} [{$this->flag}] at {$this->location}: {$this->text}";
    }
}

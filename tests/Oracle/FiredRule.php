<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

/**
 * One failed assertion reported by an official Schematron stylesheet (an SVRL failed-assert).
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

    public function isFatal(): bool
    {
        return $this->flag === 'fatal';
    }

    public function __toString(): string
    {
        return "{$this->id} [{$this->flag}] at {$this->location}: {$this->text}";
    }
}

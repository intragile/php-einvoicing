<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * A dynamic XPath error (such as FORG0001 for a non-numeric amount) raised while evaluating an
 * assert test, or a rule context when $ruleId is null. $ruleSet names the rule set that raised
 * it as "<id> <version>", when known.
 */
final readonly class DynamicError
{
    public function __construct(
        public ?string $ruleId,
        public string $errorCode,
        public string $location,
        public string $message,
        public ?string $ruleSet = null,
    ) {}
}

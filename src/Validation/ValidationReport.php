<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Syntax\Syntax;

/**
 * Outcome of validating one document against the rule sets of its syntax.
 */
final readonly class ValidationReport
{
    /**
     * @param  list<string>  $ruleSets  the rule sets that ran, as "<id> <version>", in run order
     * @param  list<Finding>  $findings  failed asserts, in rule set order, then pattern order, then
     *                                   document order
     * @param  list<DynamicError>  $dynamicErrors  asserts that could not be evaluated, such as a
     *                                             non-numeric amount (FORG0001)
     */
    public function __construct(
        public array $ruleSets,
        public Syntax $syntax,
        public array $findings,
        public array $dynamicErrors,
    ) {}

    /**
     * True when no fatal assert failed and every assert could be evaluated. Warnings and
     * information findings do not make a document invalid.
     */
    public function isValid(): bool
    {
        return $this->dynamicErrors === [] && $this->fatal() === [];
    }

    /**
     * @return list<Finding>
     */
    public function fatal(): array
    {
        return $this->withSeverity(Severity::Fatal);
    }

    /**
     * @return list<Finding>
     */
    public function warnings(): array
    {
        return $this->withSeverity(Severity::Warning);
    }

    /**
     * @return list<Finding>
     */
    public function information(): array
    {
        return $this->withSeverity(Severity::Information);
    }

    /**
     * The findings as comparable data: sorted, de-duplicated "<rule id> <flag>" strings, the form
     * in which verdicts are compared with the official validation artefacts.
     *
     * @return list<string>
     */
    public function verdict(): array
    {
        $verdict = array_values(array_unique(array_map(
            static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value}",
            $this->findings,
        )));
        sort($verdict, SORT_STRING);

        return $verdict;
    }

    /**
     * @return list<Finding>
     */
    private function withSeverity(Severity $severity): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->severity === $severity,
        ));
    }
}

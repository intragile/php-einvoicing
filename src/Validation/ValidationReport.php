<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Schematron\DynamicError;
use IntrAgile\EInvoicing\Schematron\Finding;
use IntrAgile\EInvoicing\Schematron\Severity;
use IntrAgile\EInvoicing\Syntax\Syntax;

/**
 * Outcome of validating one document against a rule set.
 */
final readonly class ValidationReport
{
    /**
     * @param  list<Finding>  $findings  failed asserts, in pattern order, then document order
     * @param  list<DynamicError>  $errors  asserts that could not be evaluated, such as a
     *                                      non-numeric amount (FORG0001)
     */
    public function __construct(
        public string $ruleSet,
        public string $version,
        public Syntax $syntax,
        public array $findings,
        public array $errors,
    ) {}

    /**
     * True when no fatal assert failed and every assert could be evaluated. Warnings and
     * information findings do not make a document invalid.
     */
    public function isValid(): bool
    {
        return $this->errors === [] && $this->fatal() === [];
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

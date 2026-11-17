<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * The verdict behaviour shared by the validation reports: validity, findings by severity and the
 * comparable verdict.
 *
 * @internal
 *
 * @property-read list<Finding> $findings
 * @property-read list<DynamicError> $dynamicErrors
 * @property-read list<SchemaError> $schemaErrors
 */
trait ReportFindings
{
    /**
     * True when the document conforms to its XML Schema, no fatal assert failed and every assert
     * could be evaluated. Warnings and information findings do not make a document invalid.
     */
    public function isValid(): bool
    {
        return $this->schemaErrors === [] && $this->dynamicErrors === [] && $this->fatal() === [];
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

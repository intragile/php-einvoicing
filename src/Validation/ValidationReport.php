<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Syntax\Syntax;

/**
 * Outcome of validating one document against the rule sets of its syntax, which run one after
 * the other (for Peppol: OpenPeppol's copy of the CEN rules, then the Peppol rules).
 */
final readonly class ValidationReport
{
    use ReportFindings;

    /**
     * @param  list<string>  $ruleSets  the rule sets that ran, as "<id> <version>", in run order
     * @param  list<Finding>  $findings  failed asserts, in rule set order, then pattern order, then
     *                                   document order
     * @param  list<DynamicError>  $dynamicErrors  asserts that could not be evaluated, such as a
     *                                             non-numeric amount (FORG0001), in rule set order
     * @param  list<SchemaError>  $schemaErrors  XML Schema violations, in document order
     * @param  string|null  $scenario  the name of the validation scenario that was applied, when
     *                                 the validator selects one
     */
    public function __construct(
        public array $ruleSets,
        public Syntax $syntax,
        public array $findings,
        public array $dynamicErrors,
        public array $schemaErrors = [],
        public ?string $scenario = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * Outcome of validating one message that is not an invoice, such as a French CDAR lifecycle
 * message or a Flux 10 e-reporting transmission, against its XML Schema and rule set. Validity and
 * verdict follow the same rules as ValidationReport.
 */
final readonly class MessageValidationReport
{
    use ReportFindings;

    /**
     * @param  MessageType  $type  the kind of message that was validated
     * @param  list<string>  $ruleSets  the rule sets that ran, as "<id> <version>", in run order
     * @param  list<Finding>  $findings  failed asserts, in rule set order, then pattern order, then
     *                                   document order
     * @param  list<DynamicError>  $dynamicErrors  asserts that could not be evaluated, in rule set
     *                                             order
     * @param  list<SchemaError>  $schemaErrors  XML Schema violations, in document order
     */
    public function __construct(
        public MessageType $type,
        public array $ruleSets,
        public array $findings,
        public array $dynamicErrors,
        public array $schemaErrors = [],
    ) {}
}

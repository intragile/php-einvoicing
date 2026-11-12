<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\ValidationReport;

/**
 * The document failed validation before it was sent; the report holds the findings.
 */
final class InvalidDocument extends TransportException
{
    /** Number of rule ids named in the message. */
    private const LISTED = 10;

    public function __construct(public readonly ValidationReport $report)
    {
        parent::__construct(self::describe($report));
    }

    public function isRetryable(): bool
    {
        return false;
    }

    private static function describe(ValidationReport $report): string
    {
        $fatal = $report->fatal();
        $rules = array_values(array_unique([
            ...array_map(static fn (Finding $finding): string => $finding->ruleId, $fatal),
            ...array_map(static fn (DynamicError $error): string => $error->ruleId ?? $error->errorCode, $report->dynamicErrors),
        ]));
        $listed = implode(', ', array_slice($rules, 0, self::LISTED));
        if (count($rules) > self::LISTED) {
            $listed .= sprintf(' and %d more', count($rules) - self::LISTED);
        }

        return sprintf(
            'The document was not sent: validation found %d fatal finding(s) and %d dynamic error(s) (%s).',
            count($fatal),
            count($report->dynamicErrors),
            $listed,
        );
    }
}

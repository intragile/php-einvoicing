<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use DOMDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaValidator;

/**
 * Runs the installed XML Schema and rule set of a French message on a document whose element the
 * caller has already checked. The message type's value is both the schema tree and the rule set
 * key. The rule set runs even when the schema check fails, so the report lists every problem.
 *
 * @internal
 */
final class MessageRun
{
    private readonly Runner $runner;

    private readonly SchemaValidator $schemaValidator;

    /** @var array<string, RuleSet> loaded rule sets, keyed by message type */
    private array $loaded = [];

    public function __construct(private readonly string $rulesDir)
    {
        $this->runner = new Runner;
        $this->schemaValidator = new SchemaValidator;
    }

    /**
     * @param  string  $schema  path of the top-level schema below the root of the type's tree
     *
     * @throws RulesNotInstalled when the French rules or schemas are not installed in the folder
     * @throws UnusableSchema when the installed schema cannot be used
     * @throws UnsupportedSchematron when the installed rule set cannot be used
     */
    public function validate(DOMDocument $document, MessageType $type, string $schema): MessageValidationReport
    {
        $schemaErrors = $this->schemaValidator->validate($document, FranceRules::schema($this->rulesDir, $type->value, $schema));
        $ruleSet = $this->loaded[$type->value] ??= FranceRules::load($this->rulesDir, $type->value);
        $result = $this->runner->run($ruleSet, $document);

        return new MessageValidationReport(
            $type,
            ["{$ruleSet->id()} {$ruleSet->version()}"],
            $result->findings,
            $result->dynamicErrors,
            $schemaErrors,
        );
    }
}

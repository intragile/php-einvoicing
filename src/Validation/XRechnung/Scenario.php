<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\XRechnung;

use DOMDocument;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\XPathError;
use InvalidArgumentException;

/**
 * One validation scenario of the KoSIT validator configuration: which documents it applies to,
 * the XML Schema and the rule sets they are checked with, and the custom levels that override
 * the flags of individual rules.
 *
 * The match expression is compiled once, with the scenario's namespace bindings, and evaluated as
 * an effective boolean value with the document node as the context item, as KoSIT does.
 */
final readonly class Scenario
{
    /**
     * @param  array<mixed>  $match  the compiled match expression
     * @param  string  $schema  path of the XML Schema, relative to the schema folder
     * @param  list<array{id: string, version: string}>  $ruleSets  in run order
     * @param  array<string, Severity>  $customLevels  rule id => severity that replaces its flag
     */
    private function __construct(
        public string $name,
        private array $match,
        public string $schema,
        public array $ruleSets,
        public array $customLevels,
    ) {}

    /**
     * Builds a scenario from one row of the compiled scenario table.
     *
     * @param  array<mixed>  $row  with the keys name, namespaces, match, schema, ruleSets and
     *                             customLevels
     *
     * @throws InvalidArgumentException when the row is malformed or its match expression cannot be
     *                                  compiled
     */
    public static function fromArray(array $row): self
    {
        $name = self::string($row, 'name');
        $namespaces = $row['namespaces'] ?? null;
        $ruleSets = $row['ruleSets'] ?? null;
        $levels = $row['customLevels'] ?? null;
        if (! is_array($namespaces) || ! is_array($ruleSets) || ! is_array($levels)) {
            throw new InvalidArgumentException("The scenario \"{$name}\" lacks namespaces, ruleSets or customLevels.");
        }

        $bindings = [];
        foreach ($namespaces as $prefix => $uri) {
            if (! is_string($prefix) || ! is_string($uri)) {
                throw new InvalidArgumentException("The scenario \"{$name}\" has a malformed namespace binding.");
            }
            $bindings[$prefix] = $uri;
        }

        $steps = [];
        foreach ($ruleSets as $ruleSet) {
            if (! is_array($ruleSet)) {
                throw new InvalidArgumentException("The scenario \"{$name}\" has a malformed rule set reference.");
            }
            $steps[] = ['id' => self::string($ruleSet, 'id'), 'version' => self::string($ruleSet, 'version')];
        }

        $customLevels = [];
        foreach ($levels as $ruleId => $level) {
            $customLevels[(string) $ruleId] = match ($level) {
                'error' => Severity::Fatal,
                'warning' => Severity::Warning,
                'information' => Severity::Information,
                default => throw new InvalidArgumentException("The scenario \"{$name}\" has an unknown custom level for {$ruleId}."),
            };
        }

        $expression = self::string($row, 'match');
        try {
            $match = (new Parser)->parse($expression, new StaticContext($bindings));
        } catch (XPathSyntaxError|UnsupportedXPath $error) {
            throw new InvalidArgumentException("The match expression of the scenario \"{$name}\" cannot be compiled: {$error->getMessage()}", 0, $error);
        }

        return new self($name, $match, self::string($row, 'schema'), $steps, $customLevels);
    }

    /**
     * Whether the scenario applies to the document. A dynamic error in the match expression
     * counts as no match.
     *
     * @param  Evaluator|null  $evaluator  an evaluator built on the index of $document, to share it
     *                                     between scenarios; one is built when omitted
     */
    public function matches(DOMDocument $document, ?Evaluator $evaluator = null): bool
    {
        $evaluator ??= new Evaluator(DocumentIndex::build($document));
        try {
            return $evaluator->effectiveBoolean($this->match, $document);
        } catch (XPathError) {
            return false;
        }
    }

    /**
     * The severity of a finding under this scenario: its custom level when one is set, otherwise
     * the flag of the rule.
     */
    public function severity(string $ruleId, Severity $flag): Severity
    {
        return $this->customLevels[$ruleId] ?? $flag;
    }

    /**
     * @param  array<mixed>  $row
     */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("The scenario row lacks the string \"{$key}\".");
        }

        return $value;
    }
}

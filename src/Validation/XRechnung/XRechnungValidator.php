<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\XRechnung;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles as En16931RuleFiles;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles as XRechnungRuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Validation\DocumentValidator;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use InvalidArgumentException;

/**
 * Validates XRechnung 3.0 invoices the way the KoSIT validator does with its XRechnung
 * configuration: the first scenario whose match expression holds is applied, the document is
 * checked against that scenario's XML Schema and, only when it is schema valid, against the
 * scenario's Schematron rule sets in order; the scenario's custom levels then replace the flags
 * of individual rules.
 *
 * The configuration also covers plain EN 16931 documents (specification identifier
 * "urn:cen.eu:en16931:2017"), which are checked with the CEN rules only.
 *
 * Usage: XRechnungValidator::create()->validate($xml)->isValid(). It needs the package
 * intragile/einvoicing-rules-xrechnung.
 */
final class XRechnungValidator implements DocumentValidator
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-xrechnung';

    /** @var non-empty-list<Scenario> */
    private readonly array $scenarios;

    private readonly Runner $runner;

    private readonly SchemaValidator $schemaValidator;

    /** @var array<string, RuleSet> loaded rule sets, keyed by "<id> <version>" */
    private array $loaded = [];

    /**
     * @param  string  $scenarioTable  path of the compiled scenario table
     * @param  string  $schemaDir  folder the scenario schemas are relative to
     * @param  array<string, string>  $ruleFiles  "<id> <version>" => path of the compiled rule set,
     *                                            for every rule set the table names
     *
     * @throws RulesNotInstalled when the scenario table is missing or names a rule set that is
     *                           not given
     * @throws InvalidArgumentException when the scenario table is malformed
     */
    public function __construct(string $scenarioTable, private readonly string $schemaDir, private readonly array $ruleFiles)
    {
        if (! is_file($scenarioTable)) {
            throw new RulesNotInstalled(sprintf(
                'The XRechnung scenario table "%s" does not exist. Install the XRechnung rules: %s',
                $scenarioTable,
                self::COMPOSER_COMMAND,
            ));
        }

        $table = require $scenarioTable;
        if (! is_array($table) || ! is_array($table['scenarios'] ?? null) || $table['scenarios'] === []) {
            throw new InvalidArgumentException("The XRechnung scenario table \"{$scenarioTable}\" is malformed.");
        }

        $scenarios = [];
        foreach ($table['scenarios'] as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("The XRechnung scenario table \"{$scenarioTable}\" is malformed.");
            }
            $scenario = Scenario::fromArray($row);
            foreach ($scenario->ruleSets as $ruleSet) {
                $key = "{$ruleSet['id']} {$ruleSet['version']}";
                if (! isset($ruleFiles[$key])) {
                    throw new RulesNotInstalled(sprintf(
                        'The scenario "%s" needs the rule set %s, which is not installed. Install matching rule packages: %s',
                        $scenario->name,
                        $key,
                        self::COMPOSER_COMMAND,
                    ));
                }
            }
            $scenarios[] = $scenario;
        }

        $this->scenarios = $scenarios;
        $this->runner = new Runner;
        $this->schemaValidator = new SchemaValidator;
    }

    /**
     * A validator for the KoSIT XRechnung configuration shipped in
     * intragile/einvoicing-rules-xrechnung.
     *
     * @throws RulesNotInstalled when that package is not installed
     */
    public static function create(): self
    {
        if (! class_exists(XRechnungRuleFiles::class)) {
            throw new RulesNotInstalled('XRechnung validation needs the KoSIT XRechnung rules. Run: '.self::COMPOSER_COMMAND);
        }

        // The KoSIT configuration pins CEN 1.3.16, which is not the default CEN release.
        $cen = '1.3.16';

        return new self(XRechnungRuleFiles::scenarios(), XRechnungRuleFiles::schemaDir(), [
            "en16931-ubl {$cen}" => En16931RuleFiles::ubl($cen),
            "en16931-cii {$cen}" => En16931RuleFiles::cii($cen),
            'xrechnung-ubl '.XRechnungRuleFiles::VERSION => XRechnungRuleFiles::ubl(),
            'xrechnung-cii '.XRechnungRuleFiles::VERSION => XRechnungRuleFiles::cii(),
        ]);
    }

    /**
     * Validates an XML document. When it violates the scenario's XML Schema, the Schematron rule
     * sets do not run, as with KoSIT: the report then has schema errors, no findings and no rule
     * sets, and is not valid.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or no scenario matches
     *                             it, for example because its specification identifier (BT-24) is
     *                             not one of XRechnung::CIUS, EXTENSION, CVD or the EN 16931 one
     * @throws UnusableSchema when a scenario schema of the rules package cannot be used
     * @throws UnsupportedSchematron when a rule set was compiled by an engine of another format
     * @throws RulesNotInstalled when a rule file holds another rule set or version than the
     *                           scenario table names for it
     */
    public function validate(string $xml): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $evaluator = new Evaluator(DocumentIndex::build($document));

        $scenario = null;
        foreach ($this->scenarios as $candidate) {
            if ($candidate->matches($document, $evaluator)) {
                $scenario = $candidate;
                break;
            }
        }
        if ($scenario === null) {
            throw new UnsupportedDocument(sprintf(
                'No XRechnung validation scenario matches the document. The specification identifier (BT-24) must be exactly one of %s (XRechnung 3.0), %s (XRechnung 3.0 Extension), %s (XRechnung CVD) or urn:cen.eu:en16931:2017 (EN 16931), in a UBL Invoice, UBL CreditNote or CII CrossIndustryInvoice.',
                XRechnung::CIUS,
                XRechnung::EXTENSION,
                XRechnung::CVD,
            ));
        }

        // A matched scenario implies a UBL or CII document element.
        $syntax = Syntax::detect($document);
        $schemaErrors = $this->schemaValidator->validate($document, $this->schemaDir.'/'.$scenario->schema);
        if ($schemaErrors !== []) {
            return new ValidationReport([], $syntax, [], [], $schemaErrors, $scenario->name);
        }

        $names = [];
        $findings = [];
        $dynamicErrors = [];
        foreach ($scenario->ruleSets as $step) {
            $ruleSet = $this->ruleSet("{$step['id']} {$step['version']}");
            $result = $this->runner->run($ruleSet, $document);
            $names[] = "{$ruleSet->id()} {$ruleSet->version()}";
            foreach ($result->findings as $finding) {
                $severity = $scenario->severity($finding->ruleId, $finding->severity);
                $findings[] = $severity === $finding->severity
                    ? $finding
                    : new Finding($finding->ruleId, $severity, $finding->location, $finding->message, $finding->test);
            }
            array_push($dynamicErrors, ...$result->dynamicErrors);
        }

        return new ValidationReport($names, $syntax, $findings, $dynamicErrors, [], $scenario->name);
    }

    /**
     * Loads the rule set the scenario table names as $key and checks that the file holds exactly
     * that rule set, so that a rule map pointing at another release cannot run it under the
     * pinned name.
     *
     * @throws RulesNotInstalled when the file holds another rule set or version
     * @throws UnsupportedSchematron
     */
    private function ruleSet(string $key): RuleSet
    {
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }
        $ruleSet = RuleSet::fromFile($this->ruleFiles[$key]);
        $loaded = "{$ruleSet->id()} {$ruleSet->version()}";
        if ($loaded !== $key) {
            throw new RulesNotInstalled(sprintf(
                'The scenario table needs the rule set %s, but "%s" holds %s. Install matching rule packages: %s',
                $key,
                $this->ruleFiles[$key],
                $loaded,
                self::COMPOSER_COMMAND,
            ));
        }

        return $this->loaded[$key] = $ruleSet;
    }
}

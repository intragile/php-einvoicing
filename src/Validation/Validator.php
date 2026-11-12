<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolRules;
use InvalidArgumentException;

/**
 * Validates invoices against the compiled rule sets of their syntax.
 *
 * Usage: Validator::en16931()->validate($xml)->isValid(), or
 * Validator::peppol($rulesDir)->validate($xml) once the Peppol rules are installed.
 */
final class Validator
{
    private readonly Runner $runner;

    /** @var array<string, non-empty-list<RuleSet>> */
    private readonly array $ruleSets;

    /**
     * Custom compiled rule sets are passed as RuleSet::fromFile($path).
     *
     * @param  array<string, RuleSet|list<RuleSet>>  $ruleSets  keyed by Syntax value; every syntax
     *                                                          needs at least one. The rule sets of
     *                                                          a syntax all run, in list order; the
     *                                                          first must handle the document element.
     *
     * @throws InvalidArgumentException when a syntax has no rule set
     */
    public function __construct(array $ruleSets)
    {
        $normalised = [];
        foreach (Syntax::cases() as $syntax) {
            $given = $ruleSets[$syntax->value] ?? [];
            $list = $given instanceof RuleSet ? [$given] : $given;
            if ($list === []) {
                throw new InvalidArgumentException("No rule set is given for the syntax \"{$syntax->value}\".");
            }
            $normalised[$syntax->value] = $list;
        }
        $this->ruleSets = $normalised;
        $this->runner = new Runner;
    }

    /**
     * A validator for the CEN EN 16931 rules (UBL and CII).
     */
    public static function en16931(): self
    {
        return new self([
            Syntax::Ubl->value => RuleSet::fromFile(RuleFiles::ubl()),
            Syntax::Cii->value => RuleSet::fromFile(RuleFiles::cii()),
        ]);
    }

    /**
     * A validator for Peppol BIS Billing 3.0: OpenPeppol's copy of the CEN rules, then the Peppol
     * rules, per syntax. The rules are compiled on the user's machine first, with
     * vendor/bin/einvoicing-rules install peppol --dir=DIR.
     *
     * @throws RulesNotInstalled when the rules are not installed in $rulesDir
     * @throws UnsupportedSchematron when they were compiled by an engine of another format
     */
    public static function peppol(string $rulesDir): self
    {
        return new self(PeppolRules::load($rulesDir));
    }

    /**
     * Validates an XML invoice; the syntax is detected from its document element.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, its document element
     *                             is not an invoice of a supported syntax, or no rule of the first
     *                             rule set of the syntax handles the document element
     */
    public function validate(string $xml): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $syntax = Syntax::detect($document);
        $ruleSets = $this->ruleSets[$syntax->value];

        $names = [];
        $findings = [];
        $dynamicErrors = [];
        foreach ($ruleSets as $ruleSet) {
            $result = $this->runner->run($ruleSet, $document);
            // Only the first rule set must handle the document element: later layers, such as
            // the Peppol rules, may use relative rule contexts that never match the root itself.
            if ($names === [] && ! $result->rootRuleFired) {
                throw new UnsupportedDocument(sprintf(
                    'No rule of the rule set "%s" handles the document element {%s}%s.',
                    $ruleSet->id(),
                    $document->documentElement->namespaceURI ?? '',
                    $document->documentElement->localName ?? '',
                ));
            }
            $names[] = "{$ruleSet->id()} {$ruleSet->version()}";
            array_push($findings, ...$result->findings);
            array_push($dynamicErrors, ...$result->dynamicErrors);
        }

        return new ValidationReport($names, $syntax, $findings, $dynamicErrors);
    }

    /**
     * Writes an invoice model in the given syntax and validates the result.
     *
     * @throws InvalidArgumentException when the writer refuses the model, for example because its
     *                                  totals are not completed (see TotalsCalculator::complete())
     * @throws UnsupportedDocument when no rule of the rule set handles the written document element
     */
    public function validateInvoice(Invoice $invoice, Syntax $syntax = Syntax::Ubl): ValidationReport
    {
        return $this->validate(match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter)->write($invoice),
        });
    }
}

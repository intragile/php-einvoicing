<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use InvalidArgumentException;

/**
 * Validates invoices against one compiled rule set per syntax.
 *
 * Usage: Validator::en16931()->validate($xml)->isValid()
 */
final class Validator
{
    private readonly Runner $runner;

    /**
     * @param  array<string, RuleSet>  $ruleSets  keyed by Syntax value; every syntax needs one
     *
     * @throws InvalidArgumentException when a syntax has no rule set
     */
    public function __construct(private readonly array $ruleSets)
    {
        foreach (Syntax::cases() as $syntax) {
            if (! isset($ruleSets[$syntax->value])) {
                throw new InvalidArgumentException("No rule set is given for the syntax \"{$syntax->value}\".");
            }
        }
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
     * Validates an XML invoice; the syntax is detected from its document element.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, its document element
     *                             is not an invoice of a supported syntax, or no rule of the rule
     *                             set handles the document element
     */
    public function validate(string $xml): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $syntax = Syntax::detect($document);
        $ruleSet = $this->ruleSets[$syntax->value];

        $result = $this->runner->run($ruleSet, $document);
        if (! $result->rootRuleFired) {
            throw new UnsupportedDocument(sprintf(
                'No rule of the rule set "%s" handles the document element {%s}%s.',
                $ruleSet->id(),
                $document->documentElement->namespaceURI ?? '',
                $document->documentElement->localName ?? '',
            ));
        }

        return new ValidationReport($ruleSet->id(), $ruleSet->version(), $syntax, $result->findings, $result->errors);
    }

    /**
     * Writes an invoice model in the given syntax and validates the result.
     *
     * @throws InvalidArgumentException when the writer refuses the model, for example because its
     *                                  totals are not completed (see TotalsCalculator::complete())
     */
    public function validateInvoice(Invoice $invoice, Syntax $syntax = Syntax::Ubl): ValidationReport
    {
        return $this->validate(match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter)->write($invoice),
        });
    }
}

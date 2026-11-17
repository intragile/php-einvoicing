<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiVersion;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolRules;
use InvalidArgumentException;
use LogicException;

/**
 * Validates invoices against the compiled rule sets of their syntax.
 *
 * Usage: Validator::en16931()->validate($xml)->isValid(), or
 * Validator::peppol($rulesDir)->validate($xml) once the Peppol rules are installed. An enveloped
 * Peppol invoice (a Standard Business Document) is checked with
 * Validator::peppolEnvelope($rulesDir)->validate($sbdXml).
 */
final class Validator implements DocumentValidator
{
    private const SBDH_NAMESPACE = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';

    private readonly Runner $runner;

    /** @var array<string, non-empty-list<RuleSet>> */
    private readonly array $ruleSets;

    /**
     * Set only on the validator of peppolEnvelope(): the rule set that runs on the whole Standard
     * Business Document instead of the rule sets of the invoice syntax.
     */
    private ?RuleSet $envelopeRuleSet = null;

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
     * A validator for the Peppol Business Message Envelope (SBDH) around a Peppol BIS invoice: it
     * takes the whole Standard Business Document and checks that the envelope's sender and
     * receiver match the electronic addresses of the enveloped invoice (BR-SBDH-1 to BR-SBDH-4).
     *
     * Like the official rules, the comparison is case-sensitive, although Peppol participant
     * identifiers are not: write the envelope identifiers exactly as they appear in the invoice.
     * The invoice itself is not validated; run Validator::peppol() on it for that.
     *
     * @throws RulesNotInstalled when the envelope rules are not installed in $rulesDir
     * @throws UnsupportedSchematron when they were compiled by an engine of another format
     */
    public static function peppolEnvelope(string $rulesDir): self
    {
        $ruleSet = PeppolRules::loadEnvelope($rulesDir);
        $validator = new self([Syntax::Ubl->value => $ruleSet, Syntax::Cii->value => $ruleSet]);
        $validator->envelopeRuleSet = $ruleSet;

        return $validator;
    }

    /**
     * Validates an XML invoice; the syntax is detected from its document element. A validator made
     * by peppolEnvelope() takes a Standard Business Document instead and reports the syntax of the
     * enveloped invoice.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, its document element
     *                             is not an invoice of a supported syntax, or no rule of the first
     *                             rule set of the syntax handles the document element; for the
     *                             envelope validator, when the document element is not an SBDH
     *                             StandardBusinessDocument or does not envelope an invoice
     */
    public function validate(string $xml): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        if ($this->envelopeRuleSet !== null) {
            return $this->validateEnvelope($document, $this->envelopeRuleSet);
        }
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
     * Runs the envelope rule set on a whole Standard Business Document. Its rules match only
     * header elements, never the document element, so the document element is checked here.
     *
     * @throws UnsupportedDocument
     */
    private function validateEnvelope(DOMDocument $document, RuleSet $ruleSet): ValidationReport
    {
        $root = $document->documentElement ?? throw new UnsupportedDocument('The document has no document element.');
        if ($root->namespaceURI !== self::SBDH_NAMESPACE || $root->localName !== 'StandardBusinessDocument') {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not an SBDH StandardBusinessDocument.',
                $root->namespaceURI ?? '',
                $root->localName,
            ));
        }

        $syntax = self::envelopedSyntax($root);
        $result = $this->runner->run($ruleSet, $document);

        return new ValidationReport(
            ["{$ruleSet->id()} {$ruleSet->version()}"],
            $syntax,
            $result->findings,
            $result->dynamicErrors,
        );
    }

    /**
     * The syntax of the invoice in a Standard Business Document: its last child element, after
     * the header.
     *
     * @throws UnsupportedDocument when the document envelopes no invoice of a supported syntax
     */
    private static function envelopedSyntax(DOMElement $root): Syntax
    {
        $payload = $root->lastElementChild;
        if ($payload === null || ($payload->namespaceURI === self::SBDH_NAMESPACE && $payload->localName === 'StandardBusinessDocumentHeader')) {
            throw new UnsupportedDocument('The StandardBusinessDocument envelopes no document.');
        }
        // Syntax::detect() reads only the document element, so a shallow copy is enough.
        $probe = new DOMDocument;
        $probe->appendChild($probe->importNode($payload, false));

        return Syntax::detect($probe);
    }

    /**
     * Writes an invoice model in the given syntax and validates the result.
     *
     * @throws InvalidArgumentException when the writer refuses the model, for example because its
     *                                  totals are not completed (see TotalsCalculator::complete())
     * @throws UnsupportedDocument when no rule of the rule set handles the written document element
     * @throws LogicException when called on the envelope validator of peppolEnvelope(), which
     *                        takes a Standard Business Document, not an invoice
     */
    public function validateInvoice(Invoice $invoice, Syntax $syntax = Syntax::Ubl): ValidationReport
    {
        if ($this->envelopeRuleSet !== null) {
            throw new LogicException('validateInvoice() needs an invoice validator; this one validates SBDH envelopes');
        }

        return $this->validate(match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter(CiiVersion::D16B))->write($invoice),
        });
    }
}

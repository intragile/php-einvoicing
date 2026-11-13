<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\FacturX;

use DOMDocument;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\ProfileMismatch;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

/**
 * Validates Factur-X / ZUGFeRD XML documents against the profile their guideline identifier
 * (BT-24) declares: the profile's XML Schema, then its official Schematron rules.
 *
 * The Schematron rules run even when the schema check fails. The official rules are independent
 * of the schema, so the report lists every problem at once.
 *
 * The XRechnung profile is validated by the XRechnung validator, with its own scenarios.
 *
 * Usage: FacturXValidator::create()->validate($xml)->isValid(). It needs the package
 * intragile/einvoicing-rules-facturx.
 */
final class FacturXValidator
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-facturx';

    private const GUIDELINE_ID = 'rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID';

    private readonly Runner $runner;

    private readonly SchemaValidator $schemaValidator;

    /** @var array<string, RuleSet> loaded rule sets, keyed by profile key */
    private array $loaded = [];

    /**
     * @param  array<string, string>  $ruleFiles  path of the compiled rule set, keyed by RuleFiles profile key
     * @param  array<string, string>  $schemaFiles  path of the profile's top-level schema, keyed by RuleFiles profile key
     * @param  XRechnungValidator|null  $xrechnung  validator for the XRechnung profile; created from the XRechnung
     *                                              rules package on first use when null
     */
    public function __construct(
        private readonly array $ruleFiles,
        private readonly array $schemaFiles,
        private readonly ?XRechnungValidator $xrechnung = null,
    ) {
        $this->runner = new Runner;
        $this->schemaValidator = new SchemaValidator;
    }

    /**
     * A validator for the five Factur-X profiles shipped in intragile/einvoicing-rules-facturx.
     *
     * @throws RulesNotInstalled when that package is not installed
     */
    public static function create(): self
    {
        if (! class_exists(RuleFiles::class)) {
            throw new RulesNotInstalled('Factur-X validation needs the Factur-X rules. Run: '.self::COMPOSER_COMMAND);
        }

        $rules = [];
        $schemas = [];
        foreach (RuleFiles::profiles() as $profile) {
            $rules[$profile] = RuleFiles::rules($profile);
            $schemas[$profile] = RuleFiles::schema($profile);
        }

        return new self($rules, $schemas);
    }

    /**
     * Validates a Factur-X XML document.
     *
     * @param  FacturXProfile|null  $profile  the profile to check against; the document's own guideline
     *                                        identifier decides when null
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws ProfileMismatch when $profile is not the profile the document declares
     * @throws UnsupportedDocument when the document is not well-formed XML, is not a CII invoice or
     *                             declares a guideline identifier that is not a Factur-X one
     * @throws RulesNotInstalled when the profile's rules or schema are not available
     * @throws UnusableSchema when a profile schema cannot be used
     * @throws UnsupportedSchematron when a rule set was compiled by an engine of another format
     */
    public function validate(string $xml, ?FacturXProfile $profile = null): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $declared = $this->declaredProfile($document);

        if ($profile !== null && $profile !== $declared) {
            throw new ProfileMismatch(sprintf(
                'The document declares the %s profile (%s), not %s.',
                $declared->value,
                $declared->guidelineId(),
                $profile->value,
            ));
        }

        $key = $declared->ruleProfile();
        if ($key === null) {
            return ($this->xrechnung ?? XRechnungValidator::create())->validate($xml);
        }

        if (! isset($this->ruleFiles[$key], $this->schemaFiles[$key])) {
            throw new RulesNotInstalled("The Factur-X {$declared->value} profile has no rules or schema installed. Run: ".self::COMPOSER_COMMAND);
        }

        $schemaErrors = $this->schemaValidator->validate($document, $this->schemaFiles[$key]);
        $ruleSet = $this->ruleSet($key);
        $result = $this->runner->run($ruleSet, $document);

        return new ValidationReport(
            ["{$ruleSet->id()} {$ruleSet->version()}"],
            Syntax::Cii,
            $result->findings,
            $result->dynamicErrors,
            $schemaErrors,
            $declared->value,
        );
    }

    private function declaredProfile(DOMDocument $document): FacturXProfile
    {
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== CiiDocument::RSM || $root->localName !== 'CrossIndustryInvoice') {
            throw new UnsupportedDocument('A Factur-X document must be a CII CrossIndustryInvoice.');
        }

        $id = (new CiiDocument($document))->text(self::GUIDELINE_ID);
        $profile = $id === null ? null : FacturXProfile::fromGuidelineId($id);
        if ($profile === null) {
            throw new UnsupportedDocument(sprintf(
                'The guideline identifier (BT-24) %s is not a Factur-X one. It must be exactly one of: %s.',
                $id === null ? 'is missing and' : '"'.$id.'"',
                implode(', ', array_map(static fn (FacturXProfile $p): string => $p->guidelineId(), FacturXProfile::cases())),
            ));
        }

        return $profile;
    }

    private function ruleSet(string $key): RuleSet
    {
        return $this->loaded[$key] ??= RuleSet::fromFile($this->ruleFiles[$key]);
    }
}

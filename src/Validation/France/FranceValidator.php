<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use DOMDocument;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles as En16931RuleFiles;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles as FacturXRuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\ProfileMismatch;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblDocument;
use IntrAgile\EInvoicing\Validation\DocumentValidator;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;

/**
 * Validates French invoices against the FNFE-MPE format rules of the e-invoicing reform.
 *
 * The specification identifier (BT-24) selects a chain: an XML Schema, then rule sets that run one
 * after the other.
 *
 * - EN 16931 (UBL or CII): the FNFE schema, the CEN EN 16931 1.3.16 rules, then the French Flux 2
 *   rules of the syntax.
 * - EXTENDED-CTC-FR (UBL or CII): the FNFE schema, the EXTENDED-CTC-FR rules, then Flux 2.
 * - Factur-X BASIC WL, BASIC and EN 16931: the Factur-X profile schema and rules, then Flux 2 CII.
 * - Factur-X EXTENDED: the Factur-X EXTENDED schema, the FNFE Factur-X EXTENDED rules, then Flux 2
 *   CII.
 *
 * The rule sets run even when the schema check fails, so the report lists every problem at once.
 * Factur-X MINIMUM and XRECHNUNG documents are not invoices under these rules and are refused, as
 * are CDAR lifecycle messages and Flux 10 e-reporting files, which CdarValidator and
 * Flux10Validator check.
 *
 * Usage: FranceValidator::create($rulesDir)->validate($xml)->isValid(), once the French rules are
 * installed with vendor/bin/einvoicing-rules install france --dir=DIR. Factur-X documents also need
 * the package intragile/einvoicing-rules-facturx.
 */
final class FranceValidator implements DocumentValidator
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-facturx';

    /** The CEN release the FNFE rules build on. */
    private const CEN_VERSION = '1.3.16';

    private const CII_SPECIFICATION_ID = 'rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID';

    private const UBL_SPECIFICATION_ID = 'cbc:CustomizationID';

    private const CII_SCHEMA = 'CrossIndustryInvoice_100pD22B.xsd';

    private const UBL_INVOICE_SCHEMA = 'maindoc/UBL-Invoice-2.1.xsd';

    private const UBL_CREDIT_NOTE_SCHEMA = 'maindoc/UBL-CreditNote-2.1.xsd';

    /** The Factur-X profiles that are invoices under the French rules. */
    private const FACTUR_X_PROFILES = [
        FacturXProfile::BasicWl,
        FacturXProfile::Basic,
        FacturXProfile::En16931,
        FacturXProfile::Extended,
    ];

    private readonly Runner $runner;

    private readonly SchemaValidator $schemaValidator;

    /** @var array<string, RuleSet> loaded rule sets, keyed by source and name */
    private array $loaded = [];

    /**
     * @param  string  $rulesDir  folder the French rules were installed into
     * @param  array<string, string>  $facturXRuleFiles  path of the compiled Factur-X rule set, keyed by
     *                                                   FacturXProfile::ruleProfile(); empty when the
     *                                                   Factur-X rules package is not installed
     * @param  array<string, string>  $facturXSchemaFiles  path of the Factur-X profile's top-level schema,
     *                                                     keyed the same way
     */
    public function __construct(
        private readonly string $rulesDir,
        private readonly array $facturXRuleFiles = [],
        private readonly array $facturXSchemaFiles = [],
    ) {
        $this->runner = new Runner;
        $this->schemaValidator = new SchemaValidator;
    }

    /**
     * A validator for the French rules installed in $rulesDir. The rules are loaded on first use;
     * the Factur-X profiles come from intragile/einvoicing-rules-facturx when it is installed.
     */
    public static function create(string $rulesDir): self
    {
        if (! class_exists(FacturXRuleFiles::class)) {
            return new self($rulesDir);
        }

        $rules = [];
        $schemas = [];
        foreach (self::FACTUR_X_PROFILES as $profile) {
            $key = (string) $profile->ruleProfile();
            $rules[$key] = FacturXRuleFiles::rules($key);
            $schemas[$key] = FacturXRuleFiles::schema($key);
        }

        return new self($rulesDir, $rules, $schemas);
    }

    /**
     * Validates a standalone UBL or CII invoice; its specification identifier (BT-24) must be
     * EN 16931 or EXTENDED-CTC-FR.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, is not a UBL Invoice or
     *                             CreditNote or a CII CrossIndustryInvoice, declares another
     *                             specification identifier, or the first rule set does not handle
     *                             the document element
     * @throws RulesNotInstalled when the French rules or schemas are not installed in the folder
     * @throws UnusableSchema when an installed schema cannot be used
     * @throws UnsupportedSchematron when an installed rule set cannot be used
     */
    public function validate(string $xml): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $syntax = self::invoiceSyntax($document);
        $profile = self::frenchProfile($document, $syntax);

        if ($syntax === Syntax::Ubl) {
            $schema = FranceRules::schema($this->rulesDir, 'ubl', $document->documentElement?->localName === 'CreditNote' ? self::UBL_CREDIT_NOTE_SCHEMA : self::UBL_INVOICE_SCHEMA);
            $first = $profile === FrenchProfile::En16931
                ? $this->packaged(En16931RuleFiles::ubl(self::CEN_VERSION))
                : $this->french('extended-ctc-fr-ubl');
            $flux2 = $this->french('flux2-ubl');
        } else {
            $schema = FranceRules::schema($this->rulesDir, 'cii', self::CII_SCHEMA);
            $first = $profile === FrenchProfile::En16931
                ? $this->packaged(En16931RuleFiles::cii(self::CEN_VERSION))
                : $this->french('extended-ctc-fr-cii');
            $flux2 = $this->french('flux2-cii');
        }

        $scenario = $profile === FrenchProfile::En16931 ? 'EN 16931' : 'EXTENDED-CTC-FR';

        return $this->run($document, $syntax, $schema, [$first, $flux2], $scenario);
    }

    /**
     * Validates the CII of a Factur-X invoice of the BASIC WL, BASIC, EN 16931 or EXTENDED profile.
     *
     * @param  FacturXProfile|null  $profile  the profile to check against; the document's own guideline
     *                                        identifier decides when null
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, is not a CII invoice, or
     *                             declares a guideline identifier that is not one of the accepted
     *                             Factur-X profiles
     * @throws ProfileMismatch when $profile is not the profile the document declares
     * @throws RulesNotInstalled when the Factur-X rules package or the French rules are not installed
     * @throws UnusableSchema when a schema cannot be used
     * @throws UnsupportedSchematron when a rule set cannot be used
     */
    public function validateFacturX(string $xml, ?FacturXProfile $profile = null): ValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $declared = self::facturXProfile($document);

        if ($profile !== null && $profile !== $declared) {
            throw new ProfileMismatch(sprintf(
                'The document declares the %s profile (%s), not %s.',
                $declared->value,
                $declared->guidelineId(),
                $profile->value,
            ));
        }

        $key = (string) $declared->ruleProfile();
        if (! isset($this->facturXRuleFiles[$key], $this->facturXSchemaFiles[$key])) {
            throw new RulesNotInstalled("French validation of the Factur-X {$declared->value} profile needs the Factur-X rules. Run: ".self::COMPOSER_COMMAND);
        }

        $first = $declared === FacturXProfile::Extended
            ? $this->french('facturx-extended')
            : $this->packaged($this->facturXRuleFiles[$key]);

        return $this->run($document, Syntax::Cii, $this->facturXSchemaFiles[$key], [$first, $this->french('flux2-cii')], $declared->value);
    }

    /**
     * Checks the schema, then runs every rule set in order. Only the first rule set must handle
     * the document element: Flux 2 uses relative rule contexts that never match the root itself.
     *
     * @param  non-empty-list<RuleSet>  $ruleSets
     *
     * @throws UnsupportedDocument when no rule of the first rule set handles the document element
     */
    private function run(DOMDocument $document, Syntax $syntax, string $schema, array $ruleSets, string $scenario): ValidationReport
    {
        $schemaErrors = $this->schemaValidator->validate($document, $schema);

        $names = [];
        $findings = [];
        $dynamicErrors = [];
        foreach ($ruleSets as $ruleSet) {
            $result = $this->runner->run($ruleSet, $document);
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

        return new ValidationReport($names, $syntax, $findings, $dynamicErrors, $schemaErrors, $scenario);
    }

    /**
     * @throws UnsupportedDocument when the document element is not a UBL or CII invoice
     */
    private static function invoiceSyntax(DOMDocument $document): Syntax
    {
        try {
            return Syntax::detect($document);
        } catch (UnsupportedDocument $exception) {
            $root = $document->documentElement;

            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not an invoice. French invoices are UBL Invoice or CreditNote or CII CrossIndustryInvoice documents; use CdarValidator for lifecycle messages and Flux10Validator for e-reporting.',
                $root->namespaceURI ?? '',
                $root->localName ?? '',
            ), previous: $exception);
        }
    }

    /**
     * @throws UnsupportedDocument when the specification identifier is not a French profile
     */
    private static function frenchProfile(DOMDocument $document, Syntax $syntax): FrenchProfile
    {
        $id = $syntax === Syntax::Ubl
            ? (new UblDocument($document))->token(self::UBL_SPECIFICATION_ID)
            : (new CiiDocument($document))->token(self::CII_SPECIFICATION_ID);

        $profile = $id === null ? null : FrenchProfile::fromSpecificationIdentifier($id);
        if ($profile === null) {
            throw new UnsupportedDocument(sprintf(
                'The specification identifier (BT-24) %s is not accepted for a French invoice. It must be exactly one of: %s.%s',
                $id === null ? 'is missing and' : '"'.$id.'"',
                implode(', ', array_map(static fn (FrenchProfile $p): string => $p->value, FrenchProfile::cases())),
                $syntax === Syntax::Cii && $id !== null ? self::facturXHint($id) : '',
            ));
        }

        return $profile;
    }

    /**
     * What to tell the caller about a Factur-X guideline identifier in a standalone CII: an
     * accepted Factur-X profile is validated with validateFacturX(), which refuses the others.
     */
    private static function facturXHint(string $id): string
    {
        $profile = FacturXProfile::fromGuidelineId($id);

        return match (true) {
            $profile === null => '',
            in_array($profile, self::FACTUR_X_PROFILES, true) => ' The CII of a Factur-X invoice is validated with validateFacturX().',
            $profile === FacturXProfile::Minimum => ' The Factur-X MINIMUM profile is not a valid invoice under the French reform.',
            default => " The Factur-X {$profile->value} profile is not accepted for a French invoice.",
        };
    }

    /**
     * @throws UnsupportedDocument when the document is not a CII invoice or its guideline
     *                             identifier is not an accepted Factur-X profile
     */
    private static function facturXProfile(DOMDocument $document): FacturXProfile
    {
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== CiiDocument::RSM || $root->localName !== 'CrossIndustryInvoice') {
            throw new UnsupportedDocument('A Factur-X document must be a CII CrossIndustryInvoice.');
        }

        $id = (new CiiDocument($document))->token(self::CII_SPECIFICATION_ID);
        $profile = $id === null ? null : FacturXProfile::fromGuidelineId($id);
        $accepted = implode(', ', array_map(static fn (FacturXProfile $p): string => $p->guidelineId(), self::FACTUR_X_PROFILES));

        if ($profile === FacturXProfile::Minimum) {
            throw new UnsupportedDocument("The Factur-X MINIMUM profile is not a valid invoice under the French reform. The guideline identifier (BT-24) must be exactly one of: {$accepted}.");
        }
        if ($profile === null || ! in_array($profile, self::FACTUR_X_PROFILES, true)) {
            throw new UnsupportedDocument(sprintf(
                'The guideline identifier (BT-24) %s is not accepted for a French Factur-X invoice. It must be exactly one of: %s.',
                $id === null ? 'is missing and' : '"'.$id.'"',
                $accepted,
            ));
        }

        return $profile;
    }

    /**
     * A rule set shipped in a rules package, by the path of its compiled file.
     */
    private function packaged(string $path): RuleSet
    {
        return $this->loaded['file:'.$path] ??= RuleSet::fromFile($path);
    }

    /**
     * An installed French rule set, by its FranceSources::ruleSources() key.
     */
    private function french(string $key): RuleSet
    {
        return $this->loaded['france:'.$key] ??= FranceRules::load($this->rulesDir, $key);
    }
}

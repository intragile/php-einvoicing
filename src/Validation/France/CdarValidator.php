<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaValidator;

/**
 * Validates French CDAR invoice lifecycle messages (CDV) against the FNFE CDAR schema and the
 * FNFE-MPE CDAR rules.
 *
 * The rule set runs even when the schema check fails, so the report lists every problem at once.
 * The rule set has no rule on the document element, so the document element is checked by name.
 *
 * Usage: CdarValidator::create($rulesDir)->validate($xml)->isValid(), once the French rules are
 * installed with vendor/bin/einvoicing-rules install france --dir=DIR.
 */
final class CdarValidator
{
    public const NAMESPACE = 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100';

    public const ROOT = 'CrossDomainAcknowledgementAndResponse';

    private const SCHEMA = 'CrossDomainAcknowledgementAndResponse_100pD22B.xsd';

    private ?RuleSet $ruleSet = null;

    private function __construct(
        private readonly string $rulesDir,
        private readonly Runner $runner,
        private readonly SchemaValidator $schemaValidator,
    ) {}

    /**
     * A validator for the French rules installed in $rulesDir. The rules are loaded on first use.
     */
    public static function create(string $rulesDir): self
    {
        return new self($rulesDir, new Runner, new SchemaValidator);
    }

    /**
     * Validates one CDAR message. A leading UTF-8 byte order mark is accepted.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its document element
     *                             is not a CDAR CrossDomainAcknowledgementAndResponse
     * @throws RulesNotInstalled when the French rules or schemas are not installed in the folder
     * @throws UnusableSchema when the installed schema cannot be used
     * @throws UnsupportedSchematron when the installed rule set cannot be used
     */
    public function validate(string $xml): MessageValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        if ($root?->namespaceURI !== self::NAMESPACE || $root->localName !== self::ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not a CDAR lifecycle message; expected {%s}%s.',
                $root->namespaceURI ?? '',
                $root->localName ?? '',
                self::NAMESPACE,
                self::ROOT,
            ));
        }

        $schemaErrors = $this->schemaValidator->validate($document, FranceRules::schema($this->rulesDir, 'cdar', self::SCHEMA));
        $ruleSet = $this->ruleSet ??= FranceRules::load($this->rulesDir, 'cdar');
        $result = $this->runner->run($ruleSet, $document);

        return new MessageValidationReport(
            MessageType::Cdar,
            ["{$ruleSet->id()} {$ruleSet->version()}"],
            $result->findings,
            $result->dynamicErrors,
            $schemaErrors,
        );
    }
}

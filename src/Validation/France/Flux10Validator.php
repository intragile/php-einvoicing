<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;

/**
 * Validates French Flux 10 e-reporting transmissions against the DGFiP e-reporting schema and the
 * FNFE-MPE Flux 10 rules.
 *
 * The rule set runs even when the schema check fails, so the report lists every problem at once.
 * The rule set has no rule on the document element, so the document element is checked by name: a
 * Report element in no namespace.
 *
 * Usage: Flux10Validator::create($rulesDir)->validate($xml)->isValid(), once the French rules are
 * installed with vendor/bin/einvoicing-rules install france --dir=DIR.
 */
final class Flux10Validator
{
    private const ROOT = 'Report';

    private const SCHEMA = 'ereporting.xsd';

    private function __construct(private readonly MessageRun $run) {}

    /**
     * A validator for the French rules installed in $rulesDir. The rules are loaded on first use.
     */
    public static function create(string $rulesDir): self
    {
        return new self(new MessageRun($rulesDir));
    }

    /**
     * Validates one Flux 10 transmission. A leading UTF-8 byte order mark is accepted.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its document element
     *                             is not a Report element in no namespace
     * @throws RulesNotInstalled when the French rules or schemas are not installed in the folder
     * @throws UnusableSchema when the installed schema cannot be used
     * @throws UnsupportedSchematron when the installed rule set cannot be used
     */
    public function validate(string $xml): MessageValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== null || $root->localName !== self::ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not a Flux 10 e-reporting transmission; expected {}%s.',
                $root->namespaceURI ?? '',
                $root->localName ?? '',
                self::ROOT,
            ));
        }

        return $this->run->validate($document, MessageType::Flux10, self::SCHEMA);
    }
}

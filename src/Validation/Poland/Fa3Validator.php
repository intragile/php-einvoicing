<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Poland;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaValidator;

/**
 * Validates Polish FA(3) structured invoices against the bundled FA(3) XML Schema (version 1-0E).
 *
 * The schema ships with the library and resolves without network access, so nothing needs to be
 * installed. The document element is checked by name before the schema runs.
 *
 * Usage: Fa3Validator::create()->validate($xml)->isValid().
 */
final class Fa3Validator
{
    private const SCHEMA_RUN = 'fa3-xsd '.Fa3Format::SCHEMA_VERSION;

    private function __construct(private readonly SchemaValidator $schemaValidator) {}

    public static function create(): self
    {
        return new self(new SchemaValidator);
    }

    /**
     * Validates one FA(3) invoice. A leading UTF-8 byte order mark is accepted.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its document element
     *                             is not an FA(3) Faktura
     * @throws UnusableSchema when the bundled schema cannot be used
     */
    public function validate(string $xml): MessageValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        if ($root?->namespaceURI !== Fa3Format::NS || $root->localName !== Fa3Format::ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not an FA(3) invoice; expected {%s}%s.',
                $root->namespaceURI ?? '',
                $root->localName ?? '',
                Fa3Format::NS,
                Fa3Format::ROOT,
            ));
        }

        $schemaErrors = $this->schemaValidator->validate($document, Fa3Schema::path(), Fa3Schema::localPrefixes());

        return new MessageValidationReport(MessageType::Fa3, [self::SCHEMA_RUN], [], [], $schemaErrors);
    }
}

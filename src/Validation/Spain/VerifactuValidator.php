<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Spain;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaValidator;

/**
 * Validates Spanish VERI*FACTU billing records against the installed AEAT "tikeV1.0" XML Schemas
 * and the published business rules the schemas cannot express (VerifactuRules).
 *
 * A bare RegistroAlta or RegistroAnulacion is checked against SuministroInformacion.xsd, which
 * declares both as global elements; a RegFactuSistemaFacturacion submission against
 * SuministroLR.xsd. The rules run on every record of the document, also when the schema check
 * fails, so the report lists every problem at once. The schemas are not bundled; install them
 * with "vendor/bin/einvoicing-rules install spain --dir=DIR".
 *
 * Usage: VerifactuValidator::create(new VerifactuSchemas($dir))->validate($xml)->isValid().
 */
final class VerifactuValidator
{
    private const SCHEMA_RUN = 'verifactu-xsd '.VerifactuFormat::RELEASE;

    private const RULES_RUN = VerifactuRules::ID.' '.VerifactuRules::VERSION;

    private const SUBMISSION = 'RegFactuSistemaFacturacion';

    private const RECORDS = ['RegistroAlta', 'RegistroAnulacion'];

    private function __construct(
        private readonly VerifactuSchemas $schemas,
        private readonly SchemaValidator $schemaValidator,
    ) {}

    public static function create(VerifactuSchemas $schemas): self
    {
        return new self($schemas, new SchemaValidator);
    }

    /**
     * Validates one record or one submission. A leading UTF-8 byte order mark is accepted.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its document element
     *                             is neither a VERI*FACTU record nor a submission
     * @throws UnusableSchema when the installed schemas cannot be used
     */
    public function validate(string $xml): MessageValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        $namespace = $root?->namespaceURI;
        $name = $root?->localName;
        $xsd = match (true) {
            $namespace === VerifactuFormat::SF_NS && in_array($name, self::RECORDS, true) => $this->schemas->informationXsd(),
            $namespace === VerifactuFormat::LR_NS && $name === self::SUBMISSION => $this->schemas->submissionXsd(),
            default => throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not a VERI*FACTU record or submission; expected %s in {%s} or %s in {%s}.',
                $namespace ?? '',
                $name ?? '',
                implode(' or ', self::RECORDS),
                VerifactuFormat::SF_NS,
                self::SUBMISSION,
                VerifactuFormat::LR_NS,
            )),
        };

        $schemaErrors = $this->schemaValidator->validate($document, $xsd, $this->schemas->localPrefixes());

        return new MessageValidationReport(
            MessageType::Verifactu,
            [self::SCHEMA_RUN, self::RULES_RUN],
            VerifactuRules::check($document),
            [],
            $schemaErrors,
        );
    }
}

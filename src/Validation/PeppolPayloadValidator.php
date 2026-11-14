<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles as XRechnungRuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

/**
 * Validates a Peppol payload with the rules of the specification it declares: a Peppol BIS
 * Billing 3.0 document with the BIS validator, an XRechnung 3.0 document with the XRechnung
 * validator.
 *
 * The validator is chosen by the exact pair of document element and specification identifier
 * (BT-24) that PeppolDocumentTypes defines, the same pairs the Peppol transport sends. Any other
 * document is refused, so that nothing is sent that was not checked against its own rules.
 *
 * Usage: PeppolPayloadValidator::create($peppolRulesDir)->validate($xml).
 */
final class PeppolPayloadValidator implements DocumentValidator
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-xrechnung';

    /**
     * @param  DocumentValidator  $bis  validator of Peppol BIS Billing 3.0 documents
     * @param  DocumentValidator|null  $xrechnung  validator of XRechnung 3.0 documents; null when
     *                                             the XRechnung rules are not installed
     */
    public function __construct(
        private readonly DocumentValidator $bis,
        private readonly ?DocumentValidator $xrechnung = null,
    ) {}

    /**
     * The Peppol BIS validator of the rules installed in $peppolRulesDir and, when the package
     * intragile/einvoicing-rules-xrechnung is installed, the XRechnung validator.
     *
     * @throws RulesNotInstalled when the Peppol rules are not installed in $peppolRulesDir
     * @throws UnsupportedSchematron when they were compiled by an engine of another format
     */
    public static function create(string $peppolRulesDir): self
    {
        return new self(
            Validator::peppol($peppolRulesDir),
            class_exists(XRechnungRuleFiles::class) ? XRechnungValidator::create() : null,
        );
    }

    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML, or its document element
     *                             and specification identifier are not a sendable pair
     * @throws RulesNotInstalled when the document is an XRechnung document and the XRechnung rules
     *                           are not installed
     */
    public function validate(string $xml): ValidationReport
    {
        $root = SafeXml::load($xml)->documentElement
            ?? throw new UnsupportedDocument('The document has no root element.');
        $rootKey = PeppolDocumentTypes::rootKey($root);
        $specification = PeppolDocumentTypes::specification($root);
        $documentType = PeppolDocumentTypes::resolve($rootKey, $specification);

        if ($documentType !== null && ! PeppolDocumentTypes::isXRechnung($documentType)) {
            return $this->bis->validate($xml);
        }
        if ($documentType !== null) {
            $validator = $this->xrechnung
                ?? throw new RulesNotInstalled('Validating an XRechnung document for Peppol needs the KoSIT XRechnung rules. Run: '.self::COMPOSER_COMMAND);

            return $validator->validate($xml);
        }

        throw new UnsupportedDocument(sprintf(
            'The document {%s}%s with the specification identifier (BT-24) "%s" is not a Peppol document this library validates. Accepted are %s',
            (string) $root->namespaceURI,
            (string) $root->localName,
            $specification ?? '',
            PeppolDocumentTypes::sendable(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles as XRechnungRuleFiles;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

/**
 * Validates a Peppol payload with the rules of the specification it declares: a Peppol BIS
 * Billing 3.0 document with the BIS validator, an XRechnung 3.0 document with the XRechnung
 * validator.
 *
 * The validator is chosen by the exact pair of document element and specification identifier
 * (BT-24), the same pairs OutboundDocument::fromXml() sends. Any other document is refused, so
 * that nothing is sent that was not checked against its own rules.
 *
 * Usage: PeppolPayloadValidator::create($peppolRulesDir)->validate($xml).
 */
final class PeppolPayloadValidator implements DocumentValidator
{
    /** Specification identifier (BT-24) of Peppol BIS Billing 3.0. */
    private const BIS = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private const UBL_ROOTS = [
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2|Invoice',
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2|CreditNote',
    ];

    private const CII = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

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
        $rootKey = $root->namespaceURI.'|'.$root->localName;
        $xpath = new DOMXPath($root->ownerDocument ?? throw new UnsupportedDocument('The document has no owner.'));
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('rsm', self::CII);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $isUbl = in_array($rootKey, self::UBL_ROOTS, true);
        $nodes = $xpath->query($isUbl ? 'cbc:CustomizationID' : 'rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID', $root);
        $node = $nodes === false ? null : $nodes->item(0);
        $specification = $node instanceof DOMElement ? $node->textContent : null;

        if ($isUbl && $specification === self::BIS) {
            return $this->bis->validate($xml);
        }
        if ($specification !== null && isset(XRechnungPeppol::documentTypes()[$rootKey.'|'.$specification])) {
            $validator = $this->xrechnung
                ?? throw new RulesNotInstalled('Validating an XRechnung document for Peppol needs the KoSIT XRechnung rules. Run: '.self::COMPOSER_COMMAND);

            return $validator->validate($xml);
        }

        throw new UnsupportedDocument(sprintf(
            'The document {%s}%s with the specification identifier (BT-24) "%s" is not a Peppol document this library validates. Accepted are a UBL Invoice or CreditNote with %s (Peppol BIS Billing 3.0), a UBL Invoice, UBL CreditNote or CII CrossIndustryInvoice with %s (XRechnung 3.0), and a UBL Invoice or CII CrossIndustryInvoice with %s (XRechnung 3.0 Extension).',
            (string) $root->namespaceURI,
            (string) $root->localName,
            $specification ?? '',
            self::BIS,
            XRechnung::CIUS,
            XRechnung::EXTENSION,
        ));
    }
}

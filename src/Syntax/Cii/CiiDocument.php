<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\XmlDocument;

/**
 * A loaded UN/CEFACT Cross Industry Invoice (D16B).
 *
 * Dates are udt:DateTimeString, udt:DateString or qdt:DateTimeString elements in format 102
 * (CCYYMMDD), the only format the CEN binding allows; a missing format attribute is read as 102.
 *
 * @internal
 */
final class CiiDocument extends XmlDocument
{
    public const RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    public const RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    public const UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    public const QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

    private const PREFIXES = [self::RSM => 'rsm', self::RAM => 'ram', self::UDT => 'udt', self::QDT => 'qdt'];

    private const DATE_FORMAT = '102';

    public function __construct(DOMDocument $document)
    {
        parent::__construct($document, self::PREFIXES);
        $root = $this->root;

        if ($root->namespaceURI !== self::RSM || $root->localName !== 'CrossIndustryInvoice') {
            throw new UnsupportedDocument(sprintf(
                'Unsupported root element {%s}%s; expected a CII D16B CrossIndustryInvoice.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));
        }
    }

    protected function parseDate(DOMElement $element): ?DateTimeImmutable
    {
        if (($this->attribute($element, 'format') ?? self::DATE_FORMAT) !== self::DATE_FORMAT) {
            return null;
        }
        $value = trim($element->textContent);
        $date = DateTimeImmutable::createFromFormat('!Ymd', $value);

        return $date !== false && $date->format('Ymd') === $value ? $date : null;
    }

    protected function invalidDate(DOMElement $element): string
    {
        $format = $this->attribute($element, 'format') ?? self::DATE_FORMAT;

        return sprintf("invalid date '%s'", trim($element->textContent))
            .($format === self::DATE_FORMAT ? '' : " in format '{$format}'");
    }
}

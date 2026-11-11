<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\XmlDocument;

/**
 * A loaded UBL 2.1 Invoice or CreditNote; dates are xsd:date values (YYYY-MM-DD).
 *
 * @internal
 */
final class UblDocument extends XmlDocument
{
    public const INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    public const CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    public const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    public const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public const EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const PREFIXES = [self::CAC => 'cac', self::CBC => 'cbc', self::EXT => 'ext'];

    public readonly bool $isCreditNote;

    public function __construct(DOMDocument $document)
    {
        parent::__construct($document, self::PREFIXES);
        $root = $this->root;

        $this->isCreditNote = match (true) {
            $root->namespaceURI === self::INVOICE && $root->localName === 'Invoice' => false,
            $root->namespaceURI === self::CREDIT_NOTE && $root->localName === 'CreditNote' => true,
            default => throw new UnsupportedDocument(sprintf(
                'Unsupported root element {%s}%s; expected a UBL 2.1 Invoice or CreditNote.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            )),
        };
    }

    protected function parseDate(DOMElement $element): ?DateTimeImmutable
    {
        $value = trim($element->textContent);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}

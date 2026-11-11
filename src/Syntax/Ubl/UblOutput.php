<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\Identifier;
use RuntimeException;

/**
 * A UBL document under construction, with typed element builders.
 *
 * Text goes through DOM text nodes and attribute setters, so markup characters are escaped and
 * UTF-8 is kept as is. Decimals are written in their own plain notation with their scale; dates
 * as Y-m-d.
 *
 * @internal
 */
final class UblOutput
{
    private const NAMESPACES = ['cac' => UblDocument::CAC, 'cbc' => UblDocument::CBC];

    public readonly DOMElement $root;

    private readonly DOMDocument $document;

    public function __construct(bool $creditNote)
    {
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;

        $this->root = $this->document->createElementNS(
            $creditNote ? UblDocument::CREDIT_NOTE : UblDocument::INVOICE,
            $creditNote ? 'CreditNote' : 'Invoice',
        );
        foreach (self::NAMESPACES as $prefix => $namespace) {
            $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:'.$prefix, $namespace);
        }
        $this->document->appendChild($this->root);
    }

    public function xml(): string
    {
        $xml = $this->document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('The UBL document could not be serialised.');
        }

        return $xml;
    }

    /**
     * Appends an empty aggregate or basic element, e.g. "cac:Party".
     */
    public function add(DOMElement $parent, string $name): DOMElement
    {
        [$prefix] = explode(':', $name, 2);
        $element = $this->document->createElementNS(self::NAMESPACES[$prefix], $name);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appends an element with text content; a null value writes nothing.
     *
     * @param  array<string, string|null>  $attributes  attributes with a null value are left out
     */
    public function text(DOMElement $parent, string $name, ?string $value, array $attributes = []): ?DOMElement
    {
        if ($value === null) {
            return null;
        }
        $element = $this->add($parent, $name);
        $element->appendChild($this->document->createTextNode($value));
        foreach ($attributes as $attribute => $attributeValue) {
            if ($attributeValue !== null) {
                $element->setAttribute($attribute, $attributeValue);
            }
        }

        return $element;
    }

    /**
     * Appends an identifier with its scheme in the schemeID attribute.
     */
    public function identifier(DOMElement $parent, string $name, ?Identifier $identifier): void
    {
        if ($identifier !== null) {
            $this->text($parent, $name, $identifier->value, ['schemeID' => $identifier->scheme]);
        }
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    public function decimal(DOMElement $parent, string $name, ?BigDecimal $value, array $attributes = []): void
    {
        if ($value !== null) {
            $this->text($parent, $name, (string) $value, $attributes);
        }
    }

    public function amount(DOMElement $parent, string $name, ?BigDecimal $value, CurrencyCode $currency): void
    {
        $this->decimal($parent, $name, $value, ['currencyID' => $currency->value]);
    }

    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date): void
    {
        if ($date !== null) {
            $this->text($parent, $name, $date->format('Y-m-d'));
        }
    }

    /**
     * Appends cac:TaxScheme with the given identifier; UBL requires the element in every tax category.
     */
    public function taxScheme(DOMElement $parent, string $identifier): void
    {
        $this->text($this->add($parent, 'cac:TaxScheme'), 'cbc:ID', $identifier);
    }
}

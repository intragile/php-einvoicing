<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use Brick\Math\BigDecimal;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Model\Identifier;
use InvalidArgumentException;
use RuntimeException;

/**
 * A syntax document under construction, with typed element builders.
 *
 * Text goes through DOM text nodes and attribute setters, so markup characters are escaped and
 * UTF-8 is kept as is. Text that is not valid UTF-8 or holds a character XML 1.0 forbids is
 * refused rather than stripped, since no receiver could parse the document. Decimals are written in their own plain notation with their scale.
 *
 * @internal
 */
abstract class XmlOutput
{
    public readonly DOMElement $root;

    private readonly DOMDocument $document;

    /**
     * @param  array<string, string>  $namespaces  namespace per prefix, declared on the root
     * @param  string  $rootName  qualified root element name, with or without a prefix
     */
    public function __construct(private readonly array $namespaces, string $rootNamespace, string $rootName)
    {
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;

        $this->root = $this->document->createElementNS($rootNamespace, $rootName);
        foreach ($namespaces as $prefix => $namespace) {
            $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:'.$prefix, $namespace);
        }
        $this->document->appendChild($this->root);
    }

    public function xml(): string
    {
        $xml = $this->document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('The document could not be serialised.');
        }

        return $xml;
    }

    /**
     * Appends an empty element with a prefixed name, e.g. "cac:Party".
     */
    public function add(DOMElement $parent, string $name): DOMElement
    {
        [$prefix] = explode(':', $name, 2);
        $element = $this->document->createElementNS($this->namespaces[$prefix], $name);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appends an element with text content; a null value writes nothing.
     *
     * @param  array<string, string|null>  $attributes  attributes with a null value are left out
     *
     * @throws InvalidArgumentException when the text or an attribute value is not valid UTF-8 or
     *                                  contains a character outside the XML 1.0 Char production
     */
    public function text(DOMElement $parent, string $name, ?string $value, array $attributes = []): ?DOMElement
    {
        if ($value === null) {
            return null;
        }
        self::assertXmlText($value, 'Text', $name);
        foreach ($attributes as $attribute => $attributeValue) {
            if ($attributeValue !== null) {
                self::assertXmlText($attributeValue, 'Attribute '.$attribute, $name);
            }
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

    /**
     * XML 1.0 Char: #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF].
     */
    private static function assertXmlText(string $value, string $what, string $element): void
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException(sprintf('%s is not valid UTF-8 in element %s.', $what, $element));
        }
        if (preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $value, $match) === 1) {
            throw new InvalidArgumentException(sprintf(
                '%s contains U+%04X, which is not allowed in XML 1.0, in element %s.',
                $what,
                mb_ord($match[0], 'UTF-8'),
                $element,
            ));
        }
    }
}

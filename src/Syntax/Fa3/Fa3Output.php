<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\XmlOutput;
use RuntimeException;

/**
 * A Polish FA(3) invoice under construction. FA(3) elements live in the default namespace, so
 * element names are written without a prefix; the etd and xsi namespaces are declared on the
 * root as in the official templates.
 *
 * @internal
 */
final class Fa3Output extends XmlOutput
{
    public function __construct()
    {
        parent::__construct(
            ['etd' => Fa3Format::ETD_NS, 'xsi' => Fa3Format::XSI_NS],
            Fa3Format::NS,
            Fa3Format::ROOT,
        );
    }

    /**
     * Appends an empty element; a name without a prefix is an FA(3) element.
     */
    public function add(DOMElement $parent, string $name): DOMElement
    {
        if (str_contains($name, ':')) {
            return parent::add($parent, $name);
        }
        $element = $this->document($parent)->createElementNS(Fa3Format::NS, $name);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appends a calendar date (Y-m-d); a null value writes nothing.
     */
    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date): void
    {
        if ($date !== null) {
            $this->text($parent, $name, $date->format('Y-m-d'));
        }
    }

    /**
     * Appends a yes/no marker (etd:TWybor1_2): 1 for yes, 2 for no.
     */
    public function yesNo(DOMElement $parent, string $name, bool $value): void
    {
        $this->text($parent, $name, $value ? '1' : '2');
    }

    /**
     * Appends a marker that only exists in the affirmative (etd:TWybor1), written as 1.
     */
    public function marker(DOMElement $parent, string $name): void
    {
        $this->text($parent, $name, '1');
    }

    /**
     * Appends a verbatim fragment. The fragment text is parsed again and its element node is
     * imported, so an XML declaration or namespace declarations of the text cannot leak into
     * the document.
     */
    public function fragment(DOMElement $parent, RawFragment $fragment): void
    {
        $element = SafeXml::load($fragment->xml)->documentElement;
        if ($element === null) {
            throw new RuntimeException('The raw FA(3) fragment has no element.');
        }
        $parent->appendChild($this->document($parent)->importNode($element, true));
    }

    private function document(DOMElement $parent): DOMDocument
    {
        $document = $parent->ownerDocument;
        if ($document === null) {
            throw new RuntimeException('The element does not belong to a document.');
        }

        return $document;
    }
}

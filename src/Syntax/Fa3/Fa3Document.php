<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Syntax\XmlDocument;

/**
 * A loaded Polish structured invoice FA(3). Elements are addressed with the prefix "fa" for the
 * FA(3) namespace and "etd" for the shared type definitions; dates are written as Y-m-d, the
 * creation timestamp (DataWytworzeniaFa) as an xsd:dateTime read in UTC.
 *
 * @internal
 */
final class Fa3Document extends XmlDocument
{
    /** An xsd:dateTime lexical value with an optional fraction and zone. */
    private const DATE_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?$/';

    public function __construct(DOMDocument $document)
    {
        parent::__construct($document, [Fa3Format::NS => 'fa', Fa3Format::ETD_NS => 'etd']);
        $root = $this->root;

        if ($root->namespaceURI !== Fa3Format::NS || $root->localName !== Fa3Format::ROOT) {
            throw new UnsupportedDocument(sprintf(
                'Unsupported root element {%s}%s; expected an FA(3) Faktura in %s.',
                (string) $root->namespaceURI,
                (string) $root->localName,
                Fa3Format::NS,
            ));
        }
    }

    /**
     * A date-time read in UTC; an element without a valid value is reported and read as null.
     */
    public function dateTime(string $expression, DOMNode $context): ?DateTimeImmutable
    {
        $element = $this->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $value = trim($element->textContent);
        if (preg_match(self::DATE_TIME, $value) !== 1) {
            $this->reject($element, "invalid date-time '{$value}'");

            return null;
        }
        $this->use($element);

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Resolves a code of an integer-backed list held in the element text. A value the list
     * rejects is reported as "(invalid code '<value>')" and yields null.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public function intCode(string $enum, ?DOMElement $element): ?BackedEnum
    {
        if ($element === null) {
            return null;
        }
        $value = trim($element->textContent);
        if ($value === '') {
            return null;
        }
        $case = preg_match('/^\d{1,9}$/', $value) === 1 ? $enum::tryFrom((int) $value) : null;
        if ($case === null) {
            $this->reject($element, "invalid code '{$value}'");

            return null;
        }
        $this->use($element);

        return $case;
    }

    /**
     * Whether a marker element (etd:TWybor1 or the 1 of etd:TWybor1_2) holds 1; the element is
     * marked as used when present.
     */
    public function flag(string $expression, DOMNode $context): bool
    {
        return $this->token($expression, $context) === '1';
    }

    /**
     * Whether the element is present, without marking it.
     */
    public function has(string $expression, DOMNode $context): bool
    {
        return $this->one($expression, $context) !== null;
    }

    /**
     * The element as a verbatim fragment, built from its serialisation; it and its descendants
     * are marked as used.
     */
    public function fragment(DOMElement $element): RawFragment
    {
        $copy = new DOMDocument;
        $copy->appendChild($copy->importNode($element, true));
        $this->use($element);
        $this->useDescendants($element);

        return new RawFragment((string) $copy->saveXML($copy->documentElement));
    }

    protected function parseDate(DOMElement $element): ?DateTimeImmutable
    {
        $value = trim($element->textContent);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function useDescendants(DOMElement $element): void
    {
        for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            $this->use($child);
            $this->useDescendants($child);
        }
    }
}

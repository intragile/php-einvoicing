<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Builds an XPath location such as "/ubl:Invoice[1]/cac:LegalMonetaryTotal[1]/cbc:PayableAmount[1]"
 * for a node, using the rule set's prefixes for known namespace URIs and "Q{uri}local" otherwise.
 */
final class LocationPath
{
    /**
     * @param  array<string, string>  $namespaces  prefix => namespace URI
     */
    public static function of(DOMNode $node, array $namespaces): string
    {
        if ($node instanceof DOMDocument) {
            return '/';
        }

        $prefixes = [];
        foreach ($namespaces as $prefix => $uri) {
            $prefixes[$uri] ??= $prefix;
        }

        $steps = [];
        if ($node instanceof DOMAttr) {
            $steps[] = '@'.self::name($node, $prefixes);
            $node = $node->ownerElement;
        }
        for (; $node instanceof DOMElement; $node = $node->parentNode) {
            $steps[] = self::name($node, $prefixes).'['.self::position($node).']';
        }

        return '/'.implode('/', array_reverse($steps));
    }

    /**
     * @param  array<string, string>  $prefixes  namespace URI => prefix
     */
    private static function name(DOMElement|DOMAttr $node, array $prefixes): string
    {
        $uri = $node->namespaceURI ?? '';
        $local = $node->localName ?? $node->nodeName;
        if ($uri === '') {
            return $local;
        }

        return isset($prefixes[$uri]) ? "{$prefixes[$uri]}:{$local}" : "Q{{$uri}}{$local}";
    }

    /**
     * One-based position among the preceding siblings with the same expanded name.
     */
    private static function position(DOMElement $element): int
    {
        $position = 1;
        for ($sibling = $element->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
            if ($sibling instanceof DOMElement
                && $sibling->localName === $element->localName
                && $sibling->namespaceURI === $element->namespaceURI) {
                $position++;
            }
        }

        return $position;
    }
}

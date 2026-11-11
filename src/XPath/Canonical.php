<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;

/**
 * A canonical text form of an XPath result, used to compare this engine with Saxon:
 * "(item, item)" where an atomic value is "type:lexical" (for example "xs:decimal:1.5") and a node is
 * "node:" followed by its path of name()[n] steps ("node:/Invoice[1]/cbc:ID[1]", "/@currencyID" for an
 * attribute, "node:/" for the document node). An evaluation error is written "error:CODE".
 */
final class Canonical
{
    /**
     * @param  list<DOMNode|AtomicValue>  $sequence
     * @param  array<string, string>  $namespaces  bindings of the evaluated expression; names are written as in the document
     */
    public static function of(array $sequence, array $namespaces = []): string
    {
        $items = [];
        foreach ($sequence as $item) {
            $items[] = $item instanceof AtomicValue ? $item->type().':'.$item->lexical() : 'node:'.self::path($item);
        }

        return '('.implode(', ', $items).')';
    }

    public static function error(XPathError $error): string
    {
        return 'error:'.$error->errorCode;
    }

    private static function path(DOMNode $node): string
    {
        if ($node instanceof DOMDocument) {
            return '/';
        }

        $steps = [];
        for ($current = $node; $current !== null && ! $current instanceof DOMDocument; $current = DocumentIndex::parent($current)) {
            $steps[] = self::step($current);
        }

        return implode('', array_reverse($steps));
    }

    private static function step(DOMNode $node): string
    {
        if ($node instanceof DOMAttr) {
            return '/@'.$node->nodeName;
        }

        $position = 1;
        for ($sibling = $node->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
            if ($node instanceof DOMElement ? $sibling instanceof DOMElement && $sibling->nodeName === $node->nodeName : $sibling instanceof DOMText) {
                $position++;
            }
        }

        return $node instanceof DOMElement ? "/{$node->nodeName}[{$position}]" : "/text()[{$position}]";
    }
}

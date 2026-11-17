<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Negative;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * Applies the DOM edits of a negative corpus recipe to a document.
 *
 * Each edit is [XPath 1.0 expression, operation, argument]:
 *
 * - set: the selected elements get the argument as their only text; selected attributes get it as
 *   their value.
 * - remove: the selected elements or attributes are removed (no argument).
 * - append: "@name=value" adds (or replaces) an attribute on each selected element; any other
 *   argument is an XML fragment whose nodes are appended as the last children of each selected
 *   element.
 * - before: the nodes of an XML fragment are inserted before each selected element, which keeps
 *   the element order a schema prescribes.
 * - rename: the selected elements (or attributes) get the qualified name of the argument, keeping
 *   their attributes and children (or value). A prefixed name takes the namespace of its prefix;
 *   an unprefixed element name takes no namespace.
 *
 * Prefixes in fragments and names resolve through the namespace map. An expression that selects
 * nothing, or selects a node the operation cannot change, is an error: a recipe never passes by
 * editing nothing.
 */
final class Mutator
{
    public const OPERATIONS = ['set', 'remove', 'append', 'before', 'rename'];

    /**
     * @param  list<array<int, string>>  $edits  [XPath, operation, argument?] each, applied in order
     * @param  array<string, string>  $namespaces  prefix => namespace URI for the XPath expressions,
     *                                             fragments and qualified names
     *
     * @throws RuntimeException when an edit is malformed or selects nothing
     */
    public static function apply(string $xml, array $edits, array $namespaces): string
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The base document is not well-formed XML.');
        }
        $xpath = new DOMXPath($document);
        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        foreach ($edits as $index => $edit) {
            $expression = $edit[0] ?? throw new RuntimeException("Edit {$index} has no XPath expression.");
            $operation = $edit[1] ?? throw new RuntimeException("Edit {$index} has no operation.");
            if (! in_array($operation, self::OPERATIONS, true)) {
                throw new RuntimeException("Edit {$index} has the unknown operation \"{$operation}\"; expected one of ".implode(', ', self::OPERATIONS).'.');
            }
            $argument = $edit[2] ?? null;
            if (($operation === 'remove') !== ($argument === null)) {
                throw new RuntimeException("Edit {$index} ({$operation} {$expression}): remove takes no argument, every other operation one.");
            }

            $selected = @$xpath->query($expression);
            if ($selected === false) {
                throw new RuntimeException("Edit {$index}: \"{$expression}\" is not a valid XPath 1.0 expression for the namespaces ".implode(', ', array_keys($namespaces)).'.');
            }
            if ($selected->length === 0) {
                throw new RuntimeException("Edit {$index}: \"{$expression}\" selects nothing.");
            }

            // Collect first: removing or replacing nodes while iterating a live list skips nodes.
            foreach (iterator_to_array($selected) as $node) {
                if (! $node instanceof DOMNode) {
                    throw new RuntimeException("Edit {$index}: \"{$expression}\" selects a namespace node, which cannot be edited.");
                }
                match ($operation) {
                    'set' => self::set($node, (string) $argument, $expression),
                    'remove' => self::remove($node, $expression),
                    'append' => self::append($node, (string) $argument, $namespaces, $expression),
                    'before' => self::before($node, (string) $argument, $namespaces, $expression),
                    'rename' => self::rename($node, (string) $argument, $namespaces, $expression),
                };
            }
        }

        return (string) $document->saveXML();
    }

    private static function set(DOMNode $node, string $value, string $expression): void
    {
        if ($node instanceof DOMAttr) {
            $node->value = $value;

            return;
        }
        if (! $node instanceof DOMElement) {
            throw new RuntimeException("\"{$expression}\" selects a node that is neither an element nor an attribute.");
        }
        while ($node->firstChild !== null) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild(self::owner($node)->createTextNode($value));
    }

    private static function remove(DOMNode $node, string $expression): void
    {
        if ($node instanceof DOMAttr) {
            $owner = $node->ownerElement ?? throw new RuntimeException("\"{$expression}\" selects a detached attribute.");
            $owner->removeAttributeNode($node);

            return;
        }
        $parent = $node->parentNode;
        if (! $node instanceof DOMElement || ! $parent instanceof DOMElement) {
            throw new RuntimeException("\"{$expression}\" selects a node that cannot be removed (only attributes and non-root elements can).");
        }
        $parent->removeChild($node);
    }

    /**
     * @param  array<string, string>  $namespaces
     */
    private static function append(DOMNode $node, string $argument, array $namespaces, string $expression): void
    {
        if (! $node instanceof DOMElement) {
            throw new RuntimeException("\"{$expression}\" selects a node that is not an element; append adds to elements only.");
        }

        if (str_starts_with($argument, '@')) {
            $pair = explode('=', substr($argument, 1), 2);
            if (count($pair) !== 2 || $pair[0] === '') {
                throw new RuntimeException("The attribute \"{$argument}\" is not of the form @name=value.");
            }
            [$name, $value] = $pair;
            $uri = self::namespaceOf($name, $namespaces, attribute: true);
            if ($uri === null) {
                $node->setAttribute($name, $value);
            } else {
                $node->setAttributeNS($uri, $name, $value);
            }

            return;
        }

        foreach (self::fragment($argument, $namespaces) as $child) {
            $node->appendChild(self::owner($node)->importNode($child, true));
        }
    }

    /**
     * @param  array<string, string>  $namespaces
     */
    private static function before(DOMNode $node, string $argument, array $namespaces, string $expression): void
    {
        $parent = $node->parentNode;
        if (! $node instanceof DOMElement || ! $parent instanceof DOMElement) {
            throw new RuntimeException("\"{$expression}\" selects a node that is not a non-root element; before inserts next to elements only.");
        }
        foreach (self::fragment($argument, $namespaces) as $child) {
            $parent->insertBefore(self::owner($node)->importNode($child, true), $node);
        }
    }

    /**
     * @param  array<string, string>  $namespaces
     */
    private static function rename(DOMNode $node, string $name, array $namespaces, string $expression): void
    {
        if ($node instanceof DOMAttr) {
            $owner = $node->ownerElement ?? throw new RuntimeException("\"{$expression}\" selects a detached attribute.");
            $value = $node->value;
            $owner->removeAttributeNode($node);
            $uri = self::namespaceOf($name, $namespaces, attribute: true);
            if ($uri === null) {
                $owner->setAttribute($name, $value);
            } else {
                $owner->setAttributeNS($uri, $name, $value);
            }

            return;
        }
        $parent = $node->parentNode;
        if (! $node instanceof DOMElement || $parent === null) {
            throw new RuntimeException("\"{$expression}\" selects a node that cannot be renamed.");
        }

        $document = self::owner($node);
        $uri = self::namespaceOf($name, $namespaces, attribute: false);
        $renamed = $uri === null ? $document->createElement($name) : $document->createElementNS($uri, $name);
        foreach (iterator_to_array($node->attributes) as $attribute) {
            if ($attribute->namespaceURI === null) {
                $renamed->setAttribute($attribute->name, $attribute->value);
            } else {
                $renamed->setAttributeNS($attribute->namespaceURI, $attribute->nodeName, $attribute->value);
            }
        }
        while ($node->firstChild !== null) {
            $renamed->appendChild($node->firstChild);
        }
        $parent->replaceChild($renamed, $node);
    }

    /**
     * The top-level nodes of an XML fragment, parsed with the namespace prefixes declared.
     *
     * @param  array<string, string>  $namespaces
     * @return list<DOMNode>
     */
    private static function fragment(string $xml, array $namespaces): array
    {
        $declarations = '';
        foreach ($namespaces as $prefix => $uri) {
            $declarations .= ' xmlns:'.$prefix.'="'.htmlspecialchars($uri, ENT_XML1 | ENT_QUOTES).'"';
        }
        $fragment = new DOMDocument;
        if (! @$fragment->loadXML('<fragment'.$declarations.'>'.$xml.'</fragment>', LIBXML_NONET)) {
            throw new RuntimeException("The fragment \"{$xml}\" is not well-formed XML with the declared namespace prefixes.");
        }
        $root = $fragment->documentElement;
        if ($root === null || $root->firstChild === null) {
            throw new RuntimeException("The fragment \"{$xml}\" is empty.");
        }

        return array_values(iterator_to_array($root->childNodes));
    }

    /**
     * The namespace of a qualified name: the URI of its prefix, or none for an unprefixed name.
     *
     * @param  array<string, string>  $namespaces
     */
    private static function namespaceOf(string $name, array $namespaces, bool $attribute): ?string
    {
        if (! str_contains($name, ':')) {
            return null;
        }
        $prefix = explode(':', $name, 2)[0];
        if ($attribute && $prefix === 'xml') {
            return 'http://www.w3.org/XML/1998/namespace';
        }

        return $namespaces[$prefix] ?? throw new RuntimeException("The prefix \"{$prefix}\" of \"{$name}\" is not declared.");
    }

    private static function owner(DOMNode $node): DOMDocument
    {
        return $node->ownerDocument ?? throw new RuntimeException('The node belongs to no document.');
    }
}

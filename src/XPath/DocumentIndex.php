<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use DOMAttr;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use InvalidArgumentException;

/**
 * Document order of every node of one document, plus a name index for descendant lookups.
 *
 * The index keeps every node wrapper in an array, so PHP returns the same DOMNode object for a node
 * on every access and spl_object_id() identifies nodes for as long as the index lives. Attributes of
 * an element follow the element directly and precede its children.
 *
 * @internal
 */
final class DocumentIndex
{
    /** @var list<DOMNode> nodes in document order */
    private array $nodes = [];

    /** @var array<int, int> spl_object_id => position in document order */
    private array $order = [];

    /** @var array<int, int> spl_object_id => position of the last node in the subtree */
    private array $last = [];

    /** @var array<string, list<DOMElement>> "uri local" => elements in document order */
    private array $byName = [];

    /** @var array<string, list<int>> "uri local" => document-order positions of the same elements */
    private array $positionsByName = [];

    /** @var array<string, array{int, array<string, list<DOMElement>>, ?array<string, list<DOMElement>>}> see childrenByAttribute() */
    private array $byAttribute = [];

    private function __construct(public readonly DOMDocument $document) {}

    public static function build(DOMDocument $document): self
    {
        $index = new self($document);
        $index->add($document);

        return $index;
    }

    /**
     * Position of a node in document order.
     */
    public function order(DOMNode $node): int
    {
        return $this->order[spl_object_id($node)] ?? throw new InvalidArgumentException('The node does not belong to the indexed document.');
    }

    /**
     * Elements with the given namespace URI and local name, in document order.
     *
     * @return list<DOMElement>
     */
    public function descendantsNamed(string $uri, string $local): array
    {
        return $this->byName[$uri.' '.$local] ?? [];
    }

    /**
     * Elements with the given name that are proper descendants of $node, in document order.
     *
     * @return list<DOMElement>
     */
    public function descendantsOfNamed(DOMNode $node, string $uri, string $local): array
    {
        if ($node instanceof DOMDocument) {
            return $this->descendantsNamed($uri, $local);
        }

        $key = $uri.' '.$local;
        $positions = $this->positionsByName[$key] ?? [];
        $from = $this->order($node);
        $to = $this->last[spl_object_id($node)];
        $start = self::lowerBound($positions, $from + 1);
        $end = self::lowerBound($positions, $to + 1);

        return array_slice($this->byName[$key] ?? [], $start, $end - $start);
    }

    /**
     * The node itself followed by all its descendants (attributes excluded), in document order.
     *
     * @return list<DOMNode>
     */
    public function descendantsOrSelf(DOMNode $node): array
    {
        $from = $this->order($node);
        $to = $this->last[spl_object_id($node)];
        $result = [];
        for ($i = $from; $i <= $to; $i++) {
            if (! $this->nodes[$i] instanceof DOMAttr) {
                $result[] = $this->nodes[$i];
            }
        }

        return $result;
    }

    /**
     * Nodes that precede $node in document order and are not its ancestors, nearest first
     * (attributes excluded).
     *
     * @return list<DOMNode>
     */
    public function preceding(DOMNode $node): array
    {
        $ancestors = [];
        for ($parent = self::parent($node); $parent !== null; $parent = self::parent($parent)) {
            $ancestors[spl_object_id($parent)] = true;
        }

        $result = [];
        for ($i = $this->order($node) - 1; $i >= 0; $i--) {
            $candidate = $this->nodes[$i];
            if (! $candidate instanceof DOMAttr && ! isset($ancestors[spl_object_id($candidate)])) {
                $result[] = $candidate;
            }
        }

        return $result;
    }

    /**
     * Elements with the given name that precede $node in document order and are not its ancestors,
     * nearest first.
     *
     * @return list<DOMElement>
     */
    public function precedingNamed(DOMNode $node, string $uri, string $local): array
    {
        $key = $uri.' '.$local;
        $positions = $this->positionsByName[$key] ?? [];
        $elements = $this->byName[$key] ?? [];
        $from = $this->order($node);

        $result = [];
        for ($i = self::lowerBound($positions, $from) - 1; $i >= 0; $i--) {
            // An element whose subtree reaches $node is one of its ancestors.
            if ($this->last[spl_object_id($elements[$i])] < $from) {
                $result[] = $elements[$i];
            }
        }

        return $result;
    }

    /**
     * Sorts nodes into document order and removes duplicates.
     *
     * @param  list<DOMNode>  $nodes
     * @return list<DOMNode>
     */
    public function sort(array $nodes): array
    {
        $byOrder = [];
        foreach ($nodes as $node) {
            $byOrder[$this->order($node)] = $node;
        }
        ksort($byOrder);

        return array_values($byOrder);
    }

    /**
     * The child elements of $parent with the given name, grouped by the value of the given
     * attribute, for equality lookups: the number of such children, the children that carry the
     * attribute keyed by its string value, and the same keyed by doubleKey() of the value cast to
     * xs:double. The double map is null when a value does not cast to xs:double; NaN values are
     * left out of it, as NaN equals nothing. Built on first use and kept with the index, so the
     * document must not change while the index lives.
     *
     * @return array{int, array<string, list<DOMElement>>, ?array<string, list<DOMElement>>}
     */
    public function childrenByAttribute(DOMNode $parent, string $uri, string $local, string $attributeUri, string $attributeLocal): array
    {
        $key = spl_object_id($parent)." {$uri} {$local} {$attributeUri} {$attributeLocal}";
        if (isset($this->byAttribute[$key])) {
            return $this->byAttribute[$key];
        }

        $count = 0;
        $strings = [];
        $doubles = [];
        foreach ($parent->childNodes as $child) {
            if (! $child instanceof DOMElement || ($child->namespaceURI ?? '') !== $uri || ($child->localName ?? $child->nodeName) !== $local) {
                continue;
            }
            $count++;
            foreach ($child->attributes as $attribute) {
                if (($attribute->namespaceURI ?? '') !== $attributeUri || ($attribute->localName ?? $attribute->nodeName) !== $attributeLocal) {
                    continue;
                }
                $strings[$attribute->value][] = $child;
                if ($doubles === null) {
                    continue;
                }
                try {
                    $double = Atomic::cast(new UntypedAtomic($attribute->value), 'xs:double');
                } catch (XPathError) {
                    $doubles = null;

                    continue;
                }
                if ($double instanceof DoubleValue && ! is_nan($double->value)) {
                    $doubles[self::doubleKey($double->value)][] = $child;
                }
            }
        }

        return $this->byAttribute[$key] = [$count, $strings, $doubles];
    }

    /**
     * A key that is equal for two doubles exactly when they compare equal (NaN excepted).
     */
    public static function doubleKey(float $value): string
    {
        return $value == 0.0 ? '0' : sprintf('%.17g', $value);
    }

    /**
     * The XPath parent: the owner element of an attribute, null for the document node.
     */
    public static function parent(DOMNode $node): ?DOMNode
    {
        return $node instanceof DOMAttr ? $node->ownerElement : $node->parentNode;
    }

    private function add(DOMNode $node): void
    {
        $id = spl_object_id($node);
        $position = count($this->nodes);
        $this->nodes[] = $node;
        $this->order[$id] = $position;

        if ($node instanceof DOMElement) {
            $key = ($node->namespaceURI ?? '').' '.($node->localName ?? $node->nodeName);
            $this->byName[$key][] = $node;
            $this->positionsByName[$key][] = $position;
            foreach ($node->attributes as $attribute) {
                $this->order[spl_object_id($attribute)] = count($this->nodes);
                $this->last[spl_object_id($attribute)] = count($this->nodes);
                $this->nodes[] = $attribute;
            }
        }

        foreach ($node->childNodes as $child) {
            // The document type declaration is not part of the XPath data model.
            if (! $child instanceof DOMDocumentType) {
                $this->add($child);
            }
        }

        $this->last[$id] = count($this->nodes) - 1;
    }

    /**
     * Index of the first value that is not less than $value.
     *
     * @param  list<int>  $values  ascending
     */
    private static function lowerBound(array $values, int $value): int
    {
        $low = 0;
        $high = count($values);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($values[$middle] < $value) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}

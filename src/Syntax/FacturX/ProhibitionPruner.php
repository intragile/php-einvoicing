<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use LogicException;

/**
 * Removes the nodes a profile's Schematron rules prohibit unconditionally.
 *
 * A profile's schema is wider than its rules: the rules mark further elements and attributes as
 * "not used" with a report that always fires. Two shapes are recognised, purely from the compiled
 * rule structure:
 *
 * - a report whose test is exactly "true()" prohibits its context node, which is removed;
 * - a report whose test is exactly one attribute step, such as "@schemeID", prohibits that
 *   attribute on its context element, which is removed.
 *
 * Reports with any other test are conditional and are left alone. Removing a node can expose
 * another prohibited one, so the rules are applied again until a round removes nothing.
 */
final class ProhibitionPruner
{
    /** Upper bound on rounds; a rule set that needs more is not converging. */
    private const MAX_ROUNDS = 10;

    /**
     * Prunes the document in place.
     *
     * @return list<string> paths of the removed nodes in the notation of ReducedXml::$removed,
     *                      de-duplicated and sorted
     *
     * @throws LogicException when the rules still prohibit nodes after the last round
     */
    public function prune(RuleSet $rules, DOMDocument $document): array
    {
        $removed = [];
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $nodes = [];
            $attributes = [];
            $runner = new Runner(null, static function (DOMNode $node, array $assert) use (&$nodes, &$attributes): void {
                if (! $assert['report']) {
                    return;
                }
                if ($assert['test'] === 'true()') {
                    $nodes[] = $node;
                } elseif ($node instanceof DOMElement && preg_match('/^@([A-Za-z_][A-Za-z0-9_.-]*)$/', $assert['test'], $m) === 1) {
                    $attributes[] = [$node, $m[1]];
                }
            });
            $runner->run($rules, $document);

            $before = count($removed);
            foreach ($nodes as $node) {
                $this->removeNode($node, $removed);
            }
            foreach ($attributes as [$element, $name]) {
                $attribute = $element->getAttributeNode($name);
                if ($attribute instanceof DOMAttr && self::attached($element)) {
                    $removed[] = ProfileReducer::pathOf($attribute);
                    $element->removeAttributeNode($attribute);
                }
            }
            if (count($removed) === $before) {
                $removed = array_values(array_unique($removed));
                sort($removed, SORT_STRING);

                return $removed;
            }
        }

        throw new LogicException('The profile rules still prohibit nodes after '.self::MAX_ROUNDS.' pruning rounds.');
    }

    /**
     * @param  list<string>  $removed
     */
    private function removeNode(DOMNode $node, array &$removed): void
    {
        // A node already detached with an ancestor, or removed through another rule, is gone.
        if (! self::attached($node instanceof DOMAttr ? $node->ownerElement : $node)) {
            return;
        }
        if ($node instanceof DOMAttr) {
            $element = $node->ownerElement;
            if ($element !== null) {
                $removed[] = ProfileReducer::pathOf($node);
                $element->removeAttributeNode($node);
            }

            return;
        }
        if (! $node instanceof DOMElement || $node->parentNode instanceof DOMDocument) {
            return;
        }

        $parent = $node->parentNode;
        $removed[] = ProfileReducer::pathOf($node);
        $previous = $node->previousSibling;
        if ($previous instanceof DOMText && trim($previous->data) === '') {
            $previous->remove();
        }
        $node->remove();

        // A parent left without elements keeps no indentation, as in ProfileReducer.
        if ($parent instanceof DOMElement && $parent->firstElementChild === null) {
            for ($child = $parent->firstChild; $child !== null; $child = $next) {
                $next = $child->nextSibling;
                if ($child instanceof DOMText && trim($child->data) === '') {
                    $child->remove();
                }
            }
        }
    }

    private static function attached(?DOMNode $node): bool
    {
        while ($node !== null && ! $node instanceof DOMDocument) {
            $node = $node->parentNode;
        }

        return $node !== null;
    }
}

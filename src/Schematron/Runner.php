<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\XPathError;

/**
 * Runs a compiled rule set over a document with the semantics of the ISO Schematron XSLT skeleton:
 * each pattern visits the document node and every element and attribute (apply-templates on
 * "@*|*") in document order, a node is handled by the first rule of the pattern whose context
 * matches it, and every assert of that rule is evaluated with the node as context item.
 *
 * Each rule's match set is computed once per document instead of testing the pattern per node.
 * Unlike the XSLT, a dynamic error does not abort the run: it is recorded and evaluation continues.
 *
 * @phpstan-import-type Rule from RuleSet
 */
final class Runner
{
    public function run(RuleSet $rules, DOMDocument $document): RunResult
    {
        $index = DocumentIndex::build($document);
        $evaluator = new Evaluator($index);
        $namespaces = $rules->namespaces();
        $root = $document->documentElement;

        $findings = [];
        $errors = [];
        $rootRuleFired = false;

        foreach ($rules->patterns() as $pattern) {
            /** @var array<int, array{DOMNode, Rule}> $handled document order => node and its handling rule */
            $handled = [];
            foreach ($pattern['rules'] as $rule) {
                try {
                    $matches = $evaluator->evaluate($rule['match'], $document);
                } catch (XPathError $error) {
                    $errors[] = new DynamicError(null, $error->errorCode, '/', "The rule context \"{$rule['context']}\" cannot be evaluated: {$error->getMessage()}");

                    continue;
                }
                foreach ($matches as $node) {
                    if ($node instanceof DOMElement || $node instanceof DOMAttr || $node instanceof DOMDocument) {
                        $handled[$index->order($node)] ??= [$node, $rule];
                    }
                }
            }
            ksort($handled);

            foreach ($handled as [$node, $rule]) {
                if ($node === $root && ! $rootRuleFired) {
                    $rootRuleFired = self::names($root, $rule['roots']);
                }
                foreach ($rule['asserts'] as $assert) {
                    try {
                        if ($evaluator->effectiveBoolean($assert['ast'], $node)) {
                            continue;
                        }
                        $findings[] = new Finding($assert['id'], $assert['severity'], LocationPath::of($node, $namespaces), $assert['message'], $assert['test']);
                    } catch (XPathError $error) {
                        $errors[] = new DynamicError($assert['id'], $error->errorCode, LocationPath::of($node, $namespaces), $error->getMessage());
                    }
                }
            }
        }

        return new RunResult($findings, $errors, $rootRuleFired);
    }

    /**
     * @param  list<array{string, string}>  $names  namespace URI and local name
     */
    private static function names(DOMElement $element, array $names): bool
    {
        foreach ($names as [$uri, $local]) {
            if (($element->namespaceURI ?? '') === $uri && $element->localName === $local) {
                return true;
            }
        }

        return false;
    }
}

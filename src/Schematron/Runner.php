<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Thunk;
use IntrAgile\EInvoicing\XPath\UserFunction;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\XPathError;

/**
 * Runs a compiled rule set over a document with the semantics of the ISO Schematron XSLT skeleton:
 * each pattern visits the document node and every element and attribute (apply-templates on
 * "@*|*") in document order, a node is handled by the first rule of the pattern whose context
 * matches it, and every assert of that rule is evaluated with the node as context item.
 *
 * Variables follow the skeleton too: schema and pattern lets are global variables, evaluated with
 * the document node as context; rule lets are evaluated with the handled node as context. Every
 * let is computed on first use and kept (see Thunk), so a let no assert reads is never evaluated,
 * and a let that fails is reported by every assert that reads it. Global variables are computed
 * at most once per document. Rule contexts see the global variables; asserts see those and the
 * lets of their rule. XSLT functions see the schema lets.
 *
 * Each rule's match set is computed once per document instead of testing the pattern per node.
 * Unlike the XSLT, a dynamic error does not abort the run: it is recorded and evaluation continues.
 *
 * @internal
 *
 * @phpstan-import-type Let from RuleSet
 * @phpstan-import-type Rule from RuleSet
 */
final class Runner
{
    public function run(RuleSet $rules, DOMDocument $document): RunResult
    {
        $index = DocumentIndex::build($document);
        $evaluator = new Evaluator($index);
        // The global variables capture $evaluator by reference, so they run on the evaluator that
        // knows the functions, which in turn needs the schema lets for the function bodies.
        $schemaLets = self::bind($rules->lets(), [], $document, $evaluator);
        $functions = array_map(static fn (array $function): UserFunction => new UserFunction($function), $rules->functions());
        $evaluator = $evaluator->withFunctions($functions, $schemaLets);
        $globals = $schemaLets;
        foreach ($rules->patterns() as $pattern) {
            $globals = self::bind($pattern['lets'], $globals, $document, $evaluator);
        }
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
                    $matches = $evaluator->matchPattern($rule['match'], $document, $globals);
                } catch (XPathError $error) {
                    // A recoverable error in a step predicate only drops its node (see
                    // Evaluator::matchPattern()). A non-recoverable one, such as the recursion
                    // limit, and an error elsewhere in the pattern leave the rule without any node
                    // and are reported once for the rule, at the document node.
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
                $variables = $rule['lets'] === [] ? $globals : self::bind($rule['lets'], $globals, $node, $evaluator);
                foreach ($rule['asserts'] as $assert) {
                    try {
                        if ($evaluator->effectiveBoolean($assert['ast'], $node, $variables)) {
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
     * Binds lets in order, each to a Thunk evaluated with the given context node that sees the
     * variables bound before it.
     *
     * @param  list<Let>  $lets
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables  the variables already in scope
     * @return array<string, list<DOMNode|AtomicValue>|Thunk> $variables plus the lets
     */
    private static function bind(array $lets, array $variables, DOMNode $context, Evaluator &$evaluator): array
    {
        foreach ($lets as $let) {
            $visible = $variables;
            $ast = $let['ast'];
            $variables[$let['name']] = new Thunk(static function () use (&$evaluator, $ast, $context, $visible): array {
                return $evaluator->evaluate($ast, $context, $visible);
            });
        }

        return $variables;
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

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use Closure;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Functions;
use IntrAgile\EInvoicing\XPath\Thunk;
use IntrAgile\EInvoicing\XPath\UserFunction;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\XPathError;

/**
 * Runs a compiled rule set over a document with the semantics of the ISO Schematron XSLT skeleton:
 * each pattern visits the document node and every element and attribute (apply-templates on
 * "@*|*") in document order, a node is handled by the first rule of the pattern whose context
 * matches it, and every assert and report of that rule is evaluated with the node as context item.
 * An assert yields a finding when its test is false, a report when its test is true; both are
 * otherwise handled alike (messages, dynamic errors).
 *
 * Variables follow the skeleton too: schema and pattern lets are global variables, evaluated with
 * the document node as context; rule lets are evaluated with the handled node as context. Every
 * let is computed on first use and kept (see Thunk), so a let no assert reads is never evaluated,
 * and a let that fails is reported by every assert that reads it. Global variables are computed
 * at most once per document. Rule contexts see the global variables; asserts see those and the
 * lets of their rule. XSLT functions see the schema lets.
 *
 * The message of a failed assert or fired report with name or value-of elements is built at its
 * node, as the skeleton writes it into the report: name gives the qualified name of the node as written in
 * the document (XPath name()), value-of the string values of the selected items joined by a single
 * space (the xsl:value-of default separator). A message expression that raises an error falls
 * back to the static message and is not reported: messages only feed the report, never the verdict.
 *
 * Each rule's match set is computed once per document instead of testing the pattern per node.
 * Unlike the XSLT, a dynamic error does not abort the run: it is recorded, with the id and version
 * of the rule set, and evaluation continues.
 *
 * fn:document() reads only the documents embedded in the rule set when it was compiled (see
 * RuleSet::documents()); each is parsed once per rule set and shared by every run.
 *
 * @internal
 *
 * @phpstan-import-type Let from RuleSet
 * @phpstan-import-type Rule from RuleSet
 * @phpstan-import-type RuleAssert from RuleSet
 */
final class Runner
{
    /**
     * @param  (Closure(string): void)|null  $onGlobal  called with the name of a schema or pattern let
     *                                                  each time its value is computed (for tests and profiling)
     * @param  (Closure(DOMNode, RuleAssert, Finding): void)|null  $onFinding  called with the context node,
     *                                                                         the assert and the finding (its
     *                                                                         message built) each time an
     *                                                                         assert yields a finding
     */
    public function __construct(private readonly ?Closure $onGlobal = null, private readonly ?Closure $onFinding = null) {}

    public function run(RuleSet $rules, DOMDocument $document): RunResult
    {
        $index = DocumentIndex::build($document);
        $evaluator = new Evaluator($index, $rules->documentIndex(...));
        // The global variables capture $evaluator by reference, so they run on the evaluator that
        // knows the functions, which in turn needs the schema lets for the function bodies.
        $schemaLets = $this->bindGlobals($rules->lets(), [], $document, $evaluator);
        $functions = array_map(static fn (array $function): UserFunction => new UserFunction($function), $rules->functions());
        $evaluator = $evaluator->withFunctions($functions, $schemaLets);
        $globals = $schemaLets;
        foreach ($rules->patterns() as $pattern) {
            $globals = $this->bindGlobals($pattern['lets'], $globals, $document, $evaluator);
        }
        $namespaces = $rules->namespaces();
        $root = $document->documentElement;
        $ruleSet = "{$rules->id()} {$rules->version()}";

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
                    $errors[] = new DynamicError(null, $error->errorCode, '/', "The rule context \"{$rule['context']}\" cannot be evaluated: {$error->getMessage()}", $ruleSet);

                    continue;
                }
                foreach ($matches as $node) {
                    // Only nodes of the validated document are visited; a match inside an embedded
                    // document (fn:document) is never handled, as apply-templates never reaches it.
                    if ($node === $document || (($node instanceof DOMElement || $node instanceof DOMAttr) && $node->ownerDocument === $document)) {
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
                        // An assert fires when its test is false, a report when it is true.
                        if ($evaluator->effectiveBoolean($assert['ast'], $node, $variables) !== $assert['report']) {
                            continue;
                        }
                        $finding = new Finding($assert['id'], $assert['severity'], LocationPath::of($node, $namespaces), self::message($assert, $node, $variables, $evaluator), $assert['test']);
                        $findings[] = $finding;
                        $this->onFinding?->__invoke($node, $assert, $finding);
                    } catch (XPathError $error) {
                        $errors[] = new DynamicError($assert['id'], $error->errorCode, LocationPath::of($node, $namespaces), $error->getMessage(), $ruleSet);
                    }
                }
            }
        }

        return new RunResult($findings, $errors, $rootRuleFired);
    }

    /**
     * Binds global lets (schema and pattern level) in order, each to a Thunk evaluated from the
     * document node with the call depth reset (see Evaluator::evaluateGlobal()).
     *
     * @param  list<Let>  $lets
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables  the variables already in scope
     * @return array<string, list<DOMNode|AtomicValue>|Thunk> $variables plus the lets
     */
    private function bindGlobals(array $lets, array $variables, DOMDocument $document, Evaluator &$evaluator): array
    {
        $onGlobal = $this->onGlobal;
        foreach ($lets as $let) {
            $visible = $variables;
            $ast = $let['ast'];
            $name = $let['name'];
            $variables[$name] = new Thunk(static function () use (&$evaluator, $ast, $document, $visible, $onGlobal, $name): array {
                if ($onGlobal !== null) {
                    $onGlobal($name);
                }

                return $evaluator->evaluateGlobal($ast, $document, $visible);
            });
        }

        return $variables;
    }

    /**
     * Binds rule lets in order, each to a Thunk evaluated with the given context node that sees the
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
     * The message of a failed assert or fired report at its node.
     *
     * @param  RuleAssert  $assert
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private static function message(array $assert, DOMNode $node, array $variables, Evaluator $evaluator): string
    {
        if ($assert['messageParts'] === null) {
            return $assert['message'];
        }

        $message = '';
        try {
            foreach ($assert['messageParts'] as $part) {
                $message .= match (true) {
                    is_string($part) => $part,
                    isset($part['valueOf']) => implode(' ', array_map(
                        static fn (AtomicValue $value): string => $value->lexical(),
                        Functions::data($evaluator->evaluate($part['valueOf'], $node, $variables)),
                    )),
                    default => Functions::name($node),
                };
            }
        } catch (XPathError) {
            return $assert['message'];
        }

        return Compiler::collapse($message);
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

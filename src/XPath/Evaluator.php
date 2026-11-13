<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use DOMAttr;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMText;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
use IntrAgile\EInvoicing\XPath\Value\DecimalValue;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\Value\StringValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use LogicException;

/**
 * Evaluates the array AST of Parser over one indexed DOM document with XPath 2.0 semantics
 * (no schema: element and attribute values atomize to xs:untypedAtomic).
 *
 * fn:document() reaches further documents only through the given resolver, which maps a URI to
 * an indexed document or null; nothing is ever read from the file system or the network. Nodes of
 * different documents sort by document (the evaluated document first, then the others in the
 * order they were first returned) and by document order within each.
 *
 * @internal
 */
final class Evaluator
{
    private const REVERSE_AXES = ['ancestor', 'ancestor-or-self', 'parent', 'preceding', 'preceding-sibling'];

    /** The longest sequence a range expression may produce; a longer one would exhaust memory. */
    private const MAX_RANGE = 100_000;

    /** The deepest nesting of user function calls; deeper recursion is reported, not run until PHP fails. */
    private const MAX_DEPTH = 2_000;

    /** Function calls whose result is always a boolean, so a predicate using them is never positional. */
    private const BOOLEAN_FUNCTIONS = ['fn:boolean', 'fn:contains', 'fn:empty', 'fn:ends-with', 'fn:exists', 'fn:false', 'fn:matches', 'fn:not', 'fn:true'];

    /** @var array<string, AtomicValue> */
    private array $literals = [];

    /** True while the steps of a match pattern are evaluated, outside their predicates. */
    private bool $matchingPattern = false;

    /**
     * The context size (fn:last) inside a predicate or the right operand of "/"; null at the top
     * level of an expression, where this engine does not know the size of the caller's focus.
     */
    private ?int $size = null;

    /** @var array<string, UserFunction> user functions by StaticContext::functionKey() */
    private array $functions = [];

    /** @var array<string, list<DOMNode|AtomicValue>|Thunk> global variables visible in function bodies */
    private array $globals = [];

    /** The number of user function calls in progress. */
    private int $depth = 0;

    /**
     * The error thrown when MAX_DEPTH is exceeded, created where the call stack is shallow: an
     * exception records its stack trace when it is created, and a trace of thousands of frames
     * with their arguments would take more memory than the recursion itself.
     */
    private ?XPathError $recursionError = null;

    /** @var array<int, DocumentIndex> the documents fn:document() returned, by spl_object_id of their document node */
    private array $documents = [];

    /**
     * @param  (Closure(string): ?DocumentIndex)|null  $resolver  the documents fn:document() can return, by URI
     */
    public function __construct(private readonly DocumentIndex $index, private readonly ?Closure $resolver = null) {}

    /**
     * An evaluator for the same document that can call the given user functions; their bodies see
     * the given global variables.
     *
     * @param  array<string, UserFunction>  $functions  by StaticContext::functionKey()
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $globals
     */
    public function withFunctions(array $functions, array $globals = []): self
    {
        $evaluator = clone $this;
        $evaluator->functions = $functions;
        $evaluator->globals = $globals;
        $evaluator->recursionError = new XPathError('INTRAGILE_RECURSION', sprintf('More than %s nested calls of user functions.', number_format(self::MAX_DEPTH)));

        return $evaluator;
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public function evaluate(array $ast, DOMNode $context, array $variables = []): array
    {
        // A fresh focus state: this method is re-entered while an expression is evaluated, when a
        // variable is computed on first use or a function body runs, and that inner expression must
        // not inherit the context size or the pattern mode of the outer one.
        [$outerMatching, $outerSize] = [$this->matchingPattern, $this->size];
        $this->matchingPattern = false;
        $this->size = null;
        try {
            return $this->eval($ast, $context, $variables);
        } finally {
            $this->matchingPattern = $outerMatching;
            $this->size = $outerSize;
        }
    }

    /**
     * Evaluates the value of a global variable: like evaluate(), but with the call depth starting
     * from zero, so the value (or the recursion error) does not depend on how deep inside user
     * function calls the variable happens to be read first.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public function evaluateGlobal(array $ast, DOMNode $context, array $variables = []): array
    {
        $outerDepth = $this->depth;
        $this->depth = 0;
        try {
            return $this->evaluate($ast, $context, $variables);
        } finally {
            $this->depth = $outerDepth;
        }
    }

    /**
     * Nodes selected by a match pattern (an XSLT rule context), evaluated as an expression from the
     * document node. As in XSLT 2.0 pattern matching, where a dynamic error is recoverable, a node
     * for which a predicate of one of the pattern's own steps raises a dynamic error is not
     * selected, and the other nodes still are. An error inside a nested predicate makes the
     * enclosing step predicate fail as a whole. Only the data-driven errors listed in
     * XPathError::RECOVERABLE are handled this way.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError when the pattern fails outside its step predicates, or with a
     *                    non-recoverable error inside one
     */
    public function matchPattern(array $ast, DOMNode $context, array $variables = []): array
    {
        [$outerMatching, $outerSize] = [$this->matchingPattern, $this->size];
        $this->matchingPattern = true;
        $this->size = null;
        try {
            return $this->eval($ast, $context, $variables);
        } finally {
            $this->matchingPattern = $outerMatching;
            $this->size = $outerSize;
        }
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     *
     * @throws XPathError
     */
    public function effectiveBoolean(array $ast, DOMNode $context, array $variables = []): bool
    {
        return Functions::boolean($this->evaluate($ast, $context, $variables));
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function eval(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        // Each case stays a single call: without the opcode optimizer, PHP reserves a slot in every
        // frame of this method for each temporary of every case, and recursion multiplies it.
        switch ($ast[0]) {
            case Op::LITERAL:
                return [$this->literal(self::text($ast, 1), self::text($ast, 2))];
            case Op::CONTEXT:
                return [$item];
            case Op::ROOT:
                return $this->root($item);
            case Op::PATH:
                return $this->path(self::sub($ast, 1), self::sub($ast, 2), $item, $variables);
            case Op::STEP:
                return $this->step(self::text($ast, 1), self::sub($ast, 2), self::subList($ast, 3), $item, $variables);
            case Op::FILTER:
                return $this->applyPredicates($this->eval(self::sub($ast, 1), $item, $variables), self::subList($ast, 2), $variables);
            case Op::VAR:
                return self::variable(self::text($ast, 1), $variables);
            case Op::CALL:
                return $this->callBuiltIn($ast, $item, $variables);
            case Op::OR:
            case Op::AND:
                return $this->logical($ast, $item, $variables);
            case Op::GCMP:
                return $this->generalComparison($ast, $item, $variables);
            case Op::VCMP:
            case Op::ARITH:
                return $this->binary($ast, $item, $variables);
            case Op::NEG:
                return $this->negation($ast, $item, $variables);
            case Op::UNION:
                return $this->union($ast, $item, $variables);
            case Op::QUANT:
                return $this->quantifiedExpression($ast, $item, $variables);
            case Op::CAST:
                return $this->cast($ast, $item, $variables);
            case Op::CASTABLE:
                return $this->castable($ast, $item, $variables);
            case Op::IF:
                return $this->conditional($ast, $item, $variables);
            case Op::FOR:
                return $this->forLoop(self::subList($ast, 1), self::sub($ast, 2), $item, $variables);
            case Op::RANGE:
                return $this->range(self::sub($ast, 1), self::sub($ast, 2), $item, $variables);
            case Op::UCALL:
                return $this->callFunction($ast, $item, $variables);
            case Op::SEQUENCE:
                return $this->sequence($ast, $item, $variables);
        }

        throw self::unknownNode($ast);
    }

    /**
     * @return list<DOMNode>
     */
    private function root(DOMNode|AtomicValue $item): array
    {
        if (! $item instanceof DOMNode) {
            throw new XPathError('XPDY0050', 'The context item for "/" is not a node.');
        }

        if ($item instanceof DOMDocument) {
            return [$item];
        }

        return [$this->documents === [] ? $this->index->document : $this->indexOf($item)->document];
    }

    /**
     * The document node of an embedded document (fn:document); a URI the resolver does not know
     * raises FODC0002.
     */
    private function document(string $uri): DOMDocument
    {
        $index = $this->resolver !== null ? ($this->resolver)($uri) : null;
        if ($index === null) {
            throw new XPathError('FODC0002', "The document \"{$uri}\" is not available; only documents embedded when the rule set was compiled can be read.");
        }
        $this->documents[spl_object_id($index->document)] = $index;

        return $index->document;
    }

    /**
     * The index of the document a node belongs to.
     */
    private function indexOf(DOMNode $node): DocumentIndex
    {
        if ($this->documents === []) {
            return $this->index;
        }
        $document = $node instanceof DOMDocument ? $node : $node->ownerDocument;

        return $document === null || $document === $this->index->document ? $this->index : ($this->documents[spl_object_id($document)] ?? $this->index);
    }

    /**
     * Sorts nodes into document order and removes duplicates; nodes of different documents are
     * grouped by document, the evaluated document first.
     *
     * @param  list<DOMNode>  $nodes
     * @return list<DOMNode>
     */
    private function sort(array $nodes): array
    {
        if ($this->documents === [] || $nodes === []) {
            return $this->index->sort($nodes);
        }
        $groups = [];
        foreach ($nodes as $node) {
            $groups[spl_object_id($this->indexOf($node)->document)][] = $node;
        }
        if (count($groups) === 1) {
            return $this->indexOf($nodes[0])->sort($nodes);
        }
        $sorted = $this->index->sort($groups[spl_object_id($this->index->document)] ?? []);
        foreach ($this->documents as $id => $index) {
            if (isset($groups[$id])) {
                $sorted = [...$sorted, ...$index->sort($groups[$id])];
            }
        }

        return $sorted;
    }

    /**
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private static function variable(string $name, array $variables): array
    {
        $value = $variables[$name] ?? throw new XPathError('XPST0008', "The variable \${$name} is not bound.");

        return $value instanceof Thunk ? $value->value() : $value;
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function callBuiltIn(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        if ($ast[1] === 'fn:last') {
            return [new IntegerValue(BigDecimal::of($this->size ?? throw new UnsupportedXPath('fn:last() outside a predicate or a path step', 'last()', 0)))];
        }
        if ($ast[1] === 'fn:document') {
            return [$this->document(Functions::requiredString($this->eval(self::subList($ast, 2)[0], $item, $variables), 'fn:document'))];
        }
        $arguments = [];
        foreach (self::subList($ast, 2) as $argument) {
            $arguments[] = $this->eval($argument, $item, $variables);
        }

        return Functions::call(self::text($ast, 1), $arguments, $item);
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<BooleanValue>
     */
    private function generalComparison(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        return [new BooleanValue(Atomic::generalCompare(
            self::text($ast, 1),
            Functions::data($this->eval(self::sub($ast, 2), $item, $variables)),
            Functions::data($this->eval(self::sub($ast, 3), $item, $variables)),
        ))];
    }

    /**
     * A value comparison or an arithmetic operation: both operands are single atomic values, and an
     * empty operand gives the empty sequence.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<AtomicValue>
     */
    private function binary(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $left = $this->singleAtomic(self::sub($ast, 2), $item, $variables);
        $right = $this->singleAtomic(self::sub($ast, 3), $item, $variables);
        if ($left === null || $right === null) {
            return [];
        }

        return $ast[0] === Op::VCMP
            ? [new BooleanValue(Atomic::valueCompare(self::text($ast, 1), $left, $right))]
            : [Atomic::arithmetic(self::text($ast, 1), $left, $right)];
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<AtomicValue>
     */
    private function negation(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $operand = $this->singleAtomic(self::sub($ast, 1), $item, $variables);

        return $operand === null ? [] : [self::negate($operand)];
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<AtomicValue>
     */
    private function cast(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $operand = $this->singleAtomic(self::sub($ast, 3), $item, $variables);
        if ($operand === null) {
            if ($ast[2] === true) {
                return [];
            }

            throw new XPathError('XPTY0004', 'An empty sequence cannot be cast to '.self::text($ast, 1).'.');
        }

        return [Atomic::cast($operand, self::text($ast, 1))];
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function sequence(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $result = [];
        foreach (self::subList($ast, 1) as $member) {
            array_push($result, ...$this->eval($member, $item, $variables));
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $ast
     */
    private static function unknownNode(array $ast): LogicException
    {
        return new LogicException('Unknown AST node '.(is_string($ast[0] ?? null) ? $ast[0] : '?').'.');
    }

    /**
     * Calls a user function: the arguments are evaluated first, in order, then the body runs.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function callFunction(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $argumentAsts = self::subList($ast, 3);
        $key = StaticContext::functionKey(self::text($ast, 1), self::text($ast, 2), count($argumentAsts));
        $function = $this->functions[$key] ?? throw new UnsupportedXPath("user function {$key} is not available", $key, 0);

        $arguments = [];
        foreach ($argumentAsts as $argument) {
            $arguments[] = $this->eval($argument, $item, $variables);
        }

        if ($this->depth >= self::MAX_DEPTH) {
            throw $this->recursionError ?? throw new LogicException('A function table without its recursion error.');
        }
        $this->depth++;
        try {
            return $function->call($this, $arguments, $this->globals, $this->index->document);
        } finally {
            $this->depth--;
        }
    }

    /**
     * @param  array<mixed>  $lhs
     * @param  array<mixed>  $rhs
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function path(array $lhs, array $rhs, DOMNode|AtomicValue $item, array $variables): array
    {
        $left = $this->eval($lhs, $item, $variables);
        $descendants = self::descendantSelectors($rhs);

        $nodes = [];
        $atomics = [];
        $outerSize = $this->size;
        $size = count($left);
        foreach ($left as $context) {
            if (! $context instanceof DOMNode) {
                throw new XPathError('XPTY0019', 'The left operand of "/" contains an atomic value.');
            }

            if ($descendants !== null) {
                foreach ($descendants as [$uri, $local, $predicates]) {
                    foreach ($this->indexOf($context)->descendantsOfNamed($context, $uri, $local) as $candidate) {
                        if ($this->allPredicatesHold($candidate, $predicates, $variables)) {
                            $nodes[] = $candidate;
                        }
                    }
                }

                continue;
            }

            $this->size = $size;
            try {
                $results = $this->eval($rhs, $context, $variables);
            } finally {
                $this->size = $outerSize;
            }
            foreach ($results as $result) {
                if ($result instanceof DOMNode) {
                    $nodes[] = $result;
                } else {
                    $atomics[] = $result;
                }
            }
        }

        if ($nodes !== [] && $atomics !== []) {
            throw new XPathError('XPTY0018', 'The result of a path expression mixes nodes and atomic values.');
        }

        return $atomics !== [] ? $atomics : $this->sort($nodes);
    }

    /**
     * Evaluates "or" ($decisive true) or "and" ($decisive false) as Saxon does: an operand that
     * yields $decisive decides the result even when the other operand raises a dynamic error; the
     * error is raised only when the result stays open, and when both operands fail the error of
     * the right operand wins. XPath 2.0 (section 3.6) leaves this choice to the processor.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<BooleanValue>
     */
    private function logical(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        return [new BooleanValue($this->decide($ast[0] === Op::OR, $ast, $item, $variables))];
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function decide(bool $decisive, array $ast, DOMNode|AtomicValue $item, array $variables): bool
    {
        try {
            $left = Functions::boolean($this->eval(self::sub($ast, 1), $item, $variables));
        } catch (XPathError $error) {
            $left = $error;
        }
        if ($left === $decisive) {
            return $decisive;
        }

        if (Functions::boolean($this->eval(self::sub($ast, 2), $item, $variables)) === $decisive) {
            return $decisive;
        }
        if ($left instanceof XPathError) {
            throw $left;
        }

        return ! $decisive;
    }

    /**
     * Recognises "//name" (descendant-or-self::node()/child::name, or a union of such child steps)
     * whose predicates do not depend on the position, which the name index answers directly.
     *
     * @param  array<mixed>  $rhs
     * @return list<array{string, string, list<array<mixed>>}>|null
     */
    private static function descendantSelectors(array $rhs): ?array
    {
        if ($rhs[0] !== Op::PATH || $rhs[1] !== [Op::STEP, 'descendant-or-self', ['node'], []]) {
            return null;
        }

        return self::childNameSelectors(self::sub($rhs, 2));
    }

    /**
     * @param  array<mixed>  $ast
     * @return list<array{string, string, list<array<mixed>>}>|null
     */
    private static function childNameSelectors(array $ast): ?array
    {
        if ($ast[0] === Op::UNION) {
            $left = self::childNameSelectors(self::sub($ast, 1));
            $right = self::childNameSelectors(self::sub($ast, 2));

            return $left === null || $right === null ? null : [...$left, ...$right];
        }

        if ($ast[0] !== Op::STEP || $ast[1] !== 'child') {
            return null;
        }
        $test = self::sub($ast, 2);
        if ($test[0] !== 'name') {
            return null;
        }
        $predicates = self::subList($ast, 3);
        foreach ($predicates as $predicate) {
            if (! self::isPositionFree($predicate)) {
                return null;
            }
        }

        return [[self::text($test, 1), self::text($test, 2), $predicates]];
    }

    /**
     * Whether a predicate is known to yield a boolean or nodes and does not call fn:last, so its
     * outcome never depends on the position of the context item or the context size.
     *
     * @param  array<mixed>  $predicate
     */
    private static function isPositionFree(array $predicate): bool
    {
        if (self::callsLast($predicate)) {
            return false;
        }

        return match ($predicate[0]) {
            Op::GCMP, Op::VCMP, Op::AND, Op::OR, Op::QUANT, Op::CASTABLE, Op::STEP => true,
            Op::CALL => in_array($predicate[1], self::BOOLEAN_FUNCTIONS, true),
            Op::PATH => self::isPositionFree(self::sub($predicate, 2)),
            default => false,
        };
    }

    /**
     * Whether an AST calls fn:last anywhere, nested predicates included.
     *
     * @param  array<mixed>  $ast
     */
    private static function callsLast(array $ast): bool
    {
        if ($ast !== [] && $ast[0] === Op::CALL && ($ast[1] ?? null) === 'fn:last') {
            return true;
        }
        foreach ($ast as $value) {
            if (is_array($value) && self::callsLast($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $test
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function step(string $axis, array $test, array $predicates, DOMNode|AtomicValue $item, array $variables): array
    {
        if (! $item instanceof DOMNode) {
            throw new XPathError('XPTY0020', "The context item of the {$axis} axis is not a node.");
        }
        if ($axis === 'child' && $test[0] === 'name' && count($predicates) === 1 && $this->documents !== [] && ! $this->matchingPattern) {
            $indexed = $this->indexedChildren($item, self::text($test, 1), self::text($test, 2), $predicates[0], $variables);
            if ($indexed !== null) {
                return $indexed;
            }
        }

        $candidates = $this->axis($axis, $test, $item);
        $selected = [];
        foreach ($candidates as $candidate) {
            if (self::matches($candidate, $test, $axis)) {
                $selected[] = $candidate;
            }
        }

        if ($predicates !== []) {
            $selected = $this->applyPredicates($selected, $predicates, $variables);
        }

        return in_array($axis, self::REVERSE_AXES, true) ? array_reverse($selected) : $selected;
    }

    /**
     * The result of a step "name[@attribute = value]" from a node of an embedded document (code
     * lists read through fn:document), looked up in the attribute index of that document instead
     * of testing the predicate on every child. The value must not depend on the focus; it is
     * evaluated once, and only when there is at least one child of that name, as the predicate
     * would be. Returns null, so the step is evaluated as usual, for any other shape, for a node
     * of the evaluated document, and for values whose comparison the index cannot reproduce:
     * values other than strings, untyped values or numbers, strings mixed with numbers, and
     * numbers when an attribute value does not cast to xs:double (the predicate raises FORG0001).
     *
     * @param  array<mixed>  $predicate
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode>|null
     */
    private function indexedChildren(DOMNode $parent, string $uri, string $local, array $predicate, array $variables): ?array
    {
        if ($predicate[0] !== Op::GCMP || $predicate[1] !== '=') {
            return null;
        }
        $index = $this->indexOf($parent);
        if ($index === $this->index) {
            return null;
        }
        $left = self::sub($predicate, 2);
        $right = self::sub($predicate, 3);
        [$attribute, $operand] = self::isAttributeStep($left) ? [$left, $right] : (self::isAttributeStep($right) ? [$right, $left] : [null, null]);
        if ($attribute === null || $operand === null || ! self::isFocusFree($operand)) {
            return null;
        }
        $name = self::sub($attribute, 2);
        [$count, $byString, $byDouble] = $index->childrenByAttribute($parent, $uri, $local, self::text($name, 1), self::text($name, 2));
        if ($count === 0) {
            return [];
        }

        $strings = [];
        $doubles = [];
        foreach (Functions::data($this->eval($operand, $parent, $variables)) as $value) {
            if ($value instanceof StringValue || $value instanceof UntypedAtomic) {
                $strings[] = $value->value;
            } elseif (($value instanceof IntegerValue || $value instanceof DecimalValue || $value instanceof DoubleValue) && ($double = Atomic::cast($value, 'xs:double')) instanceof DoubleValue) {
                $doubles[] = $double->value;
            } else {
                return null;
            }
        }
        if (($strings !== [] && $doubles !== []) || ($doubles !== [] && $byDouble === null)) {
            return null;
        }

        $selected = [];
        foreach ($strings as $string) {
            array_push($selected, ...$byString[$string] ?? []);
        }
        foreach ($doubles as $double) {
            array_push($selected, ...$byDouble[DocumentIndex::doubleKey($double)] ?? []);
        }

        return count($strings) + count($doubles) > 1 ? $index->sort($selected) : $selected;
    }

    /**
     * Whether an AST is an attribute step with a name test and no predicates, such as "@id".
     *
     * @param  array<mixed>  $ast
     */
    private static function isAttributeStep(array $ast): bool
    {
        return ($ast[0] ?? null) === Op::STEP && ($ast[1] ?? null) === 'attribute' && is_array($ast[2] ?? null) && ($ast[2][0] ?? null) === 'name' && ($ast[3] ?? null) === [];
    }

    /**
     * Nodes on an axis in axis order (reverse axes nearest first). Name tests may already be applied.
     *
     * @param  array<mixed>  $test
     * @return list<DOMNode>
     */
    private function axis(string $axis, array $test, DOMNode $node): array
    {
        switch ($axis) {
            case 'child':
                $children = [];
                foreach ($node->childNodes as $child) {
                    if (! $child instanceof DOMDocumentType) {
                        $children[] = $child;
                    }
                }

                return $children;
            case 'attribute':
                $attributes = [];
                if ($node instanceof DOMElement) {
                    foreach ($node->attributes as $attribute) {
                        $attributes[] = $attribute;
                    }
                }

                return $attributes;
            case 'self':
                return [$node];
            case 'parent':
                $parent = DocumentIndex::parent($node);

                return $parent === null ? [] : [$parent];
            case 'ancestor':
            case 'ancestor-or-self':
                $ancestors = $axis === 'ancestor-or-self' ? [$node] : [];
                for ($parent = DocumentIndex::parent($node); $parent !== null; $parent = DocumentIndex::parent($parent)) {
                    $ancestors[] = $parent;
                }

                return $ancestors;
            case 'descendant':
            case 'descendant-or-self':
                if ($test[0] === 'name' && ! $node instanceof DOMAttr) {
                    $named = $this->indexOf($node)->descendantsOfNamed($node, self::text($test, 1), self::text($test, 2));

                    return $axis === 'descendant-or-self' && self::matches($node, $test, $axis) ? [$node, ...$named] : $named;
                }
                if ($node instanceof DOMAttr) {
                    return $axis === 'descendant-or-self' ? [$node] : [];
                }
                $descendants = $this->indexOf($node)->descendantsOrSelf($node);

                return $axis === 'descendant' ? array_slice($descendants, 1) : $descendants;
            case 'following-sibling':
            case 'preceding-sibling':
                $siblings = [];
                if ($node instanceof DOMAttr) {
                    return $siblings;
                }
                $following = $axis === 'following-sibling';
                for ($sibling = $following ? $node->nextSibling : $node->previousSibling; $sibling !== null; $sibling = $following ? $sibling->nextSibling : $sibling->previousSibling) {
                    if (! $sibling instanceof DOMDocumentType) {
                        $siblings[] = $sibling;
                    }
                }

                return $siblings;
            case 'preceding':
                return $test[0] === 'name'
                    ? $this->indexOf($node)->precedingNamed($node, self::text($test, 1), self::text($test, 2))
                    : $this->indexOf($node)->preceding($node);
        }

        throw new LogicException("Unknown axis {$axis}.");
    }

    /**
     * @param  array<mixed>  $test
     */
    private static function matches(DOMNode $node, array $test, string $axis): bool
    {
        if ($test[0] === 'node') {
            return true;
        }
        if ($test[0] === 'text') {
            return $node instanceof DOMText;
        }

        // The principal node kind is attribute on the attribute axis and element elsewhere.
        if (! ($axis === 'attribute' ? $node instanceof DOMAttr : $node instanceof DOMElement)) {
            return false;
        }

        return match ($test[0]) {
            'any-name' => true,
            'ns-any' => ($node->namespaceURI ?? '') === $test[1],
            'name' => ($node->namespaceURI ?? '') === $test[1] && ($node->localName ?? $node->nodeName) === $test[2],
            default => throw new LogicException('Unknown node test.'),
        };
    }

    /**
     * Applies predicates left to right; a numeric predicate value selects by position, and fn:last
     * is the number of items the predicate filters.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function applyPredicates(array $items, array $predicates, array $variables): array
    {
        foreach ($predicates as $predicate) {
            if ($predicate[0] === Op::LITERAL && $predicate[1] === 'xs:integer') {
                $index = (int) self::text($predicate, 2) - 1;
                $items = isset($items[$index]) ? [$items[$index]] : [];

                continue;
            }
            if ($items !== [] && ! $this->matchingPattern && self::isFocusFree($predicate)) {
                $items = $this->applyFocusFreePredicate($items, $predicate, $variables);

                continue;
            }

            $outerSize = $this->size;
            $this->size = count($items);
            try {
                $kept = [];
                foreach ($items as $position => $item) {
                    if ($this->keeps($predicate, $item, $position + 1, $variables)) {
                        $kept[] = $item;
                    }
                }
            } finally {
                $this->size = $outerSize;
            }
            $items = $kept;
        }

        return $items;
    }

    /**
     * A predicate that does not depend on the focus has the same value for every item, so it is
     * evaluated once: a number selects the item at that position directly, anything else keeps
     * all items or none. This keeps "$sequence[$i]" inside a loop linear instead of quadratic.
     *
     * @param  non-empty-list<DOMNode|AtomicValue>  $items
     * @param  array<mixed>  $predicate
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function applyFocusFreePredicate(array $items, array $predicate, array $variables): array
    {
        $value = $this->eval($predicate, $items[0], $variables);
        $number = count($value) === 1 ? $value[0] : null;
        if (! $number instanceof IntegerValue && ! $number instanceof DecimalValue && ! $number instanceof DoubleValue) {
            return Functions::boolean($value) ? $items : [];
        }

        if ($number instanceof IntegerValue) {
            // An xs:integer has no fraction, so a position of up to nine digits converts exactly.
            $digits = (string) $number->value;
            $position = strlen($digits) <= 9 ? (int) $digits : 0;

            return $position >= 1 && isset($items[$position - 1]) ? [$items[$position - 1]] : [];
        }
        if ($number instanceof DoubleValue) {
            $candidate = is_finite($number->value) && $number->value >= 1 && $number->value <= count($items) ? (int) floor($number->value) : null;
        } else {
            $candidate = $number->value->isGreaterThanOrEqualTo(1) && $number->value->isLessThanOrEqualTo(count($items))
                ? $number->value->toScale(0, RoundingMode::Down)->toInt()
                : null;
        }

        return $candidate !== null && Atomic::valueCompare('eq', $number, new IntegerValue(BigDecimal::of($candidate)))
            ? [$items[$candidate - 1]]
            : [];
    }

    /**
     * Whether an expression is built only from literals, variable references and arithmetic, so
     * its value cannot depend on the context item, position or size.
     *
     * @param  array<mixed>  $ast
     */
    private static function isFocusFree(array $ast): bool
    {
        return match ($ast[0] ?? null) {
            Op::LITERAL, Op::VAR => true,
            Op::NEG => is_array($ast[1] ?? null) && self::isFocusFree($ast[1]),
            Op::ARITH => is_array($ast[2] ?? null) && is_array($ast[3] ?? null) && self::isFocusFree($ast[2]) && self::isFocusFree($ast[3]),
            default => false,
        };
    }

    /**
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function allPredicatesHold(DOMNode $node, array $predicates, array $variables): bool
    {
        foreach ($predicates as $predicate) {
            if (! $this->keeps($predicate, $node, null, $variables)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a predicate keeps an item: a numeric value compares with the position (null for a
     * predicate known to be position-free), anything else by its effective boolean value. Within
     * a match pattern a recoverable dynamic error (see XPathError::RECOVERABLE) drops the item
     * instead of propagating; any other error, and an engine limitation, always propagates.
     *
     * @param  array<mixed>  $predicate
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function keeps(array $predicate, DOMNode|AtomicValue $item, ?int $position, array $variables): bool
    {
        if (! $this->matchingPattern) {
            return $this->predicateHolds($predicate, $item, $position, $variables);
        }

        $this->matchingPattern = false;
        try {
            return $this->predicateHolds($predicate, $item, $position, $variables);
        } catch (XPathError $error) {
            if (! $error->isRecoverable()) {
                throw $error;
            }

            return false;
        } finally {
            $this->matchingPattern = true;
        }
    }

    /**
     * @param  array<mixed>  $predicate
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function predicateHolds(array $predicate, DOMNode|AtomicValue $item, ?int $position, array $variables): bool
    {
        $value = $this->eval($predicate, $item, $variables);
        $number = $position !== null && count($value) === 1 ? $value[0] : null;
        if ($number instanceof IntegerValue || $number instanceof DecimalValue || $number instanceof DoubleValue) {
            return Atomic::valueCompare('eq', $number, new IntegerValue(BigDecimal::of($position)));
        }

        return Functions::boolean($value);
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode>
     */
    private function union(array $ast, DOMNode|AtomicValue $context, array $variables): array
    {
        $left = $this->eval(self::sub($ast, 1), $context, $variables);
        $right = $this->eval(self::sub($ast, 2), $context, $variables);
        $nodes = [];
        foreach ([...$left, ...$right] as $item) {
            if (! $item instanceof DOMNode) {
                throw new XPathError('XPTY0004', 'An operand of a union contains an atomic value.');
            }
            $nodes[] = $item;
        }

        return $this->sort($nodes);
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<BooleanValue>
     */
    private function quantifiedExpression(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        return [new BooleanValue($this->quantified(self::text($ast, 1) === 'some', self::subList($ast, 2), self::sub($ast, 3), $item, $variables))];
    }

    /**
     * "if (c) then a else b": only the chosen branch is evaluated.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function conditional(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $branch = Functions::boolean($this->eval(self::sub($ast, 1), $item, $variables)) ? 2 : 3;

        return $this->eval(self::sub($ast, $branch), $item, $variables);
    }

    /**
     * @param  list<array<mixed>>  $bindings
     * @param  array<mixed>  $satisfies
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function quantified(bool $some, array $bindings, array $satisfies, DOMNode|AtomicValue $item, array $variables): bool
    {
        if ($bindings === []) {
            return Functions::boolean($this->eval($satisfies, $item, $variables));
        }

        $binding = array_shift($bindings);
        $name = self::text($binding, 0);
        foreach ($this->eval(self::sub($binding, 1), $item, $variables) as $value) {
            $holds = $this->quantified($some, $bindings, $satisfies, $item, [...$variables, $name => [$value]]);
            if ($holds === $some) {
                return $some;
            }
        }

        return ! $some;
    }

    /**
     * Evaluates the bindings of a "for" expression as nested loops, left to right, and concatenates
     * the results in iteration order.
     *
     * @param  list<array<mixed>>  $bindings
     * @param  array<mixed>  $return
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function forLoop(array $bindings, array $return, DOMNode|AtomicValue $item, array $variables): array
    {
        if ($bindings === []) {
            return $this->eval($return, $item, $variables);
        }

        $binding = array_shift($bindings);
        $name = self::text($binding, 0);
        $result = [];
        foreach ($this->eval(self::sub($binding, 1), $item, $variables) as $value) {
            $variables[$name] = [$value];
            // The innermost binding evaluates the return expression directly: one call less per
            // item, which matters in the long loops of check-digit functions.
            foreach ($bindings === [] ? $this->eval($return, $item, $variables) : $this->forLoop($bindings, $return, $item, $variables) as $produced) {
                $result[] = $produced;
            }
        }

        return $result;
    }

    /**
     * "l to r": both operands are atomized to at most one xs:integer (an untyped value is cast, any
     * other type is XPTY0004); an empty operand or l > r gives the empty sequence.
     *
     * @param  array<mixed>  $lhs
     * @param  array<mixed>  $rhs
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<IntegerValue>
     */
    private function range(array $lhs, array $rhs, DOMNode|AtomicValue $item, array $variables): array
    {
        $low = self::rangeBound($this->singleAtomic($lhs, $item, $variables));
        $high = self::rangeBound($this->singleAtomic($rhs, $item, $variables));
        if ($low === null || $high === null || $low->isGreaterThan($high)) {
            return [];
        }
        if ($high->minus($low)->isGreaterThanOrEqualTo(self::MAX_RANGE)) {
            throw new XPathError('INTRAGILE_RANGE', sprintf('The range %s to %s has more than %d items.', $low, $high, self::MAX_RANGE));
        }

        $result = [];
        for ($offset = 0, $count = $high->minus($low)->toInt(); $offset <= $count; $offset++) {
            $result[] = new IntegerValue($low->plus($offset));
        }

        return $result;
    }

    private static function rangeBound(?AtomicValue $value): ?BigDecimal
    {
        if ($value instanceof UntypedAtomic) {
            $value = Atomic::cast($value, 'xs:integer');
        }

        return match (true) {
            $value === null => null,
            $value instanceof IntegerValue => $value->value,
            default => throw new XPathError('XPTY0004', "An operand of \"to\" must be an xs:integer, not {$value->type()}."),
        };
    }

    /**
     * "castable as": whether the atomized operand can be cast to the type. An empty operand is
     * castable only with "?"; more than one item is never castable (false, as in Saxon, rather
     * than a type error). Errors raised while evaluating the operand itself still propagate.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<BooleanValue>
     */
    private function castable(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        $values = Functions::data($this->eval(self::sub($ast, 3), $item, $variables));
        if (count($values) !== 1) {
            return [new BooleanValue($values === [] && $ast[2] === true)];
        }

        try {
            Atomic::cast($values[0], self::text($ast, 1));

            return [new BooleanValue(true)];
        } catch (XPathError) {
            return [new BooleanValue(false)];
        }
    }

    /**
     * Atomizes an operand that must hold at most one item.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     */
    private function singleAtomic(array $ast, DOMNode|AtomicValue $item, array $variables): ?AtomicValue
    {
        $values = Functions::data($this->eval($ast, $item, $variables));
        if (count($values) > 1) {
            throw new XPathError('XPTY0004', 'A sequence of more than one item is not allowed as an operand.');
        }

        return $values[0] ?? null;
    }

    private static function negate(AtomicValue $operand): AtomicValue
    {
        if ($operand instanceof UntypedAtomic) {
            $operand = Atomic::cast($operand, 'xs:double');
        }

        return match (true) {
            $operand instanceof IntegerValue => new IntegerValue($operand->value->negated()),
            $operand instanceof DecimalValue => new DecimalValue($operand->value->negated()),
            $operand instanceof DoubleValue => new DoubleValue(-$operand->value),
            default => throw new XPathError('XPTY0004', "Unary minus is not defined for {$operand->type()}."),
        };
    }

    private function literal(string $type, string $lexical): AtomicValue
    {
        return $this->literals[$type.' '.$lexical] ??= match ($type) {
            'xs:string' => new StringValue($lexical),
            'xs:integer' => new IntegerValue(BigDecimal::of($lexical)),
            default => Atomic::cast(new StringValue($lexical), $type),
        };
    }

    /**
     * @param  array<mixed>  $ast
     * @return array<mixed>
     */
    private static function sub(array $ast, int $index): array
    {
        $value = $ast[$index] ?? null;

        return is_array($value) ? $value : throw new LogicException('Malformed AST.');
    }

    /**
     * @param  array<mixed>  $ast
     * @return list<array<mixed>>
     */
    private static function subList(array $ast, int $index): array
    {
        $list = [];
        foreach (self::sub($ast, $index) as $value) {
            $list[] = is_array($value) ? $value : throw new LogicException('Malformed AST.');
        }

        return $list;
    }

    /**
     * @param  array<mixed>  $ast
     */
    private static function text(array $ast, int $index): string
    {
        $value = $ast[$index] ?? null;

        return is_string($value) ? $value : throw new LogicException('Malformed AST.');
    }
}

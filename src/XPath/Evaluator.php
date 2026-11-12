<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use DOMAttr;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMText;
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
 */
final class Evaluator
{
    private const REVERSE_AXES = ['ancestor', 'ancestor-or-self', 'parent', 'preceding'];

    /** Function calls whose result is always a boolean, so a predicate using them is never positional. */
    private const BOOLEAN_FUNCTIONS = ['fn:boolean', 'fn:contains', 'fn:empty', 'fn:ends-with', 'fn:exists', 'fn:false', 'fn:matches', 'fn:not', 'fn:true'];

    /** @var array<string, AtomicValue> */
    private array $literals = [];

    /** True while the steps of a match pattern are evaluated, outside their predicates. */
    private bool $matchingPattern = false;

    public function __construct(private readonly DocumentIndex $index) {}

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public function evaluate(array $ast, DOMNode $context, array $variables = []): array
    {
        return $this->eval($ast, $context, $variables);
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
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError when the pattern fails outside its step predicates, or with a
     *                    non-recoverable error inside one
     */
    public function matchPattern(array $ast, DOMNode $context, array $variables = []): array
    {
        $this->matchingPattern = true;
        try {
            return $this->eval($ast, $context, $variables);
        } finally {
            $this->matchingPattern = false;
        }
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     *
     * @throws XPathError
     */
    public function effectiveBoolean(array $ast, DOMNode $context, array $variables = []): bool
    {
        return Functions::boolean($this->eval($ast, $context, $variables));
    }

    /**
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function eval(array $ast, DOMNode|AtomicValue $item, array $variables): array
    {
        switch ($ast[0]) {
            case Op::LITERAL:
                return [$this->literal(self::text($ast, 1), self::text($ast, 2))];
            case Op::CONTEXT:
                return [$item];
            case Op::ROOT:
                if (! $item instanceof DOMNode) {
                    throw new XPathError('XPDY0050', 'The context item for "/" is not a node.');
                }

                return [$item instanceof DOMDocument ? $item : $this->index->document];
            case Op::PATH:
                return $this->path(self::sub($ast, 1), self::sub($ast, 2), $item, $variables);
            case Op::STEP:
                return $this->step(self::text($ast, 1), self::sub($ast, 2), self::subList($ast, 3), $item, $variables);
            case Op::FILTER:
                return $this->applyPredicates($this->eval(self::sub($ast, 1), $item, $variables), self::subList($ast, 2), $variables);
            case Op::VAR:
                $name = self::text($ast, 1);

                return $variables[$name] ?? throw new XPathError('XPST0008', "The variable \${$name} is not bound.");
            case Op::CALL:
                $arguments = [];
                foreach (self::subList($ast, 2) as $argument) {
                    $arguments[] = $this->eval($argument, $item, $variables);
                }

                return Functions::call(self::text($ast, 1), $arguments, $item);
            case Op::OR:
                return [new BooleanValue($this->logical(true, $ast, $item, $variables))];
            case Op::AND:
                return [new BooleanValue($this->logical(false, $ast, $item, $variables))];
            case Op::GCMP:
                return [new BooleanValue(Atomic::generalCompare(
                    self::text($ast, 1),
                    Functions::data($this->eval(self::sub($ast, 2), $item, $variables)),
                    Functions::data($this->eval(self::sub($ast, 3), $item, $variables)),
                ))];
            case Op::VCMP:
                $left = $this->singleAtomic(self::sub($ast, 2), $item, $variables);
                $right = $this->singleAtomic(self::sub($ast, 3), $item, $variables);

                return $left === null || $right === null ? [] : [new BooleanValue(Atomic::valueCompare(self::text($ast, 1), $left, $right))];
            case Op::ARITH:
                $left = $this->singleAtomic(self::sub($ast, 2), $item, $variables);
                $right = $this->singleAtomic(self::sub($ast, 3), $item, $variables);

                return $left === null || $right === null ? [] : [Atomic::arithmetic(self::text($ast, 1), $left, $right)];
            case Op::NEG:
                $operand = $this->singleAtomic(self::sub($ast, 1), $item, $variables);

                return $operand === null ? [] : [self::negate($operand)];
            case Op::UNION:
                return $this->union($this->eval(self::sub($ast, 1), $item, $variables), $this->eval(self::sub($ast, 2), $item, $variables));
            case Op::QUANT:
                return [new BooleanValue($this->quantified(self::text($ast, 1) === 'some', self::subList($ast, 2), self::sub($ast, 3), $item, $variables))];
            case Op::CAST:
                $operand = $this->singleAtomic(self::sub($ast, 3), $item, $variables);
                if ($operand === null) {
                    if ($ast[2] === true) {
                        return [];
                    }

                    throw new XPathError('XPTY0004', 'An empty sequence cannot be cast to '.self::text($ast, 1).'.');
                }

                return [Atomic::cast($operand, self::text($ast, 1))];
            case Op::SEQUENCE:
                $result = [];
                foreach (self::subList($ast, 1) as $member) {
                    array_push($result, ...$this->eval($member, $item, $variables));
                }

                return $result;
        }

        throw new LogicException('Unknown AST node '.(is_string($ast[0]) ? $ast[0] : '?').'.');
    }

    /**
     * @param  array<mixed>  $lhs
     * @param  array<mixed>  $rhs
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function path(array $lhs, array $rhs, DOMNode|AtomicValue $item, array $variables): array
    {
        $left = $this->eval($lhs, $item, $variables);
        $descendants = self::descendantSelectors($rhs);

        $nodes = [];
        $atomics = [];
        foreach ($left as $context) {
            if (! $context instanceof DOMNode) {
                throw new XPathError('XPTY0019', 'The left operand of "/" contains an atomic value.');
            }

            if ($descendants !== null) {
                foreach ($descendants as [$uri, $local, $predicates]) {
                    foreach ($this->index->descendantsOfNamed($context, $uri, $local) as $candidate) {
                        if ($this->allPredicatesHold($candidate, $predicates, $variables)) {
                            $nodes[] = $candidate;
                        }
                    }
                }

                continue;
            }

            foreach ($this->eval($rhs, $context, $variables) as $result) {
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

        return $atomics !== [] ? $atomics : $this->index->sort($nodes);
    }

    /**
     * Evaluates "or" ($decisive true) or "and" ($decisive false) as Saxon does: an operand that
     * yields $decisive decides the result even when the other operand raises a dynamic error; the
     * error is raised only when the result stays open, and when both operands fail the error of
     * the right operand wins. XPath 2.0 (section 3.6) leaves this choice to the processor.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     */
    private function logical(bool $decisive, array $ast, DOMNode|AtomicValue $item, array $variables): bool
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
     * Whether a predicate is known to yield a boolean or nodes, so its outcome never depends on the
     * position of the context item.
     *
     * @param  array<mixed>  $predicate
     */
    private static function isPositionFree(array $predicate): bool
    {
        return match ($predicate[0]) {
            Op::GCMP, Op::VCMP, Op::AND, Op::OR, Op::QUANT, Op::STEP => true,
            Op::CALL => in_array($predicate[1], self::BOOLEAN_FUNCTIONS, true),
            Op::PATH => self::isPositionFree(self::sub($predicate, 2)),
            default => false,
        };
    }

    /**
     * @param  array<mixed>  $test
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function step(string $axis, array $test, array $predicates, DOMNode|AtomicValue $item, array $variables): array
    {
        if (! $item instanceof DOMNode) {
            throw new XPathError('XPTY0020', "The context item of the {$axis} axis is not a node.");
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
                    $named = $this->index->descendantsOfNamed($node, self::text($test, 1), self::text($test, 2));

                    return $axis === 'descendant-or-self' && self::matches($node, $test, $axis) ? [$node, ...$named] : $named;
                }
                if ($node instanceof DOMAttr) {
                    return $axis === 'descendant-or-self' ? [$node] : [];
                }
                $descendants = $this->index->descendantsOrSelf($node);

                return $axis === 'descendant' ? array_slice($descendants, 1) : $descendants;
            case 'preceding':
                return $test[0] === 'name'
                    ? $this->index->precedingNamed($node, self::text($test, 1), self::text($test, 2))
                    : $this->index->preceding($node);
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
     * Applies predicates left to right; a numeric predicate value selects by position.
     *
     * @param  list<DOMNode|AtomicValue>  $items
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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

            $kept = [];
            foreach ($items as $position => $item) {
                if ($this->keeps($predicate, $item, $position + 1, $variables)) {
                    $kept[] = $item;
                }
            }
            $items = $kept;
        }

        return $items;
    }

    /**
     * @param  list<array<mixed>>  $predicates
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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
     * @param  list<DOMNode|AtomicValue>  $left
     * @param  list<DOMNode|AtomicValue>  $right
     * @return list<DOMNode>
     */
    private function union(array $left, array $right): array
    {
        $nodes = [];
        foreach ([...$left, ...$right] as $item) {
            if (! $item instanceof DOMNode) {
                throw new XPathError('XPTY0004', 'An operand of a union contains an atomic value.');
            }
            $nodes[] = $item;
        }

        return $this->index->sort($nodes);
    }

    /**
     * @param  list<array<mixed>>  $bindings
     * @param  array<mixed>  $satisfies
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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
     * Atomizes an operand that must hold at most one item.
     *
     * @param  array<mixed>  $ast
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
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

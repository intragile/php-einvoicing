<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

/**
 * Node kinds of the array AST produced by the parser. Every node is a list whose first element is
 * one of these constants, followed by its operands.
 *
 * @internal
 */
final class Op
{
    /** [LITERAL, type, lexical] with type one of xs:string, xs:integer, xs:decimal, xs:double. */
    public const LITERAL = 'literal';

    /** [CONTEXT] — the context item ("."). */
    public const CONTEXT = 'context';

    /** [ROOT] — the root of the tree containing the context node ("/"). */
    public const ROOT = 'root';

    /** [PATH, lhs, rhs] — evaluates rhs for every node selected by lhs. */
    public const PATH = 'path';

    /** [STEP, axis, test, predicates] with test ['name', uri, local] | ['any-name'] | ['ns-any', uri] | ['node'] | ['text']. */
    public const STEP = 'step';

    /** [FILTER, primary, predicates]. */
    public const FILTER = 'filter';

    /** [VAR, name]. */
    public const VAR = 'var';

    /** [CALL, function, args] with a canonical function name such as fn:count or xs:decimal. */
    public const CALL = 'call';

    /** [OR, l, r]. */
    public const OR = 'or';

    /** [AND, l, r]. */
    public const AND = 'and';

    /** [GCMP, op, l, r] — general comparison (= != < <= > >=). */
    public const GCMP = 'gcmp';

    /** [VCMP, op, l, r] — value comparison (eq ne lt le gt ge). */
    public const VCMP = 'vcmp';

    /** [ARITH, op, l, r] with op one of + - * div idiv mod. */
    public const ARITH = 'arith';

    /** [NEG, operand] — unary minus. */
    public const NEG = 'neg';

    /** [UNION, l, r]. */
    public const UNION = 'union';

    /** [QUANT, 'some'|'every', [[var, expr], ...], satisfies]. */
    public const QUANT = 'quant';

    /** [CAST, type, allowEmpty, operand]. */
    public const CAST = 'cast';

    /** [SEQUENCE, items] — "()" and comma-separated expressions. */
    public const SEQUENCE = 'sequence';
}

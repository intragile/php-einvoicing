<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;

/**
 * Recursive-descent parser for the XPath 2.0 subset used by the CEN EN 16931 rules. It produces an
 * array AST (see Op) with namespace prefixes already resolved to URIs. Constructs outside the subset
 * raise UnsupportedXPath instead of being approximated.
 */
final class Parser
{
    /** Supported functions by canonical name, with their minimum and maximum (null: unbounded) arity. */
    private const FUNCTIONS = [
        'fn:abs' => [1, 1],
        'fn:boolean' => [1, 1],
        'fn:concat' => [2, null],
        'fn:contains' => [2, 2],
        'fn:count' => [1, 1],
        'fn:distinct-values' => [1, 1],
        'fn:empty' => [1, 1],
        'fn:ends-with' => [2, 2],
        'fn:exists' => [1, 1],
        'fn:false' => [0, 0],
        'fn:local-name' => [0, 1],
        'fn:matches' => [2, 3],
        'fn:name' => [0, 1],
        'fn:normalize-space' => [0, 1],
        'fn:not' => [1, 1],
        'fn:round' => [1, 1],
        'fn:string' => [0, 1],
        'fn:string-length' => [0, 1],
        'fn:substring' => [2, 3],
        'fn:substring-after' => [2, 2],
        'fn:substring-before' => [2, 2],
        'fn:sum' => [1, 2],
        'fn:true' => [0, 0],
        'fn:upper-case' => [1, 1],
        'xs:date' => [1, 1],
        'xs:decimal' => [1, 1],
    ];

    /** Target types accepted by "cast as". */
    private const CAST_TYPES = ['xs:boolean', 'xs:date', 'xs:decimal'];

    private const AXES = ['ancestor', 'ancestor-or-self', 'attribute', 'child', 'descendant', 'descendant-or-self', 'parent', 'preceding', 'self'];

    /** Names that are kind tests when followed by "(", supported or not. */
    private const KIND_TESTS = ['attribute', 'comment', 'document-node', 'element', 'node', 'processing-instruction', 'schema-attribute', 'schema-element', 'text'];

    /** Names that can never be function names because they start other expressions. */
    private const RESERVED = ['empty-sequence', 'if', 'item', 'typeswitch'];

    private const GENERAL_COMPARISONS = ['=', '!=', '<', '<=', '>', '>='];

    private const VALUE_COMPARISONS = ['eq', 'ne', 'lt', 'le', 'gt', 'ge'];

    /** Operators that are recognised only to be rejected, keyed by their first token. */
    private const UNSUPPORTED_OPERATORS = [
        'castable' => 'castable as',
        'except' => 'except',
        'instance' => 'instance of',
        'intersect' => 'intersect',
        'is' => 'is',
        'to' => 'to',
        'treat' => 'treat as',
        '<<' => '<<',
        '>>' => '>>',
    ];

    private string $expression = '';

    /** @var list<Token> */
    private array $tokens = [];

    private int $position = 0;

    private StaticContext $context;

    /** @var list<string> */
    private array $variables = [];

    /**
     * @return array<mixed>
     */
    public function parse(string $expression, StaticContext $context): array
    {
        $this->expression = $expression;
        $this->tokens = (new Lexer)->tokenize($expression);
        $this->position = 0;
        $this->context = $context;
        $this->variables = $context->variables;

        $ast = $this->expr();
        $this->rejectUnsupportedOperator();
        if (! $this->current()->is(Token::END)) {
            throw $this->syntaxError('Unexpected "'.$this->current()->value.'"');
        }

        return $ast;
    }

    /**
     * Parses an XSLT match pattern and returns the AST of the equivalent selection expression: each
     * union alternative that does not start at the root is evaluated as a descendant of the root, so
     * "a/b" becomes "//a/b" and "@x" becomes "//@x". The result has the same shape as the parsed
     * "//a/b", with "//" bound to the first step, which the evaluator answers from its name index.
     *
     * @return array<mixed>
     */
    public function parsePattern(string $pattern, StaticContext $context): array
    {
        $alternatives = [];
        foreach ($this->alternatives($this->parse($pattern, $context)) as $alternative) {
            $this->assertPatternPath($alternative, true, $pattern);
            $alternatives[] = $this->startsAtRoot($alternative)
                ? $alternative
                : self::descendantOfRoot($alternative);
        }

        $selection = array_shift($alternatives);
        foreach ($alternatives as $alternative) {
            $selection = [Op::UNION, $selection, $alternative];
        }

        return $selection ?? [];
    }

    /**
     * @return array<mixed>
     */
    private function expr(): array
    {
        $items = [$this->exprSingle()];
        while ($this->accept(Token::SYMBOL, ',')) {
            $items[] = $this->exprSingle();
        }

        return count($items) === 1 ? $items[0] : [Op::SEQUENCE, $items];
    }

    /**
     * @return array<mixed>
     */
    private function exprSingle(): array
    {
        $token = $this->current();
        if ($token->is(Token::NAME) && $this->peek()->is(Token::SYMBOL, '$')) {
            if ($token->value === 'some' || $token->value === 'every') {
                return $this->quantified();
            }
            if ($token->value === 'for' || $token->value === 'let') {
                throw $this->unsupported($token->value, $token);
            }
        }

        return $this->orExpr();
    }

    /**
     * @return array<mixed>
     */
    private function quantified(): array
    {
        $quantifier = $this->advance()->value;
        $outerScope = $this->variables;
        $bindings = [];

        do {
            $name = $this->variableName();
            $this->expectName('in');
            $bindings[] = [$name, $this->exprSingle()];
            $this->variables[] = $name;
        } while ($this->accept(Token::SYMBOL, ','));

        $this->expectName('satisfies');
        $satisfies = $this->exprSingle();
        $this->variables = $outerScope;

        return [Op::QUANT, $quantifier, $bindings, $satisfies];
    }

    /**
     * @return array<mixed>
     */
    private function orExpr(): array
    {
        $left = $this->andExpr();
        while ($this->acceptOperatorName('or')) {
            $left = [Op::OR, $left, $this->andExpr()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function andExpr(): array
    {
        $left = $this->comparisonExpr();
        while ($this->acceptOperatorName('and')) {
            $left = [Op::AND, $left, $this->comparisonExpr()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function comparisonExpr(): array
    {
        $left = $this->additiveExpr();
        $token = $this->current();

        if ($token->is(Token::SYMBOL) && in_array($token->value, self::GENERAL_COMPARISONS, true)) {
            $this->advance();

            return [Op::GCMP, $token->value, $left, $this->additiveExpr()];
        }
        if ($token->is(Token::NAME) && in_array($token->value, self::VALUE_COMPARISONS, true)) {
            $this->advance();

            return [Op::VCMP, $token->value, $left, $this->additiveExpr()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function additiveExpr(): array
    {
        $left = $this->multiplicativeExpr();
        while ($this->current()->is(Token::SYMBOL, '+') || $this->current()->is(Token::SYMBOL, '-')) {
            $operator = $this->advance()->value;
            $left = [Op::ARITH, $operator, $left, $this->multiplicativeExpr()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function multiplicativeExpr(): array
    {
        $left = $this->unionExpr();
        while (true) {
            $token = $this->current();
            if ($token->is(Token::SYMBOL, '*') || ($token->is(Token::NAME) && in_array($token->value, ['div', 'idiv', 'mod'], true))) {
                $this->advance();
                $left = [Op::ARITH, $token->value, $left, $this->unionExpr()];

                continue;
            }

            return $left;
        }
    }

    /**
     * @return array<mixed>
     */
    private function unionExpr(): array
    {
        $left = $this->castExpr();
        while ($this->accept(Token::SYMBOL, '|') || $this->acceptOperatorName('union')) {
            $left = [Op::UNION, $left, $this->castExpr()];
        }

        return $left;
    }

    /**
     * @return array<mixed>
     */
    private function castExpr(): array
    {
        $operand = $this->unaryExpr();
        $this->rejectUnsupportedOperator();

        if (! ($this->current()->is(Token::NAME, 'cast') && $this->peek()->is(Token::NAME, 'as'))) {
            return $operand;
        }

        $this->advance();
        $this->advance();
        $typeToken = $this->current();
        $type = $this->canonicalName($this->expectTypeName(), $typeToken);
        if (! in_array($type, self::CAST_TYPES, true)) {
            throw $this->unsupported("cast as {$type}", $typeToken);
        }

        return [Op::CAST, $type, $this->accept(Token::SYMBOL, '?'), $operand];
    }

    /**
     * @return array<mixed>
     */
    private function unaryExpr(): array
    {
        $token = $this->current();
        if ($token->is(Token::SYMBOL, '-')) {
            $this->advance();

            return [Op::NEG, $this->unaryExpr()];
        }
        if ($token->is(Token::SYMBOL, '+')) {
            throw $this->unsupported('unary +', $token);
        }

        return $this->pathExpr();
    }

    /**
     * A leading "/" is the initial step ROOT; every later "/" or "//" joins steps left-associatively,
     * and "//" stands for "/descendant-or-self::node()/".
     *
     * @return array<mixed>
     */
    private function pathExpr(): array
    {
        $token = $this->current();
        if ($token->is(Token::SYMBOL, '/') && ! $this->startsStep($this->peek())) {
            $this->advance();

            return [Op::ROOT];
        }

        $left = $token->is(Token::SYMBOL, '/') || $token->is(Token::SYMBOL, '//') ? [Op::ROOT] : $this->stepExpr();
        while (true) {
            if ($this->accept(Token::SYMBOL, '/')) {
                $left = [Op::PATH, $left, $this->stepExpr()];
            } elseif ($this->accept(Token::SYMBOL, '//')) {
                $left = [Op::PATH, $left, [Op::PATH, self::descendantOrSelf(), $this->stepExpr()]];
            } else {
                return $left;
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function stepExpr(): array
    {
        $token = $this->current();

        if ($token->is(Token::SYMBOL, '..')) {
            $this->advance();

            return [Op::STEP, 'parent', ['node'], $this->predicates()];
        }
        if ($token->is(Token::SYMBOL, '@')) {
            $this->advance();

            return [Op::STEP, 'attribute', $this->nodeTest('attribute'), $this->predicates()];
        }
        if ($token->is(Token::NAME) && $this->peek()->is(Token::SYMBOL, '::')) {
            if (! in_array($token->value, self::AXES, true)) {
                throw $this->unsupported($token->value.' axis', $token);
            }
            $this->advance();
            $this->advance();

            return [Op::STEP, $token->value, $this->nodeTest($token->value), $this->predicates()];
        }
        if ($token->is(Token::SYMBOL, '*') || ($token->is(Token::NAME) && ! $this->peek()->is(Token::SYMBOL, '('))) {
            return [Op::STEP, 'child', $this->nodeTest('child'), $this->predicates()];
        }
        if ($token->is(Token::NAME) && in_array($token->value, self::KIND_TESTS, true)) {
            return [Op::STEP, 'child', $this->nodeTest('child'), $this->predicates()];
        }

        $primary = $this->primaryExpr();
        $predicates = $this->predicates();

        return $predicates === [] ? $primary : [Op::FILTER, $primary, $predicates];
    }

    /**
     * @return array<mixed>
     */
    private function primaryExpr(): array
    {
        $token = $this->current();

        switch ($token->type) {
            case Token::STRING:
                $this->advance();

                return [Op::LITERAL, 'xs:string', $token->value];
            case Token::INTEGER:
            case Token::DECIMAL:
            case Token::DOUBLE:
                $this->advance();

                return [Op::LITERAL, 'xs:'.$token->type, $token->value];
            case Token::NAME:
                return $this->functionCall();
        }

        if ($token->is(Token::SYMBOL, '.')) {
            $this->advance();

            return [Op::CONTEXT];
        }
        if ($token->is(Token::SYMBOL, '$')) {
            $name = $this->variableName();
            if (! in_array($name, $this->variables, true)) {
                throw new XPathSyntaxError("Undeclared variable \${$name}", $this->expression, $token->offset);
            }

            return [Op::VAR, $name];
        }
        if ($this->accept(Token::SYMBOL, '(')) {
            if ($this->accept(Token::SYMBOL, ')')) {
                return [Op::SEQUENCE, []];
            }
            $inner = $this->expr();
            $this->rejectUnsupportedOperator();
            $this->expect(Token::SYMBOL, ')');

            return $inner;
        }

        throw $this->syntaxError($token->is(Token::END) ? 'Unexpected end of expression' : 'Unexpected "'.$token->value.'"');
    }

    /**
     * @return array<mixed>
     */
    private function functionCall(): array
    {
        $token = $this->advance();
        if (in_array($token->value, self::RESERVED, true)) {
            throw $this->unsupported($token->value, $token);
        }

        $name = $this->canonicalName($token->value, $token);
        $this->expect(Token::SYMBOL, '(');
        $arguments = [];
        if (! $this->accept(Token::SYMBOL, ')')) {
            do {
                $arguments[] = $this->exprSingle();
                $this->rejectUnsupportedOperator();
            } while ($this->accept(Token::SYMBOL, ','));
            $this->expect(Token::SYMBOL, ')');
        }

        [$min, $max] = self::FUNCTIONS[$name] ?? [null, null];
        if ($min === null) {
            throw $this->unsupported("function {$name}()", $token);
        }
        if (count($arguments) < $min || ($max !== null && count($arguments) > $max)) {
            throw $this->unsupported(sprintf('function %s() with %d arguments', $name, count($arguments)), $token);
        }

        return [Op::CALL, $name, $arguments];
    }

    /**
     * Resolves a function or type QName to its canonical form (fn:local or xs:local); unprefixed
     * names belong to the functions-and-operators namespace.
     */
    private function canonicalName(string $qname, Token $token): string
    {
        [$prefix, $local] = str_contains($qname, ':') ? explode(':', $qname, 2) : ['fn', $qname];
        $uri = $this->context->namespaceUri($prefix);

        return match ($uri) {
            StaticContext::FN => 'fn:'.$local,
            StaticContext::XS => 'xs:'.$local,
            null => throw $this->unsupported("unknown prefix {$prefix} in {$qname}()", $token),
            default => throw $this->unsupported("function {$qname}() in namespace {$uri}", $token),
        };
    }

    /**
     * @return list<string>
     */
    private function nodeTest(string $axis): array
    {
        $token = $this->current();

        if ($token->is(Token::SYMBOL, '*')) {
            $this->advance();

            return ['any-name'];
        }
        if (! $token->is(Token::NAME)) {
            throw $this->syntaxError('Expected a node test');
        }

        if ($this->peek()->is(Token::SYMBOL, '(')) {
            if (! in_array($token->value, ['node', 'text'], true)) {
                throw $this->unsupported($token->value.'()', $token);
            }
            $this->advance();
            $this->advance();
            $this->expect(Token::SYMBOL, ')');

            return [$token->value];
        }

        $this->advance();
        if (str_starts_with($token->value, '*:')) {
            throw $this->unsupported('wildcard name test '.$token->value, $token);
        }
        if (! str_contains($token->value, ':')) {
            return ['name', '', $token->value];
        }

        [$prefix, $local] = explode(':', $token->value, 2);
        $uri = $this->context->namespaceUri($prefix);
        if ($uri === null) {
            throw $this->unsupported("unknown prefix {$prefix}", $token);
        }

        return $local === '*' ? ['ns-any', $uri] : ['name', $uri, $local];
    }

    /**
     * @return list<array<mixed>>
     */
    private function predicates(): array
    {
        $predicates = [];
        while ($this->accept(Token::SYMBOL, '[')) {
            $predicates[] = $this->expr();
            $this->rejectUnsupportedOperator();
            $this->expect(Token::SYMBOL, ']');
        }

        return $predicates;
    }

    private function variableName(): string
    {
        $this->expect(Token::SYMBOL, '$');
        $token = $this->current();
        if (! $token->is(Token::NAME) || str_contains($token->value, ':')) {
            throw $token->is(Token::NAME) ? $this->unsupported('prefixed variable $'.$token->value, $token) : $this->syntaxError('Expected a variable name');
        }
        $this->advance();

        return $token->value;
    }

    private function expectTypeName(): string
    {
        $token = $this->current();
        if (! $token->is(Token::NAME) || str_contains($token->value, '*')) {
            throw $this->syntaxError('Expected a type name');
        }
        $this->advance();

        return $token->value;
    }

    private function startsStep(Token $token): bool
    {
        return in_array($token->type, [Token::NAME, Token::STRING, Token::INTEGER, Token::DECIMAL, Token::DOUBLE], true)
            || ($token->is(Token::SYMBOL) && in_array($token->value, ['*', '@', '.', '..', '(', '$'], true));
    }

    private function rejectUnsupportedOperator(): void
    {
        $token = $this->current();
        if (($token->is(Token::NAME) || $token->is(Token::SYMBOL)) && isset(self::UNSUPPORTED_OPERATORS[$token->value])) {
            throw $this->unsupported(self::UNSUPPORTED_OPERATORS[$token->value], $token);
        }
    }

    /**
     * Splits a top-level union into its alternatives, left to right.
     *
     * @param  array<mixed>  $ast
     * @return list<array<mixed>>
     */
    private function alternatives(array $ast): array
    {
        if ($ast[0] !== Op::UNION) {
            return [$ast];
        }

        /** @var array<mixed> $left */
        $left = $ast[1];
        /** @var array<mixed> $right */
        $right = $ast[2];

        return [...$this->alternatives($left), ...$this->alternatives($right)];
    }

    /**
     * Accepts only the path shapes an XSLT pattern may take: steps on the child or attribute axis,
     * joined by "/" or "//", optionally starting at the root.
     *
     * @param  array<mixed>  $ast
     */
    private function assertPatternPath(array $ast, bool $leftmost, string $pattern): void
    {
        $valid = match ($ast[0]) {
            Op::ROOT => $leftmost,
            Op::STEP => in_array($ast[1], ['child', 'attribute'], true) || $ast === self::descendantOrSelf(),
            Op::PATH => true,
            default => false,
        };
        if (! $valid) {
            $construct = $ast[0] === Op::STEP && is_string($ast[1]) ? $ast[1].' axis' : (is_string($ast[0]) ? $ast[0] : '?');

            throw new UnsupportedXPath("{$construct} in a match pattern", $pattern, 0);
        }

        if ($ast[0] === Op::PATH) {
            /** @var array<mixed> $left */
            $left = $ast[1];
            /** @var array<mixed> $right */
            $right = $ast[2];
            $this->assertPatternPath($left, $leftmost, $pattern);
            $this->assertPatternPath($right, false, $pattern);
        }
    }

    /**
     * @param  array<mixed>  $ast
     */
    private function startsAtRoot(array $ast): bool
    {
        while ($ast[0] === Op::PATH) {
            /** @var array<mixed> $ast */
            $ast = $ast[1];
        }

        return $ast[0] === Op::ROOT;
    }

    /**
     * Prefixes a relative path with "//": "a/b/c", parsed as (a/b)/c, becomes ((//a)/b)/c.
     *
     * @param  array<mixed>  $ast
     * @return array<mixed>
     */
    private static function descendantOfRoot(array $ast): array
    {
        if ($ast[0] === Op::PATH) {
            /** @var array<mixed> $left */
            $left = $ast[1];

            return [Op::PATH, self::descendantOfRoot($left), $ast[2]];
        }

        return [Op::PATH, [Op::ROOT], [Op::PATH, self::descendantOrSelf(), $ast]];
    }

    /**
     * @return array{string, string, array{string}, array{}}
     */
    private static function descendantOrSelf(): array
    {
        return [Op::STEP, 'descendant-or-self', ['node'], []];
    }

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function peek(): Token
    {
        return $this->tokens[min($this->position + 1, count($this->tokens) - 1)];
    }

    private function advance(): Token
    {
        $token = $this->tokens[$this->position];
        if (! $token->is(Token::END)) {
            $this->position++;
        }

        return $token;
    }

    private function accept(string $type, string $value): bool
    {
        if ($this->current()->is($type, $value)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function acceptOperatorName(string $name): bool
    {
        return $this->accept(Token::NAME, $name);
    }

    private function expect(string $type, string $value): void
    {
        if (! $this->accept($type, $value)) {
            $token = $this->current();
            throw $this->syntaxError(sprintf('Expected "%s" but found %s', $value, $token->is(Token::END) ? 'end of expression' : '"'.$token->value.'"'));
        }
    }

    private function expectName(string $name): void
    {
        $this->expect(Token::NAME, $name);
    }

    private function syntaxError(string $reason): XPathSyntaxError
    {
        return new XPathSyntaxError($reason, $this->expression, $this->current()->offset);
    }

    private function unsupported(string $construct, Token $token): UnsupportedXPath
    {
        return new UnsupportedXPath($construct, $this->expression, $token->offset);
    }
}

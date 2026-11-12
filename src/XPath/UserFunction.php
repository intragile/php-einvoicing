<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use DOMNode;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use LogicException;

/**
 * A compiled XSLT stylesheet function (xsl:function), executed with the semantics Saxon shows:
 *
 * - each argument is evaluated before the call and converted to its parameter's "as" type
 *   (SequenceType, type errors XPTY0004);
 * - local variables are lazy (Thunk): one that is never read is never evaluated, and its "as"
 *   type is applied when it is (type errors XTTE0570);
 * - xsl:choose runs the body of the first xsl:when whose test is true, else xsl:otherwise;
 * - the result is converted to the function's "as" type (type errors XTTE0780).
 *
 * A variable with text content and no "as" is a temporary tree in XSLT; it is held as its
 * untyped atomic value, which is what every use in an expression sees after atomisation.
 *
 * The body sees its parameters, its earlier local variables and the global variables passed to
 * call(); the compiler guarantees it never depends on the focus, which XSLT leaves absent in a
 * function body.
 *
 * @internal
 *
 * @phpstan-type CompiledFunction array{name: string, as: ?string, params: list<array{name: string, as: ?string}>, body: list<list<mixed>>}
 */
final readonly class UserFunction
{
    /**
     * @param  CompiledFunction  $definition
     */
    public function __construct(private array $definition) {}

    public function name(): string
    {
        return $this->definition['name'];
    }

    /**
     * @param  list<list<DOMNode|AtomicValue>>  $arguments  one value per parameter
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $globals  the global variables visible in the body
     * @param  DOMNode  $document  the document node, the context item passed to the evaluator
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public function call(Evaluator $evaluator, array $arguments, array $globals, DOMNode $document): array
    {
        $variables = $globals;
        foreach ($this->definition['params'] as $index => $param) {
            $variables[$param['name']] = SequenceType::convert($param['as'], $arguments[$index] ?? throw new LogicException("Missing argument {$index} of {$this->name()}."));
        }

        return SequenceType::convert($this->definition['as'], $this->body($this->definition['body'], $variables, $evaluator, $document), 'XTTE0780');
    }

    /**
     * @param  list<list<mixed>>  $instructions
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function body(array $instructions, array $variables, Evaluator $evaluator, DOMNode $document): array
    {
        // Each case stays a single call, which keeps the frame of this recursive method small.
        foreach ($instructions as $instruction) {
            switch ($instruction[0] ?? null) {
                case 'variable':
                case 'variable-text':
                    $variables = self::bind($instruction, $variables, $evaluator, $document);
                    break;
                case 'sequence':
                    return $evaluator->evaluate(self::ast($instruction[1] ?? null), $document, $variables);
                case 'choose':
                    return $this->choose($instruction, $variables, $evaluator, $document);
                default:
                    throw self::malformed();
            }
        }

        throw self::malformed();
    }

    /**
     * Adds a local variable, computed on first use and converted to its "as" type.
     *
     * @param  list<mixed>  $instruction
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return array<string, list<DOMNode|AtomicValue>|Thunk>
     */
    private static function bind(array $instruction, array $variables, Evaluator $evaluator, DOMNode $document): array
    {
        $name = self::string($instruction[1] ?? null);
        $as = self::optionalString($instruction[2] ?? null);
        if ($instruction[0] === 'variable-text') {
            $value = [new UntypedAtomic(self::string($instruction[3] ?? null))];
            $variables[$name] = $as === null ? $value : new Thunk(static fn (): array => SequenceType::convert($as, $value, 'XTTE0570'));

            return $variables;
        }
        $ast = self::ast($instruction[3] ?? null);
        $scope = $variables;
        $variables[$name] = new Thunk(static fn (): array => SequenceType::convert($as, $evaluator->evaluate($ast, $document, $scope), 'XTTE0570'));

        return $variables;
    }

    /**
     * @param  list<mixed>  $instruction
     * @param  array<string, list<DOMNode|AtomicValue>|Thunk>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function choose(array $instruction, array $variables, Evaluator $evaluator, DOMNode $document): array
    {
        foreach (self::list($instruction[1] ?? null) as $branch) {
            $branch = self::list($branch);
            if ($evaluator->effectiveBoolean(self::ast($branch[0] ?? null), $document, $variables)) {
                return $this->body(self::instructions($branch[1] ?? null), $variables, $evaluator, $document);
            }
        }
        $otherwise = $instruction[2] ?? null;

        return $otherwise === null ? [] : $this->body(self::instructions($otherwise), $variables, $evaluator, $document);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : throw self::malformed();
    }

    private static function optionalString(mixed $value): ?string
    {
        return $value === null || is_string($value) ? $value : throw self::malformed();
    }

    /**
     * @return array<mixed>
     */
    private static function ast(mixed $value): array
    {
        return is_array($value) ? $value : throw self::malformed();
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : throw self::malformed();
    }

    /**
     * @return list<list<mixed>>
     */
    private static function instructions(mixed $value): array
    {
        $instructions = [];
        foreach (self::list($value) as $instruction) {
            $instructions[] = self::list($instruction);
        }

        return $instructions;
    }

    private static function malformed(): LogicException
    {
        // RuleSet checks every body when it loads a rule set.
        return new LogicException('Malformed function body.');
    }
}

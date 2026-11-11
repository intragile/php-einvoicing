<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\XPath\Op;

/**
 * A compiled Schematron rule set (the array a compiled rule file returns), with its shape checked.
 *
 * @phpstan-type RuleAssert array{id: string, severity: Severity, test: string, ast: array<mixed>, message: string}
 * @phpstan-type Rule array{context: string, match: array<mixed>, asserts: list<RuleAssert>, roots: list<array{string, string}>}
 * @phpstan-type Pattern array{id: string, rules: list<Rule>}
 */
final class RuleSet
{
    /** @var array<string, self> */
    private static array $loaded = [];

    /**
     * @param  array<string, string>  $namespaces  prefix => namespace URI
     * @param  list<Pattern>  $patterns
     */
    private function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly array $namespaces,
        private readonly array $patterns,
    ) {}

    /**
     * Loads a compiled rule file once per path.
     *
     * @throws UnsupportedSchematron
     */
    public static function fromFile(string $path): self
    {
        if (isset(self::$loaded[$path])) {
            return self::$loaded[$path];
        }
        if (! is_file($path)) {
            throw new UnsupportedSchematron("The compiled rule set \"{$path}\" does not exist.");
        }

        return self::$loaded[$path] = self::fromArray(require $path);
    }

    /**
     * @throws UnsupportedSchematron
     */
    public static function fromArray(mixed $compiled): self
    {
        $compiled = self::map($compiled, 'The rule set');
        $id = self::string($compiled, 'id', 'The rule set');
        $namespaces = [];
        foreach (self::map($compiled['namespaces'] ?? null, 'The namespaces of the rule set') as $prefix => $uri) {
            if (! is_string($prefix) || ! is_string($uri)) {
                throw new UnsupportedSchematron("The rule set \"{$id}\" has a malformed namespace declaration.");
            }
            $namespaces[$prefix] = $uri;
        }

        $patterns = [];
        foreach (self::list($compiled['patterns'] ?? null, "The patterns of \"{$id}\"") as $pattern) {
            $pattern = self::map($pattern, "A pattern of \"{$id}\"");
            $patternId = self::string($pattern, 'id', "A pattern of \"{$id}\"");
            $rules = [];
            foreach (self::list($pattern['rules'] ?? null, "The rules of pattern \"{$patternId}\"") as $rule) {
                $rules[] = self::rule(self::map($rule, "A rule of pattern \"{$patternId}\""), $namespaces, $patternId);
            }
            $patterns[] = ['id' => $patternId, 'rules' => $rules];
        }

        return new self($id, self::string($compiled, 'version', "The rule set \"{$id}\""), $namespaces, $patterns);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, string> prefix => namespace URI
     */
    public function namespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * @return list<Pattern>
     */
    public function patterns(): array
    {
        return $this->patterns;
    }

    /**
     * @param  array<mixed>  $rule
     * @param  array<string, string>  $namespaces
     * @return Rule
     */
    private static function rule(array $rule, array $namespaces, string $patternId): array
    {
        $what = "A rule of pattern \"{$patternId}\"";
        $context = self::string($rule, 'context', $what);
        $what = "The rule \"{$context}\" of pattern \"{$patternId}\"";

        $asserts = [];
        foreach (self::list($rule['asserts'] ?? null, $what) as $assert) {
            $assert = self::map($assert, "An assert of {$what}");
            $assertId = self::string($assert, 'id', "An assert of {$what}");
            $flag = self::string($assert, 'flag', "The assert \"{$assertId}\"");
            $asserts[] = [
                'id' => $assertId,
                'severity' => Severity::tryFrom($flag) ?? throw new UnsupportedSchematron("The assert \"{$assertId}\" has the unknown flag \"{$flag}\"."),
                'test' => self::string($assert, 'test', "The assert \"{$assertId}\""),
                'ast' => self::map($assert['ast'] ?? null, "The test of the assert \"{$assertId}\""),
                'message' => self::string($assert, 'message', "The assert \"{$assertId}\""),
            ];
        }

        return [
            'context' => $context,
            'match' => self::regroup(self::map($rule['match'] ?? null, "The match of {$what}")),
            'asserts' => $asserts,
            'roots' => self::roots($context, $namespaces),
        ];
    }

    /**
     * Rewrites "/descendant-or-self::node()/(a/b/c)", the selection a relative context such as
     * "a/b/c" compiles to, into "((/descendant-or-self::node()/a)/b)/c", the grouping of a literal
     * "//a/b/c". Both select the same nodes because "/" is associative over nodes, but only the
     * latter lets the evaluator answer the first step from its name index instead of visiting every
     * node of the document.
     *
     * @param  array<mixed>  $ast
     * @return array<mixed>
     */
    private static function regroup(array $ast): array
    {
        if (($ast[0] ?? null) === Op::UNION && is_array($ast[1] ?? null) && is_array($ast[2] ?? null)) {
            return [Op::UNION, self::regroup($ast[1]), self::regroup($ast[2])];
        }

        $descendants = [Op::STEP, 'descendant-or-self', ['node'], []];
        if (($ast[0] ?? null) !== Op::PATH || ($ast[1] ?? null) !== [Op::ROOT]
            || ! is_array($ast[2] ?? null) || ($ast[2][0] ?? null) !== Op::PATH || ($ast[2][1] ?? null) !== $descendants
            || ! is_array($ast[2][2] ?? null)) {
            return $ast;
        }

        $steps = [];
        $first = $ast[2][2];
        while (($first[0] ?? null) === Op::PATH && is_array($first[1] ?? null)) {
            array_unshift($steps, $first[2] ?? null);
            $first = $first[1];
        }

        $regrouped = [Op::PATH, [Op::ROOT], [Op::PATH, $descendants, $first]];
        foreach ($steps as $step) {
            $regrouped = [Op::PATH, $regrouped, $step];
        }

        return $regrouped;
    }

    /**
     * Expanded names of the context alternatives that select a document element, such as
     * "/ubl:Invoice" in "/ubl:Invoice | /cn:CreditNote".
     *
     * @param  array<string, string>  $namespaces
     * @return list<array{string, string}> namespace URI and local name
     */
    private static function roots(string $context, array $namespaces): array
    {
        $roots = [];
        foreach (explode('|', $context) as $alternative) {
            if (preg_match('~^/([A-Za-z][\w.-]*):([\w.-]+)$~', trim($alternative), $match) === 1 && isset($namespaces[$match[1]])) {
                $roots[] = [$namespaces[$match[1]], $match[2]];
            }
        }

        return $roots;
    }

    /**
     * @return array<mixed>
     */
    private static function map(mixed $value, string $what): array
    {
        return is_array($value) ? $value : throw new UnsupportedSchematron("{$what} is malformed: an array is expected.");
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, string $what): array
    {
        return is_array($value) && array_is_list($value) ? $value : throw new UnsupportedSchematron("{$what} is malformed: a list is expected.");
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function string(array $array, string $key, string $what): string
    {
        $value = $array[$key] ?? null;

        return is_string($value) ? $value : throw new UnsupportedSchematron("{$what} is malformed: the string \"{$key}\" is missing.");
    }
}

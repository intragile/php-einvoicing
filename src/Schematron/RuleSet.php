<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Validation\Severity;

/**
 * A compiled Schematron rule set (the array a compiled rule file returns), with its shape checked.
 *
 * Compiled arrays carry the format they were written in; a file of any other format is refused
 * with a request to recompile it, so a rule file left over from an older engine never runs with
 * a different meaning.
 *
 * @internal
 *
 * @phpstan-type Let array{name: string, value: string, ast: array<mixed>}
 * @phpstan-type Instruction list<mixed>
 * @phpstan-type CompiledFunction array{name: string, as: ?string, params: list<array{name: string, as: ?string}>, body: list<Instruction>}
 * @phpstan-type RuleAssert array{id: string, severity: Severity, test: string, ast: array<mixed>, message: string}
 * @phpstan-type Rule array{context: string, match: array<mixed>, lets: list<Let>, asserts: list<RuleAssert>, roots: list<array{string, string}>}
 * @phpstan-type Pattern array{id: string, lets: list<Let>, rules: list<Rule>}
 */
final class RuleSet
{
    /**
     * The layout of the compiled array this engine reads and the compiler writes. Files written
     * before the "format" key existed are format 1.
     */
    public const FORMAT = 2;

    /** @var array<string, self> */
    private static array $loaded = [];

    /**
     * @param  array<string, string>  $namespaces  prefix => namespace URI
     * @param  list<Let>  $lets
     * @param  array<string, CompiledFunction>  $functions
     * @param  list<Pattern>  $patterns
     */
    private function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly array $namespaces,
        private readonly array $lets,
        private readonly array $functions,
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
        $format = $compiled['format'] ?? 1;
        if ($format !== self::FORMAT) {
            $format = is_int($format) ? (string) $format : (json_encode($format) ?: get_debug_type($format));

            throw new UnsupportedSchematron("The rule set \"{$id}\" was compiled with format {$format}; this engine reads format ".self::FORMAT.'. Recompile it.');
        }
        $namespaces = [];
        foreach (self::map($compiled['namespaces'] ?? null, 'The namespaces of the rule set') as $prefix => $uri) {
            if (! is_string($prefix) || ! is_string($uri)) {
                throw new UnsupportedSchematron("The rule set \"{$id}\" has a malformed namespace declaration.");
            }
            $namespaces[$prefix] = $uri;
        }

        $functions = [];
        foreach (self::map($compiled['functions'] ?? null, "The functions of \"{$id}\"") as $key => $function) {
            if (! is_string($key)) {
                throw new UnsupportedSchematron("The rule set \"{$id}\" has a function without a key.");
            }
            $functions[$key] = self::userFunction(self::map($function, "The function {$key}"), $key);
        }

        $patterns = [];
        foreach (self::list($compiled['patterns'] ?? null, "The patterns of \"{$id}\"") as $pattern) {
            $pattern = self::map($pattern, "A pattern of \"{$id}\"");
            $patternId = self::string($pattern, 'id', "A pattern of \"{$id}\"");
            $rules = [];
            foreach (self::list($pattern['rules'] ?? null, "The rules of pattern \"{$patternId}\"") as $rule) {
                $rules[] = self::rule(self::map($rule, "A rule of pattern \"{$patternId}\""), $namespaces, $patternId);
            }
            $patterns[] = ['id' => $patternId, 'lets' => self::checkLets($pattern['lets'] ?? null, "The lets of pattern \"{$patternId}\""), 'rules' => $rules];
        }

        return new self(
            $id,
            self::string($compiled, 'version', "The rule set \"{$id}\""),
            $namespaces,
            self::checkLets($compiled['lets'] ?? null, "The lets of \"{$id}\""),
            $functions,
            $patterns,
        );
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
     * The schema-level lets, in document order.
     *
     * @return list<Let>
     */
    public function lets(): array
    {
        return $this->lets;
    }

    /**
     * The XSLT functions, keyed by "{namespace URI}local-name#arity".
     *
     * @return array<string, CompiledFunction>
     */
    public function functions(): array
    {
        return $this->functions;
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
            'match' => self::map($rule['match'] ?? null, "The match of {$what}"),
            'lets' => self::checkLets($rule['lets'] ?? null, 'The lets of '.lcfirst($what)),
            'asserts' => $asserts,
            'roots' => self::roots($context, $namespaces),
        ];
    }

    /**
     * @return list<Let>
     */
    private static function checkLets(mixed $lets, string $what): array
    {
        $result = [];
        foreach (self::list($lets, $what) as $let) {
            $let = self::map($let, "A let of {$what}");
            $name = self::string($let, 'name', "A let of {$what}");
            $result[] = [
                'name' => $name,
                'value' => self::string($let, 'value', "The let \"{$name}\""),
                'ast' => self::map($let['ast'] ?? null, "The value of the let \"{$name}\""),
            ];
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $function
     * @return CompiledFunction
     */
    private static function userFunction(array $function, string $key): array
    {
        $what = "The function {$key}";
        $params = [];
        foreach (self::list($function['params'] ?? null, "The parameters of {$key}") as $param) {
            $param = self::map($param, "A parameter of {$key}");
            $params[] = ['name' => self::string($param, 'name', "A parameter of {$key}"), 'as' => self::optionalString($param, 'as', "A parameter of {$key}")];
        }

        return [
            'name' => self::string($function, 'name', $what),
            'as' => self::optionalString($function, 'as', $what),
            'params' => $params,
            'body' => self::instructions($function['body'] ?? null, "The body of {$key}"),
        ];
    }

    /**
     * Checks a function body: local variables, ['variable', name, as, ast] or ['variable-text',
     * name, as, text], followed by exactly one result, ['sequence', ast] or ['choose', list of
     * [test ast, body], otherwise body or null], where every body is again such a body.
     *
     * @return list<Instruction>
     */
    private static function instructions(mixed $body, string $what): array
    {
        $instructions = [];
        $result = false;
        foreach (self::list($body, $what) as $instruction) {
            $instruction = self::list($instruction, "An instruction of {$what}");
            if ($result) {
                throw self::noResult($what);
            }
            $valid = match ($instruction[0] ?? null) {
                'variable' => count($instruction) === 4 && is_string($instruction[1]) && self::isOptionalString($instruction[2]) && is_array($instruction[3]),
                'variable-text' => count($instruction) === 4 && is_string($instruction[1]) && self::isOptionalString($instruction[2]) && is_string($instruction[3]),
                'sequence' => count($instruction) === 2 && is_array($instruction[1]),
                'choose' => count($instruction) === 3 && self::validBranches($instruction[1], $what) && ($instruction[2] === null || self::instructions($instruction[2], $what) !== []),
                default => false,
            };
            if (! $valid) {
                throw new UnsupportedSchematron("{$what} is malformed: an unknown or incomplete instruction.");
            }
            $result = $instruction[0] === 'sequence' || $instruction[0] === 'choose';
            $instructions[] = $instruction;
        }
        if (! $result) {
            throw self::noResult($what);
        }

        return $instructions;
    }

    private static function noResult(string $what): UnsupportedSchematron
    {
        return new UnsupportedSchematron("{$what} is malformed: it must end with one sequence or choose, after its variables.");
    }

    /**
     * Checks the branches of a choose: at least one [test ast, body].
     */
    private static function validBranches(mixed $branches, string $what): bool
    {
        $branches = self::list($branches, "The branches of {$what}");
        foreach ($branches as $branch) {
            if (! is_array($branch) || ! array_is_list($branch) || count($branch) !== 2 || ! is_array($branch[0])) {
                return false;
            }
            self::instructions($branch[1], $what);
        }

        return $branches !== [];
    }

    private static function isOptionalString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function optionalString(array $array, string $key, string $what): ?string
    {
        $value = $array[$key] ?? null;
        if (! array_key_exists($key, $array) || ($value !== null && ! is_string($value))) {
            throw new UnsupportedSchematron("{$what} is malformed: \"{$key}\" must be a string or null.");
        }

        return $value;
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

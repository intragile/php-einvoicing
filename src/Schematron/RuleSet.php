<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\XPath\DocumentIndex;

/**
 * A compiled Schematron rule set (the array a compiled rule file returns), with its shape checked.
 *
 * Compiled arrays carry the format they were written in. The current format and the formats it
 * only extends are read; a file of any other format is refused with a request to recompile it, so
 * a rule file left over from another engine never runs with a different meaning.
 *
 * Load a compiled rule file with RuleSet::fromFile() to pass custom rule sets to the Validator
 * constructor; the rest of the class serves the engine.
 *
 * @phpstan-type Let array{name: string, value: string, ast: array<mixed>}
 * @phpstan-type Instruction list<mixed>
 * @phpstan-type CompiledFunction array{name: string, as: ?string, params: list<array{name: string, as: ?string}>, body: list<Instruction>}
 * @phpstan-type MessagePart string|array{name: true}|array{valueOf: array<mixed>}
 * @phpstan-type RuleAssert array{id: string, severity: Severity, test: string, ast: array<mixed>, message: string, messageParts: ?list<MessagePart>, report: bool}
 * @phpstan-type Rule array{context: string, match: array<mixed>, lets: list<Let>, asserts: list<RuleAssert>, roots: list<array{string, string}>}
 * @phpstan-type Pattern array{id: string, lets: list<Let>, rules: list<Rule>}
 */
final class RuleSet
{
    /**
     * The layout of the compiled array the compiler writes. Files written before the "format" key
     * existed are format 1. Format 3 adds the optional "messageParts" of an assert (name and
     * value-of in messages) to format 2. Format 4 adds the optional "report" => true of an assert
     * compiled from a report element, which fires when its test is true, and the optional
     * "documents" (URI => XML) of the files fn:document() reads, embedded at compile time, with
     * "documentSha256" (URI => SHA-256 of that XML) next to "sha256". Both are left out when the
     * schema reads no document, so such a rule set compiles to the same bytes as before.
     */
    public const FORMAT = 4;

    /** The formats this engine reads: the current one and formats 2 and 3, which it only extends. */
    private const READABLE_FORMATS = [2, 3, self::FORMAT];

    /** @var array<string, self> */
    private static array $loaded = [];

    /** @var array<string, DocumentIndex> the embedded documents parsed so far, by URI */
    private array $documentIndexes = [];

    /**
     * @param  array<string, string>  $namespaces  prefix => namespace URI
     * @param  list<Let>  $lets
     * @param  array<string, CompiledFunction>  $functions
     * @param  list<Pattern>  $patterns
     * @param  array<string, string>  $documents  URI => XML
     * @param  array<string, string>  $documentSha256  URI => SHA-256 of the XML
     */
    private function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly ?string $sha256,
        private readonly array $namespaces,
        private readonly array $lets,
        private readonly array $functions,
        private readonly array $patterns,
        private readonly array $documents = [],
        private readonly array $documentSha256 = [],
    ) {}

    /**
     * Loads a compiled rule file (a PHP file written by the compiler that returns the compiled
     * array) once per path; later calls with the same path return the same instance.
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

        try {
            $compiled = require $path;
        } catch (\Error $error) {
            throw new UnsupportedSchematron("The compiled rule set \"{$path}\" is corrupt; reinstall it.", 0, $error);
        }

        return self::$loaded[$path] = self::fromArray($compiled);
    }

    /**
     * @throws UnsupportedSchematron
     */
    public static function fromArray(mixed $compiled): self
    {
        $compiled = self::map($compiled, 'The rule set');
        $id = self::string($compiled, 'id', 'The rule set');
        $format = $compiled['format'] ?? 1;
        if (! in_array($format, self::READABLE_FORMATS, true)) {
            $format = is_int($format) ? (string) $format : (json_encode($format) ?: get_debug_type($format));

            throw new UnsupportedSchematron("The rule set \"{$id}\" was compiled with format {$format}; this engine reads formats ".implode(', ', array_slice(self::READABLE_FORMATS, 0, -1)).' and '.self::FORMAT.'. Recompile it.');
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

        $documents = self::stringMap($compiled['documents'] ?? [], "The documents of \"{$id}\"");

        return new self(
            $id,
            self::string($compiled, 'version', "The rule set \"{$id}\""),
            isset($compiled['sha256']) && is_string($compiled['sha256']) ? $compiled['sha256'] : null,
            $namespaces,
            self::checkLets($compiled['lets'] ?? null, "The lets of \"{$id}\""),
            $functions,
            $patterns,
            $documents,
            self::documentHashes($compiled['documentSha256'] ?? [], $documents, $id),
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
     * The SHA-256 of the Schematron source the rule set was compiled from, when the file records it.
     */
    public function sourceSha256(): ?string
    {
        return $this->sha256;
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
     * The documents fn:document() reads, embedded when the rule set was compiled.
     *
     * @return array<string, string> URI as written in the schema => XML
     */
    public function documents(): array
    {
        return $this->documents;
    }

    /**
     * The SHA-256 of each embedded document, as recorded by the compiler.
     *
     * @return array<string, string> URI => SHA-256
     */
    public function documentSha256(): array
    {
        return $this->documentSha256;
    }

    /**
     * The indexed document node of an embedded document, parsed on first use and kept for the
     * lifetime of the rule set; null for a URI that is not embedded.
     *
     * @internal
     *
     * @throws UnsupportedSchematron when the embedded XML cannot be loaded
     */
    public function documentIndex(string $uri): ?DocumentIndex
    {
        if (isset($this->documentIndexes[$uri])) {
            return $this->documentIndexes[$uri];
        }
        if (! isset($this->documents[$uri])) {
            return null;
        }
        try {
            $document = SafeXml::load($this->documents[$uri]);
        } catch (UnsupportedDocument|UnsafeXml $exception) {
            throw new UnsupportedSchematron("The embedded document \"{$uri}\" of the rule set \"{$this->id}\" cannot be loaded: {$exception->getMessage()}", 0, $exception);
        }

        return $this->documentIndexes[$uri] = DocumentIndex::build($document);
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
                'messageParts' => isset($assert['messageParts']) ? self::messageParts($assert['messageParts'], $assertId) : null,
                'report' => is_bool($assert['report'] ?? false) ? ($assert['report'] ?? false) : throw new UnsupportedSchematron("The assert \"{$assertId}\" is malformed: \"report\" must be a boolean."),
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
     * Checks the message parts of an assert: strings, ['name' => true] and ['valueOf' => AST].
     *
     * @return list<MessagePart>
     */
    private static function messageParts(mixed $parts, string $assertId): array
    {
        $what = "The message of the assert \"{$assertId}\"";
        $result = [];
        foreach (self::list($parts, $what) as $part) {
            $result[] = match (true) {
                is_string($part) => $part,
                $part === ['name' => true] => ['name' => true],
                is_array($part) && count($part) === 1 && is_array($part['valueOf'] ?? null) => ['valueOf' => $part['valueOf']],
                default => throw new UnsupportedSchematron("{$what} is malformed: an unknown message part."),
            };
        }

        return $result;
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
     * @return array<string, string>
     */
    private static function stringMap(mixed $value, string $what): array
    {
        $result = [];
        foreach (self::map($value, $what) as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                throw new UnsupportedSchematron("{$what} is malformed: a map of strings is expected.");
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * Checks "documentSha256": a string per embedded document, for no other URI.
     *
     * @param  array<string, string>  $documents
     * @return array<string, string>
     */
    private static function documentHashes(mixed $value, array $documents, string $id): array
    {
        $what = "The documentSha256 of \"{$id}\"";
        $hashes = self::stringMap($value, $what);
        if (array_diff_key($hashes, $documents) !== []) {
            throw new UnsupportedSchematron("{$what} is malformed: it names a document that is not embedded.");
        }

        return $hashes;
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

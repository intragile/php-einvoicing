<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;
use IntrAgile\EInvoicing\XPath\Op;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\Regex;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\XPathError;
use InvalidArgumentException;

/**
 * Compiles a preprocessed ISO Schematron schema (includes and abstract patterns resolved, XSLT 2.0
 * query binding) into a plain array with every rule context and assert test parsed to an XPath AST.
 *
 * Only schema, title, ns, let, phase, active, pattern, rule and assert are accepted, plus XSLT
 * functions at schema level, each with the attributes listed in ATTRIBUTES; anything else (report,
 * value-of in messages, foreign elements, processing instructions, abstract patterns or rules,
 * attributes such as pattern/@documents or assert/@diagnostics) raises UnsupportedSchematron
 * instead of being ignored, because ignoring it could change a verdict or a message. Phases are
 * not compiled: like the official XSLT without a selected phase, all patterns run.
 *
 * Variables are scoped statically, in document order, the way the ISO Schematron skeleton turns
 * them into XSLT variables: schema and pattern lets become global variables (so their names must
 * be unique), rule lets become local variables of the rule template. A schema let sees the
 * earlier schema lets; a pattern let sees every schema let and the earlier lets of its pattern; a
 * rule context sees the same; a rule let or an assert sees those plus the earlier lets of its
 * rule. A function body sees its parameters, its earlier local variables and every schema let,
 * never a pattern or rule let. Functions may call each other, and themselves, in any order.
 *
 * A function body is a list of local variables followed by exactly one result: an xsl:sequence or
 * an xsl:choose whose every branch (an xsl:otherwise is required) is again such a body.
 *
 * @internal
 *
 * @phpstan-type CompiledLet array{name: string, value: string, ast: array<mixed>}
 * @phpstan-type CompiledParam array{name: string, as: ?string}
 * @phpstan-type Instruction list<mixed>
 * @phpstan-type CompiledFunction array{name: string, as: ?string, params: list<CompiledParam>, body: list<Instruction>}
 * @phpstan-type CompiledAssert array{id: string, flag: string, test: string, ast: array<mixed>, message: string}
 * @phpstan-type CompiledRule array{context: string, match: array<mixed>, lets: list<CompiledLet>, asserts: list<CompiledAssert>}
 * @phpstan-type CompiledPattern array{id: string, lets: list<CompiledLet>, rules: list<CompiledRule>}
 * @phpstan-type CompiledRuleSet array{format: int, id: string, version: string, source: string, sha256: string, namespaces: array<string, string>, lets: list<CompiledLet>, functions: array<string, CompiledFunction>, patterns: list<CompiledPattern>}
 */
final class Compiler
{
    public const SCHEMATRON_NAMESPACE = 'http://purl.oclc.org/dsdl/schematron';

    public const XSLT_NAMESPACE = 'http://www.w3.org/1999/XSL/Transform';

    private const FLAGS = ['fatal', 'warning', 'information'];

    /** The sequence types accepted in the "as" attribute of XSLT functions, parameters and variables. */
    private const SEQUENCE_TYPES = ['xs:boolean', 'xs:integer', 'xs:decimal', 'xs:string', 'xs:string?'];

    /** Namespaces a stylesheet function may not be declared in (XSLT 2.0 XTSE0740). */
    private const RESERVED_NAMESPACES = [
        self::XSLT_NAMESPACE,
        StaticContext::XS,
        StaticContext::FN,
        'http://www.w3.org/2001/XMLSchema-instance',
        'http://www.w3.org/XML/1998/namespace',
    ];

    /**
     * The attributes accepted per element: every attribute the CEN preprocessed schemas and the
     * Peppol BIS Billing schemas use. Any other attribute is refused by name.
     */
    private const ATTRIBUTES = [
        self::SCHEMATRON_NAMESPACE => [
            'schema' => ['schemaVersion', 'queryBinding'],
            'title' => [],
            'ns' => ['uri', 'prefix'],
            'let' => ['name', 'value'],
            'phase' => ['id'],
            'active' => ['pattern'],
            'pattern' => ['id'],
            'rule' => ['context', 'flag'],
            'assert' => ['id', 'test', 'flag'],
        ],
        self::XSLT_NAMESPACE => [
            'function' => ['name', 'as'],
            'param' => ['name', 'as'],
            'variable' => ['name', 'select', 'as'],
            'sequence' => ['select'],
            'choose' => [],
            'when' => ['test'],
            'otherwise' => [],
        ],
    ];

    private readonly Parser $parser;

    /** @var array<string, string> prefix => URI, from the ns elements */
    private array $namespaces = [];

    /** @var array<string, true> keys of the declared functions, see StaticContext::functionKey() */
    private array $functions = [];

    /** @var list<string> names of the schema lets */
    private array $schemaLets = [];

    /** @var array<string, true> names of the schema and pattern lets, which become global variables */
    private array $globals = [];

    private function __construct(private readonly string $source)
    {
        $this->parser = new Parser;
    }

    /**
     * @return CompiledRuleSet
     *
     * @throws UnsupportedSchematron
     */
    public static function compile(string $schPath, string $id, string $version): array
    {
        $xml = is_file($schPath) ? file_get_contents($schPath) : false;
        if ($xml === false) {
            throw new UnsupportedSchematron("Cannot read the Schematron schema \"{$schPath}\".");
        }

        $source = basename($schPath);

        return [
            'format' => RuleSet::FORMAT,
            'id' => $id,
            'version' => $version,
            'source' => $source,
            'sha256' => hash('sha256', $xml),
            ...(new self($source))->schema($xml),
        ];
    }

    /**
     * @return array{namespaces: array<string, string>, lets: list<CompiledLet>, functions: array<string, CompiledFunction>, patterns: list<CompiledPattern>}
     */
    private function schema(string $xml): array
    {
        try {
            $schema = SafeXml::load($xml)->documentElement;
        } catch (UnsupportedDocument|UnsafeXml $exception) {
            throw new UnsupportedSchematron("{$this->source} cannot be loaded: {$exception->getMessage()}", 0, $exception);
        }
        if ($schema === null || $schema->namespaceURI !== self::SCHEMATRON_NAMESPACE || $schema->localName !== 'schema') {
            throw new UnsupportedSchematron("{$this->source} is not an ISO Schematron schema.");
        }
        if ($schema->getAttribute('queryBinding') !== 'xslt2') {
            throw new UnsupportedSchematron("{$this->source} uses the query binding \"{$schema->getAttribute('queryBinding')}\"; only xslt2 is supported.");
        }
        if ($schema->hasAttribute('defaultPhase')) {
            throw new UnsupportedSchematron("{$this->source} selects a default phase; only schemas that run all patterns are supported.");
        }
        $this->checkAttributes($schema);

        $letElements = [];
        $functionElements = [];
        $patternElements = [];
        foreach ($this->children($schema, allowFunctions: true) as $child) {
            if ($child->namespaceURI === self::XSLT_NAMESPACE) {
                $functionElements[] = $child;

                continue;
            }
            switch ($child->localName) {
                case 'title':
                    $this->checkAttributes($child);
                    break;
                case 'ns':
                    $this->checkAttributes($child);
                    $prefix = $child->getAttribute('prefix');
                    $uri = $child->getAttribute('uri');
                    if (isset($this->namespaces[$prefix]) && $this->namespaces[$prefix] !== $uri) {
                        throw new UnsupportedSchematron("{$this->source} binds the prefix \"{$prefix}\" twice ({$this->line($child)}).");
                    }
                    $this->namespaces[$prefix] = $uri;
                    break;
                case 'let':
                    $letElements[] = $child;
                    break;
                case 'phase':
                    $this->checkAttributes($child);
                    foreach ($this->children($child) as $active) {
                        if ($active->localName !== 'active') {
                            throw $this->unsupportedElement($active);
                        }
                        $this->checkAttributes($active);
                    }
                    break;
                case 'pattern':
                    $patternElements[] = $child;
                    break;
                default:
                    throw $this->unsupportedElement($child);
            }
        }

        try {
            new StaticContext($this->namespaces);
        } catch (InvalidArgumentException $exception) {
            throw new UnsupportedSchematron("{$this->source}: {$exception->getMessage()}", 0, $exception);
        }

        // Every signature is known before any expression is parsed, so calls may go in any order.
        $signatures = [];
        foreach ($functionElements as $function) {
            $key = $this->signature($function);
            $signatures[$key] = $function;
            $this->functions[$key] = true;
        }

        $lets = [];
        foreach ($letElements as $let) {
            $lets[] = $this->let($let, $this->schemaLets, global: true);
            $this->schemaLets[] = $let->getAttribute('name');
        }

        return [
            'namespaces' => $this->namespaces,
            'lets' => $lets,
            'functions' => array_map($this->userFunction(...), $signatures),
            'patterns' => array_map($this->pattern(...), $patternElements),
        ];
    }

    /**
     * Checks the declaration of a function and returns its key, such as "{utils}gln#1".
     */
    private function signature(DOMElement $function): string
    {
        if ($function->localName !== 'function') {
            throw $this->unsupportedElement($function);
        }
        $this->checkAttributes($function);
        $name = $function->getAttribute('name');
        if (preg_match('/^([\p{L}_][\p{L}\p{N}_.\-]*):([\p{L}_][\p{L}\p{N}_.\-]*)$/u', $name, $match) !== 1) {
            throw new UnsupportedSchematron("The function name \"{$name}\" ({$this->line($function)}) must be a prefixed name.");
        }
        [, $prefix, $local] = $match;
        $uri = $this->namespaces[$prefix] ?? null;
        $declared = $function->lookupNamespaceURI($prefix);
        if ($uri === null || ($declared !== null && $declared !== $uri)) {
            throw new UnsupportedSchematron("The prefix of the function {$name} ({$this->line($function)}) must be bound by an ns element".($declared !== null ? ' to the same URI as in the XML' : '').'.');
        }
        if (in_array($uri, self::RESERVED_NAMESPACES, true)) {
            throw new UnsupportedSchematron("The function {$name} ({$this->line($function)}) is in the reserved namespace {$uri}.");
        }

        $arity = 0;
        foreach ($this->xsltChildren($function, "function {$name}")[0] as $child) {
            if ($child->localName === 'param') {
                $arity++;
            }
        }
        $key = StaticContext::functionKey($uri, $local, $arity);
        if (isset($this->functions[$key])) {
            throw new UnsupportedSchematron("The function {$name} is declared twice with {$arity} parameter(s) ({$this->line($function)}).");
        }

        return $key;
    }

    /**
     * @return CompiledFunction
     */
    private function userFunction(DOMElement $function): array
    {
        $name = $function->getAttribute('name');
        $owner = "function {$name}";
        [$children] = $this->xsltChildren($function, $owner);

        $params = [];
        $scope = $this->schemaLets;
        $parameterNames = [];
        while ($children !== [] && $children[0]->localName === 'param') {
            $param = array_shift($children);
            $this->checkAttributes($param);
            $paramName = $this->variableName($param, 'parameter');
            if (isset($parameterNames[$paramName])) {
                throw new UnsupportedSchematron("The parameter \"{$paramName}\" is declared twice in {$owner} ({$this->line($param)}).");
            }
            [$content, $text] = $this->xsltChildren($param, "parameter \"{$paramName}\"", allowText: true);
            if ($content !== [] || trim($text) !== '') {
                throw new UnsupportedSchematron("The parameter \"{$paramName}\" of {$owner} ({$this->line($param)}) has a default value; parameters of stylesheet functions take none.");
            }
            $parameterNames[$paramName] = true;
            $scope[] = $paramName;
            $params[] = ['name' => $paramName, 'as' => $this->sequenceType($param)];
        }

        return [
            'name' => $name,
            'as' => $this->sequenceType($function),
            'params' => $params,
            'body' => $this->instructions($children, $scope, $owner, $function),
        ];
    }

    /**
     * Compiles a function body: local variables followed by exactly one result.
     *
     * @param  list<DOMElement>  $elements
     * @param  list<string>  $scope  names of the variables in scope
     * @return list<Instruction>
     */
    private function instructions(array $elements, array $scope, string $owner, DOMElement $parent): array
    {
        $instructions = [];
        $result = false;
        foreach ($elements as $element) {
            if ($result) {
                throw $this->resultCount($owner, $element);
            }
            switch ($element->localName) {
                case 'variable':
                    $instructions[] = $this->variable($element, $scope, $owner);
                    $scope[] = $element->getAttribute('name');
                    break;
                case 'sequence':
                    $instructions[] = ['sequence', $this->sequence($element, $scope, $owner)];
                    $result = true;
                    break;
                case 'choose':
                    $instructions[] = $this->choose($element, $scope, $owner);
                    $result = true;
                    break;
                default:
                    throw $this->unsupportedElement($element);
            }
        }
        if (! $result) {
            throw $this->resultCount($owner, $parent);
        }

        return $instructions;
    }

    /**
     * @param  list<string>  $scope
     * @return Instruction
     */
    private function variable(DOMElement $variable, array $scope, string $owner): array
    {
        $this->checkAttributes($variable);
        $name = $this->variableName($variable, 'variable');
        $as = $this->sequenceType($variable);
        [$content, $text] = $this->xsltChildren($variable, "variable \"{$name}\"", allowText: true);
        $what = "the variable \"{$name}\" of {$owner}";

        if ($variable->hasAttribute('select')) {
            if ($content !== [] || trim($text) !== '') {
                throw new UnsupportedSchematron(ucfirst($what)." ({$this->line($variable)}) has both a select attribute and content.");
            }

            return ['variable', $name, $as, $this->expression($variable->getAttribute('select'), $scope, $what, $variable)];
        }
        if ($content === []) {
            // A stylesheet drops whitespace-only text nodes, so such a variable holds an empty string.
            return ['variable-text', $name, $as, trim($text, " \t\r\n") === '' ? '' : $text];
        }
        if (trim($text, " \t\r\n") !== '') {
            throw new UnsupportedSchematron(ucfirst($what)." ({$this->line($variable)}) mixes text with elements.");
        }
        if ($as === null) {
            // Without "as", the content would build a temporary tree instead of a sequence.
            throw new UnsupportedSchematron(ucfirst($what)." ({$this->line($variable)}) has element content without \"as\".");
        }
        if (count($content) !== 1 || $content[0]->localName !== 'sequence') {
            throw new UnsupportedSchematron(ucfirst($what)." ({$this->line($variable)}) must contain a single xsl:sequence.");
        }

        return ['variable', $name, $as, $this->sequence($content[0], $scope, $what)];
    }

    /**
     * @param  list<string>  $scope
     * @return array<mixed>
     */
    private function sequence(DOMElement $sequence, array $scope, string $owner): array
    {
        $this->checkAttributes($sequence);
        [$content, $text] = $this->xsltChildren($sequence, $owner, allowText: true);
        if (! $sequence->hasAttribute('select') || $content !== [] || trim($text, " \t\r\n") !== '') {
            throw new UnsupportedSchematron("The xsl:sequence of {$owner} ({$this->line($sequence)}) must have a select attribute and no content.");
        }

        return $this->expression($sequence->getAttribute('select'), $scope, $owner, $sequence);
    }

    /**
     * @param  list<string>  $scope
     * @return Instruction
     */
    private function choose(DOMElement $choose, array $scope, string $owner): array
    {
        $this->checkAttributes($choose);
        [$children] = $this->xsltChildren($choose, $owner);

        $branches = [];
        $otherwise = null;
        foreach ($children as $child) {
            if ($otherwise !== null || ! in_array($child->localName, ['when', 'otherwise'], true)) {
                throw $this->unsupportedElement($child);
            }
            $this->checkAttributes($child);
            [$body] = $this->xsltChildren($child, $owner);
            if ($child->localName === 'otherwise') {
                $otherwise = $this->instructions($body, $scope, $owner, $child);

                continue;
            }
            if (! $child->hasAttribute('test')) {
                throw new UnsupportedSchematron("An xsl:when of {$owner} ({$this->line($child)}) has no test.");
            }
            $branches[] = [
                $this->expression($child->getAttribute('test'), $scope, $owner, $child),
                $this->instructions($body, $scope, $owner, $child),
            ];
        }
        if ($branches === [] || $otherwise === null) {
            // Without xsl:otherwise a branch-free path would return the empty sequence.
            throw $this->resultCount($owner, $choose);
        }

        return ['choose', $branches, $otherwise];
    }

    private function resultCount(string $owner, DOMElement $element): UnsupportedSchematron
    {
        return new UnsupportedSchematron("Every path through {$owner} must end with exactly one result, an xsl:sequence or an xsl:choose with xsl:otherwise ({$this->line($element)}).");
    }

    private function sequenceType(DOMElement $element): ?string
    {
        if (! $element->hasAttribute('as')) {
            return null;
        }
        $as = $element->getAttribute('as');
        if (! in_array($as, self::SEQUENCE_TYPES, true)) {
            throw new UnsupportedSchematron("Unsupported sequence type \"{$as}\" of element \"{$this->elementName($element)}\" ({$this->line($element)}).");
        }

        return $as;
    }

    private function variableName(DOMElement $element, string $kind): string
    {
        $name = $element->getAttribute('name');
        if (preg_match('/^[\p{L}_][\p{L}\p{N}_.\-]*$/u', $name) !== 1) {
            throw new UnsupportedSchematron("Unsupported {$kind} name \"{$name}\" ({$this->line($element)}).");
        }

        return $name;
    }

    /**
     * @param  list<string>  $scope  names of the variables the value may use
     * @param  bool  $global  whether the let becomes a global variable (schema and pattern lets)
     * @return CompiledLet
     */
    private function let(DOMElement $let, array $scope, bool $global): array
    {
        $this->checkAttributes($let);
        $name = $this->variableName($let, 'variable');
        if (! $let->hasAttribute('value')) {
            throw new UnsupportedSchematron("The let \"{$name}\" without a value ({$this->line($let)}) is not supported.");
        }
        foreach ($let->childNodes as $node) {
            if ($node instanceof DOMElement) {
                throw $this->unsupportedElement($node);
            }
            if ($node->nodeType === XML_PI_NODE) {
                throw $this->unsupportedProcessingInstruction($node);
            }
            if (($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) && trim((string) $node->nodeValue, " \t\r\n") !== '') {
                throw new UnsupportedSchematron("The let \"{$name}\" ({$this->line($let)}) has both a value and content.");
            }
        }
        if ($global) {
            if (isset($this->globals[$name])) {
                throw new UnsupportedSchematron("The global variable \"{$name}\" is declared twice ({$this->line($let)}).");
            }
            $this->globals[$name] = true;
        }

        $value = $let->getAttribute('value');

        return ['name' => $name, 'value' => $value, 'ast' => $this->expression($value, $scope, "the let \"{$name}\"", $let)];
    }

    /**
     * Parses an XPath expression with the given variables in scope.
     *
     * @param  list<string>  $scope
     * @return array<mixed>
     */
    private function expression(string $expression, array $scope, string $owner, DOMElement $element): array
    {
        try {
            $ast = $this->parser->parse($expression, $this->context($scope));
        } catch (UnsupportedXPath|XPathSyntaxError $exception) {
            throw new UnsupportedSchematron("Cannot compile the expression \"{$expression}\" of {$owner} ({$this->line($element)}): {$exception->getMessage()}", 0, $exception);
        }
        $this->checkRegularExpressions($ast, $owner);

        return $ast;
    }

    /**
     * @param  list<string>  $scope
     */
    private function context(array $scope): StaticContext
    {
        return new StaticContext($this->namespaces, array_values(array_unique($scope)), $this->functions);
    }

    /**
     * @return CompiledPattern
     */
    private function pattern(DOMElement $pattern): array
    {
        $id = $pattern->getAttribute('id');
        if ($pattern->getAttribute('abstract') === 'true' || $pattern->hasAttribute('is-a')) {
            throw new UnsupportedSchematron("Pattern \"{$id}\" ({$this->line($pattern)}) is or instantiates an abstract pattern; compile the preprocessed schema instead.");
        }
        $this->checkAttributes($pattern);

        $scope = $this->schemaLets;
        $lets = [];
        $rules = [];
        foreach ($this->children($pattern) as $child) {
            switch ($child->localName) {
                case 'let':
                    $lets[] = $this->let($child, $scope, global: true);
                    $scope[] = $child->getAttribute('name');
                    break;
                case 'rule':
                    $rules[] = $this->rule($child, $scope);
                    break;
                default:
                    throw $this->unsupportedElement($child);
            }
        }

        return ['id' => $id, 'lets' => $lets, 'rules' => $rules];
    }

    /**
     * @param  list<string>  $scope  the schema lets and the earlier lets of the pattern
     * @return CompiledRule
     */
    private function rule(DOMElement $rule, array $scope): array
    {
        if ($rule->getAttribute('abstract') === 'true') {
            throw new UnsupportedSchematron("The abstract rule ({$this->line($rule)}) is not supported; compile the preprocessed schema instead.");
        }
        if (! $rule->hasAttribute('context')) {
            throw new UnsupportedSchematron("Rule without a context ({$this->line($rule)}).");
        }
        $this->checkAttributes($rule);

        $context = $rule->getAttribute('context');
        try {
            $match = $this->parser->parsePattern($context, $this->context($scope));
        } catch (UnsupportedXPath|XPathSyntaxError $exception) {
            throw new UnsupportedSchematron("Cannot compile the context \"{$context}\" of the rule ({$this->line($rule)}): {$exception->getMessage()}", 0, $exception);
        }
        $this->checkRegularExpressions($match, "the rule with context \"{$context}\"");

        $lets = [];
        $asserts = [];
        foreach ($this->children($rule) as $child) {
            switch ($child->localName) {
                case 'let':
                    $lets[] = $this->let($child, $scope, global: false);
                    $scope[] = $child->getAttribute('name');
                    break;
                case 'assert':
                    $asserts[] = $this->assert($child, $context, $scope);
                    break;
                default:
                    throw $this->unsupportedElement($child);
            }
        }

        return ['context' => $context, 'match' => $match, 'lets' => $lets, 'asserts' => $asserts];
    }

    /**
     * @param  list<string>  $scope  names of the variables in scope
     * @return CompiledAssert
     */
    private function assert(DOMElement $assert, string $context, array $scope): array
    {
        $id = $assert->getAttribute('id');
        if ($id === '') {
            throw new UnsupportedSchematron("Assert without an id in the rule with context \"{$context}\" ({$this->line($assert)}).");
        }
        $this->checkAttributes($assert);
        $flag = $assert->getAttribute('flag');
        if (! in_array($flag, self::FLAGS, true)) {
            throw new UnsupportedSchematron("Assert \"{$id}\" has the unsupported flag \"{$flag}\" ({$this->line($assert)}).");
        }
        if (! $assert->hasAttribute('test')) {
            throw new UnsupportedSchematron("Assert \"{$id}\" has no test ({$this->line($assert)}).");
        }

        $test = $assert->getAttribute('test');
        try {
            $ast = $this->parser->parse($test, $this->context($scope));
        } catch (UnsupportedXPath|XPathSyntaxError $exception) {
            throw new UnsupportedSchematron("Cannot compile the test \"{$test}\" of assert \"{$id}\" ({$this->line($assert)}): {$exception->getMessage()}", 0, $exception);
        }
        $this->checkRegularExpressions($ast, "assert \"{$id}\"");

        foreach ($assert->childNodes as $node) {
            if ($node instanceof DOMElement) {
                throw $this->unsupportedElement($node);
            }
            if ($node->nodeType === XML_PI_NODE) {
                throw $this->unsupportedProcessingInstruction($node);
            }
        }

        return [
            'id' => $id,
            'flag' => $flag,
            'test' => $test,
            'ast' => $ast,
            'message' => trim((string) preg_replace('/[\x20\x09\x0D\x0A]+/', ' ', $assert->textContent), " \t\r\n"),
        ];
    }

    /**
     * Translates every fn:matches pattern given as a literal now, so that a regular expression the
     * runtime cannot handle fails the build instead of a validation.
     *
     * @param  array<mixed>  $node
     */
    private function checkRegularExpressions(array $node, string $owner): void
    {
        if (($node[0] ?? null) === Op::CALL && ($node[1] ?? null) === 'fn:matches' && is_array($node[2] ?? null)) {
            $regex = self::stringLiteral($node[2][1] ?? null);
            $flags = array_key_exists(2, $node[2]) ? self::stringLiteral($node[2][2]) : '';
            if ($regex !== null && $flags !== null) {
                try {
                    Regex::toPcre($regex, $flags);
                } catch (UnsupportedXPath|XPathError $exception) {
                    throw new UnsupportedSchematron("Cannot compile the regular expression \"{$regex}\" of {$owner}: {$exception->getMessage()}", 0, $exception);
                }
            }
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->checkRegularExpressions($child, $owner);
            }
        }
    }

    private static function stringLiteral(mixed $node): ?string
    {
        return is_array($node) && ($node[0] ?? null) === Op::LITERAL && ($node[1] ?? null) === 'xs:string' && is_string($node[2] ?? null)
            ? $node[2]
            : null;
    }

    /**
     * Element children of a structural element; text and comments between them carry no meaning.
     * XSLT elements are accepted only where functions may be declared (schema level).
     *
     * @return list<DOMElement>
     */
    private function children(DOMElement $parent, bool $allowFunctions = false): array
    {
        $children = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $function = $allowFunctions && $node->namespaceURI === self::XSLT_NAMESPACE && $node->localName === 'function';
                if ($node->namespaceURI !== self::SCHEMATRON_NAMESPACE && ! $function) {
                    throw $this->unsupportedElement($node);
                }
                $children[] = $node;
            } elseif ($node->nodeType === XML_PI_NODE) {
                throw $this->unsupportedProcessingInstruction($node);
            }
        }

        return $children;
    }

    /**
     * Element children of an XSLT element, all of which must be XSLT elements, and its text.
     * Comments are skipped; text other than whitespace is refused unless allowed.
     *
     * @return array{list<DOMElement>, string}
     */
    private function xsltChildren(DOMElement $parent, string $owner, bool $allowText = false): array
    {
        $children = [];
        $text = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if ($node->namespaceURI !== self::XSLT_NAMESPACE) {
                    throw $this->unsupportedElement($node);
                }
                $children[] = $node;
            } elseif ($node->nodeType === XML_PI_NODE) {
                throw $this->unsupportedProcessingInstruction($node);
            } elseif ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $text .= $node->nodeValue;
                if (! $allowText && trim((string) $node->nodeValue, " \t\r\n") !== '') {
                    throw new UnsupportedSchematron("Unexpected text in {$owner} ({$this->line($node)}).");
                }
            }
        }

        return [$children, $text];
    }

    /**
     * Refuses any attribute the element does not accept (see ATTRIBUTES), naming it, the element
     * and the line.
     */
    private function checkAttributes(DOMElement $element): void
    {
        $allowed = self::ATTRIBUTES[$element->namespaceURI ?? ''][$element->localName ?? $element->nodeName] ?? [];
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute->namespaceURI !== null || ! in_array($attribute->localName, $allowed, true)) {
                throw new UnsupportedSchematron("Unsupported attribute \"{$attribute->nodeName}\" of element \"{$this->elementName($element)}\" ({$this->line($element)}).");
            }
        }
    }

    private function unsupportedElement(DOMElement $element): UnsupportedSchematron
    {
        return new UnsupportedSchematron("Unsupported Schematron element \"{$this->elementName($element)}\" ({$this->line($element)}).");
    }

    private function unsupportedProcessingInstruction(DOMNode $node): UnsupportedSchematron
    {
        return new UnsupportedSchematron("Unsupported processing instruction in {$this->source} ({$this->line($node)}).");
    }

    private function elementName(DOMElement $element): string
    {
        $localName = $element->localName ?? $element->nodeName;

        return $element->namespaceURI === self::SCHEMATRON_NAMESPACE ? $localName : '{'.$element->namespaceURI.'}'.$localName;
    }

    private function line(DOMNode $node): string
    {
        return "{$this->source} line {$node->getLineNo()}";
    }
}

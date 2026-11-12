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
 * Only schema, title, ns, phase, active, pattern, rule and assert are accepted, each with the
 * attributes listed in ATTRIBUTES; anything else (report, let, value-of in messages, foreign
 * elements, processing instructions, abstract patterns or rules, attributes such as
 * pattern/@documents or assert/@diagnostics) raises UnsupportedSchematron instead of being
 * ignored, because ignoring it could change a verdict or a message. Phases are not compiled: like
 * the official XSLT without a selected phase, all patterns run.
 *
 * @internal
 *
 * @phpstan-type CompiledAssert array{id: string, flag: string, test: string, ast: array<mixed>, message: string}
 * @phpstan-type CompiledRule array{context: string, match: array<mixed>, asserts: list<CompiledAssert>}
 * @phpstan-type CompiledPattern array{id: string, rules: list<CompiledRule>}
 * @phpstan-type CompiledRuleSet array{format: int, id: string, version: string, source: string, sha256: string, namespaces: array<string, string>, patterns: list<CompiledPattern>}
 */
final class Compiler
{
    public const SCHEMATRON_NAMESPACE = 'http://purl.oclc.org/dsdl/schematron';

    public const XSLT_NAMESPACE = 'http://www.w3.org/1999/XSL/Transform';

    private const FLAGS = ['fatal', 'warning', 'information'];

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

    private StaticContext $context;

    private function __construct(private readonly string $source)
    {
        $this->parser = new Parser;
        $this->context = new StaticContext([]);
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
     * @return array{namespaces: array<string, string>, patterns: list<CompiledPattern>}
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

        $namespaces = [];
        $patternElements = [];
        foreach ($this->children($schema) as $child) {
            switch ($child->localName) {
                case 'title':
                    $this->checkAttributes($child);
                    break;
                case 'ns':
                    $this->checkAttributes($child);
                    $prefix = $child->getAttribute('prefix');
                    $uri = $child->getAttribute('uri');
                    if (isset($namespaces[$prefix]) && $namespaces[$prefix] !== $uri) {
                        throw new UnsupportedSchematron("{$this->source} binds the prefix \"{$prefix}\" twice ({$this->line($child)}).");
                    }
                    $namespaces[$prefix] = $uri;
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
            $this->context = new StaticContext($namespaces);
        } catch (InvalidArgumentException $exception) {
            throw new UnsupportedSchematron("{$this->source}: {$exception->getMessage()}", 0, $exception);
        }

        return [
            'namespaces' => $namespaces,
            'patterns' => array_map($this->pattern(...), $patternElements),
        ];
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

        $rules = [];
        foreach ($this->children($pattern) as $rule) {
            if ($rule->localName !== 'rule') {
                throw $this->unsupportedElement($rule);
            }
            $rules[] = $this->rule($rule);
        }

        return ['id' => $id, 'rules' => $rules];
    }

    /**
     * @return CompiledRule
     */
    private function rule(DOMElement $rule): array
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
            $match = $this->parser->parsePattern($context, $this->context);
        } catch (UnsupportedXPath|XPathSyntaxError $exception) {
            throw new UnsupportedSchematron("Cannot compile the context \"{$context}\" of the rule ({$this->line($rule)}): {$exception->getMessage()}", 0, $exception);
        }
        $this->checkRegularExpressions($match, "the rule with context \"{$context}\"");

        $asserts = [];
        foreach ($this->children($rule) as $assert) {
            if ($assert->localName !== 'assert') {
                throw $this->unsupportedElement($assert);
            }
            $asserts[] = $this->assert($assert, $context);
        }

        return ['context' => $context, 'match' => $match, 'asserts' => $asserts];
    }

    /**
     * @return CompiledAssert
     */
    private function assert(DOMElement $assert, string $context): array
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
            $ast = $this->parser->parse($test, $this->context);
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
     *
     * @return list<DOMElement>
     */
    private function children(DOMElement $parent): array
    {
        $children = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if ($node->namespaceURI !== self::SCHEMATRON_NAMESPACE) {
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

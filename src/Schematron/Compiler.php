<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;
use IntrAgile\EInvoicing\XPath\Op;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\Regex;
use IntrAgile\EInvoicing\XPath\SequenceType;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\XPathError;
use InvalidArgumentException;
use LogicException;
use SplObjectStorage;

/**
 * Compiles a preprocessed ISO Schematron schema (abstract patterns resolved, XSLT 2.0 query
 * binding) into a plain array with every rule context and assert test parsed to an XPath AST.
 *
 * An include element is replaced by the document element of the file it names, recursively, before
 * anything else is compiled. Its href must be a relative path without a fragment; it resolves
 * against the directory of the including file and must stay inside the include root (by default
 * the directory of the top-level schema), also after symbolic links are resolved. Paths are
 * compared with "/" as the separator, and a Windows drive letter in any case. A file that is
 * reached a second time through another include (a diamond, or a sibling include) is inlined only
 * at its first include, by real path; the later include elements are removed. A file reached a
 * second time that declares global lets (schema or pattern lets, also through its own includes) is
 * refused, as the duplicate global variables of a textual include are in XSLT (XTSE0630). A file that includes itself, directly or
 * through other files, is refused. Errors in an included file name that file, relative to the
 * include root.
 *
 * Only schema, title, ns, let, phase, active, pattern, rule, assert and report are accepted, plus
 * XSLT functions at schema level and empty name (without path) and value-of elements in assert and
 * report messages, each with the attributes listed in ATTRIBUTES. A pattern may also contain title
 * and p elements, which are ignored, and the schema element may carry xsl:exclude-result-prefixes,
 * which is ignored too; anything else (other message
 * elements, foreign elements, processing instructions, abstract patterns or rules, attributes such
 * as pattern/@documents or assert/@diagnostics) raises UnsupportedSchematron instead of being ignored,
 * because ignoring it could change a verdict or a message. Phases are not compiled: like the
 * official XSLT without a selected phase, all patterns run.
 *
 * A report compiles like an assert with the extra key "report" => true: it fires when its test is
 * true instead of false. Every assert and report needs an id and a flag unless CompileOptions
 * supply them (a default flag, an id synthesized from the rule context and the test). An id
 * attribute that is present but empty is never synthesized; it is refused like a missing id. The
 * flag of a rule is not inherited by its asserts and reports: each takes only its own flag or the
 * default flag.
 *
 * The message of an assert is its text with whitespace collapsed. When the message contains name or
 * value-of elements, the assert also carries "messageParts": the text pieces, ['name' => true] for
 * each name and ['valueOf' => AST] for each value-of, whose select is parsed with the variables the
 * test sees. The static "message" then has each element replaced by the empty string.
 *
 * Every fn:document() call (in a rule context, let, test, message or function body) must have a
 * string literal argument: a relative path (no scheme, not absolute, no ".." segment, no NUL
 * character) naming a file inside the directory of the top-level schema, also for a call in an
 * included file. The file is loaded with SafeXml, so a DOCTYPE is refused, and its XML is embedded
 * under "documents" (URI as written => XML), with its SHA-256 under "documentSha256". The runtime
 * reads only these embedded copies. A schema without such calls compiles without both keys.
 *
 * Variables are scoped the way the ISO Schematron skeleton and SchXslt turn them into XSLT
 * variables: schema and pattern lets become global variables (so their names must be unique,
 * XTSE0630), rule lets become local variables of the rule template. The compiled array therefore
 * lists the pattern lets after the schema lets, in document order, as schema-level lets, and
 * leaves the lets of every pattern empty. A global let sees the global lets before it in that
 * order. A rule context sees every global let; a rule let or an assert sees those plus the
 * earlier lets of its rule. A function body sees its parameters, its earlier local variables and
 * every global let, never a rule let. Functions may call each other, and themselves, in any order.
 *
 * A function body is a list of local variables followed by exactly one result: an xsl:sequence or
 * an xsl:choose whose every branch (an xsl:otherwise is required) is again such a body. Its
 * expressions may not use the focus, which XSLT leaves absent in a function body.
 *
 * @internal
 *
 * @phpstan-type CompiledLet array{name: string, value: string, ast: array<mixed>}
 * @phpstan-type CompiledParam array{name: string, as: ?string}
 * @phpstan-type Instruction list<mixed>
 * @phpstan-type CompiledFunction array{name: string, as: ?string, params: list<CompiledParam>, body: list<Instruction>}
 * @phpstan-type MessagePart string|array{name: true}|array{valueOf: array<mixed>}
 * @phpstan-type CompiledAssert array{id: string, flag: string, test: string, ast: array<mixed>, message: string, messageParts?: list<MessagePart>, report?: true}
 * @phpstan-type CompiledRule array{context: string, match: array<mixed>, lets: list<CompiledLet>, asserts: list<CompiledAssert>}
 * @phpstan-type CompiledPattern array{id: string, lets: list<CompiledLet>, rules: list<CompiledRule>}
 * @phpstan-type CompiledRuleSet array{format: int, id: string, version: string, source: string, sha256: string, documentSha256?: array<string, string>, namespaces: array<string, string>, lets: list<CompiledLet>, functions: array<string, CompiledFunction>, patterns: list<CompiledPattern>, documents?: array<string, string>}
 */
final class Compiler
{
    public const SCHEMATRON_NAMESPACE = 'http://purl.oclc.org/dsdl/schematron';

    public const XSLT_NAMESPACE = 'http://www.w3.org/1999/XSL/Transform';

    /** The flags an assert or report may carry. */
    public const FLAGS = ['fatal', 'warning', 'information'];

    /** Functions that read the context item when called without argument. */
    private const FOCUS_FUNCTIONS = ['fn:local-name', 'fn:name', 'fn:normalize-space', 'fn:number', 'fn:string', 'fn:string-length'];

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
            'include' => ['href'],
            'let' => ['name', 'value'],
            'phase' => ['id'],
            'active' => ['pattern'],
            'pattern' => ['id'],
            'rule' => ['context', 'flag'],
            'assert' => ['id', 'test', 'flag'],
            'report' => ['id', 'test', 'flag'],
            'name' => [],
            'value-of' => ['select'],
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

    private const MISSING_INCLUDE_DIRECTORY = 'The include directory "%s" does not exist.';

    private readonly Parser $parser;

    /** @var array<string, string> prefix => URI, from the ns elements */
    private array $namespaces = [];

    /** @var array<string, true> keys of the declared functions, see StaticContext::functionKey() */
    private array $functions = [];

    /** @var list<string> names of the global lets (schema and pattern lets), in document order */
    private array $globalLets = [];

    /** @var array<string, string> name of each global let => where it is declared */
    private array $globals = [];

    /** @var array<string, string> URI as written in a document() call => XML of the file it names */
    private array $documents = [];

    /** @var array<string, DOMElement|true> real path of every file inlined so far => its inlined element; true for the top-level schema */
    private array $inlined = [];

    /** @var SplObjectStorage<DOMElement, string> document element of each included file => its name in messages */
    private SplObjectStorage $includedFiles;

    /** @var SplObjectStorage<DOMElement, string> assert or report without an id => the id taken from its message code */
    private SplObjectStorage $messageCodeIds;

    /**
     * @param  ?string  $baseDir  directory the includes of the top-level schema resolve against
     * @param  ?string  $includeRoot  directory tree every included file must stay inside
     * @param  ?string  $path  the top-level schema file, when it is one
     */
    private function __construct(
        private readonly string $source,
        private readonly ?string $baseDir = null,
        private readonly ?string $includeRoot = null,
        private readonly ?string $path = null,
        private readonly CompileOptions $options = new CompileOptions,
    ) {
        $this->parser = new Parser;
        $this->includedFiles = new SplObjectStorage;
        $this->messageCodeIds = new SplObjectStorage;
    }

    /**
     * Compiles a schema file. Its includes resolve against its directory and must stay inside
     * $includeRoot, by default that same directory.
     *
     * @return CompiledRuleSet
     *
     * @throws UnsupportedSchematron
     */
    public static function compile(string $schPath, string $id, string $version, ?string $includeRoot = null, ?CompileOptions $options = null): array
    {
        $xml = is_file($schPath) ? file_get_contents($schPath) : false;
        if ($xml === false) {
            throw new UnsupportedSchematron("Cannot read the Schematron schema \"{$schPath}\".");
        }

        return self::build(new self(basename($schPath), dirname($schPath), $includeRoot ?? dirname($schPath), $schPath, $options ?? new CompileOptions), $xml, $id, $version);
    }

    /**
     * Compiles a schema given as a string; $source names it in the compiled array and in errors.
     * Its includes resolve against $baseDir and must stay inside it; without $baseDir an include
     * is refused.
     *
     * @return CompiledRuleSet
     *
     * @throws UnsupportedSchematron
     */
    public static function compileString(string $xml, string $source, string $id, string $version, ?string $baseDir = null, ?CompileOptions $options = null): array
    {
        return self::build(new self($source, $baseDir, $baseDir, null, $options ?? new CompileOptions), $xml, $id, $version);
    }

    /**
     * @return CompiledRuleSet
     */
    private static function build(self $compiler, string $xml, string $id, string $version): array
    {
        $schema = $compiler->schema($xml);
        $compiled = [
            'format' => RuleSet::FORMAT,
            'id' => $id,
            'version' => $version,
            'source' => $compiler->source,
            'sha256' => hash('sha256', $xml),
        ];
        // Left out when empty, so a schema without document() compiles to the same bytes as before.
        if ($compiler->documents !== []) {
            $compiled['documentSha256'] = array_map(static fn (string $document): string => hash('sha256', $document), $compiler->documents);
        }
        $compiled = [...$compiled, ...$schema];
        if ($compiler->documents !== []) {
            $compiled['documents'] = $compiler->documents;
        }

        return $compiled;
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
        $this->resolveIncludes($schema);
        if ($this->options->idFromMessage) {
            $this->assignMessageCodeIds($schema);
        }

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
            $lets[] = $this->let($let, $this->globalLets, global: true);
            $this->globalLets[] = $this->variableName($let, 'variable');
        }
        // Pattern lets are global variables too; they follow the schema lets in document order.
        foreach ($patternElements as $pattern) {
            $this->checkPattern($pattern);
            foreach ($this->children($pattern) as $child) {
                if ($child->localName === 'let') {
                    $lets[] = $this->let($child, $this->globalLets, global: true);
                    $this->globalLets[] = $this->variableName($child, 'variable');
                }
            }
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
        $scope = $this->globalLets;
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
                    $scope[] = $this->variableName($element, 'variable');
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

            return ['variable', $name, $as, $this->bodyExpression($variable->getAttribute('select'), $scope, $what, $variable)];
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

        return $this->bodyExpression($sequence->getAttribute('select'), $scope, $owner, $sequence);
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
                $this->bodyExpression($child->getAttribute('test'), $scope, $owner, $child),
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
        if (! in_array($as, SequenceType::SUPPORTED, true)) {
            throw new UnsupportedSchematron("Unsupported sequence type \"{$as}\" of element \"{$this->elementName($element)}\" ({$this->line($element)}).");
        }

        return $as;
    }

    private function variableName(DOMElement $element, string $kind): string
    {
        $name = $element->getAttribute('name');
        if (preg_match('/^(?:[\p{L}_][\p{L}\p{N}_.\-]*:)?[\p{L}_][\p{L}\p{N}_.\-]*$/u', $name) !== 1) {
            throw new UnsupportedSchematron("Unsupported {$kind} name \"{$name}\" ({$this->line($element)}).");
        }
        $expanded = (new StaticContext($this->namespaces))->variableName($name);
        if ($expanded === null) {
            throw new UnsupportedSchematron("The prefix of the {$kind} name \"{$name}\" ({$this->line($element)}) is not bound by an ns element (XPST0081).");
        }

        return $expanded;
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
                throw new UnsupportedSchematron("The global variable \"{$name}\" is declared twice ({$this->globals[$name]} and {$this->line($let)}; XTSE0630).");
            }
            $this->globals[$name] = $this->line($let);
        }

        $value = $let->getAttribute('value');

        return ['name' => $name, 'value' => $value, 'ast' => $this->expression($value, $scope, "the let \"{$name}\"", $let)];
    }

    /**
     * Parses an expression of a function body, which must not depend on the focus: XSLT leaves the
     * context item, position and size absent in a stylesheet function (XPDY0002).
     *
     * @param  list<string>  $scope
     * @return array<mixed>
     */
    private function bodyExpression(string $expression, array $scope, string $owner, DOMElement $element): array
    {
        $ast = $this->expression($expression, $scope, $owner, $element);
        if (self::usesFocus($ast)) {
            throw new UnsupportedSchematron("The expression \"{$expression}\" of {$owner} ({$this->line($element)}) uses the focus (the context item, \".\", \"/\", a relative path, last() or a function such as string() without argument), which is absent in a stylesheet function.");
        }

        return $ast;
    }

    /**
     * Whether an expression reads the focus it is evaluated with. The right operand of "/" and
     * predicates get their own focus, so only their left operand or filtered expression counts.
     *
     * @param  array<mixed>  $ast
     */
    private static function usesFocus(array $ast): bool
    {
        switch ($ast[0] ?? null) {
            case Op::CONTEXT:
            case Op::ROOT:
            case Op::STEP:
                return true;
            case Op::PATH:
            case Op::FILTER:
                return is_array($ast[1] ?? null) && self::usesFocus($ast[1]);
            case Op::CALL:
                if ($ast[1] === 'fn:last' || ($ast[2] === [] && in_array($ast[1], self::FOCUS_FUNCTIONS, true))) {
                    return true;
                }
                break;
        }
        foreach ($ast as $operand) {
            if (is_array($operand) && self::containsFocus($operand)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a list of operands (expressions, or bindings and argument lists that contain them)
     * reads the focus.
     *
     * @param  array<mixed>  $operand
     */
    private static function containsFocus(array $operand): bool
    {
        if (is_string($operand[0] ?? null)) {
            return self::usesFocus($operand);
        }
        foreach ($operand as $item) {
            if (is_array($item) && self::containsFocus($item)) {
                return true;
            }
        }

        return false;
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
        $this->checkCalls($ast, $owner);

        return $ast;
    }

    /**
     * @param  list<string>  $scope
     */
    private function context(array $scope): StaticContext
    {
        return new StaticContext($this->namespaces, array_values(array_unique($scope)), $this->functions);
    }

    private function checkPattern(DOMElement $pattern): void
    {
        if ($pattern->getAttribute('abstract') === 'true' || $pattern->hasAttribute('is-a')) {
            throw new UnsupportedSchematron("Pattern \"{$pattern->getAttribute('id')}\" ({$this->line($pattern)}) is or instantiates an abstract pattern; compile the preprocessed schema instead.");
        }
        $this->checkAttributes($pattern);
    }

    /**
     * Compiles the rules of a pattern; its lets are already compiled as global lets.
     *
     * @return CompiledPattern
     */
    private function pattern(DOMElement $pattern): array
    {
        $rules = [];
        foreach ($this->children($pattern) as $child) {
            switch ($child->localName) {
                case 'title':
                case 'p':
                case 'let':
                    break;
                case 'rule':
                    $rules[] = $this->rule($child, $this->globalLets);
                    break;
                default:
                    throw $this->unsupportedElement($child);
            }
        }

        return ['id' => $pattern->getAttribute('id'), 'lets' => [], 'rules' => $rules];
    }

    /**
     * @param  list<string>  $scope  the global lets
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
        $this->checkCalls($match, "the rule with context \"{$context}\"");

        $lets = [];
        $asserts = [];
        foreach ($this->children($rule) as $child) {
            switch ($child->localName) {
                case 'let':
                    $lets[] = $this->let($child, $scope, global: false);
                    $scope[] = $this->variableName($child, 'variable');
                    break;
                case 'assert':
                case 'report':
                    $asserts[] = $this->assert($child, $context, $scope);
                    break;
                default:
                    throw $this->unsupportedElement($child);
            }
        }

        return ['context' => $context, 'match' => $match, 'lets' => $lets, 'asserts' => $asserts];
    }

    /**
     * Derives the ids of the asserts and reports without an id attribute from the "[CODE]" that
     * their message starts with: the code itself, or "CODE-1" to "CODE-n" in document order when
     * several of them share it. Asserts without such a code are left out.
     */
    private function assignMessageCodeIds(DOMElement $schema): void
    {
        $xpath = new DOMXPath($schema->ownerDocument ?? throw new LogicException('The schema has no document.'));
        $xpath->registerNamespace('s', self::SCHEMATRON_NAMESPACE);
        $nodes = $xpath->query('//s:assert[not(@id)] | //s:report[not(@id)]');
        $codes = [];
        $byCode = [];
        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $text = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    break;
                }
                if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                    $text .= $child->nodeValue;
                }
            }
            $code = CompileOptions::messageCode($text);
            if ($code !== null) {
                $byCode[$code][] = $node;
            }
        }
        foreach ($byCode as $code => $elements) {
            foreach ($elements as $index => $element) {
                $this->messageCodeIds[$element] = count($elements) > 1 ? $code.'-'.($index + 1) : (string) $code;
            }
        }
    }

    /**
     * Compiles an assert or a report.
     *
     * @param  list<string>  $scope  names of the variables in scope
     * @return CompiledAssert
     */
    private function assert(DOMElement $assert, string $context, array $scope): array
    {
        $kind = $assert->localName === 'report' ? 'report' : 'assert';
        $what = ucfirst($kind);
        $id = $assert->getAttribute('id');
        $fromMessage = isset($this->messageCodeIds[$assert]) ? $this->messageCodeIds[$assert] : null;
        if ($fromMessage !== null) {
            $id = $fromMessage;
        }
        $synthesize = ! $assert->hasAttribute('id') && $fromMessage === null && $this->options->missingIdPrefix !== null;
        if ($id === '' && ! $synthesize) {
            throw new UnsupportedSchematron("{$what} without an id in the rule with context \"{$context}\" ({$this->line($assert)}).");
        }
        $this->checkAttributes($assert);
        if (! $assert->hasAttribute('test')) {
            $label = $synthesize ? " without an id in the rule with context \"{$context}\"" : " \"{$id}\"";

            throw new UnsupportedSchematron("{$what}{$label} has no test ({$this->line($assert)}).");
        }
        $test = $assert->getAttribute('test');
        if ($synthesize) {
            $id = CompileOptions::synthesizedId($this->options->missingIdPrefix, $context, $test, $this->options->normalizeTestWhitespace);
        }

        if ($assert->hasAttribute('flag')) {
            $flag = $assert->getAttribute('flag');
        } elseif ($this->options->defaultFlag !== null) {
            $flag = $this->options->defaultFlag;
        } else {
            throw new UnsupportedSchematron("{$what} \"{$id}\" has no flag ({$this->line($assert)}).");
        }
        if (! in_array($flag, self::FLAGS, true)) {
            throw new UnsupportedSchematron("{$what} \"{$id}\" has the unsupported flag \"{$flag}\" ({$this->line($assert)}).");
        }

        try {
            $ast = $this->parser->parse($test, $this->context($scope));
        } catch (UnsupportedXPath|XPathSyntaxError $exception) {
            throw new UnsupportedSchematron("Cannot compile the test \"{$test}\" of {$kind} \"{$id}\" ({$this->line($assert)}): {$exception->getMessage()}", 0, $exception);
        }
        $this->checkCalls($ast, "{$kind} \"{$id}\"");

        $parts = $this->messageParts($assert, "{$kind} \"{$id}\"", $scope);
        $compiled = [
            'id' => $id,
            'flag' => $flag,
            'test' => $test,
            'ast' => $ast,
            'message' => self::collapse(implode('', array_filter($parts, is_string(...)))),
        ];
        if (count(array_filter($parts, is_array(...))) > 0) {
            $compiled['messageParts'] = $parts;
        }
        if ($kind === 'report') {
            $compiled['report'] = true;
        }

        return $compiled;
    }

    /**
     * Splits the content of an assert or report into text pieces (adjacent text and CDATA joined,
     * comments skipped) and its name and value-of elements, in document order.
     *
     * @param  string  $owner  the assert or report, such as 'assert "BR-01"', for messages
     * @param  list<string>  $scope  names of the variables the assert test sees
     * @return list<MessagePart>
     */
    private function messageParts(DOMElement $assert, string $owner, array $scope): array
    {
        /** @var list<MessagePart> $parts */
        $parts = [];
        $text = '';
        foreach ($assert->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                throw $this->unsupportedProcessingInstruction($node);
            }
            if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $text .= $node->nodeValue;

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->namespaceURI !== self::SCHEMATRON_NAMESPACE || ! in_array($node->localName, ['name', 'value-of'], true)) {
                throw $this->unsupportedElement($node);
            }
            $this->checkAttributes($node);
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    throw $this->unsupportedElement($child);
                }
                if ($child->nodeType !== XML_COMMENT_NODE) {
                    throw new UnsupportedSchematron("The {$node->localName} element in the message of {$owner} ({$this->line($node)}) must be empty.");
                }
            }
            if ($text !== '') {
                $parts[] = $text;
                $text = '';
            }
            if ($node->localName === 'name') {
                $parts[] = ['name' => true];
            } elseif ($node->hasAttribute('select')) {
                $parts[] = ['valueOf' => $this->expression($node->getAttribute('select'), $scope, "the message of {$owner}", $node)];
            } else {
                throw new UnsupportedSchematron("A value-of without a select in the message of {$owner} ({$this->line($node)}).");
            }
        }
        if ($text !== '') {
            $parts[] = $text;
        }

        return $parts;
    }

    /**
     * Collapses runs of XML whitespace to one space and trims the result, as messages are reported.
     */
    public static function collapse(string $message): string
    {
        return trim((string) preg_replace('/[\x20\x09\x0D\x0A]+/', ' ', $message), " \t\r\n");
    }

    /**
     * Translates every fn:matches pattern given as a literal now, so that a regular expression the
     * runtime cannot handle fails the build instead of a validation, and embeds the file of every
     * fn:document() call.
     *
     * @param  array<mixed>  $node
     */
    private function checkCalls(array $node, string $owner): void
    {
        if (($node[0] ?? null) === Op::CALL && ($node[1] ?? null) === 'fn:document') {
            $uri = is_array($node[2] ?? null) ? self::stringLiteral($node[2][0] ?? null) : null;
            if ($uri === null) {
                throw new UnsupportedSchematron("The document() argument must be a string literal in {$owner}, since documents are embedded when the rule set is compiled.");
            }
            $this->embedDocument($uri, $owner);
        }
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
                $this->checkCalls($child, $owner);
            }
        }
    }

    /**
     * Loads the file a document() URI names, relative to the directory of the top-level schema,
     * and keeps its XML for the compiled "documents".
     */
    private function embedDocument(string $uri, string $owner): void
    {
        if (isset($this->documents[$uri])) {
            return;
        }
        $what = "The document \"{$uri}\" read in {$owner}";
        $problem = match (true) {
            $uri === '' => 'is empty',
            str_contains($uri, "\0") => 'contains a NUL character',
            preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*:~', $uri) === 1 => 'has a URI scheme; only a relative path to a file next to the schema is allowed',
            preg_match('~^[/\\\\]~', $uri) === 1 => 'is an absolute path; only a relative path to a file next to the schema is allowed',
            in_array('..', preg_split('~[/\\\\]~', $uri) ?: [], true) => 'has a ".." segment; only files inside the schema directory are allowed',
            $this->baseDir === null => 'cannot be resolved: document() needs a base directory; use compile()',
            default => null,
        };
        if ($problem !== null) {
            throw new UnsupportedSchematron("{$what} {$problem}.");
        }

        $root = $this->realDirectory((string) $this->baseDir, 'The schema directory "%s" that document() reads from does not exist.');
        $real = realpath($root.'/'.$uri);
        if ($real === false || ! is_file($real)) {
            throw new UnsupportedSchematron("{$what} does not exist as a file in {$root}.");
        }
        if (! self::isInside($real, $root)) {
            throw new UnsupportedSchematron("{$what} resolves to {$real}, outside {$root}.");
        }
        $xml = file_get_contents($real);
        if ($xml === false) {
            throw new UnsupportedSchematron("{$what} cannot be read.");
        }
        try {
            SafeXml::load($xml);
        } catch (UnsupportedDocument|UnsafeXml $exception) {
            throw new UnsupportedSchematron("{$what} cannot be loaded: {$exception->getMessage()}", 0, $exception);
        }
        $this->documents[$uri] = $xml;
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
            // Only affects how a stylesheet is serialized, which this compiler does not do.
            if ($element->localName === 'schema' && $attribute->namespaceURI === self::XSLT_NAMESPACE && $attribute->localName === 'exclude-result-prefixes') {
                continue;
            }
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

    /**
     * Where a node is: the top-level source or the included file it comes from, and its line.
     */
    private function line(DOMNode $node): string
    {
        $source = $this->source;
        for ($ancestor = $node; $ancestor !== null; $ancestor = $ancestor->parentNode) {
            if ($ancestor instanceof DOMElement && isset($this->includedFiles[$ancestor])) {
                $source = $this->includedFiles[$ancestor];
                break;
            }
        }

        return "{$source} line {$node->getLineNo()}";
    }

    /**
     * Replaces every include element below $element by the document element of the file it names,
     * recursively.
     *
     * @param  list<string>  $stack  real paths of the files being included, outermost first
     */
    private function resolveIncludes(DOMElement $element, ?string $dir = null, array $stack = []): void
    {
        if ($dir === null && $this->baseDir !== null) {
            // Resolved only when an include needs it, so a missing directory is reported for what
            // reads from it.
            $dir = $this->baseDir;
            if ($this->path !== null && ($top = realpath($this->path)) !== false) {
                $stack = [$top];
                $this->inlined[$top] = true;
            }
        }
        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($child->namespaceURI === self::SCHEMATRON_NAMESPACE && $child->localName === 'include') {
                $included = $this->includeFile($child, $dir, $stack);
                if ($included !== null) {
                    [$child, $childDir, $childStack] = $included;
                    $this->resolveIncludes($child, $childDir, $childStack);
                }
            } else {
                $this->resolveIncludes($child, $dir, $stack);
            }
        }
    }

    /**
     * Replaces one include element by the document element of the file it names, or removes it
     * when that file is already inlined elsewhere.
     *
     * @param  list<string>  $stack
     * @return array{DOMElement, string, list<string>}|null the included element, its directory and the extended stack; null when removed
     */
    private function includeFile(DOMElement $include, ?string $dir, array $stack): ?array
    {
        $this->checkAttributes($include);
        if ($dir === null || $this->includeRoot === null) {
            throw new UnsupportedSchematron("The include ({$this->line($include)}) cannot be resolved: an include needs a base directory; use compile().");
        }
        $href = $include->getAttribute('href');
        if ($href === '') {
            throw new UnsupportedSchematron("The include ({$this->line($include)}) has no href.");
        }
        if (str_contains(rawurldecode($href), "\0")) {
            throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) contains a NUL character.");
        }
        if (str_contains($href, '#')) {
            throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) has a fragment identifier; only whole files can be included.");
        }
        if (preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*:|^[/\\\\]|[?\\\\]~', $href) === 1) {
            throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) must be a relative path to a local file.");
        }

        $root = $this->realDirectory($this->includeRoot, self::MISSING_INCLUDE_DIRECTORY);
        $target = self::normalizePath($this->realDirectory($dir, self::MISSING_INCLUDE_DIRECTORY).'/'.rawurldecode($href));
        $real = realpath($target);
        if (! self::isInside($target, $root) || ($real !== false && ! self::isInside($real, $root))) {
            throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) points outside {$root}.");
        }
        $xml = $real !== false && is_file($real) ? file_get_contents($real) : false;
        if ($real === false || $xml === false) {
            throw new UnsupportedSchematron("Cannot read the included Schematron \"{$href}\" ({$this->line($include)}).");
        }
        if (in_array($real, $stack, true)) {
            throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) includes itself: ".implode(' -> ', array_map(fn (string $path): string => $this->relativeName($path, $root), [...$stack, $real])).'.');
        }
        if (isset($this->inlined[$real])) {
            $first = $this->inlined[$real];
            if ($first instanceof DOMElement && (self::declaresGlobalLet($first, $first->parentNode) || self::declaresGlobalLet($first, $include->parentNode))) {
                $name = $this->relativeName($real, $root);

                throw new UnsupportedSchematron("The include \"{$href}\" ({$this->line($include)}) includes {$name} a second time, and that file declares global variables (schema or pattern lets); a textual include would declare them twice, which XSLT refuses (XTSE0630, duplicate global variable).");
            }
            $include->parentNode?->removeChild($include);

            return null;
        }

        $name = $this->relativeName($real, $root);
        try {
            $included = SafeXml::load($xml)->documentElement;
        } catch (UnsupportedDocument|UnsafeXml $exception) {
            throw new UnsupportedSchematron("The included Schematron {$name} cannot be loaded: {$exception->getMessage()}", 0, $exception);
        }
        $document = $include->ownerDocument;
        $parent = $include->parentNode;
        if ($included === null || $document === null || $parent === null) {
            throw new UnsupportedSchematron("The included Schematron {$name} has no document element.");
        }
        $imported = $document->importNode($included, true);
        if (! $imported instanceof DOMElement) {
            throw new UnsupportedSchematron("The included Schematron {$name} cannot be imported.");
        }
        $parent->replaceChild($imported, $include);
        $this->includedFiles[$imported] = $name;
        $this->inlined[$real] = $imported;

        return [$imported, dirname($real), [...$stack, $real]];
    }

    /**
     * Whether an inlined file, placed under $parent, declares a global variable: a let (its own
     * includes already resolved) that is not inside a rule, neither within the file nor, for a
     * file whose document element is a let, above it.
     */
    private static function declaresGlobalLet(DOMElement $element, ?DOMNode $parent): bool
    {
        $lets = $element->localName === 'let' ? [$element] : iterator_to_array($element->getElementsByTagNameNS(self::SCHEMATRON_NAMESPACE, 'let'));
        foreach ($lets as $let) {
            $inRule = false;
            for ($ancestor = $let === $element ? $parent : $let->parentNode; $ancestor instanceof DOMElement; $ancestor = $ancestor === $element ? null : $ancestor->parentNode) {
                if ($ancestor->namespaceURI === self::SCHEMATRON_NAMESPACE && $ancestor->localName === 'rule') {
                    $inRule = true;
                    break;
                }
            }
            if (! $inRule) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $message  the refusal when the directory does not exist, with %s for its path
     */
    private function realDirectory(string $directory, string $message): string
    {
        $real = realpath($directory);
        if ($real === false || ! is_dir($real)) {
            throw new UnsupportedSchematron(sprintf($message, $directory));
        }

        return $real;
    }

    private function relativeName(string $path, string $root): string
    {
        return self::isInside($path, $root) ? substr(self::comparable($path), strlen(rtrim(self::comparable($root), '/')) + 1) : $path;
    }

    /**
     * Resolves "." and ".." segments of an absolute path without touching the file system, with
     * "/" as the separator. A Windows drive letter is kept, in upper case.
     */
    private static function normalizePath(string $path): string
    {
        $path = self::comparable($path);
        $drive = preg_match('~^[A-Z]:(?=/|$)~', $path, $match) === 1 ? $match[0] : '';
        $segments = [];
        foreach (explode('/', substr($path, strlen($drive))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return $drive.'/'.implode('/', $segments);
    }

    /**
     * Whether $path lies below the directory $root, both absolute and already resolved.
     */
    private static function isInside(string $path, string $root): bool
    {
        return str_starts_with(self::comparable($path), rtrim(self::comparable($root), '/').'/');
    }

    /**
     * A path with "/" as the separator and the drive letter of a Windows path in upper case. A
     * path with a drive letter is a Windows path on any host; on Windows, where both separators
     * are accepted, every backslash becomes "/".
     */
    private static function comparable(string $path): string
    {
        $windows = preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
        if ($windows || DIRECTORY_SEPARATOR === '\\') {
            $path = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $path);
        }

        return $windows ? ucfirst($path) : $path;
    }
}

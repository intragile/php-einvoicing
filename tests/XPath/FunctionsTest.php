<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    private const LISTS = <<<'XML'
        <lists>
          <cl id="01"><e value="71"/><e value="071"/><e value=" A "/><e value="A"/><e/></cl>
          <cl id="2.0"><e value="NaN"/><e value="1e0"/><e value="x"/></cl>
          <cl id="INF"><e value="-0"/><e value="NaN"/><e value="0"/></cl>
          <cl id=" 4 "/>
        </lists>
        XML;

    private DOMDocument $document;

    private DocumentIndex $codedb;

    private Evaluator $evaluator;

    protected function setUp(): void
    {
        $this->document = SafeXml::load('<r><code>A</code><code>Z</code></r>');
        $this->codedb = DocumentIndex::build(SafeXml::load('<codedb><cl id="1"><enumeration value="A"/><enumeration value="B"/></cl><cl id="2"><enumeration value="C"/></cl></codedb>'));
        $documents = [
            'codedb.xml' => $this->codedb,
            'lists.xml' => DocumentIndex::build(SafeXml::load(self::LISTS)),
        ];
        $this->evaluator = new Evaluator(DocumentIndex::build($this->document), static fn (string $uri): ?DocumentIndex => $documents[$uri] ?? null);
    }

    public function test_document_returns_the_document_node_of_the_embedded_document(): void
    {
        $result = $this->evaluate("document('codedb.xml')");

        self::assertSame([$this->codedb->document], $result);
    }

    public function test_paths_and_predicates_run_inside_the_embedded_document(): void
    {
        self::assertSame(['A', 'B'], $this->values("document('codedb.xml')/codedb/cl[@id=1]/enumeration/@value"));
        self::assertSame(['A', 'B', 'C'], $this->values("document('codedb.xml')//enumeration/@value"));
        self::assertSame(['C'], $this->values("document('codedb.xml')//cl[last()]/enumeration/@value"));
        self::assertSame(['1', '2'], $this->values("document('codedb.xml')//enumeration[@value = 'C']/preceding::cl/@id | document('codedb.xml')//cl[2]/@id"));
        self::assertSame(['A'], $this->values("/r/code[. = document('codedb.xml')/codedb/cl[@id=1]/enumeration/@value]"));
    }

    public function test_root_of_an_embedded_node_is_its_own_document(): void
    {
        self::assertSame(['1', '2'], $this->values("document('codedb.xml')//enumeration[/codedb]/../@id"));
        self::assertSame([], $this->values("document('codedb.xml')//enumeration[/r]/../@id"));
    }

    public function test_union_of_nodes_from_two_documents_keeps_each_document_in_order(): void
    {
        $result = $this->evaluate("document('codedb.xml')//cl | /r/code");

        self::assertCount(4, $result);
        $names = array_map(static fn ($node): string => $node instanceof DOMElement ? ($node->localName ?? $node->nodeName) : '?', $result);
        self::assertSame(['code', 'code', 'cl', 'cl'], $names);
    }

    public function test_unknown_document_is_fodc0002(): void
    {
        try {
            $this->evaluate("document('other.xml')");
            self::fail('No error.');
        } catch (XPathError $error) {
            self::assertSame('FODC0002', $error->errorCode);
            self::assertStringContainsString('other.xml', $error->getMessage());
        }
    }

    public function test_document_without_embedded_documents_is_fodc0002(): void
    {
        $evaluator = new Evaluator(DocumentIndex::build($this->document));

        $this->expectException(XPathError::class);
        $this->expectExceptionMessage('codedb.xml');

        $evaluator->evaluate((new Parser)->parse("document('codedb.xml')", new StaticContext([])), $this->document);
    }

    /**
     * @return iterable<string, array{string, list<string>|string}>
     */
    public static function attributeLookups(): iterable
    {
        $cl = "document('lists.xml')/lists/cl";
        yield 'number against a padded integer' => ["{$cl}[@id = 1]/@id", ['01']];
        yield 'number against a decimal lexical' => ["{$cl}[@id = 2]/@id", ['2.0']];
        yield 'decimal' => ["{$cl}[@id = 1.0]/@id", ['01']];
        yield 'number against whitespace' => ["{$cl}[@id = 4]/@id", [' 4 ']];
        yield 'infinity' => ["{$cl}[@id = 1 div 0e0]/@id", ['INF']];
        yield 'string does not cast' => ["{$cl}[@id = '1']/@id", []];
        yield 'operand first' => ["{$cl}['2.0' = @id]/@id", ['2.0']];
        yield 'several values in document order' => ["{$cl}[1]/e[@value = ('A', '71')]/@value", ['71', 'A']];
        yield 'duplicate values' => ["{$cl}[1]/e[@value = ('A', 'A')]/@value", ['A']];
        yield 'untyped values from a variable' => ["{$cl}[1]/e[@value = \$codes]/@value", ['A']];
        yield 'empty operand' => ["{$cl}[1]/e[@value = ()]/@value", []];
        yield 'strings mixed with numbers' => ["{$cl}[@id = (1, '2.0')]/@id", ['01', '2.0']];
        yield 'negative zero equals zero' => ["{$cl}[3]/e[@value = 0]/@value", ['-0', '0']];
        yield 'NaN equals nothing' => ["{$cl}[3]/e[@value = 0e0 div 0e0]/@value", []];
        yield 'number against an uncastable value' => ["{$cl}[1]/e[@value = 71]/@value", 'FORG0001'];
        yield 'number against NaN and an uncastable value' => ["{$cl}[2]/e[@value = 1]/@value", 'FORG0001'];
        yield 'boolean operand' => ["{$cl}[@id = true()]/@id", 'FORG0001'];
        yield 'error in the operand' => ["{$cl}[@id = 1 idiv 0]/@id", 'FOAR0001'];
        yield 'error in the operand without children' => ["document('lists.xml')/lists/none[@id = 1 idiv 0]", []];
        yield 'namespaced name' => ["document('lists.xml')/lists/cl[@xml:id = 1]", []];
    }

    /**
     * @param  list<string>|string  $expected  the selected values or the error code
     */
    #[DataProvider('attributeLookups')]
    public function test_attribute_lookup_in_an_embedded_document_matches_the_predicate(string $expression, array|string $expected): void
    {
        $variables = ['codes' => $this->evaluator->evaluate((new Parser)->parse('/r/code', new StaticContext([])), $this->document)];

        // A second predicate keeps the step off the attribute index.
        $scanned = preg_replace('~\](/@\w+)?$~', '][true()]$1', $expression, 1);

        self::assertSame($expected, $this->outcome($expression, $variables));
        self::assertSame($expected, $this->outcome((string) $scanned, $variables), (string) $scanned);
    }

    public function test_document_takes_exactly_one_argument(): void
    {
        $this->expectException(UnsupportedXPath::class);

        (new Parser)->parse("document('a.xml', /)", new StaticContext([]));
    }

    /**
     * @return list<\DOMNode|AtomicValue>
     */
    private function evaluate(string $expression): array
    {
        return $this->evaluator->evaluate((new Parser)->parse($expression, new StaticContext([])), $this->document);
    }

    /**
     * The string values of the result, or the error code.
     *
     * @param  array<string, list<\DOMNode|AtomicValue>>  $variables
     * @return list<string>|string
     */
    private function outcome(string $expression, array $variables): array|string
    {
        try {
            $result = $this->evaluator->evaluate((new Parser)->parse($expression, new StaticContext(['xml' => 'http://www.w3.org/XML/1998/namespace'], array_keys($variables))), $this->document, $variables);
        } catch (XPathError $error) {
            return $error->errorCode;
        }

        return array_map(static fn ($item): string => $item instanceof \DOMNode ? (string) $item->nodeValue : $item->lexical(), $result);
    }

    /**
     * @return list<string>
     */
    private function values(string $expression): array
    {
        return array_map(static fn ($item): string => $item instanceof \DOMNode ? (string) $item->nodeValue : $item->lexical(), $this->evaluate($expression));
    }
}

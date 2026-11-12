<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\XPath\Canonical;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\Regex;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\XPathError;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
    private const XML = <<<'XML'
        <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
                 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
                 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
          <cbc:ID>INV-1</cbc:ID>
          <cac:PaymentMeans><cbc:PaymentMeansCode>30</cbc:PaymentMeansCode></cac:PaymentMeans>
          <cac:PaymentMeans><cbc:PaymentMeansCode>58</cbc:PaymentMeansCode></cac:PaymentMeans>
          <cac:InvoiceLine>
            <cbc:ID>1</cbc:ID>
            <cbc:LineExtensionAmount currencyID="EUR" extra="x">100.00</cbc:LineExtensionAmount>
          </cac:InvoiceLine>
          <cac:InvoiceLine>
            <cbc:ID>2</cbc:ID>
            <cbc:LineExtensionAmount currencyID="HUF">50</cbc:LineExtensionAmount>
          </cac:InvoiceLine>
        </Invoice>
        XML;

    private DOMDocument $document;

    private DocumentIndex $index;

    private Evaluator $evaluator;

    protected function setUp(): void
    {
        $this->document = new DOMDocument;
        $this->document->loadXML(self::XML);
        $this->index = DocumentIndex::build($this->document);
        $this->evaluator = new Evaluator($this->index);
    }

    public function test_child_axis_selects_elements_by_namespace_uri(): void
    {
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[1], node:/Invoice[1]/cac:InvoiceLine[2])', $this->canonical('/ubl:Invoice/cac:InvoiceLine'));
        self::assertSame('()', $this->canonical('/ubl:Invoice/cbc:InvoiceLine'));
    }

    public function test_attribute_axis(): void
    {
        self::assertSame(
            '(node:/Invoice[1]/cac:InvoiceLine[1]/cbc:LineExtensionAmount[1]/@currencyID, node:/Invoice[1]/cac:InvoiceLine[1]/cbc:LineExtensionAmount[1]/@extra)',
            $this->canonical('@*', $this->node('//cbc:LineExtensionAmount[1]')),
        );
    }

    public function test_parent_of_an_attribute_is_its_element(): void
    {
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[2]/cbc:LineExtensionAmount[1])', $this->canonical('..', $this->node('//cac:InvoiceLine[2]/cbc:LineExtensionAmount/@currencyID')));
    }

    public function test_ancestor_axis_returns_document_order(): void
    {
        self::assertSame('(node:/Invoice[1], node:/Invoice[1]/cac:InvoiceLine[2])', $this->canonical('ancestor::*', $this->node('//cac:InvoiceLine[2]/cbc:ID')));
    }

    public function test_preceding_axis_excludes_ancestors_and_returns_document_order(): void
    {
        self::assertSame(
            '(node:/Invoice[1]/cac:PaymentMeans[1]/cbc:PaymentMeansCode[1], node:/Invoice[1]/cac:PaymentMeans[2]/cbc:PaymentMeansCode[1])',
            $this->canonical('preceding::cbc:PaymentMeansCode', $this->node('//cac:InvoiceLine[1]/cbc:ID')),
        );
        self::assertSame('(node:/Invoice[1]/cbc:ID[1])', $this->canonical('preceding::cbc:ID', $this->node('//cac:InvoiceLine[1]/cbc:ID')));
        self::assertSame('(xs:boolean:true)', $this->canonical('preceding::*[1] = 58', $this->node('//cac:InvoiceLine[1]')));
    }

    public function test_double_slash_from_an_inner_node_searches_the_whole_document(): void
    {
        self::assertSame('(xs:integer:3)', $this->canonical('count(//cbc:ID)', $this->node('//cac:InvoiceLine[2]')));
        self::assertSame('(xs:integer:1)', $this->canonical('count(.//cbc:ID)', $this->node('//cac:InvoiceLine[2]')));
        self::assertSame('(xs:integer:1)', $this->canonical('count(descendant::cbc:ID)', $this->node('//cac:InvoiceLine[2]')));
    }

    public function test_descendant_axis_without_name_index(): void
    {
        self::assertSame('(xs:integer:3)', $this->canonical('count(descendant-or-self::*)', $this->node('//cac:InvoiceLine[1]')));
        self::assertSame('(xs:integer:2)', $this->canonical('count(descendant::*[self::cbc:ID or self::cbc:LineExtensionAmount])', $this->node('//cac:InvoiceLine[1]')));
    }

    public function test_boolean_and_numeric_predicates(): void
    {
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[2])', $this->canonical('//cac:InvoiceLine[cbc:LineExtensionAmount/@currencyID = \'HUF\']'));
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[2])', $this->canonical('/*/cac:InvoiceLine[2]'));
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[2])', $this->canonical('/*/cac:InvoiceLine[1 + 1]'));
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[2]/cbc:ID[1])', $this->canonical("//cac:InvoiceLine/cbc:ID[. = '2'][1]"));
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[1])', $this->canonical('(//cbc:ID/..)[2]'));
    }

    public function test_effective_boolean_value_of_two_atomic_values_is_forg0006(): void
    {
        try {
            $this->evaluator->effectiveBoolean($this->parse('(1, 2)'), $this->document);
            self::fail('Expected FORG0006.');
        } catch (XPathError $error) {
            self::assertSame('FORG0006', $error->errorCode);
        }
    }

    public function test_effective_boolean_value_of_nodes_and_values(): void
    {
        self::assertTrue($this->evaluator->effectiveBoolean($this->parse('//cbc:ID'), $this->document));
        self::assertFalse($this->evaluator->effectiveBoolean($this->parse('//cbc:Nothing'), $this->document));
        self::assertFalse($this->evaluator->effectiveBoolean($this->parse('0.0'), $this->document));
    }

    public function test_variables_are_visible_inside_quantified_expressions(): void
    {
        $ast = $this->parse('every $line in //cac:InvoiceLine satisfies $line/cbc:LineExtensionAmount/@currencyID = $currencies', ['currencies']);
        $currencies = $this->evaluator->evaluate($this->parse("('EUR', 'HUF')"), $this->document);

        self::assertTrue($this->evaluator->effectiveBoolean($ast, $this->document, ['currencies' => $currencies]));
        self::assertFalse($this->evaluator->effectiveBoolean($ast, $this->document, ['currencies' => array_slice($currencies, 0, 1)]));
    }

    public function test_and_or_raise_an_operand_error_only_when_the_other_does_not_decide(): void
    {
        self::assertFalse($this->evaluator->effectiveBoolean($this->parse("false() and xs:decimal('x') = 1"), $this->document));
        self::assertTrue($this->evaluator->effectiveBoolean($this->parse("true() or xs:decimal('x') = 1"), $this->document));
        self::assertTrue($this->evaluator->effectiveBoolean($this->parse("xs:decimal('x') = 1 or true()"), $this->document));
        self::assertFalse($this->evaluator->effectiveBoolean($this->parse("xs:decimal('x') = 1 and false()"), $this->document));

        $this->expectException(XPathError::class);
        $this->evaluator->effectiveBoolean($this->parse("xs:decimal('x') = 1 or false()"), $this->document);
    }

    public function test_pattern_predicate_does_not_swallow_engine_errors(): void
    {
        // A data-driven dynamic error only drops the node.
        self::assertSame([], $this->matchPattern('cbc:LineExtensionAmount[xs:decimal(@currencyID) > 0]'));
        self::assertCount(1, $this->matchPattern("cac:InvoiceLine[xs:decimal(cbc:LineExtensionAmount) > 0 and cbc:LineExtensionAmount/@currencyID = 'HUF']"));

        // An engine limitation propagates.
        try {
            $this->matchPattern("cbc:ID[matches(., concat('\\p{IsBasic', 'Latin}'))]");
            self::fail('Expected UnsupportedXPath.');
        } catch (UnsupportedXPath $error) {
            self::assertStringContainsString('Unicode block escape', $error->getMessage());
        }

        // A non-recoverable dynamic error propagates.
        $failing = new class implements AtomicValue
        {
            public function type(): string
            {
                return 'xs:string';
            }

            public function lexical(): string
            {
                throw new XPathError('INTRAGILE_RECURSION', 'Recursion too deep.');
            }
        };
        try {
            $this->matchPattern("cac:InvoiceLine[string(\$v) = 'x']", ['v' => [$failing]]);
            self::fail('Expected INTRAGILE_RECURSION.');
        } catch (XPathError $error) {
            self::assertSame('INTRAGILE_RECURSION', $error->errorCode);
            self::assertFalse($error->isRecoverable());
        }
    }

    public function test_recoverable_error_codes(): void
    {
        self::assertTrue((new XPathError('FORG0001', 'x'))->isRecoverable());
        self::assertTrue((new XPathError('XPTY0004', 'x'))->isRecoverable());
        self::assertFalse((new XPathError('XPST0008', 'x'))->isRecoverable());
        self::assertFalse((new XPathError('INTRAGILE_RECURSION', 'x'))->isRecoverable());
        self::assertSame(
            ['FORG0001', 'XPTY0004', 'FORG0006', 'FOAR0001', 'FOAR0002', 'FOCA0002', 'FOCH0002', 'FORX0002', 'FORX0003', 'FORX0004', 'FODT0001', 'FOCA0001', 'FOCA0003'],
            XPathError::RECOVERABLE,
        );
    }

    public function test_index_orders_attributes_after_their_element_and_before_children(): void
    {
        $amount = $this->node('//cac:InvoiceLine[1]/cbc:LineExtensionAmount');
        self::assertInstanceOf(DOMElement::class, $amount);
        $currency = $amount->getAttributeNode('currencyID');
        $text = $amount->firstChild;
        self::assertInstanceOf(DOMNode::class, $currency);
        self::assertInstanceOf(DOMNode::class, $text);

        self::assertSame($this->index->order($amount) + 1, $this->index->order($currency));
        self::assertLessThan($this->index->order($text), $this->index->order($currency));
        self::assertSame([$amount, $currency], $this->index->sort([$currency, $amount, $currency]));
        self::assertCount(3, $this->index->descendantsNamed(ProbeCases::NAMESPACES['cbc'], 'ID'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedRegexes(): iterable
    {
        yield 'name start escape' => ['\i\c*', 'construct "\i'];
        yield 'class subtraction' => ['[a-z-[aeiou]]', 'character class subtraction'];
        yield 'unicode block' => ['\p{IsBasicLatin}', 'Unicode block'];
    }

    #[DataProvider('unsupportedRegexes')]
    public function test_regex_constructs_outside_pcre_are_unsupported(string $regex, string $message): void
    {
        $this->expectException(UnsupportedXPath::class);
        $this->expectExceptionMessage($message);
        Regex::toPcre($regex, '');
    }

    public function test_regex_flags_and_escapes(): void
    {
        self::assertSame(1, preg_match(Regex::toPcre('^a b$', 'x'), 'ab'));
        self::assertSame(1, preg_match(Regex::toPcre('^\d+/\s$', ''), "12/\t"));
        self::assertSame(0, preg_match(Regex::toPcre('^\w$', ''), '_'));
        self::assertSame(1, preg_match(Regex::toPcre('^[\w]$', ''), 'é'));
        self::assertSame(0, preg_match(Regex::toPcre('a$', ''), "a\n"));

        try {
            Regex::toPcre('a', 'q');
            self::fail('Expected FORX0001.');
        } catch (XPathError $error) {
            self::assertSame('FORX0001', $error->errorCode);
        }
    }

    public function test_invalid_regex_is_forx0002(): void
    {
        try {
            Regex::toPcre('(a', '');
            self::fail('Expected FORX0002.');
        } catch (XPathError $error) {
            self::assertSame('FORX0002', $error->errorCode);
        }
    }

    /**
     * @param  list<string>  $variables
     * @return array<mixed>
     */
    private function parse(string $expression, array $variables = []): array
    {
        return (new Parser)->parse($expression, new StaticContext(ProbeCases::NAMESPACES, $variables));
    }

    /**
     * @param  array<string, list<DOMNode|AtomicValue>>  $variables
     * @return list<DOMNode|AtomicValue>
     */
    private function matchPattern(string $pattern, array $variables = []): array
    {
        $ast = (new Parser)->parsePattern($pattern, new StaticContext(ProbeCases::NAMESPACES, array_keys($variables)));

        return $this->evaluator->matchPattern($ast, $this->document, $variables);
    }

    private function canonical(string $expression, ?DOMNode $context = null): string
    {
        return Canonical::of($this->evaluator->evaluate($this->parse($expression), $context ?? $this->document), ProbeCases::NAMESPACES);
    }

    private function node(string $path): DOMNode
    {
        $xpath = new DOMXPath($this->document);
        foreach (ProbeCases::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        $nodes = $xpath->query($path);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node : throw new LogicException("No node at {$path}.");
    }
}

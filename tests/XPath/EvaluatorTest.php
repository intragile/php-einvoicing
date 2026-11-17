<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\XPath\Canonical;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\Regex;
use IntrAgile\EInvoicing\XPath\StaticContext;
use IntrAgile\EInvoicing\XPath\Thunk;
use IntrAgile\EInvoicing\XPath\UserFunction;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
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

    public function test_descendant_name_absent_from_the_document_from_an_element_is_empty(): void
    {
        self::assertSame('()', $this->canonical('.//cbc:Note', $this->node('//cac:InvoiceLine[1]')));
        self::assertSame('(xs:boolean:false)', $this->canonical('exists(..//cbc:Note)', $this->node('//cac:InvoiceLine[1]')));
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function focusFreePredicates(): array
    {
        return [
            'integer variable' => ['for $i in 2 return (10, 20, 30)[$i]', '(xs:integer:20)'],
            'arithmetic' => ['for $i in 1 return (10, 20, 30)[$i + 2]', '(xs:integer:30)'],
            'negated' => ['for $i in -3 return (10, 20, 30)[-$i]', '(xs:integer:30)'],
            'zero' => ['for $i in 0 return (10, 20, 30)[$i]', '()'],
            'past the end' => ['for $i in 4 return (10, 20, 30)[$i]', '()'],
            'integral decimal' => ['for $i in 2.0 return (10, 20, 30)[$i]', '(xs:integer:20)'],
            'fractional decimal' => ['for $i in 1.5 return (10, 20, 30)[$i]', '()'],
            'integral double' => ['for $i in 3e0 return (10, 20, 30)[$i]', '(xs:integer:30)'],
            'fractional double' => ['for $i in 2.5e0 return (10, 20, 30)[$i]', '()'],
            'string keeps all' => ["for \$s in 'x' return (10, 20)[\$s]", '(xs:integer:10, xs:integer:20)'],
            'empty string keeps none' => ["for \$s in '' return (10, 20)[\$s]", '()'],
            'nodes' => ['for $i in 2 return /*/cac:InvoiceLine[$i]', '(node:/Invoice[1]/cac:InvoiceLine[2])'],
            'filter of nodes' => ['for $i in 1 return (//cbc:ID/..)[$i + 1]', '(node:/Invoice[1]/cac:InvoiceLine[1])'],
        ];
    }

    #[DataProvider('focusFreePredicates')]
    public function test_focus_free_predicates_select_like_positional_ones(string $expression, string $expected): void
    {
        self::assertSame($expected, $this->canonical($expression));
    }

    public function test_indexing_a_long_sequence_in_a_loop_is_linear(): void
    {
        $sequence = $this->evaluator->evaluate($this->parse('1 to 20000'), $this->document);

        $start = hrtime(true);
        $result = $this->evaluator->evaluate($this->parse('count(for $i in 1 to 20000 return $s[$i])', ['s']), $this->document, ['s' => $sequence]);

        self::assertSame('(xs:integer:20000)', Canonical::of($result, ProbeCases::NAMESPACES));
        self::assertLessThan(5.0, (hrtime(true) - $start) / 1e9);
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
        self::assertFalse((new XPathError('INTRAGILE_RANGE', 'x'))->isRecoverable());
        self::assertFalse((new XPathError('XTDE0640', 'x'))->isRecoverable());
        self::assertSame(
            ['FORG0001', 'XPTY0004', 'FORG0006', 'FOAR0001', 'FOAR0002', 'FOCA0002', 'FOCH0001', 'FOCH0002', 'FORX0002', 'FORX0003', 'FORX0004', 'FODT0001', 'FOCA0001', 'FOCA0003', 'XPTY0018', 'XPTY0019', 'XPTY0020', 'XPDY0050', 'XTTE0570', 'XTTE0780'],
            XPathError::RECOVERABLE,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function patternTypeErrors(): iterable
    {
        yield 'mixed path result' => ['cac:InvoiceLine[./(cbc:ID, 1)]', 'XPTY0018'];
        yield 'path over atomics' => ['cac:InvoiceLine[(1, 2)/cbc:ID]', 'XPTY0019'];
        yield 'axis step on an atomic' => ['cac:InvoiceLine[(1, 2)[cbc:ID]]', 'XPTY0020'];
        yield 'root of an atomic' => ['cac:InvoiceLine[(1, 2)[/]]', 'XPDY0050'];
        yield 'illegal XML character from data' => ["cac:InvoiceLine[codepoints-to-string(xs:integer(cbc:ID) - 1) = '']", 'FOCH0001'];
    }

    #[DataProvider('patternTypeErrors')]
    public function test_pattern_predicate_type_errors_do_not_match(string $pattern, string $code): void
    {
        $predicate = substr($pattern, strlen('cac:InvoiceLine['), -1);
        try {
            $this->evaluator->evaluate($this->parse($predicate), $this->node('//cac:InvoiceLine[1]'));
            self::fail("Expected {$code}.");
        } catch (XPathError $error) {
            self::assertSame($code, $error->errorCode);
        }

        self::assertSame([], $this->matchPattern($pattern));
    }

    public function test_if_evaluates_only_the_chosen_branch(): void
    {
        self::assertSame('(xs:integer:1)', $this->canonical("if (//cbc:ID) then 1 else xs:decimal('x')"));
        self::assertSame('(xs:string:no)', $this->canonical("if (//cbc:Nothing) then xs:decimal('x') else 'no'"));
    }

    public function test_for_binds_variables_over_outer_ones(): void
    {
        $ast = $this->parse('for $i in (1 to $n) return $i * $factor', ['n', 'factor']);
        $result = $this->evaluator->evaluate($ast, $this->document, [
            'n' => $this->evaluator->evaluate($this->parse('3'), $this->document),
            'factor' => $this->evaluator->evaluate($this->parse('10'), $this->document),
        ]);

        self::assertSame('(xs:integer:10, xs:integer:20, xs:integer:30)', Canonical::of($result, ProbeCases::NAMESPACES));
        self::assertSame('(xs:integer:2, xs:integer:1)', $this->canonical('for $i in (1, 2) return (for $i in $i return 3 - $i)'));
    }

    public function test_huge_range_is_a_non_recoverable_error(): void
    {
        self::assertSame('(xs:integer:100000)', $this->canonical('count(1 to 100000)'));

        try {
            // 100,001 items: refused before any item is created.
            $this->canonical('count(0 to 100000)');
            self::fail('Expected INTRAGILE_RANGE.');
        } catch (XPathError $error) {
            self::assertSame('INTRAGILE_RANGE', $error->errorCode);
            self::assertFalse($error->isRecoverable());
        }
    }

    public function test_user_function_call_needs_a_function_table(): void
    {
        $ast = (new Parser)->parse('u:gln(.)', new StaticContext(['u' => 'utils'], [], ['{utils}gln#1' => true]));

        $this->expectException(UnsupportedXPath::class);
        $this->expectExceptionMessage('user function {utils}gln#1 is not available');
        $this->evaluator->evaluate($ast, $this->document);
    }

    public function test_unknown_user_function_with_a_function_table(): void
    {
        $functions = self::functions('<xsl:function name="u:other"><xsl:param name="v"/><xsl:sequence select="$v"/></xsl:function>');
        $ast = (new Parser)->parse('u:gln(.)', new StaticContext(['u' => 'utils'], [], ['{utils}gln#1' => true]));

        $this->expectException(UnsupportedXPath::class);
        $this->expectExceptionMessage('user function {utils}gln#1 is not available');
        $this->evaluator->withFunctions($functions)->evaluate($ast, $this->document);
    }

    public function test_parameter_and_result_conversion(): void
    {
        $functions = self::functions(
            '<xsl:function name="u:dec"><xsl:param name="v" as="xs:decimal"/><xsl:sequence select="$v * 2"/></xsl:function>'
            .'<xsl:function name="u:bool" as="xs:boolean"><xsl:param name="v"/><xsl:sequence select="$v"/></xsl:function>'
            .'<xsl:function name="u:opt" as="xs:string?"><xsl:param name="v" as="xs:string?"/><xsl:sequence select="$v"/></xsl:function>',
        );

        self::assertSame('(xs:decimal:200)', $this->call('u:dec((//cbc:LineExtensionAmount)[1])', $functions));
        self::assertSame('(xs:integer:4)', $this->call('u:dec(2)', $functions));
        self::assertSame('error:FORG0001', $this->call('u:dec(/*/cbc:ID)', $functions));
        self::assertSame('error:XPTY0004', $this->call('u:dec(//cbc:LineExtensionAmount)', $functions));
        self::assertSame('error:XPTY0004', $this->call('u:dec(())', $functions));
        self::assertSame('error:XPTY0004', $this->call("u:dec('2')", $functions));
        self::assertSame('error:XTTE0780', $this->call('u:bool(1)', $functions));
        self::assertSame('(xs:boolean:true)', $this->call('u:bool(true())', $functions));
        self::assertSame('()', $this->call('u:opt(())', $functions));
        self::assertSame('(xs:string:INV-1)', $this->call('u:opt(/*/cbc:ID)', $functions));
    }

    public function test_local_variables_are_lazy_and_typed(): void
    {
        $functions = self::functions(
            '<xsl:function name="u:guarded" as="xs:boolean"><xsl:param name="v"/>'
            .'<xsl:variable name="d" select="xs:decimal($v)"/><xsl:variable name="chars">0123456789</xsl:variable>'
            .'<xsl:choose><xsl:when test="translate($v, $chars, \'\') = \'\'"><xsl:sequence select="$d gt 0"/></xsl:when>'
            .'<xsl:otherwise><xsl:sequence select="false()"/></xsl:otherwise></xsl:choose></xsl:function>'
            .'<xsl:function name="u:typed"><xsl:param name="v"/><xsl:variable name="n" as="xs:integer" select="$v"/><xsl:sequence select="$n"/></xsl:function>',
        );

        self::assertSame('(xs:boolean:true)', $this->call('u:guarded(//cac:InvoiceLine[1]/cbc:ID)', $functions));
        self::assertSame('(xs:boolean:false)', $this->call('u:guarded(/*/cbc:ID)', $functions));
        self::assertSame('(xs:integer:58)', $this->call('u:typed(//cbc:PaymentMeansCode[. = 58])', $functions));
        self::assertSame('error:XTTE0570', $this->call("u:typed('58')", $functions));
    }

    public function test_recursion_deeper_than_the_limit_is_a_non_recoverable_error(): void
    {
        $functions = self::functions('<xsl:function name="u:depth" as="xs:integer"><xsl:param name="n" as="xs:integer"/>'
            .'<xsl:sequence select="if ($n le 0) then 0 else 1 + u:depth($n - 1)"/></xsl:function>');

        // 2,000 nested calls: u:depth(1999) down to u:depth(0).
        self::assertSame('(xs:integer:1999)', $this->call('u:depth(1999)', $functions));
        self::assertSame('error:INTRAGILE_RECURSION', $this->call('u:depth(2000)', $functions));
        // The depth is back to zero after the error.
        self::assertSame('(xs:integer:1999)', $this->call('u:depth(1999)', $functions));

        $evaluator = $this->evaluator->withFunctions($functions);
        $context = new StaticContext(ProbeCases::NAMESPACES + ['u' => 'utils'], [], array_fill_keys(array_keys($functions), true));
        self::assertCount(2, $evaluator->matchPattern((new Parser)->parsePattern('cac:InvoiceLine[u:depth(3) = 3]', $context), $this->document));
        try {
            $evaluator->matchPattern((new Parser)->parsePattern('cac:InvoiceLine[u:depth(9999) = 3]', $context), $this->document);
            self::fail('Expected INTRAGILE_RECURSION.');
        } catch (XPathError $error) {
            self::assertSame('INTRAGILE_RECURSION', $error->errorCode);
            self::assertFalse($error->isRecoverable());
        }
    }

    public function test_variables_may_be_thunks(): void
    {
        $count = 0;
        $thunk = new Thunk(function () use (&$count): array {
            $count++;

            return $this->evaluator->evaluate($this->parse('3'), $this->document);
        });

        self::assertSame('(xs:integer:6)', Canonical::of($this->evaluator->evaluate($this->parse('$t + $t', ['t']), $this->document, ['t' => $thunk])));
        self::assertSame('(xs:integer:9)', Canonical::of($this->evaluator->evaluate($this->parse('$t * 3', ['t']), $this->document, ['t' => $thunk])));
        self::assertSame(1, $count);
    }

    public function test_sibling_axes_skip_attributes_and_keep_axis_order(): void
    {
        self::assertSame('()', $this->canonical('following-sibling::node()', $this->node('//cac:InvoiceLine[1]/cbc:LineExtensionAmount/@currencyID')));
        self::assertSame('()', $this->canonical('preceding-sibling::node()', $this->node('//cac:InvoiceLine[1]/cbc:LineExtensionAmount/@extra')));
        self::assertSame('(node:/Invoice[1]/cac:PaymentMeans[2])', $this->canonical('preceding-sibling::*[1]', $this->node('//cac:InvoiceLine[1]')));
        self::assertSame('(node:/Invoice[1]/cac:InvoiceLine[1]/cbc:LineExtensionAmount[1])', $this->canonical('following-sibling::*', $this->node('//cac:InvoiceLine[1]/cbc:ID')));
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
            Regex::toPcre('a', 'z');
            self::fail('Expected FORX0001.');
        } catch (XPathError $error) {
            self::assertSame('FORX0001', $error->errorCode);
        }
    }

    public function test_xpath_30_flag_q_is_unsupported(): void
    {
        $this->expectException(UnsupportedXPath::class);
        $this->expectExceptionMessage('flag q');

        Regex::toPcre('a', 'q');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function replacements(): iterable
    {
        yield 'plain text' => ['abc', 9, 'abc'];
        yield 'group references' => ['$2-$1', 2, '${2}-${1}'];
        yield 'whole match' => ['[$0]', 0, '[${0}]'];
        yield 'escaped dollar' => ['\\$1', 1, '\\$1'];
        yield 'escaped backslash' => ['\\\\', 0, '\\\\'];
        yield 'group then literal digit' => ['$12', 2, '${1}2'];
        yield 'two-digit group' => ['$12', 12, '${12}'];
        yield 'missing group is kept for preg_replace' => ['$3', 1, '${3}'];
        yield 'non-ASCII text' => ['ß$1😀', 1, 'ß${1}😀'];
    }

    #[DataProvider('replacements')]
    public function test_replacement_strings(string $xpath, int $groups, string $pcre): void
    {
        self::assertSame($pcre, Regex::toReplacement($xpath, $groups));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReplacements(): iterable
    {
        yield 'lone dollar' => ['a$'];
        yield 'dollar letter' => ['$x'];
        yield 'lone backslash' => ['a\\'];
        yield 'backslash letter' => ['\\n'];
    }

    #[DataProvider('invalidReplacements')]
    public function test_invalid_replacement_strings_are_forx0004(string $xpath): void
    {
        try {
            Regex::toReplacement($xpath);
            self::fail('Expected FORX0004.');
        } catch (XPathError $error) {
            self::assertSame('FORX0004', $error->errorCode);
        }
    }

    public function test_group_count(): void
    {
        self::assertSame(0, Regex::groupCount(Regex::toPcre('a|b', '')));
        self::assertSame(3, Regex::groupCount(Regex::toPcre('(a)|(b(c)?)', 'i')));
    }

    public function test_last_outside_a_predicate_or_path_step_is_unsupported(): void
    {
        $this->expectException(UnsupportedXPath::class);
        $this->expectExceptionMessage('fn:last()');

        $this->evaluator->evaluate($this->parse('last()'), $this->document);
    }

    public function test_parenthesised_pattern_matches_only_when_its_predicate_holds(): void
    {
        $pattern = '(/ubl:Invoice/cac:PaymentMeans[cbc:PaymentMeansCode = 58] | /ubl:Invoice/cbc:ID)[$enabled and true()]';
        $selected = static fn (bool $enabled): array => ['enabled' => [new BooleanValue($enabled)]];

        self::assertSame(
            '(node:/Invoice[1]/cbc:ID[1], node:/Invoice[1]/cac:PaymentMeans[2])',
            Canonical::of($this->matchPattern($pattern, $selected(true))),
        );
        self::assertSame([], $this->matchPattern($pattern, $selected(false)));
    }

    public function test_parenthesised_pattern_predicate_error_is_no_match(): void
    {
        $pattern = '(/ubl:Invoice/cac:InvoiceLine)[xs:decimal(cbc:LineExtensionAmount/@currencyID) > 0]';

        self::assertSame([], $this->matchPattern($pattern));
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

    /**
     * @return array<string, UserFunction>
     */
    private static function functions(string $declarations): array
    {
        $compiled = Compiler::compileString(
            '<schema xmlns="'.Compiler::SCHEMATRON_NAMESPACE.'" xmlns:xsl="'.Compiler::XSLT_NAMESPACE.'" xmlns:xs="'.StaticContext::XS.'"'
            .' xmlns:u="utils" queryBinding="xslt2"><ns prefix="u" uri="utils"/>'.$declarations.'</schema>',
            'functions.sch',
            'functions',
            '0',
        );

        return array_map(static fn (array $function): UserFunction => new UserFunction($function), $compiled['functions']);
    }

    /**
     * The canonical result of an expression calling the given functions, or "error:" and the code.
     *
     * @param  array<string, UserFunction>  $functions
     */
    private function call(string $expression, array $functions): string
    {
        $ast = (new Parser)->parse($expression, new StaticContext(ProbeCases::NAMESPACES + ['u' => 'utils'], [], array_fill_keys(array_keys($functions), true)));
        try {
            return Canonical::of($this->evaluator->withFunctions($functions)->evaluate($ast, $this->document), ProbeCases::NAMESPACES);
        } catch (XPathError $error) {
            return Canonical::error($error);
        }
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

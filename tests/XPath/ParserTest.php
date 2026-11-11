<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Exception\XPathSyntaxError;
use IntrAgile\EInvoicing\XPath\Op;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const SCHEMATRON = 'http://purl.oclc.org/dsdl/schematron';

    public function test_or_binds_weaker_than_and(): void
    {
        self::assertSame(
            [Op::OR, self::child('a'), [Op::AND, self::child('b'), self::child('c')]],
            self::parse('a or b and c'),
        );
    }

    public function test_multiplication_binds_tighter_than_addition(): void
    {
        self::assertSame(
            [Op::ARITH, '+', self::int('1'), [Op::ARITH, '*', self::int('2'), self::int('3')]],
            self::parse('1 + 2 * 3'),
        );
    }

    public function test_unary_minus_binds_tighter_than_binary_minus(): void
    {
        self::assertSame(
            [Op::ARITH, '-', [Op::NEG, self::int('1')], self::int('1')],
            self::parse('- 1 - 1'),
        );
    }

    public function test_path_binds_tighter_than_union(): void
    {
        self::assertSame(
            [Op::UNION, self::child('a'), [Op::PATH, self::child('b'), self::child('c')]],
            self::parse('a | b / c'),
        );
    }

    public function test_paths_are_left_associative(): void
    {
        self::assertSame(
            [Op::PATH, [Op::PATH, [Op::ROOT], self::child('a')], self::child('b')],
            self::parse('/a/b'),
        );
    }

    public function test_double_slash_expands_to_descendant_or_self(): void
    {
        $descendantOrSelf = [Op::STEP, 'descendant-or-self', ['node'], []];

        self::assertSame(
            [Op::PATH, self::child('a'), [Op::PATH, $descendantOrSelf, self::child('b')]],
            self::parse('a//b'),
        );
        self::assertSame(
            [Op::PATH, [Op::ROOT], [Op::PATH, $descendantOrSelf, self::child('b')]],
            self::parse('//b'),
        );
    }

    public function test_abbreviated_steps(): void
    {
        self::assertSame([Op::STEP, 'parent', ['node'], []], self::parse('..'));
        self::assertSame([Op::CONTEXT], self::parse('.'));
        self::assertSame([Op::ROOT], self::parse('/'));
        self::assertSame([Op::STEP, 'attribute', ['name', '', 'schemeID'], []], self::parse('@schemeID'));
        self::assertSame([Op::STEP, 'attribute', ['any-name'], []], self::parse('@*'));
        self::assertSame([Op::STEP, 'child', ['any-name'], []], self::parse('*'));
        self::assertSame([Op::STEP, 'child', ['ns-any', self::CBC], []], self::parse('cbc:*'));
        self::assertSame([Op::STEP, 'child', ['text'], []], self::parse('text()'));
    }

    public function test_prefixes_resolved_to_uris(): void
    {
        self::assertSame([Op::STEP, 'child', ['name', self::CBC, 'ID'], []], self::parse('cbc:ID'));

        $unrelatedPrefix = new StaticContext(['foo' => self::CBC]);
        self::assertSame(
            [Op::STEP, 'child', ['name', self::CBC, 'ID'], []],
            (new Parser)->parse('foo:ID', $unrelatedPrefix),
        );
    }

    public function test_explicit_axes(): void
    {
        self::assertSame(
            [Op::STEP, 'ancestor', ['name', self::CAC, 'Price'], []],
            self::parse('ancestor::cac:Price'),
        );
        self::assertSame([Op::STEP, 'self', ['node'], []], self::parse('self::node()'));
        self::assertSame(
            [Op::GCMP, '=', [Op::PATH, [Op::STEP, 'preceding', ['name', '', 'x'], []], [Op::CONTEXT]], [Op::CONTEXT]],
            self::parse('preceding::x/. = .'),
        );
    }

    public function test_function_call_as_path_step(): void
    {
        self::assertSame(
            [
                Op::PATH,
                [Op::STEP, 'parent', ['node'], []],
                [Op::CALL, 'xs:decimal', [self::child('Percent', self::CBC)]],
            ],
            self::parse('../xs:decimal(cbc:Percent)'),
        );
    }

    public function test_unprefixed_functions_resolve_to_fn(): void
    {
        self::assertSame(
            [Op::CALL, 'fn:not', [[Op::CALL, 'fn:normalize-space', [self::child('ID', self::CBC)]]]],
            self::parse('not (normalize-space(cbc:ID))'),
        );
        self::assertSame([Op::CALL, 'fn:true', []], self::parse('fn:true()'));
    }

    public function test_predicates_on_steps_and_filters(): void
    {
        self::assertSame(
            [Op::STEP, 'child', ['name', self::CBC, 'ID'], [[Op::STEP, 'attribute', ['name', '', 'schemeID'], []]]],
            self::parse('cbc:ID[@schemeID]'),
        );
        self::assertSame(
            [Op::FILTER, self::child('a'), [self::int('1')]],
            self::parse('(a)[1]'),
        );
    }

    public function test_quantified_expression(): void
    {
        self::assertSame(
            [Op::QUANT, 'every', [['rate', self::child('x')]], [Op::GCMP, '=', [Op::VAR, 'rate'], self::child('y')]],
            self::parse('every $rate in x satisfies $rate = y'),
        );
        self::assertSame(
            [Op::QUANT, 'some', [['a', self::child('x')], ['b', [Op::VAR, 'a']]], [Op::VAR, 'b']],
            self::parse('some $a in x, $b in $a satisfies $b'),
        );
    }

    public function test_cast_sets_allow_empty(): void
    {
        self::assertSame([Op::CAST, 'xs:decimal', true, self::child('a')], self::parse('a cast as xs:decimal?'));
        self::assertSame([Op::CAST, 'xs:boolean', false, self::child('a')], self::parse('a cast as xs:boolean'));
    }

    public function test_comparisons(): void
    {
        self::assertSame([Op::VCMP, 'eq', self::child('a'), self::child('b')], self::parse('a eq b'));
        self::assertSame([Op::GCMP, '<=', self::child('a'), self::int('2')], self::parse('a<=2'));
        self::assertSame([Op::GCMP, '!=', self::child('a'), self::child('b')], self::parse('a != b'));
    }

    public function test_operator_names_are_element_names_in_operand_position(): void
    {
        self::assertSame(
            [Op::AND, self::child('and'), [Op::PATH, self::child('div'), self::child('or')]],
            self::parse('and and div/or'),
        );
        self::assertSame([Op::ARITH, 'div', self::child('a'), self::child('mod')], self::parse('a div mod'));
    }

    public function test_string_escapes(): void
    {
        self::assertSame([Op::LITERAL, 'xs:string', "it's"], self::parse("'it''s'"));
        self::assertSame([Op::LITERAL, 'xs:string', 'a"b'], self::parse('"a""b"'));
    }

    public function test_numeric_literal_types(): void
    {
        self::assertSame([Op::LITERAL, 'xs:integer', '1'], self::parse('1'));
        self::assertSame([Op::LITERAL, 'xs:decimal', '1.5'], self::parse('1.5'));
        self::assertSame([Op::LITERAL, 'xs:decimal', '.5'], self::parse('.5'));
        self::assertSame([Op::LITERAL, 'xs:double', '1e3'], self::parse('1e3'));
    }

    public function test_sequences(): void
    {
        self::assertSame([Op::SEQUENCE, []], self::parse('()'));
        self::assertSame([Op::SEQUENCE, [self::int('1'), self::child('a')]], self::parse('(1, a)'));
    }

    public function test_escaped_entities_are_not_decoded(): void
    {
        $this->expectException(XPathSyntaxError::class);

        self::parse('a &lt; b');
    }

    public function test_ast_round_trips_through_var_export(): void
    {
        $ast = self::parse("every \$r in //cbc:ID[1] satisfies (\$r cast as xs:decimal?) * 2 > -1.5 or 'x'");

        self::assertSame($ast, eval('return '.var_export($ast, true).';'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedExpressions(): iterable
    {
        yield 'let' => ['let $x := 1 return $x', 'let'];
        yield 'unknown prefix function' => ['foo:bar()', 'foo'];
        yield 'unknown function' => ['tokenize(a, b)', 'fn:tokenize'];
        yield 'collation argument' => ["contains(a, 'b', 'c')", 'fn:contains'];
        yield 'for' => ['for $x in a return $x', 'for'];
        yield 'if' => ['if (a) then b else c', 'if'];
        yield 'instance of' => ['a instance of xs:string', 'instance of'];
        yield 'treat as' => ['a treat as xs:string', 'treat as'];
        yield 'castable' => ['a castable as xs:decimal', 'castable as'];
        yield 'intersect' => ['a intersect b', 'intersect'];
        yield 'except' => ['a except b', 'except'];
        yield 'following-sibling axis' => ['following-sibling::a', 'following-sibling'];
        yield 'unknown name prefix' => ['foo:ID', 'foo'];
        yield 'range' => ['1 to 3', 'to'];
        yield 'node comparison' => ['a is b', 'is'];
    }

    #[DataProvider('unsupportedExpressions')]
    public function test_unsupported_constructs_fail_loudly(string $expression, string $construct): void
    {
        try {
            self::parse($expression);
            self::fail("Expected UnsupportedXPath for {$expression}");
        } catch (UnsupportedXPath $e) {
            self::assertStringContainsString($construct, $e->getMessage());
            self::assertStringContainsString('offset', $e->getMessage());
        }
    }

    public function test_incomplete_expression_reports_offset(): void
    {
        try {
            self::parse('cbc:ID =');
            self::fail('Expected XPathSyntaxError');
        } catch (XPathSyntaxError $e) {
            self::assertSame(8, $e->offset);
            self::assertStringContainsString('offset 8', $e->getMessage());
        }
    }

    public function test_pattern_keeps_absolute_alternatives(): void
    {
        self::assertSame(self::parse('/a/b'), self::parsePattern('/a/b'));
        self::assertSame(self::parse('//a'), self::parsePattern('//a'));
    }

    public function test_pattern_makes_relative_alternatives_descendants_of_the_root(): void
    {
        self::assertSame(self::parse('//a/b'), self::parsePattern('a/b'));
        self::assertSame(self::parse('//a[@x]/b/c'), self::parsePattern('a[@x]/b/c'));
        self::assertSame(self::parse('//@x'), self::parsePattern('@x'));
        self::assertSame(self::parse('/a | //b/c | //d'), self::parsePattern('/a | b/c | //d'));
        self::assertSame(self::parse('//a/b | //c/d/e'), self::parsePattern('a/b | c/d/e'));
    }

    public function test_pattern_rejects_non_pattern_expressions(): void
    {
        $this->expectException(UnsupportedXPath::class);

        self::parsePattern('ancestor::a');
    }

    public function test_parses_every_cen_expression(): void
    {
        $parser = new Parser;
        $count = 0;

        foreach (['ubl', 'cii'] as $syntax) {
            $document = new DOMDocument;
            self::assertTrue($document->load(Fixtures::schematron($syntax), LIBXML_NONET));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('sch', self::SCHEMATRON);

            $namespaces = [];
            foreach (self::elements($xpath, '//sch:ns') as $ns) {
                $namespaces[$ns->getAttribute('prefix')] = $ns->getAttribute('uri');
            }
            $context = new StaticContext($namespaces);

            foreach (self::elements($xpath, '//sch:rule') as $rule) {
                self::assertNotEmpty($parser->parsePattern($rule->getAttribute('context'), $context));
                $count++;
            }
            foreach (self::elements($xpath, '//sch:assert | //sch:report') as $assert) {
                self::assertNotEmpty($parser->parse($assert->getAttribute('test'), $context));
                $count++;
            }
        }

        self::assertSame(104 + 982 + 115 + 810, $count);
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query): array
    {
        $result = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $result[] = $node;
            }
        }

        return $result;
    }

    /**
     * @return array<mixed>
     */
    private static function parse(string $expression): array
    {
        return (new Parser)->parse($expression, self::context());
    }

    /**
     * @return array<mixed>
     */
    private static function parsePattern(string $pattern): array
    {
        return (new Parser)->parsePattern($pattern, self::context());
    }

    private static function context(): StaticContext
    {
        return new StaticContext(['cbc' => self::CBC, 'cac' => self::CAC]);
    }

    /**
     * @return array<mixed>
     */
    private static function child(string $local, string $uri = ''): array
    {
        return [Op::STEP, 'child', ['name', $uri, $local], []];
    }

    /**
     * @return array<mixed>
     */
    private static function int(string $lexical): array
    {
        return [Op::LITERAL, 'xs:integer', $lexical];
    }
}

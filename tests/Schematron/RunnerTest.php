<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\LocationPath;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Schematron\RunResult;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    private const NS = 'urn:test:runner';

    private const DOCUMENT = <<<'XML'
        <t:Root xmlns:t="urn:test:runner">
          <t:Item kind="x"><t:Amount>10</t:Amount></t:Item>
          <t:Item kind="y"><t:Amount>abc</t:Amount></t:Item>
        </t:Root>
        XML;

    public function test_first_matching_rule_wins_within_a_pattern_but_other_patterns_see_the_node(): void
    {
        $result = self::runMini([
            'first' => [
                ["t:Item[@kind = 'x']", [['A-1', 'fatal', 'false()']]],
                ['t:Item', [['A-2', 'fatal', 'false()']]],
            ],
            'second' => [
                ['t:Item', [['B-1', 'warning', 'false()']]],
            ],
        ]);

        self::assertSame([
            'A-1 /t:Root[1]/t:Item[1]',
            'A-2 /t:Root[1]/t:Item[2]',
            'B-1 /t:Root[1]/t:Item[1]',
            'B-1 /t:Root[1]/t:Item[2]',
        ], self::summary($result));
        self::assertSame([], $result->dynamicErrors);
    }

    public function test_visits_attribute_contexts(): void
    {
        $result = self::runMini(['attributes' => [['@kind', [['ATTR', 'warning', ". = 'x'"]]]]]);

        self::assertSame(['ATTR /t:Root[1]/t:Item[2]/@kind'], self::summary($result));
    }

    public function test_failing_assert_yields_a_finding_with_its_location(): void
    {
        $result = self::runMini(['amounts' => [['t:Item/t:Amount', [['POS', 'fatal', 'string-length(.) < 3', 'The amount must be short.']]]]]);

        self::assertEquals(
            [new Finding('POS', Severity::Fatal, '/t:Root[1]/t:Item[2]/t:Amount[1]', 'The amount must be short.', 'string-length(.) < 3')],
            $result->findings,
        );
    }

    public function test_multi_step_relative_contexts_match_at_any_depth(): void
    {
        $result = self::runMini(['nested' => [
            ["t:Item[@kind = 'y']/t:Amount | t:Missing/t:Amount", [['Y', 'fatal', 'false()']]],
            ['t:Root/t:Item/@kind', [['K', 'fatal', 'false()']]],
        ]]);

        self::assertSame([
            'K /t:Root[1]/t:Item[1]/@kind',
            'K /t:Root[1]/t:Item[2]/@kind',
            'Y /t:Root[1]/t:Item[2]/t:Amount[1]',
        ], self::summary($result));
    }

    public function test_dynamic_error_is_reported_and_the_next_assert_still_runs(): void
    {
        $result = self::runMini(['amounts' => [['t:Amount', [
            ['DEC', 'fatal', 'xs:decimal(.) > 0'],
            ['NEXT', 'warning', 'false()'],
        ]]]]);

        self::assertCount(1, $result->dynamicErrors);
        $error = $result->dynamicErrors[0];
        self::assertInstanceOf(DynamicError::class, $error);
        self::assertSame('DEC', $error->ruleId);
        self::assertSame('FORG0001', $error->errorCode);
        self::assertSame('/t:Root[1]/t:Item[2]/t:Amount[1]', $error->location);
        self::assertSame(['NEXT /t:Root[1]/t:Item[1]/t:Amount[1]', 'NEXT /t:Root[1]/t:Item[2]/t:Amount[1]'], self::summary($result));
    }

    public function test_node_whose_rule_context_predicate_fails_does_not_match_that_rule(): void
    {
        $result = self::runMini(['contexts' => [
            ['t:Item[xs:decimal(t:Amount) > 0]', [['POSITIVE', 'fatal', 'false()']]],
            ['t:Item', [['OTHER', 'fatal', 'false()']]],
        ]]);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame(['POSITIVE /t:Root[1]/t:Item[1]', 'OTHER /t:Root[1]/t:Item[2]'], self::summary($result));
    }

    public function test_error_in_a_nested_predicate_fails_the_whole_context_predicate(): void
    {
        $result = self::runMini(['contexts' => [
            ['t:Root[count(t:Item[xs:decimal(t:Amount) > 0]) = 1]', [['NESTED', 'fatal', 'false()']]],
            ['/t:Root//t:Item[@kind = \'y\'][xs:decimal(t:Amount) > 0] | t:Item[@kind = \'x\']', [['UNION', 'fatal', 'false()']]],
        ]]);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame(['UNION /t:Root[1]/t:Item[1]'], self::summary($result));
    }

    public function test_unused_failing_let_reports_nothing(): void
    {
        $result = self::runSchematron(<<<'SCH'
            <pattern id="p">
              <rule context="t:Amount">
                <let name="amount" value="xs:decimal(.)"/>
                <assert id="UNUSED" test="true()" flag="fatal">unused</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], $result->findings);
    }

    public function test_failing_let_reported_at_each_referencing_assert(): void
    {
        $result = self::runSchematron(<<<'SCH'
            <pattern id="p">
              <rule context="t:Amount">
                <let name="amount" value="xs:decimal(.)"/>
                <assert id="POSITIVE" test="$amount &gt; 0" flag="fatal">positive</assert>
                <assert id="OTHER" test="string-length(.) &lt; 3" flag="warning">short</assert>
                <assert id="SMALL" test="$amount &lt; 100" flag="fatal">small</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame(
            ['POSITIVE FORG0001 /t:Root[1]/t:Item[2]/t:Amount[1]', 'SMALL FORG0001 /t:Root[1]/t:Item[2]/t:Amount[1]'],
            array_map(static fn (DynamicError $error): string => "{$error->ruleId} {$error->errorCode} {$error->location}", $result->dynamicErrors),
        );
        self::assertSame(['OTHER /t:Root[1]/t:Item[2]/t:Amount[1]'], self::summary($result));
    }

    public function test_schema_and_pattern_lets_are_evaluated_from_the_document_node(): void
    {
        // From the document node t:Root is a child; from a t:Item it would not be.
        $result = self::runSchematron(<<<'SCH'
            <let name="roots" value="count(t:Root)"/>
            <pattern id="p">
              <let name="items" value="count(t:Root/t:Item) + $roots"/>
              <rule context="t:Item[$items = 3]">
                <let name="kind" value="string(@kind)"/>
                <assert id="GLOBALS" test="$roots = 1 and $items = 3 and $kind = 'x'" flag="fatal">globals</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame(['GLOBALS /t:Root[1]/t:Item[2]'], self::summary($result));
    }

    public function test_schema_let_is_computed_once_per_document(): void
    {
        $schema = <<<'SCH'
            <let name="total" value="sum(//t:Amount[. castable as xs:decimal])"/>
            <let name="unused" value="1"/>
            <pattern id="p">
              <let name="half" value="$total div 2"/>
              <rule context="t:Item">
                <assert id="A" test="$total = 10 and $half = 5" flag="fatal">a</assert>
                <assert id="B" test="$total &gt; 0" flag="fatal">b</assert>
              </rule>
              <rule context="t:Root[$total = 10]">
                <assert id="C" test="$total = 10" flag="fatal">c</assert>
              </rule>
            </pattern>
            <pattern id="q">
              <rule context="t:Amount">
                <assert id="D" test="$total = 10" flag="fatal">d</assert>
              </rule>
            </pattern>
            SCH;
        $computed = [];
        $runner = new Runner(static function (string $name) use (&$computed): void {
            $computed[] = $name;
        });
        $rules = self::compileSchematron($schema);

        $result = $runner->run($rules, SafeXml::load(self::DOCUMENT));
        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], $result->findings);
        self::assertSame(['total', 'half'], $computed);

        // A new document computes its globals again.
        $runner->run($rules, SafeXml::load(self::DOCUMENT));
        self::assertSame(['total', 'half', 'total', 'half'], $computed);
    }

    public function test_global_let_first_read_deep_in_a_recursion_starts_from_depth_zero(): void
    {
        // $deep needs 1,500 nested calls; it is first read 1,000 calls deep, which would exceed
        // the limit of 2,000 if the global inherited the depth of its first reader.
        $result = self::runSchematron(<<<'SCH'
            <let name="deep" value="u:depth(1500)"/>
            <xsl:function name="u:depth" as="xs:integer">
              <xsl:param name="n" as="xs:integer"/>
              <xsl:sequence select="if ($n le 0) then 0 else 1 + u:depth($n - 1)"/>
            </xsl:function>
            <xsl:function name="u:down" as="xs:integer">
              <xsl:param name="n" as="xs:integer"/>
              <xsl:sequence select="if ($n le 0) then $deep else u:down($n - 1)"/>
            </xsl:function>
            <pattern id="p">
              <rule context="t:Root">
                <assert id="DEEP" test="u:down(1000) = 1500" flag="fatal">deep</assert>
                <assert id="AGAIN" test="$deep = 1500" flag="fatal">again</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], $result->findings);
    }

    public function test_function_with_element_param_called_with_root_element(): void
    {
        $result = self::runSchematron(<<<'SCH'
            <xsl:function name="u:isRoot" as="xs:boolean">
              <xsl:param name="e" as="element()?"/>
              <xsl:sequence select="exists($e) and local-name($e) = 'Root'"/>
            </xsl:function>
            <pattern id="p">
              <rule context="t:Root">
                <assert id="ROOT" test="u:isRoot(/t:Root)" flag="fatal">root</assert>
                <assert id="NONE" test="not(u:isRoot(/t:Missing))" flag="fatal">none</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], $result->findings);
    }

    public function test_recursive_user_function(): void
    {
        $result = self::runSchematron(<<<'SCH'
            <xsl:function name="u:factorial" as="xs:integer">
              <xsl:param name="n" as="xs:integer"/>
              <xsl:sequence select="if ($n le 1) then 1 else $n * u:factorial($n - 1)"/>
            </xsl:function>
            <pattern id="p">
              <rule context="t:Amount">
                <assert id="FACTORIAL" test="u:factorial(string-length(.)) = 6" flag="fatal">3!</assert>
              </rule>
            </pattern>
            SCH);

        self::assertSame([], $result->dynamicErrors);
        self::assertSame(['FACTORIAL /t:Root[1]/t:Item[1]/t:Amount[1]'], self::summary($result));
    }

    public function test_deep_recursion_is_a_dynamic_error_not_a_crash(): void
    {
        $functions = <<<'SCH'
            <xsl:function name="u:count" as="xs:integer">
              <xsl:param name="s" as="xs:string"/>
              <xsl:param name="i" as="xs:integer"/>
              <xsl:sequence select="if ($i gt string-length($s)) then 0 else 1 + u:count($s, $i + 1)"/>
            </xsl:function>
            SCH;
        $xml = '<t:Root xmlns:t="urn:test:runner"><t:Big>'.str_repeat('7', 50_000).'</t:Big><t:Small>777</t:Small></t:Root>';

        $result = self::runSchematron($functions.<<<'SCH'
            <pattern id="p">
              <rule context="t:Big | t:Small">
                <assert id="COUNT" test="u:count(., 1) &gt; 3" flag="fatal">long</assert>
                <assert id="NEXT" test="false()" flag="warning">next</assert>
              </rule>
            </pattern>
            SCH, $xml);

        self::assertCount(1, $result->dynamicErrors);
        self::assertSame('COUNT', $result->dynamicErrors[0]->ruleId);
        self::assertSame('INTRAGILE_RECURSION', $result->dynamicErrors[0]->errorCode);
        self::assertSame('/t:Root[1]/t:Big[1]', $result->dynamicErrors[0]->location);
        self::assertSame(['NEXT /t:Root[1]/t:Big[1]', 'COUNT /t:Root[1]/t:Small[1]', 'NEXT /t:Root[1]/t:Small[1]'], self::summary($result));
    }

    public function test_deep_recursion_in_a_rule_context_is_one_rule_level_error(): void
    {
        $xml = '<t:Root xmlns:t="urn:test:runner"><t:Big>'.str_repeat('7', 10_000).'</t:Big></t:Root>';

        $result = self::runSchematron(<<<'SCH'
            <xsl:function name="u:depth" as="xs:integer">
              <xsl:param name="n" as="xs:integer"/>
              <xsl:sequence select="if ($n le 0) then 0 else 1 + u:depth($n - 1)"/>
            </xsl:function>
            <pattern id="p">
              <rule context="t:Big[u:depth(string-length(.)) &gt; 0]">
                <assert id="BIG" test="false()" flag="fatal">big</assert>
              </rule>
              <rule context="t:Root">
                <assert id="ROOT" test="false()" flag="fatal">root</assert>
              </rule>
            </pattern>
            SCH, $xml);

        self::assertCount(1, $result->dynamicErrors);
        self::assertNull($result->dynamicErrors[0]->ruleId);
        self::assertSame('INTRAGILE_RECURSION', $result->dynamicErrors[0]->errorCode);
        self::assertSame('mini 0', $result->dynamicErrors[0]->ruleSet);
        self::assertSame('/', $result->dynamicErrors[0]->location);
        self::assertSame(['ROOT /t:Root[1]'], self::summary($result));
    }

    public function test_reports_whether_the_root_rule_fired(): void
    {
        self::assertTrue(self::runMini(['root' => [['/t:Root | /t:Other', [['R', 'fatal', 'true()']]]]])->rootRuleFired);
        self::assertFalse(self::runMini(['root' => [['t:Root', [['R', 'fatal', 'true()']]]]])->rootRuleFired);
        self::assertFalse(self::runMini(['root' => [
            ['t:Root', [['R', 'fatal', 'true()']]],
            ['/t:Root', [['S', 'fatal', 'true()']]],
        ]])->rootRuleFired);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prefixedVariableShapes(): iterable
    {
        yield 'same prefix' => ['custom', 'custom'];
        yield 'two prefixes for one URI' => ['a', 'b'];
    }

    #[DataProvider('prefixedVariableShapes')]
    public function test_prefixed_function_variable_is_read_at_runtime(string $declared, string $referenced): void
    {
        $xml = <<<XML
            <schema xmlns="http://purl.oclc.org/dsdl/schematron" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" queryBinding="xslt2">
              <ns prefix="a" uri="urn:vars"/>
              <ns prefix="b" uri="urn:vars"/>
              <ns prefix="custom" uri="urn:vars"/>
              <ns prefix="f" uri="urn:funcs"/>
              <pattern id="p">
                <rule context="/doc">
                  <assert id="R-1" flag="fatal" test="f:valid(@code)">bad code</assert>
                </rule>
              </pattern>
              <xsl:function name="f:valid" as="xs:boolean">
                <xsl:param name="code" as="xs:string"/>
                <xsl:variable name="{$declared}:codes" select="'A B'"/>
                <xsl:sequence select="\$code = tokenize(\${$referenced}:codes, '\\s+')"/>
              </xsl:function>
            </schema>
            XML;
        $rules = RuleSet::fromArray(Compiler::compileString($xml, 'tiny.sch', 'tiny', '0.1'));
        $runner = new Runner;

        $good = $runner->run($rules, SafeXml::load('<doc code="B"/>'));
        $bad = $runner->run($rules, SafeXml::load('<doc code="Z"/>'));

        self::assertSame([], $good->dynamicErrors);
        self::assertSame([], $good->findings);
        self::assertSame([], $bad->dynamicErrors);
        self::assertCount(1, $bad->findings);
        self::assertSame('R-1', $bad->findings[0]->ruleId);
    }

    public function test_refuses_when_root_rule_did_not_fire(): void
    {
        $result = (new Runner)->run(RuleSet::fromFile(RuleFiles::ubl()), self::load(Fixtures::official('cii')[0]));

        self::assertFalse($result->rootRuleFired);
    }

    public function test_official_example_has_no_fatal(): void
    {
        $rules = RuleSet::fromFile(RuleFiles::ubl());
        $result = (new Runner)->run($rules, self::load(self::example('ubl', 'guide-example1.xml')));

        self::assertSame('en16931-ubl', $rules->id());
        self::assertSame('1.3.17', $rules->version());
        self::assertTrue($result->rootRuleFired);
        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], array_values(array_filter($result->findings, static fn (Finding $finding): bool => $finding->severity === Severity::Fatal)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function peppolLayers(): iterable
    {
        yield 'Peppol layer' => ['peppol-ubl'];
        yield 'OpenPeppol CEN copy' => ['cen-ubl'];
    }

    #[DataProvider('peppolLayers')]
    public function test_peppol_base_example_has_no_fatal(string $key): void
    {
        $rules = RuleSet::fromArray(Compiler::compile(Fixtures::peppolSchematron($key), $key, '3.0.22'));
        $xml = Fixtures::peppolExamples()['examples/base-example.xml'] ?? self::fail('base-example.xml not found.');
        $result = (new Runner)->run($rules, SafeXml::load($xml));

        self::assertSame([], $result->dynamicErrors);
        self::assertSame([], array_values(array_filter($result->findings, static fn (Finding $finding): bool => $finding->severity === Severity::Fatal)));
    }

    public function test_rule_files_are_loaded_once(): void
    {
        self::assertSame(RuleSet::fromFile(RuleFiles::cii()), RuleSet::fromFile(RuleFiles::cii()));
    }

    public function test_rejects_a_malformed_rule_set(): void
    {
        $this->expectException(UnsupportedSchematron::class);

        RuleSet::fromArray(['format' => RuleSet::FORMAT, 'id' => 'x', 'version' => '1', 'namespaces' => [], 'patterns' => [['id' => 'p', 'rules' => [['context' => 'a']]]]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformedDeclarations(): iterable
    {
        $base = ['format' => RuleSet::FORMAT, 'id' => 'x', 'version' => '1', 'namespaces' => [], 'lets' => [], 'functions' => [], 'patterns' => []];
        $function = ['name' => 'u:f', 'as' => null, 'params' => [['name' => 'v', 'as' => 'xs:string']], 'body' => [['sequence', ['var', 'v']]]];
        $rule = ['context' => 'a', 'match' => ['path'], 'lets' => [], 'asserts' => []];

        yield 'no schema lets' => [array_diff_key($base, ['lets' => true]), 'The lets of "x"'];
        yield 'no functions' => [array_diff_key($base, ['functions' => true]), 'The functions of "x"'];
        yield 'no pattern lets' => [['patterns' => [['id' => 'p', 'rules' => []]]] + $base, 'The lets of pattern "p"'];
        yield 'no rule lets' => [['patterns' => [['id' => 'p', 'lets' => [], 'rules' => [array_diff_key($rule, ['lets' => true])]]]] + $base, 'The lets of the rule "a"'];
        yield 'let without ast' => [['lets' => [['name' => 'a', 'value' => '1']]] + $base, 'The value of the let "a"'];
        yield 'function without "as"' => [['functions' => ['{u}f#1' => array_diff_key($function, ['as' => true])]] + $base, '"as" must be a string or null'];
        yield 'unknown instruction' => [['functions' => ['{u}f#1' => ['body' => [['for-each', []]]] + $function]] + $base, 'unknown or incomplete instruction'];
        yield 'empty function body' => [['functions' => ['{u}f#1' => ['body' => []] + $function]] + $base, 'The body of {u}f#1 is malformed: it must end with one sequence or choose'];
        yield 'body without a result' => [['functions' => ['{u}f#1' => ['body' => [['variable', 'w', null, ['var', 'v']]]] + $function]] + $base, 'it must end with one sequence or choose'];
        yield 'instruction after the result' => [['functions' => ['{u}f#1' => ['body' => [['sequence', ['var', 'v']], ['variable', 'w', null, ['var', 'v']]]] + $function]] + $base, 'it must end with one sequence or choose'];
        yield 'choose without branches' => [['functions' => ['{u}f#1' => ['body' => [['choose', [], [['sequence', ['var', 'v']]]]]] + $function]] + $base, 'unknown or incomplete instruction'];
        yield 'branch with an empty body' => [['functions' => ['{u}f#1' => ['body' => [['choose', [[['var', 'v'], []]], null]]] + $function]] + $base, 'it must end with one sequence or choose'];
        yield 'choose with a bad branch' => [['functions' => ['{u}f#1' => ['body' => [['choose', [[['var', 'v']]], null]]] + $function]] + $base, 'unknown or incomplete instruction'];
    }

    /**
     * @param  array<string, mixed>  $compiled
     */
    #[DataProvider('malformedDeclarations')]
    public function test_rejects_malformed_declarations(array $compiled, string $message): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($message);

        RuleSet::fromArray($compiled);
    }

    public function test_keeps_lets_and_functions(): void
    {
        $let = ['name' => 'a', 'value' => '1', 'ast' => ['literal', 'xs:integer', '1']];
        $function = ['name' => 'u:f', 'as' => 'xs:boolean', 'params' => [['name' => 'v', 'as' => null]], 'body' => [
            ['variable-text', 't', null, 'abc'],
            ['choose', [[['var', 'v'], [['sequence', ['var', 't']]]]], [['variable', 'w', 'xs:string', ['var', 'v']], ['sequence', ['var', 'w']]]],
        ]];
        $rules = RuleSet::fromArray([
            'format' => RuleSet::FORMAT, 'id' => 'x', 'version' => '1', 'namespaces' => [],
            'lets' => [$let], 'functions' => ['{u}f#1' => $function],
            'patterns' => [['id' => 'p', 'lets' => [$let], 'rules' => [['context' => 'a', 'match' => ['path'], 'lets' => [$let], 'asserts' => []]]]],
        ]);

        self::assertSame([$let], $rules->lets());
        self::assertSame(['{u}f#1' => $function], $rules->functions());
        self::assertSame([$let], $rules->patterns()[0]['lets']);
        self::assertSame([$let], $rules->patterns()[0]['rules'][0]['lets']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function staleFormats(): iterable
    {
        $current = ['id' => 'old', 'version' => '1', 'namespaces' => [], 'patterns' => []];

        yield 'no format key' => [$current, 'format 1'];
        yield 'format 1' => [['format' => 1, ...$current], 'format 1'];
        yield 'format 5' => [['format' => 5, ...$current], 'format 5'];
        yield 'format as string' => [['format' => '2', ...$current], 'format "2"'];
    }

    /**
     * @param  array<string, mixed>  $compiled
     */
    #[DataProvider('staleFormats')]
    public function test_old_format_asks_to_recompile(array $compiled, string $format): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage("The rule set \"old\" was compiled with {$format}; this engine reads formats 2, 3 and 4. Recompile it.");

        RuleSet::fromArray($compiled);
    }

    public function test_report_compiles_and_fires_when_test_is_true(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Item">'
            .'<report id="R-1" flag="warning" test="@kind = \'x\'">Kind <value-of select="@kind"/> on <name/>.</report></rule></pattern>');

        self::assertSame([], $result->dynamicErrors);
        self::assertEquals(
            [new Finding('R-1', Severity::Warning, '/t:Root[1]/t:Item[1]', 'Kind x on t:Item.', "@kind = 'x'")],
            $result->findings,
        );
    }

    public function test_report_does_not_fire_when_test_is_false(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Item">'
            .'<report id="R-1" flag="fatal" test="false()">never</report><report id="R-2" flag="fatal" test="t:Missing">never</report></rule></pattern>');

        self::assertSame([], $result->findings);
        self::assertSame([], $result->dynamicErrors);
    }

    public function test_dynamic_error_in_a_report_is_recorded_like_an_assert(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Amount">'
            .'<report id="DEC" flag="fatal" test="xs:decimal(.) &gt; 0">positive</report><report id="NEXT" flag="warning" test="true()">next</report></rule></pattern>');

        self::assertCount(1, $result->dynamicErrors);
        self::assertSame('DEC', $result->dynamicErrors[0]->ruleId);
        self::assertSame('FORG0001', $result->dynamicErrors[0]->errorCode);
        self::assertSame('/t:Root[1]/t:Item[2]/t:Amount[1]', $result->dynamicErrors[0]->location);
        self::assertSame([
            'DEC /t:Root[1]/t:Item[1]/t:Amount[1]',
            'NEXT /t:Root[1]/t:Item[1]/t:Amount[1]',
            'NEXT /t:Root[1]/t:Item[2]/t:Amount[1]',
        ], self::summary($result));
    }

    public function test_on_finding_receives_the_built_message(): void
    {
        $rules = self::compileSchematron('<pattern id="p"><rule context="t:Item">'
            .'<assert id="V" flag="fatal" test="false()">Amount <value-of select="t:Amount"/> of <name/>.</assert></rule></pattern>');
        $seen = [];
        $runner = new Runner(null, static function (DOMNode $node, array $assert, Finding $finding) use (&$seen): void {
            $seen[] = [$node instanceof DOMElement ? $node->getAttribute('kind') : '?', $assert['id'], $finding->message];
        });

        $result = $runner->run($rules, SafeXml::load(self::DOCUMENT));

        self::assertSame([['x', 'V', 'Amount 10 of t:Item.'], ['y', 'V', 'Amount abc of t:Item.']], $seen);
        self::assertSame(array_column($seen, 2), array_map(static fn (Finding $finding): string => $finding->message, $result->findings));
    }

    public function test_dynamic_error_names_its_rule_set(): void
    {
        $result = self::runMini(['amounts' => [['t:Amount', [['DEC', 'fatal', 'xs:decimal(.) > 0']]]]]);

        self::assertSame(
            [['DEC', 'mini 0']],
            array_map(static fn (DynamicError $error): array => [$error->ruleId, $error->ruleSet], $result->dynamicErrors),
        );
        self::assertNull((new DynamicError('R-1', 'FORG0001', '/', 'x'))->ruleSet);
    }

    public function test_name_in_message_is_the_context_node_name(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Item/@kind"><assert id="N" flag="fatal" test="false()">Attribute <name/>  is bad</assert></rule>'
            .'<rule context="t:Item"><assert id="N" flag="fatal" test="false()">Element <name/> is bad</assert></rule></pattern>');

        self::assertSame(
            ['Element t:Item is bad', 'Attribute kind is bad', 'Element t:Item is bad', 'Attribute kind is bad'],
            array_map(static fn (Finding $finding): string => $finding->message, $result->findings),
        );
    }

    public function test_name_in_message_keeps_the_instance_prefix(): void
    {
        $xml = '<p:Root xmlns:p="urn:test:runner"><Plain xmlns="urn:test:runner"/></p:Root>';
        $result = self::runSchematron('<pattern id="p"><rule context="t:Root | t:Plain"><assert id="N" flag="fatal" test="false()"><name/></assert></rule></pattern>', $xml);

        self::assertSame(['p:Root', 'Plain'], array_map(static fn (Finding $finding): string => $finding->message, $result->findings));
    }

    public function test_value_of_in_message_is_evaluated(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Root"><let name="unit" value="\'EUR\'"/>'
            .'<assert id="V" flag="fatal" test="false()">Amounts <value-of select="t:Item/t:Amount"/> in <value-of select="$unit"/>.</assert></rule></pattern>');

        self::assertSame([], $result->dynamicErrors);
        self::assertSame(['Amounts 10 abc in EUR.'], array_map(static fn (Finding $finding): string => $finding->message, $result->findings));
    }

    public function test_failing_value_of_falls_back_to_static_message(): void
    {
        $result = self::runSchematron('<pattern id="p"><rule context="t:Root">'
            .'<assert id="V" flag="fatal" test="false()">Total <value-of select="sum(t:Item/t:Amount)"/> of <name/>.</assert></rule></pattern>');

        self::assertSame([], $result->dynamicErrors);
        self::assertEquals(
            [new Finding('V', Severity::Fatal, '/t:Root[1]', 'Total of .', 'false()')],
            $result->findings,
        );
    }

    public function test_parenthesised_union_pattern_matches_like_the_distributed_union(): void
    {
        $xml = <<<'XML'
            <t:Root xmlns:t="urn:test:runner">
              <t:Item kind="x"><t:Amount>1</t:Amount><t:Sub><t:Amount>2</t:Amount></t:Sub></t:Item>
              <t:Line kind="y"><t:Amount>3</t:Amount></t:Line>
              <t:Line kind="z"><t:Amount>4</t:Amount></t:Line>
              <t:Other><t:Amount>5</t:Amount></t:Other>
            </t:Root>
            XML;
        $rule = static fn (string $context): string => '<pattern id="p"><let name="on" value="true()"/><rule context="'.$context.'">'
            .'<assert id="U" flag="fatal" test="false()">x</assert></rule></pattern>';

        $cases = [
            "(/t:Root[\$on]/t:Item | t:Line[@kind = 'z'])/t:Amount" => "/t:Root[\$on]/t:Item/t:Amount | t:Line[@kind = 'z']/t:Amount",
            '(t:Item | (t:Line | t:Other))//t:Amount' => 't:Item//t:Amount | t:Line//t:Amount | t:Other//t:Amount',
            '(t:Item | t:Line)/t:Amount | t:Other/t:Amount' => 't:Item/t:Amount | t:Line/t:Amount | t:Other/t:Amount',
            '(t:Item | t:Line)/@kind' => 't:Item/@kind | t:Line/@kind',
        ];
        foreach ($cases as $parenthesised => $distributed) {
            $expected = self::summary(self::runSchematron($rule($distributed), $xml));
            self::assertNotSame([], $expected, $distributed);
            self::assertSame($expected, self::summary(self::runSchematron($rule($parenthesised), $xml)), $parenthesised);
        }
    }

    /**
     * Expected contexts follow the XSLT matching rule: a node matches "(P1 | P2)[pred]/rest" when
     * "rest" leads to it from a node that matches P1 or P2 and satisfies the predicate.
     */
    public function test_filtered_union_selects_like_saxon(): void
    {
        $rule = static fn (string $context): string => '<pattern id="p"><rule context="'.$context.'">'
            .'<assert id="U" flag="fatal" test="false()">x</assert></rule></pattern>';
        $first = <<<'XML'
            <t:Root xmlns:t="urn:test:runner">
              <t:Item kind="a"><t:Amount>1</t:Amount><t:Sub><t:Amount>2</t:Amount></t:Sub></t:Item>
              <t:Item kind="b"><t:Amount>3</t:Amount></t:Item>
              <t:Line kind="a"><t:Amount>4</t:Amount></t:Line>
              <t:Other kind="a"><t:Amount>5</t:Amount></t:Other>
            </t:Root>
            XML;
        $second = <<<'XML'
            <t:Root xmlns:t="urn:test:runner">
              <t:Line kind="b"><t:Amount>6</t:Amount></t:Line>
              <t:Item kind="a"><t:Sub kind="x"><t:Amount>7</t:Amount></t:Sub></t:Item>
            </t:Root>
            XML;

        // Shape 1: filtered union of paths followed by a child step with its own predicate.
        $shapeOne = "(t:Item/t:Sub | t:Line)[@kind = 'a']/t:Amount[. > 1]";
        self::assertSame(['U /t:Root[1]/t:Line[1]/t:Amount[1]'], self::summary(self::runSchematron($rule($shapeOne), $first)));
        self::assertSame([], self::summary(self::runSchematron($rule($shapeOne), $second)));

        // Shape 2: filtered union followed by a union of child steps.
        $shapeTwo = "(t:Item | t:Line)[@kind = 'a']/(t:Amount | t:Sub)";
        self::assertSame(
            ['U /t:Root[1]/t:Item[1]/t:Amount[1]', 'U /t:Root[1]/t:Item[1]/t:Sub[1]', 'U /t:Root[1]/t:Line[1]/t:Amount[1]'],
            self::summary(self::runSchematron($rule($shapeTwo), $first)),
        );
        self::assertSame(
            ['U /t:Root[1]/t:Item[1]/t:Sub[1]'],
            self::summary(self::runSchematron($rule($shapeTwo), $second)),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidParenthesisedUnions(): iterable
    {
        yield 'variable alternative' => ['(t:Item | $v)/t:Amount'];
        yield 'parent axis alternative' => ['(t:Item | ../t:Line)/t:Amount'];
        yield 'filtered union followed by another axis' => ['(t:Item | t:Line)[$v]/ancestor::t:Root'];
        yield 'union after a step' => ['t:Root/(t:Item | t:Line)'];
    }

    #[DataProvider('invalidParenthesisedUnions')]
    public function test_parenthesised_union_needs_path_alternatives(string $context): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($context);

        self::compileSchematron('<let name="v" value="/"/><pattern id="p"><rule context="'.$context.'"><assert id="U" flag="fatal" test="false()">x</assert></rule></pattern>');
    }

    public function test_location_path_uses_known_prefixes_and_expanded_names_otherwise(): void
    {
        $document = SafeXml::load('<a:Root xmlns:a="urn:a" xmlns:b="urn:b"><b:X/><b:X b:id="1" plain="2"/><Local/></a:Root>');
        $root = $document->documentElement;
        self::assertNotNull($root);
        $second = $root->childNodes->item(1);
        self::assertInstanceOf(DOMElement::class, $second);
        $namespaced = $second->getAttributeNodeNS('urn:b', 'id');
        $plain = $second->getAttributeNode('plain');
        self::assertInstanceOf(DOMAttr::class, $namespaced);
        self::assertInstanceOf(DOMAttr::class, $plain);
        self::assertInstanceOf(DOMElement::class, $root->lastChild);

        self::assertSame('/', LocationPath::of($document, []));
        self::assertSame('/p:Root[1]/Q{urn:b}X[2]', LocationPath::of($second, ['p' => 'urn:a']));
        self::assertSame('/p:Root[1]/Q{urn:b}X[2]/@Q{urn:b}id', LocationPath::of($namespaced, ['p' => 'urn:a']));
        self::assertSame('/p:Root[1]/q:X[2]/@plain', LocationPath::of($plain, ['p' => 'urn:a', 'q' => 'urn:b']));
        self::assertSame('/p:Root[1]/Local[1]', LocationPath::of($root->lastChild, ['p' => 'urn:a']));
    }

    /**
     * Compiles a Schematron schema body (lets, XSLT functions, patterns) with the prefixes t (the
     * test document), u (functions) and xs bound, and runs it.
     */
    private static function runSchematron(string $body, string $xml = self::DOCUMENT): RunResult
    {
        return (new Runner)->run(self::compileSchematron($body), SafeXml::load($xml));
    }

    private static function compileSchematron(string $body): RuleSet
    {
        $schema = '<schema xmlns="'.Compiler::SCHEMATRON_NAMESPACE.'" xmlns:xsl="'.Compiler::XSLT_NAMESPACE.'"'
            .' xmlns:xs="'.StaticContext::XS.'" xmlns:u="urn:test:functions" queryBinding="xslt2">'
            .'<ns prefix="t" uri="'.self::NS.'"/><ns prefix="u" uri="urn:test:functions"/>'
            .$body.'</schema>';

        return RuleSet::fromArray(Compiler::compileString($schema, 'runner-test.sch', 'mini', '0'));
    }

    /**
     * @param  array<string, list<array{string, list<array{0: string, 1: string, 2: string, 3?: string}>}>>  $patterns
     */
    private static function runMini(array $patterns, string $xml = self::DOCUMENT): RunResult
    {
        $namespaces = ['t' => self::NS, 'xs' => 'http://www.w3.org/2001/XMLSchema'];
        $context = new StaticContext($namespaces);
        $parser = new Parser;

        $compiled = ['format' => RuleSet::FORMAT, 'id' => 'mini', 'version' => '0', 'namespaces' => $namespaces, 'lets' => [], 'functions' => [], 'patterns' => []];
        foreach ($patterns as $id => $rules) {
            $pattern = ['id' => $id, 'lets' => [], 'rules' => []];
            foreach ($rules as [$ruleContext, $asserts]) {
                $rule = ['context' => $ruleContext, 'match' => $parser->parsePattern($ruleContext, $context), 'lets' => [], 'asserts' => []];
                foreach ($asserts as $assert) {
                    $rule['asserts'][] = [
                        'id' => $assert[0],
                        'flag' => $assert[1],
                        'test' => $assert[2],
                        'ast' => $parser->parse($assert[2], $context),
                        'message' => $assert[3] ?? "[{$assert[0]}] failed",
                    ];
                }
                $pattern['rules'][] = $rule;
            }
            $compiled['patterns'][] = $pattern;
        }

        return (new Runner)->run(RuleSet::fromArray($compiled), SafeXml::load($xml));
    }

    /**
     * @return list<string>
     */
    private static function summary(RunResult $result): array
    {
        return array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->location}", $result->findings);
    }

    private static function example(string $syntax, string $name): string
    {
        foreach (Fixtures::official($syntax) as $path) {
            if (basename($path) === $name) {
                return $path;
            }
        }

        self::fail("Official example {$name} not found.");
    }

    private static function load(string $path): DOMDocument
    {
        $xml = file_get_contents($path);
        self::assertIsString($xml);

        return SafeXml::load($xml);
    }
}

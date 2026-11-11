<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use DOMAttr;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\DynamicError;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\Finding;
use IntrAgile\EInvoicing\Schematron\LocationPath;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Schematron\RunResult;
use IntrAgile\EInvoicing\Schematron\Severity;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\XPath\Parser;
use IntrAgile\EInvoicing\XPath\StaticContext;
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
        self::assertSame([], $result->errors);
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

        self::assertCount(1, $result->errors);
        $error = $result->errors[0];
        self::assertInstanceOf(DynamicError::class, $error);
        self::assertSame('DEC', $error->ruleId);
        self::assertSame('FORG0001', $error->errorCode);
        self::assertSame('/t:Root[1]/t:Item[2]/t:Amount[1]', $error->location);
        self::assertSame(['NEXT /t:Root[1]/t:Item[1]/t:Amount[1]', 'NEXT /t:Root[1]/t:Item[2]/t:Amount[1]'], self::summary($result));
    }

    public function test_failing_rule_context_is_reported_without_rule_id_and_skipped(): void
    {
        $result = self::runMini(['contexts' => [
            ['t:Item[xs:decimal(t:Amount) > 0]', [['NEVER', 'fatal', 'false()']]],
            ['t:Item', [['OTHER', 'fatal', 'false()']]],
        ]]);

        self::assertCount(1, $result->errors);
        self::assertNull($result->errors[0]->ruleId);
        self::assertSame('FORG0001', $result->errors[0]->errorCode);
        self::assertSame(['OTHER /t:Root[1]/t:Item[1]', 'OTHER /t:Root[1]/t:Item[2]'], self::summary($result));
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
        self::assertSame([], $result->errors);
        self::assertSame([], array_values(array_filter($result->findings, static fn (Finding $finding): bool => $finding->severity === Severity::Fatal)));
    }

    public function test_rule_files_are_loaded_once(): void
    {
        self::assertSame(RuleSet::fromFile(RuleFiles::cii()), RuleSet::fromFile(RuleFiles::cii()));
    }

    public function test_rejects_a_malformed_rule_set(): void
    {
        $this->expectException(UnsupportedSchematron::class);

        RuleSet::fromArray(['id' => 'x', 'version' => '1', 'namespaces' => [], 'patterns' => [['id' => 'p', 'rules' => [['context' => 'a']]]]]);
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
     * @param  array<string, list<array{string, list<array{0: string, 1: string, 2: string, 3?: string}>}>>  $patterns
     */
    private static function runMini(array $patterns, string $xml = self::DOCUMENT): RunResult
    {
        $namespaces = ['t' => self::NS, 'xs' => 'http://www.w3.org/2001/XMLSchema'];
        $context = new StaticContext($namespaces);
        $parser = new Parser;

        $compiled = ['id' => 'mini', 'version' => '0', 'namespaces' => $namespaces, 'patterns' => []];
        foreach ($patterns as $id => $rules) {
            $pattern = ['id' => $id, 'rules' => []];
            foreach ($rules as [$ruleContext, $asserts]) {
                $rule = ['context' => $ruleContext, 'match' => $parser->parsePattern($ruleContext, $context), 'asserts' => []];
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

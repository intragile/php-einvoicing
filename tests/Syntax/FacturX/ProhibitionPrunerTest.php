<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Syntax\FacturX\ProhibitionPruner;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ProhibitionPrunerTest extends TestCase
{
    private const NS = 'urn:test:prune';

    private static function rules(string $rules): RuleSet
    {
        $schema = '<schema xmlns="'.Compiler::SCHEMATRON_NAMESPACE.'" queryBinding="xslt2">'
            .'<ns prefix="t" uri="'.self::NS.'"/><pattern id="p">'.$rules.'</pattern></schema>';

        return RuleSet::fromArray(Compiler::compileString($schema, 'prune.sch', 'prune', '0'));
    }

    private static function report(string $context, string $test): string
    {
        return '<rule context="'.$context.'"><report id="R" flag="fatal" test="'.$test.'">not used</report></rule>';
    }

    private static function doc(string $inner): \DOMDocument
    {
        return SafeXml::loadPreservingWhitespace('<t:root xmlns:t="'.self::NS."\">\n  {$inner}\n</t:root>");
    }

    public function test_unconditional_report_prunes_context_node(): void
    {
        $document = self::doc('<t:keep/><t:drop><t:child/></t:drop>');

        $removed = (new ProhibitionPruner)->prune(self::rules(self::report('t:drop', 'true()')), $document);

        self::assertSame(['/Q{urn:test:prune}root/Q{urn:test:prune}drop'], $removed);
        self::assertSame(1, $document->getElementsByTagNameNS(self::NS, 'keep')->length);
        self::assertSame(0, $document->getElementsByTagNameNS(self::NS, 'child')->length);
    }

    public function test_attribute_report_prunes_attribute(): void
    {
        $document = self::doc('<t:amount schemeID="x" other="y">1</t:amount>');

        $removed = (new ProhibitionPruner)->prune(self::rules(self::report('t:amount', '@schemeID')), $document);

        self::assertSame(['/Q{urn:test:prune}root/Q{urn:test:prune}amount/@schemeID'], $removed);
        $amount = $document->getElementsByTagNameNS(self::NS, 'amount')->item(0);
        self::assertInstanceOf(\DOMElement::class, $amount);
        self::assertFalse($amount->hasAttribute('schemeID'));
        self::assertTrue($amount->hasAttribute('other'));
    }

    public function test_conditional_report_is_not_pruned(): void
    {
        $document = self::doc('<t:drop><t:x/></t:drop>');

        $removed = (new ProhibitionPruner)->prune(self::rules(self::report('t:drop', 't:x')), $document);

        self::assertSame([], $removed);
        self::assertSame(1, $document->getElementsByTagNameNS(self::NS, 'drop')->length);
    }

    public function test_assert_with_false_test_is_not_pruned(): void
    {
        $rules = self::rules('<rule context="t:drop"><assert id="A" flag="fatal" test="false()">no</assert></rule>');
        $document = self::doc('<t:drop/>');

        self::assertSame([], (new ProhibitionPruner)->prune($rules, $document));
    }

    public function test_pruning_reaches_fixpoint(): void
    {
        // Removing t:inner leaves t:outer without children, which a second rule then prohibits.
        $rules = self::rules(
            self::report('t:inner', 'true()')
            .'<rule context="t:outer[not(t:inner)]"><report id="R2" flag="fatal" test="true()">empty</report></rule>',
        );
        $document = self::doc('<t:outer><t:inner/></t:outer><t:keep/>');

        $removed = (new ProhibitionPruner)->prune($rules, $document);

        self::assertSame(['/Q{urn:test:prune}root/Q{urn:test:prune}outer', '/Q{urn:test:prune}root/Q{urn:test:prune}outer/Q{urn:test:prune}inner'], $removed);
        self::assertSame(0, $document->getElementsByTagNameNS(self::NS, 'outer')->length);
        self::assertSame(1, $document->getElementsByTagNameNS(self::NS, 'keep')->length);
    }

    public function test_non_converging_rules_throw(): void
    {
        // Every round removes one more t:n, and there are more than the round cap.
        $rules = self::rules('<rule context="t:n[not(t:n)]"><report id="R" flag="fatal" test="true()">leaf</report></rule>');
        $document = self::doc(str_repeat('<t:n>', 12).str_repeat('</t:n>', 12));

        $this->expectException(LogicException::class);

        (new ProhibitionPruner)->prune($rules, $document);
    }
}

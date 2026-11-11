<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\XPath\Op;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type CompiledRuleSet from Compiler
 * @phpstan-import-type CompiledAssert from Compiler
 */
final class CompilerTest extends TestCase
{
    private const SCRATCH = __DIR__.'/../../.cache/tests/schematron';

    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /** @var array<string, CompiledRuleSet> */
    private static array $compiled = [];

    /**
     * @return iterable<string, array{string, int, int, int, int, int}>
     */
    public static function officialScope(): iterable
    {
        yield 'ubl' => ['ubl', 3, 104, 982, 284, 698];
        yield 'cii' => ['cii', 3, 115, 810, 339, 471];
    }

    #[DataProvider('officialScope')]
    public function test_compiles_the_official_rule_set(string $syntax, int $patterns, int $rules, int $asserts, int $fatal, int $warning): void
    {
        $compiled = self::compiled($syntax);

        self::assertSame("en16931-{$syntax}", $compiled['id']);
        self::assertSame('1.3.17', $compiled['version']);
        self::assertSame(basename(Fixtures::schematron($syntax)), $compiled['source']);
        self::assertSame(hash_file('sha256', Fixtures::schematron($syntax)), $compiled['sha256']);

        $flags = [];
        $ruleCount = 0;
        self::assertCount($patterns, $compiled['patterns']);
        foreach ($compiled['patterns'] as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                $ruleCount++;
                foreach ($rule['asserts'] as $assert) {
                    $flags[] = $assert['flag'];
                }
            }
        }

        self::assertSame($rules, $ruleCount);
        self::assertCount($asserts, $flags);
        self::assertSame(['fatal' => $fatal, 'warning' => $warning], array_count_values($flags));
    }

    #[DataProvider('syntaxes')]
    public function test_br_co_16_is_fatal(string $syntax): void
    {
        $found = [];
        foreach (self::asserts(self::compiled($syntax)) as $assert) {
            if ($assert['id'] === 'BR-CO-16') {
                $found[] = $assert['flag'];
            }
        }

        self::assertSame(['fatal'], $found);
    }

    public function test_keeps_original_text_and_normalizes_the_message(): void
    {
        $compiled = Compiler::compile(self::writeSchematron(<<<'XML'
            <rule context="cbc:ID[@schemeID]">
              <assert id="R-1" flag="warning" test="string-length(.) &lt;= 10">
                [R-1] Too
                long.
              </assert>
            </rule>
            XML), 'tiny', '0.1');

        self::assertSame(['cbc' => self::CBC], $compiled['namespaces']);
        $rule = $compiled['patterns'][0]['rules'][0];
        self::assertSame('cbc:ID[@schemeID]', $rule['context']);
        self::assertSame(Op::PATH, $rule['match'][0]);
        self::assertSame(
            ['id' => 'R-1', 'flag' => 'warning', 'test' => 'string-length(.) <= 10', 'ast' => $rule['asserts'][0]['ast'], 'message' => '[R-1] Too long.'],
            $rule['asserts'][0],
        );
        self::assertSame(Op::GCMP, $rule['asserts'][0]['ast'][0]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedElements(): iterable
    {
        yield 'report' => ['<rule context="cbc:ID"><report id="R-1" flag="fatal" test="true()">x</report></rule>', 'report'];
        yield 'let' => ['<rule context="cbc:ID"><let name="x" value="1"/><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', 'let'];
        yield 'value-of' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x <value-of select="."/></assert></rule>', 'value-of'];
    }

    #[DataProvider('unsupportedElements')]
    public function test_rejects_unsupported_elements(string $rules, string $element): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage("\"{$element}\"");

        Compiler::compile(self::writeSchematron($rules), 'tiny', '0.1');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'assert without id' => ['<rule context="cbc:ID"><assert flag="fatal" test="true()">x</assert></rule>', 'without an id'];
        yield 'unknown flag' => ['<rule context="cbc:ID"><assert id="R-1" flag="error" test="true()">x</assert></rule>', '"error"'];
        yield 'abstract rule' => ['<rule abstract="true" id="a"><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', 'abstract'];
        yield 'unsupported XPath' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="if (.) then 1 else 2">x</assert></rule>', 'R-1'];
        yield 'XPath syntax error' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="count(">x</assert></rule>', 'count('];
        yield 'unsupported regex' => ['<rule context="cbc:ID"><assert id="R-9" flag="fatal" test="matches(., \'\i+\')">x</assert></rule>', 'R-9'];
        yield 'invalid context' => ['<rule context="cbc:ID["><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', 'cbc:ID['];
    }

    #[DataProvider('invalidRules')]
    public function test_rejects_invalid_rules(string $rules, string $message): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($message);

        Compiler::compile(self::writeSchematron($rules), 'tiny', '0.1');
    }

    public function test_rejects_abstract_patterns(): void
    {
        $path = self::writeSchematron('<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', ' abstract="true"');

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('abstract');

        Compiler::compile($path, 'tiny', '0.1');
    }

    public function test_writes_byte_stable_output_that_loads_back(): void
    {
        $compiled = self::compiled('cii');
        self::scratch();
        $first = self::SCRATCH.'/first.php';
        $second = self::SCRATCH.'/second.php';

        CompiledRuleSetWriter::write($compiled, $first);
        CompiledRuleSetWriter::write($compiled, $second);

        self::assertSame(file_get_contents($first), file_get_contents($second));
        self::assertStringContainsString('Generated from CEN EN 16931 validation artefacts 1.3.17 (EUPL-1.2) — do not edit', (string) file_get_contents($first));
        self::assertTrue((require $first) == $compiled);
    }

    public function test_check_passes_on_a_clean_tree(): void
    {
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../../bin/compile-schematron.php').' --check 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
    }

    public function test_rule_files_point_at_the_generated_rule_sets(): void
    {
        self::assertSame(realpath(__DIR__.'/../../packages/rules-en16931/rules/en16931-ubl-1.3.17.php'), realpath(RuleFiles::ubl()));
        self::assertSame(realpath(__DIR__.'/../../packages/rules-en16931/rules/en16931-cii-1.3.17.php'), realpath(RuleFiles::cii()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function syntaxes(): iterable
    {
        yield 'ubl' => ['ubl'];
        yield 'cii' => ['cii'];
    }

    /**
     * @return CompiledRuleSet
     */
    private static function compiled(string $syntax): array
    {
        return self::$compiled[$syntax] ??= Compiler::compile(Fixtures::schematron($syntax), "en16931-{$syntax}", '1.3.17');
    }

    /**
     * @param  CompiledRuleSet  $compiled
     * @return list<CompiledAssert>
     */
    private static function asserts(array $compiled): array
    {
        $asserts = [];
        foreach ($compiled['patterns'] as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                array_push($asserts, ...$rule['asserts']);
            }
        }

        return $asserts;
    }

    private static function scratch(): void
    {
        if (! is_dir(self::SCRATCH)) {
            mkdir(self::SCRATCH, 0777, true);
        }
    }

    private static function writeSchematron(string $rules, string $patternAttributes = ''): string
    {
        self::scratch();
        $path = self::SCRATCH.'/tiny.sch';
        $cbc = self::CBC;
        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <schema xmlns="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt2">
              <title>Tiny</title>
              <ns prefix="cbc" uri="{$cbc}"/>
              <pattern id="p"{$patternAttributes}>
                {$rules}
              </pattern>
            </schema>
            XML);

        return $path;
    }
}

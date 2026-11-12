<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
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

        self::assertSame(RuleSet::FORMAT, $compiled['format']);
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
        self::assertSame([[], []], [$compiled['lets'], $compiled['functions']]);
        foreach ($compiled['patterns'] as $pattern) {
            self::assertSame([], $pattern['lets']);
            self::assertSame([[]], array_values(array_unique(array_column($pattern['rules'], 'lets'), SORT_REGULAR)));
        }
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
        yield 'xsl:variable in a rule' => ['<rule context="cbc:ID"><xsl:variable xmlns:xsl="http://www.w3.org/1999/XSL/Transform" name="x" select="1"/><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', '{http://www.w3.org/1999/XSL/Transform}variable'];
        yield 'let in an assert' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x <let name="x" value="1"/></assert></rule>', 'let'];
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
        yield 'unsupported XPath' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test=". instance of xs:string">x</assert></rule>', 'R-1'];
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

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function unsupportedAttributes(): iterable
    {
        $rule = '<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x</assert></rule>';

        yield 'pattern/@documents' => [$rule, ' documents="/other"', '', '', 'attribute "documents" of element "pattern"'];
        yield 'rule/@role' => ['<rule context="cbc:ID" role="x"><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', '', '', '', 'attribute "role" of element "rule"'];
        yield 'assert/@diagnostics' => ['<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()" diagnostics="d">x</assert></rule>', '', '', '', 'attribute "diagnostics" of element "assert"'];
        yield 'schema/@see' => [$rule, '', ' see="x"', '', 'attribute "see" of element "schema"'];
        yield 'ns/@xml:base' => [$rule, '', '', ' xml:base="x"', 'attribute "xml:base" of element "ns"'];
        yield 'phase/@fpi' => [$rule.'</pattern><phase id="ph" fpi="x"><active pattern="p"/></phase><pattern id="q">', '', '', '', 'attribute "fpi" of element "phase"'];
        yield 'active/@xml:lang' => [$rule.'</pattern><phase id="ph"><active pattern="p" xml:lang="en"/></phase><pattern id="q">', '', '', '', 'attribute "xml:lang" of element "active"'];
    }

    #[DataProvider('unsupportedAttributes')]
    public function test_rejects_unsupported_attributes(string $rules, string $patternAttributes, string $schemaAttributes, string $nsAttributes, string $message): void
    {
        $path = self::writeSchematron($rules, $patternAttributes, $schemaAttributes, $nsAttributes);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessageMatches('/Unsupported '.preg_quote($message, '/').' \\(tiny\.sch line \d+\)/');

        Compiler::compile($path, 'tiny', '0.1');
    }

    /**
     * OpenPeppol's own copies of the CEN rules (1.3.16), compiled without any change to the
     * compiler: the same three patterns as 1.3.17 but other rule and assert counts.
     *
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function peppolCenCopies(): iterable
    {
        yield 'ubl' => ['cen-ubl', 104, 979, 281, 698];
        yield 'cii' => ['cen-cii', 113, 806, 335, 471];
    }

    #[DataProvider('peppolCenCopies')]
    public function test_compiles_openpeppols_cen_copies(string $key, int $rules, int $asserts, int $fatal, int $warning): void
    {
        $compiled = Compiler::compile(Fixtures::peppolSchematron($key), $key, '3.0.22');

        self::assertCount(3, $compiled['patterns']);
        self::assertSame([$rules, $asserts], [self::countRules($compiled), count(self::asserts($compiled))]);
        self::assertSame(['fatal' => $fatal, 'warning' => $warning], self::flagCounts($compiled));
        self::assertSame([[], []], [$compiled['lets'], $compiled['functions']]);
    }

    /**
     * The Peppol BIS Billing 3.0.22 rule files: patterns, rules, asserts, fatal and warning
     * asserts, schema, pattern and rule lets, and XSLT functions, as counted in the DOM.
     *
     * @return iterable<string, array{string, list<int>}>
     */
    public static function peppolScope(): iterable
    {
        yield 'ubl' => ['peppol-ubl', [13, 99, 165, 139, 26, 10, 22, 17, 13]];
        yield 'cii' => ['peppol-cii', [7, 63, 91, 74, 17, 4, 13, 6, 12]];
    }

    /**
     * @param  list<int>  $expected
     */
    #[DataProvider('peppolScope')]
    public function test_compiles_the_peppol_rule_set(string $key, array $expected): void
    {
        $compiled = self::peppol($key);

        $patternLets = 0;
        $ruleLets = 0;
        foreach ($compiled['patterns'] as $pattern) {
            $patternLets += count($pattern['lets']);
            foreach ($pattern['rules'] as $rule) {
                $ruleLets += count($rule['lets']);
            }
        }
        $flags = self::flagCounts($compiled);

        self::assertSame($expected, [
            count($compiled['patterns']), self::countRules($compiled), count(self::asserts($compiled)), $flags['fatal'], $flags['warning'],
            count($compiled['lets']), $patternLets, $ruleLets, count($compiled['functions']),
        ]);
        self::assertSame('utils', $compiled['namespaces']['u']);
        self::assertSame(RuleSet::FORMAT, $compiled['format']);
    }

    public function test_compiles_peppol_functions(): void
    {
        $functions = self::peppol('peppol-ubl')['functions'];

        $addPiva = $functions['{utils}addPIVA#2'];
        self::assertSame('u:addPIVA', $addPiva['name']);
        self::assertSame('xs:integer', $addPiva['as']);
        self::assertSame([['name' => 'arg', 'as' => 'xs:string'], ['name' => 'pari', 'as' => 'xs:integer']], $addPiva['params']);
        self::assertSame(['variable', 'variable', 'sequence'], array_column($addPiva['body'], 0));
        self::assertTrue(self::containsCall($addPiva['body'][2][1], 'utils', 'addPIVA'), 'u:addPIVA calls itself');

        self::assertSame(
            ['variable-text', 'allowed-characters', null, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'],
            $functions['{utils}checkCodiceIPA#1']['body'][0],
        );
        self::assertSame([['name' => 'val', 'as' => null]], $functions['{utils}gln#1']['params']);

        $body = $functions['{utils}checkSEOrgnr#1']['body'];
        self::assertCount(1, $body);
        [$instruction, $branches, $otherwise] = $body[0];
        self::assertSame('choose', $instruction);
        self::assertIsArray($branches);
        self::assertCount(1, $branches);
        self::assertIsArray($branches[0]);
        self::assertIsArray($branches[0][1]);
        self::assertSame(['sequence'], array_column($branches[0][1], 0));
        self::assertIsArray($otherwise);
        self::assertSame(['variable', 'variable', 'variable', 'variable', 'sequence'], array_column($otherwise, 0));
        self::assertIsArray($otherwise[2]);
        self::assertSame(['variable', 'sum', 'xs:integer'], array_slice($otherwise[2], 0, 3));
    }

    public function test_compiled_peppol_rule_set_loads_back(): void
    {
        $compiled = self::peppol('peppol-cii');
        $rules = RuleSet::fromArray(require self::writeCompiled($compiled));

        self::assertSame($compiled['lets'], $rules->lets());
        self::assertSame($compiled['functions'], $rules->functions());
        self::assertSame($compiled['patterns'][0]['lets'], $rules->patterns()[0]['lets']);
    }

    public function test_compiles_let_scopes(): void
    {
        $compiled = Compiler::compile(self::writeSchematronDocument(<<<'XML'
            <let name="a" value="1"/>
            <let name="b" value="$a + 1"/>
            <pattern id="p">
              <let name="c" value="$b + 1"/>
              <rule context="cbc:ID[$c = 3]">
                <let name="d" value="$c + $a"/>
                <assert id="R-1" flag="fatal" test="$d = 4">x</assert>
                <let name="e" value="u:twice($d)"/>
                <assert id="R-2" flag="fatal" test="$e = 8">x</assert>
              </rule>
            </pattern>
            <xsl:function name="u:twice" as="xs:integer">
              <xsl:param name="n" as="xs:integer"/>
              <xsl:variable name="m" select="$n * 2 + $a - 1"/>
              <xsl:sequence select="$m"/>
            </xsl:function>
            XML), 'tiny', '0.1');

        self::assertSame(['a', 'b'], array_column($compiled['lets'], 'name'));
        self::assertSame(['name' => 'b', 'value' => '$a + 1'], array_diff_key($compiled['lets'][1], ['ast' => true]));
        self::assertSame([Op::VAR, 'a'], $compiled['lets'][1]['ast'][2]);
        self::assertSame(['c'], array_column($compiled['patterns'][0]['lets'], 'name'));
        self::assertSame(['d', 'e'], array_column($compiled['patterns'][0]['rules'][0]['lets'], 'name'));
        self::assertSame(['{utils}twice#1'], array_keys($compiled['functions']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        $function = static fn (string $body, string $signature = 'name="u:f" as="xs:boolean"'): string => <<<XML
            <xsl:function {$signature}><xsl:param name="v"/>{$body}</xsl:function>
            <pattern id="p"><rule context="cbc:ID"><assert id="R-1" flag="fatal" test="u:f(.)">x</assert></rule></pattern>
            XML;
        $rule = static fn (string $body): string => "<pattern id=\"p\"><rule context=\"cbc:ID\">{$body}<assert id=\"R-1\" flag=\"fatal\" test=\"true()\">x</assert></rule></pattern>";

        yield 'schema let using a later schema let' => ['<let name="a" value="$b"/><let name="b" value="1"/>'.$rule(''), '$b'];
        yield 'rule let using a later rule let' => [$rule('<let name="a" value="$b"/><let name="b" value="1"/>'), '$b'];
        yield 'assert using a later rule let' => ['<pattern id="p"><rule context="cbc:ID"><assert id="R-1" flag="fatal" test="$a">x</assert><let name="a" value="1"/></rule></pattern>', '$a'];
        yield 'rule context using a rule let' => ['<pattern id="p"><rule context="cbc:ID[$a]"><let name="a" value="1"/><assert id="R-1" flag="fatal" test="true()">x</assert></rule></pattern>', '$a'];
        yield 'pattern let of another pattern' => ['<pattern id="p"><let name="a" value="1"/></pattern>'.$rule('<let name="b" value="$a"/>'), '$a'];
        yield 'duplicate schema let' => ['<let name="a" value="1"/><let name="a" value="2"/>'.$rule(''), 'variable "a" is declared twice'];
        yield 'pattern let named like a schema let' => ['<let name="a" value="1"/><pattern id="q"><let name="a" value="2"/></pattern>'.$rule(''), 'variable "a" is declared twice'];
        yield 'let without a value' => [$rule('<let name="a">1</let>'), 'without a value'];
        yield 'function using a pattern let' => [$function('<xsl:sequence select="$c"/>').'<pattern id="q"><let name="c" value="1"/></pattern>', '$c'];
        yield 'function using a rule let' => [str_replace('test="u:f(.)"', 'test="u:f($c)"', $function('<xsl:sequence select="$c"/>')), '$c'];
        yield 'duplicate function signature' => [$function('<xsl:sequence select="true()"/>').'<xsl:function name="u:f" as="xs:boolean"><xsl:param name="w"/><xsl:sequence select="false()"/></xsl:function>', 'u:f is declared twice'];
        yield 'for-each in a function' => [$function('<xsl:for-each select="1"><xsl:sequence select="true()"/></xsl:for-each>'), '{http://www.w3.org/1999/XSL/Transform}for-each'];
        yield 'unsupported sequence type' => [$function('<xsl:sequence select="true()"/>', 'name="u:f" as="item()*"'), '"item()*"'];
        yield 'unsupported param type' => [str_replace('<xsl:param name="v"/>', '<xsl:param name="v" as="node()"/>', $function('<xsl:sequence select="true()"/>')), '"node()"'];
        yield 'two results' => [$function('<xsl:sequence select="true()"/><xsl:sequence select="true()"/>'), 'exactly one result'];
        yield 'no result' => [$function('<xsl:variable name="x" select="1"/>'), 'exactly one result'];
        yield 'choose without otherwise' => [$function('<xsl:choose><xsl:when test="$v"><xsl:sequence select="true()"/></xsl:when></xsl:choose>'), 'exactly one result'];
        yield 'variable after the result' => [$function('<xsl:sequence select="true()"/><xsl:variable name="x" select="1"/>'), 'exactly one result'];
        yield 'text in a function body' => [$function('true<xsl:sequence select="true()"/>'), 'text'];
        yield 'variable with text and elements' => [$function('<xsl:variable name="x" as="xs:string">a<xsl:sequence select="1"/></xsl:variable><xsl:sequence select="true()"/>'), 'text'];
        yield 'variable with select and content' => [$function('<xsl:variable name="x" select="1">a</xsl:variable><xsl:sequence select="true()"/>'), 'both'];
        yield 'variable content without a type' => [$function('<xsl:variable name="x"><xsl:sequence select="1"/></xsl:variable><xsl:sequence select="true()"/>'), 'without "as"'];
        yield 'param after a variable' => [$function('<xsl:variable name="x" select="1"/><xsl:param name="w"/><xsl:sequence select="true()"/>'), 'param'];
        yield 'duplicate param' => [str_replace('<xsl:param name="v"/>', '<xsl:param name="v"/><xsl:param name="v"/>', $function('<xsl:sequence select="true()"/>')), 'parameter "v" is declared twice'];
        yield 'unprefixed function name' => [$function('<xsl:sequence select="true()"/>', 'name="f"'), 'prefixed'];
        yield 'xsl element in a pattern' => ['<pattern id="p"><xsl:variable name="x" select="1"/></pattern>', '{http://www.w3.org/1999/XSL/Transform}variable'];
    }

    #[DataProvider('invalidDeclarations')]
    public function test_rejects_invalid_declarations(string $body, string $message): void
    {
        $path = self::writeSchematronDocument($body);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($message);

        Compiler::compile($path, 'tiny', '0.1');
    }

    public function test_default_phase_keeps_its_own_message(): void
    {
        $path = self::writeSchematron('<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x</assert></rule>', '', ' defaultPhase="ph"');

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('selects a default phase');

        Compiler::compile($path, 'tiny', '0.1');
    }

    public function test_rejects_a_processing_instruction_inside_an_assert(): void
    {
        $path = self::writeSchematron('<rule context="cbc:ID"><assert id="R-1" flag="fatal" test="true()">x <?php echo 1; ?></assert></rule>');

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('processing instruction');

        Compiler::compile($path, 'tiny', '0.1');
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

        CompiledRuleSetWriter::write($compiled, $first, 'CEN EN 16931 validation artefacts 1.3.17 (EUPL-1.2)');
        CompiledRuleSetWriter::write($compiled, $second, 'CEN EN 16931 validation artefacts 1.3.17 (EUPL-1.2)');

        self::assertSame(file_get_contents($first), file_get_contents($second));
        self::assertStringContainsString('// Generated from CEN EN 16931 validation artefacts 1.3.17 (EUPL-1.2) — do not edit.', (string) file_get_contents($first));
        self::assertTrue((require $first) == $compiled);
    }

    public function test_header_names_the_given_origin(): void
    {
        $lines = explode("\n", CompiledRuleSetWriter::render(self::compiled('ubl'), 'X origin'));
        $comments = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, '//')));

        self::assertSame('// Generated from X origin — do not edit.', $comments[0]);
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

    /**
     * @return CompiledRuleSet
     */
    private static function peppol(string $key): array
    {
        return self::$compiled[$key] ??= Compiler::compile(Fixtures::peppolSchematron($key), $key, '3.0.22');
    }

    /**
     * @param  CompiledRuleSet  $compiled
     */
    private static function countRules(array $compiled): int
    {
        return array_sum(array_map(static fn (array $pattern): int => count($pattern['rules']), $compiled['patterns']));
    }

    /**
     * @param  CompiledRuleSet  $compiled
     * @return array<string, int>
     */
    private static function flagCounts(array $compiled): array
    {
        return array_count_values(array_column(self::asserts($compiled), 'flag'));
    }

    private static function containsCall(mixed $node, string $uri, string $local): bool
    {
        if (! is_array($node)) {
            return false;
        }
        if (($node[0] ?? null) === Op::UCALL && ($node[1] ?? null) === $uri && ($node[2] ?? null) === $local) {
            return true;
        }
        foreach ($node as $child) {
            if (self::containsCall($child, $uri, $local)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  CompiledRuleSet  $compiled
     */
    private static function writeCompiled(array $compiled): string
    {
        self::scratch();
        $path = self::SCRATCH.'/peppol-compiled.php';
        CompiledRuleSetWriter::write($compiled, $path, 'test');

        return $path;
    }

    /**
     * A schema with the cbc and u prefixes bound and the given children (lets, functions, patterns).
     */
    private static function writeSchematronDocument(string $children): string
    {
        self::scratch();
        $path = self::SCRATCH.'/tiny.sch';
        $cbc = self::CBC;
        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <schema xmlns="http://purl.oclc.org/dsdl/schematron" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:u="utils" queryBinding="xslt2">
              <ns prefix="cbc" uri="{$cbc}"/>
              <ns prefix="u" uri="utils"/>
              {$children}
            </schema>
            XML);

        return $path;
    }

    private static function scratch(): void
    {
        if (! is_dir(self::SCRATCH)) {
            mkdir(self::SCRATCH, 0777, true);
        }
    }

    private static function writeSchematron(string $rules, string $patternAttributes = '', string $schemaAttributes = '', string $nsAttributes = ''): string
    {
        self::scratch();
        $path = self::SCRATCH.'/tiny.sch';
        $cbc = self::CBC;
        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <schema xmlns="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt2"{$schemaAttributes}>
              <title>Tiny</title>
              <ns prefix="cbc" uri="{$cbc}"{$nsAttributes}/>
              <pattern id="p"{$patternAttributes}>
                {$rules}
              </pattern>
            </schema>
            XML);

        return $path;
    }
}

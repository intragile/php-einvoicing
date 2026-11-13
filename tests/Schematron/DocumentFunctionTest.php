<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Finding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentFunctionTest extends TestCase
{
    private const SCRATCH = __DIR__.'/../../.cache/tests/schematron/document';

    private const CODEDB = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <!-- code lists -->
        <codedb>
          <cl id="1">
            <enumeration value="A"/>
            <enumeration value="B"/>
          </cl>
          <cl id="2">
            <enumeration value="C"/>
          </cl>
        </codedb>
        XML;

    private const DOCUMENT = <<<'XML'
        <t:Root xmlns:t="urn:test:document">
          <t:Item code="A"/>
          <t:Item code="C"/>
          <t:Item code="B"/>
          <t:Item code="D"/>
        </t:Root>
        XML;

    public function test_document_with_literal_uri_is_embedded(): void
    {
        $dir = self::scratch('embedded', ['codedb.xml' => self::CODEDB]);

        $compiled = Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');

        self::assertSame(['codedb.xml' => self::CODEDB], $compiled['documents'] ?? null);
        self::assertSame(['codedb.xml' => hash('sha256', self::CODEDB)], $compiled['documentSha256'] ?? null);
        self::assertSame(RuleSet::FORMAT, $compiled['format']);

        $path = "{$dir}/compiled.php";
        CompiledRuleSetWriter::write($compiled, $path, 'test');
        $rules = RuleSet::fromFile($path);
        self::assertSame(['codedb.xml' => self::CODEDB], $rules->documents());
        self::assertSame(['codedb.xml' => hash('sha256', self::CODEDB)], $rules->documentSha256());
    }

    public function test_schema_without_document_calls_has_no_document_keys(): void
    {
        $dir = self::scratch('none', [], "exists(\$v[. = ('A', 'B')])");

        $compiled = Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');

        self::assertArrayNotHasKey('documents', $compiled);
        self::assertArrayNotHasKey('documentSha256', $compiled);
        self::assertSame([], RuleSet::fromArray($compiled)->documents());
    }

    public function test_document_lookup_matches_inline_sequence(): void
    {
        $lookup = self::scratch('lookup', ['codedb.xml' => self::CODEDB]);
        $inline = self::scratch('inline', [], "\$v = ('A', 'B')");

        $viaDocument = self::runRules(Compiler::compile("{$lookup}/main.sch", 'tiny', '0.1'));
        $viaSequence = self::runRules(Compiler::compile("{$inline}/main.sch", 'tiny', '0.1'));

        self::assertSame([], $viaDocument['errors']);
        self::assertSame(['CL-1 /t:Root[1]/t:Item[2]/@code', 'CL-1 /t:Root[1]/t:Item[4]/@code'], $viaDocument['findings']);
        self::assertSame($viaSequence, $viaDocument);
    }

    public function test_document_is_parsed_once_per_rule_set(): void
    {
        $dir = self::scratch('cached', ['codedb.xml' => self::CODEDB]);
        $rules = RuleSet::fromArray(Compiler::compile("{$dir}/main.sch", 'tiny', '0.1'));

        $first = $rules->documentIndex('codedb.xml');

        self::assertNotNull($first);
        self::assertSame($first, $rules->documentIndex('codedb.xml'));
        self::assertNull($rules->documentIndex('other.xml'));
    }

    public function test_document_in_a_rule_context_and_a_let(): void
    {
        $dir = self::scratch('context', ['codedb.xml' => self::CODEDB], 'false()', <<<'XML'
            <rule context="t:Item[@code = document('codedb.xml')/codedb/cl[@id = 2]/enumeration/@value]">
              <let name="count" value="count(document('codedb.xml')//enumeration)"/>
              <assert id="CTX" flag="warning" test="$count != 3">three codes</assert>
            </rule>
            XML);

        $result = self::runRules(Compiler::compile("{$dir}/main.sch", 'tiny', '0.1'));

        self::assertSame(['CTX /t:Root[1]/t:Item[2]'], $result['findings']);
        self::assertSame([], $result['errors']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonLiteralArguments(): iterable
    {
        yield 'variable' => ['document($v)', 'CL-1'];
        yield 'concatenation' => ["document(concat('codedb', '.xml'))", 'CL-1'];
        yield 'context item' => ['document(.)', 'CL-1'];
    }

    #[DataProvider('nonLiteralArguments')]
    public function test_non_literal_document_argument_is_rejected(string $call, string $assertId): void
    {
        $dir = self::scratch('non-literal', ['codedb.xml' => self::CODEDB], "exists({$call})");

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessageMatches('/document\(\).*string literal.*assert "'.$assertId.'"|assert "'.$assertId.'".*document\(\).*string literal/');

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    public function test_non_literal_document_argument_in_a_let_names_the_let(): void
    {
        $dir = self::scratch('non-literal-let', ['codedb.xml' => self::CODEDB], 'true()', <<<'XML'
            <rule context="t:Item">
              <let name="db" value="document(string(@code))"/>
              <assert id="X" flag="fatal" test="exists($db)">x</assert>
            </rule>
            XML);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('the let "db"');

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedUris(): iterable
    {
        yield 'parent directory' => ['../x.xml', '..'];
        yield 'parent segment inside' => ['sub/../codedb.xml', '..'];
        yield 'backslash parent segment' => ['..\\x.xml', '..'];
        yield 'absolute path' => ['/etc/hosts', 'absolute'];
        yield 'backslash absolute path' => ['\\etc\\hosts', 'absolute'];
        yield 'file URI' => ['file:///x', 'scheme'];
        yield 'http URI' => ['http://x', 'scheme'];
        yield 'drive letter' => ['C:/x.xml', 'scheme'];
        yield 'missing file' => ['missing.xml', 'does not exist'];
        yield 'directory' => ['sub', 'does not exist'];
        yield 'empty' => ['', 'empty'];
    }

    #[DataProvider('rejectedUris')]
    public function test_document_uri_outside_schema_dir_is_rejected(string $uri, string $message): void
    {
        $dir = self::scratch('outside', ['codedb.xml' => self::CODEDB, 'sub/codedb.xml' => self::CODEDB], "exists(document('{$uri}')/codedb)");
        file_put_contents(dirname($dir).'/x.xml', self::CODEDB);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($message);

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    public function test_document_symlink_leaving_the_schema_dir_is_rejected(): void
    {
        $dir = self::scratch('symlink', [], "exists(document('link.xml')/codedb)");
        file_put_contents(dirname($dir).'/outside.xml', self::CODEDB);
        @unlink("{$dir}/link.xml");
        if (! @symlink(dirname($dir).'/outside.xml', "{$dir}/link.xml")) {
            self::markTestSkipped('Symbolic links are not available.');
        }

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('outside');

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    public function test_document_needs_a_base_directory(): void
    {
        $dir = self::scratch('string', ['codedb.xml' => self::CODEDB]);
        $xml = (string) file_get_contents("{$dir}/main.sch");

        self::assertSame(
            Compiler::compile("{$dir}/main.sch", 'tiny', '0.1')['documents'] ?? null,
            Compiler::compileString($xml, 'main.sch', 'tiny', '0.1', $dir)['documents'] ?? null,
        );

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('base directory');

        Compiler::compileString($xml, 'main.sch', 'tiny', '0.1');
    }

    public function test_document_with_doctype_is_rejected(): void
    {
        $doctype = "<?xml version=\"1.0\"?>\n<!DOCTYPE codedb [<!ENTITY e \"x\">]>\n<codedb><cl id=\"1\"><enumeration value=\"&e;\"/></cl></codedb>";
        $dir = self::scratch('doctype', ['codedb.xml' => $doctype]);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('DOCTYPE');

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    public function test_malformed_document_is_rejected(): void
    {
        $dir = self::scratch('malformed', ['codedb.xml' => '<codedb><cl>']);

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('codedb.xml');

        Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
    }

    public function test_unknown_document_uri_at_run_time_is_fodc0002(): void
    {
        $dir = self::scratch('unknown', ['codedb.xml' => self::CODEDB]);
        $compiled = Compiler::compile("{$dir}/main.sch", 'tiny', '0.1');
        unset($compiled['documents'], $compiled['documentSha256']);

        $result = (new Runner)->run(RuleSet::fromArray($compiled), SafeXml::load(self::DOCUMENT));

        self::assertSame([], $result->findings);
        self::assertCount(4, $result->dynamicErrors);
        self::assertSame('FODC0002', $result->dynamicErrors[0]->errorCode);
        self::assertSame('CL-1', $result->dynamicErrors[0]->ruleId);
        self::assertStringContainsString('codedb.xml', $result->dynamicErrors[0]->message);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function malformedDocumentKeys(): iterable
    {
        yield 'documents not a map' => [['documents' => 'x'], 'documents'];
        yield 'document not a string' => [['documents' => ['a.xml' => 1]], 'documents'];
        yield 'document key not a string' => [['documents' => [0 => '<a/>']], 'documents'];
        yield 'hash not a string' => [['documents' => ['a.xml' => '<a/>'], 'documentSha256' => ['a.xml' => 1]], 'documentSha256'];
        yield 'hash for another document' => [['documents' => ['a.xml' => '<a/>'], 'documentSha256' => ['b.xml' => 'x']], 'documentSha256'];
    }

    /**
     * @param  array<string, mixed>  $keys
     */
    #[DataProvider('malformedDocumentKeys')]
    public function test_malformed_document_keys_are_refused(array $keys, string $message): void
    {
        $compiled = ['format' => RuleSet::FORMAT, 'id' => 'tiny', 'version' => '0.1', 'namespaces' => [], 'lets' => [], 'functions' => [], 'patterns' => [], ...$keys];

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage($message);

        RuleSet::fromArray($compiled);
    }

    /**
     * @param  array<mixed>  $compiled
     * @return array{findings: list<string>, errors: list<string>}
     */
    private static function runRules(array $compiled): array
    {
        $result = (new Runner)->run(RuleSet::fromArray($compiled), SafeXml::load(self::DOCUMENT));

        return [
            'findings' => array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->location}", $result->findings),
            'errors' => array_map(static fn ($error): string => "{$error->ruleId} {$error->errorCode}", $result->dynamicErrors),
        ];
    }

    /**
     * Writes main.sch, with one rule checking every code attribute against $test (or the given
     * rule), plus the given files, into a fresh scratch directory.
     *
     * @param  array<string, string>  $files  relative path => contents
     */
    private static function scratch(string $name, array $files, string $test = "exists(document('codedb.xml')/codedb/cl[@id=1]/enumeration[@value=\$v])", ?string $rule = null): string
    {
        $dir = self::SCRATCH."/{$name}";
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $rule ??= <<<XML
            <rule context="t:Item/@code">
              <let name="v" value="string(.)"/>
              <assert id="CL-1" flag="fatal" test="{$test}">The code is not in code list 1.</assert>
            </rule>
            XML;
        $files['main.sch'] = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <schema xmlns="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt2">
              <ns prefix="t" uri="urn:test:document"/>
              <pattern id="p">
                {$rule}
              </pattern>
            </schema>
            XML;
        foreach ($files as $path => $contents) {
            if (! is_dir(dirname("{$dir}/{$path}"))) {
                mkdir(dirname("{$dir}/{$path}"), 0777, true);
            }
            file_put_contents("{$dir}/{$path}", $contents);
        }

        return (string) realpath($dir);
    }
}

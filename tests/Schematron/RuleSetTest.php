<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;
use ParseError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleSetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('rule-set-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    public function test_a_file_with_a_syntax_error_is_reported_as_corrupt(): void
    {
        $path = $this->dir.'/broken.php';
        file_put_contents($path, "<?php\nreturn [;\n");

        try {
            RuleSet::fromFile($path);
            self::fail('A corrupt file must be refused.');
        } catch (UnsupportedSchematron $exception) {
            self::assertStringContainsString('is corrupt; reinstall', $exception->getMessage());
            self::assertInstanceOf(ParseError::class, $exception->getPrevious());
        }
    }

    public function test_a_file_that_throws_a_type_error_is_reported_as_corrupt(): void
    {
        $path = $this->dir.'/typeerror.php';
        file_put_contents($path, "<?php\nreturn strlen([]);\n");

        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('is corrupt; reinstall');

        RuleSet::fromFile($path);
    }

    /**
     * A rule set installed by an engine that wrote format 2 (before message parts existed) keeps
     * loading and running: format 3 only adds the optional "messageParts" of an assert.
     */
    public function test_format_2_rule_set_still_loads(): void
    {
        $path = $this->dir.'/format-2.php';
        file_put_contents($path, <<<'PHP'
            <?php

            declare(strict_types=1);

            return array (
              'format' => 2,
              'id' => 'legacy',
              'version' => '1.0',
              'source' => 'legacy.sch',
              'sha256' => '0000000000000000000000000000000000000000000000000000000000000000',
              'namespaces' => array ('t' => 'urn:test:legacy'),
              'lets' => array (),
              'functions' => array (),
              'patterns' => array (
                0 => array (
                  'id' => 'p',
                  'lets' => array (),
                  'rules' => array (
                    0 => array (
                      'context' => 't:Item',
                      'match' => array ('path', array ('root'), array ('path', array ('step', 'descendant-or-self', array ('node'), array ()), array ('step', 'child', array ('name', 'urn:test:legacy', 'Item'), array ()))),
                      'lets' => array (),
                      'asserts' => array (
                        0 => array ('id' => 'L-1', 'flag' => 'warning', 'test' => 'false()', 'ast' => array ('call', 'fn:false', array ()), 'message' => '[L-1] Legacy message.'),
                      ),
                    ),
                  ),
                ),
              ),
            );
            PHP);

        $rules = RuleSet::fromFile($path);
        $result = (new Runner)->run($rules, SafeXml::load('<t:Root xmlns:t="urn:test:legacy"><t:Item/></t:Root>'));

        self::assertSame('legacy', $rules->id());
        self::assertEquals([new Finding('L-1', Severity::Warning, '/t:Root[1]/t:Item[1]', '[L-1] Legacy message.', 'false()')], $result->findings);
        self::assertSame([], $result->dynamicErrors);
    }

    public function test_format_4_is_refused_with_recompile_message(): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('The rule set "future" was compiled with format 4; this engine reads formats 2 and 3. Recompile it.');

        RuleSet::fromArray(['format' => 4, 'id' => 'future', 'version' => '1', 'namespaces' => [], 'lets' => [], 'functions' => [], 'patterns' => []]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedMessageParts(): iterable
    {
        yield 'not a list' => [['a' => 'x']];
        yield 'unknown part' => [[['emph' => true]]];
        yield 'name not true' => [[['name' => false]]];
        yield 'value-of without a tree' => [[['valueOf' => 'x']]];
        yield 'integer part' => [[1]];
    }

    #[DataProvider('malformedMessageParts')]
    public function test_malformed_message_parts_are_refused(mixed $parts): void
    {
        $this->expectException(UnsupportedSchematron::class);
        $this->expectExceptionMessage('The message of the assert "M-1" is malformed');

        RuleSet::fromArray(['format' => 3, 'id' => 'm', 'version' => '1', 'namespaces' => [], 'lets' => [], 'functions' => [], 'patterns' => [
            ['id' => 'p', 'lets' => [], 'rules' => [['context' => 'a', 'match' => ['path'], 'lets' => [], 'asserts' => [
                ['id' => 'M-1', 'flag' => 'fatal', 'test' => 'true()', 'ast' => ['call', 'fn:true', []], 'message' => 'x', 'messageParts' => $parts],
            ]]]],
        ]]);
    }
}

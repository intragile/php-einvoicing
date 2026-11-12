<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Schematron;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use ParseError;
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
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use IntrAgile\EInvoicing\Tests\XPath\ProbeCases;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks that Saxon-HE produces the expected result of every probe case, so the truth table
 * used by the PHP evaluator is Saxon's behaviour and not a reading of the specification.
 */
#[Group('oracle')]
final class ProbeOracleTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cases(): array
    {
        return ProbeCases::all();
    }

    #[DataProvider('cases')]
    public function test_saxon_matches_expected_result(string $expression, string $xml, string $expected): void
    {
        self::assertSame($expected, OfficialXPath::probe($expression, $xml, ProbeCases::NAMESPACES), $expression);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function functionCases(): array
    {
        return ProbeCases::functions();
    }

    #[DataProvider('functionCases')]
    public function test_saxon_matches_expected_function_result(string $expression, string $xml, string $expected, string $prelude): void
    {
        self::assertSame($expected, OfficialXPath::probe($expression, $xml, ProbeCases::FUNCTION_NAMESPACES, ProbeCases::prelude($prelude)), $expression);
    }
}

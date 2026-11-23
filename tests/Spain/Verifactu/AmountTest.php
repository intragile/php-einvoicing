<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function canonicalCases(): iterable
    {
        yield 'two decimals' => ['123.45', '123.45'];
        yield 'integer' => ['100', '100.00'];
        yield 'negative one decimal' => ['-12.3', '-12.30'];
        yield 'zero' => ['0', '0.00'];
        yield 'rounds half up' => ['100000000.005', '100000000.01'];
        yield 'negative rounding to zero' => ['-0.004', '0.00'];
        yield 'twelve digits' => ['999999999999.99', '999999999999.99'];
    }

    #[DataProvider('canonicalCases')]
    public function test_canonical_text(string $input, string $expected): void
    {
        $this->assertSame($expected, Amount::of($input)->text);
    }

    #[DataProvider('canonicalCases')]
    public function test_canonical_text_matches_the_xsd_pattern(string $input): void
    {
        $this->assertMatchesRegularExpression('/\A(\+|-)?\d{1,12}(\.\d{0,2})?\z/', Amount::of($input)->text);
    }

    public function test_int_input_is_accepted(): void
    {
        $this->assertSame('5.00', Amount::of(5)->text);
    }

    public function test_thirteen_integer_digits_are_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        Amount::of('1000000000000');
    }

    public function test_rounding_into_thirteen_digits_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        Amount::of('999999999999.995');
    }

    public function test_literal_text_is_kept(): void
    {
        $this->assertSame('41.4', Amount::fromLiteral(' 41.4 ')->text);
        $this->assertSame('+5', Amount::fromLiteral('+5')->text);
        $this->assertTrue(Amount::fromLiteral(' 41.4 ')->value->isEqualTo('41.4'));
        $this->assertTrue(Amount::fromLiteral('5.')->value->isEqualTo('5'));
    }

    #[DataProvider('badLiterals')]
    public function test_literal_outside_pattern_is_refused(string $text): void
    {
        $this->expectException(InvalidRecord::class);
        Amount::fromLiteral($text);
    }

    /** @return iterable<string, array{string}> */
    public static function badLiterals(): iterable
    {
        yield 'three decimals' => ['1.234'];
        yield 'comma' => ['1,5'];
        yield 'letters' => ['abc'];
        yield 'empty' => [''];
        yield 'thirteen digits' => ['1000000000000'];
    }

    public function test_equality_is_by_value(): void
    {
        $a = Amount::of('100');
        $b = Amount::fromLiteral('100.0');
        $this->assertTrue($a->isEqualTo($b));
        $this->assertNotSame($a->text, $b->text);
    }

    public function test_negated_and_abs(): void
    {
        $this->assertSame('-5.00', Amount::of('5')->negated()->text);
        $this->assertSame('5.00', Amount::of('-5')->negated()->text);
        $this->assertSame('0.00', Amount::of('0')->negated()->text);
        $this->assertTrue(Amount::of('-5.5')->abs()->isEqualTo('5.5'));
    }

    public function test_rate_text(): void
    {
        $this->assertSame('21.00', TaxRate::of('21')->text);
        $this->assertSame('4', TaxRate::fromLiteral('4')->text);
        $this->assertTrue(TaxRate::fromLiteral('4')->value->isEqualTo('4'));
    }

    public function test_rate_out_of_range_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        TaxRate::of('1000');
    }

    public function test_negative_rate_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        TaxRate::of('-1');
    }

    public function test_rate_literal_outside_pattern_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        TaxRate::fromLiteral('21.123');
    }
}

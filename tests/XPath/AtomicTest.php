<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\XPath\Atomic;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
use IntrAgile\EInvoicing\XPath\Value\DateValue;
use IntrAgile\EInvoicing\XPath\Value\DecimalValue;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\Value\StringValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AtomicTest extends TestCase
{
    public function test_decimal_cast_collapses_whitespace_and_keeps_value(): void
    {
        $value = Atomic::cast(new UntypedAtomic('  12.50 '), 'xs:decimal');

        self::assertInstanceOf(DecimalValue::class, $value);
        self::assertSame('xs:decimal', $value->type());
        self::assertTrue($value->value->isEqualTo('12.50'));
        self::assertSame('12.5', $value->lexical());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDecimals(): iterable
    {
        yield 'comma' => ['12,50'];
        yield 'exponent' => ['1e3'];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'letters' => ['abc'];
        yield 'inner space' => ['1 2'];
        yield 'lone point' => ['.'];
        yield 'nbsp' => ["\u{A0}12"];
    }

    #[DataProvider('invalidDecimals')]
    public function test_invalid_decimal_lexical_is_forg0001(string $lexical): void
    {
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic($lexical), 'xs:decimal'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validDecimals(): iterable
    {
        yield 'plus sign' => ['+3', '3'];
        yield 'minus fraction' => ['-0.50', '-0.5'];
        yield 'leading point' => ['.5', '0.5'];
        yield 'trailing point' => ['5.', '5'];
        yield 'negative zero' => ['-0.0', '0'];
        yield 'tabs and newlines' => ["\t\n7\r", '7'];
    }

    #[DataProvider('validDecimals')]
    public function test_valid_decimal_lexical_forms(string $lexical, string $canonical): void
    {
        self::assertSame($canonical, Atomic::cast(new StringValue($lexical), 'xs:decimal')->lexical());
    }

    public function test_untyped_equals_integer_numerically(): void
    {
        self::assertTrue(Atomic::generalCompare('=', [new UntypedAtomic('1.0')], [self::int('1')]));
    }

    public function test_untyped_equals_string_by_codepoints(): void
    {
        self::assertFalse(Atomic::generalCompare('=', [new UntypedAtomic('1.0')], [new StringValue('1')]));
        self::assertTrue(Atomic::generalCompare('=', [new UntypedAtomic('1.0')], [new UntypedAtomic('1.0')]));
    }

    public function test_untyped_compared_with_boolean_needs_boolean_lexical(): void
    {
        self::assertXPathError('FORG0001', static fn () => Atomic::generalCompare('=', [new UntypedAtomic('abc')], [new BooleanValue(true)]));
        self::assertTrue(Atomic::generalCompare('=', [new UntypedAtomic(' 1 ')], [new BooleanValue(true)]));
        self::assertTrue(Atomic::generalCompare('=', [new UntypedAtomic('false')], [new BooleanValue(false)]));
    }

    public function test_general_comparison_is_existential(): void
    {
        $left = [new UntypedAtomic('1'), new UntypedAtomic('2')];

        self::assertTrue(Atomic::generalCompare('=', $left, [self::int('2')]));
        self::assertTrue(Atomic::generalCompare('!=', $left, [self::int('2')]));
        self::assertFalse(Atomic::generalCompare('=', $left, []));
        self::assertFalse(Atomic::generalCompare('>', $left, [self::int('5')]));
    }

    public function test_general_comparison_of_non_numeric_untyped_with_number_is_forg0001(): void
    {
        self::assertXPathError('FORG0001', static fn () => Atomic::generalCompare('=', [new UntypedAtomic('12,50')], [self::int('0')]));
    }

    public function test_value_comparison_of_string_and_integer_is_xpty0004(): void
    {
        self::assertXPathError('XPTY0004', static fn () => Atomic::valueCompare('eq', new StringValue('1'), self::int('1')));
    }

    public function test_value_comparison_treats_untyped_as_string(): void
    {
        self::assertTrue(Atomic::valueCompare('eq', new UntypedAtomic('abc'), new StringValue('abc')));
        self::assertXPathError('XPTY0004', static fn () => Atomic::valueCompare('eq', new UntypedAtomic('1'), self::int('1')));
    }

    public function test_value_comparison_promotes_numerics(): void
    {
        self::assertTrue(Atomic::valueCompare('eq', self::int('1'), new DecimalValue(BigDecimal::of('1.00'))));
        self::assertTrue(Atomic::valueCompare('lt', new DecimalValue(BigDecimal::of('0.5')), new DoubleValue(0.75)));
        self::assertFalse(Atomic::valueCompare('eq', new DoubleValue(NAN), new DoubleValue(NAN)));
        self::assertTrue(Atomic::valueCompare('ne', new DoubleValue(NAN), new DoubleValue(NAN)));
    }

    public function test_string_comparison_uses_codepoints(): void
    {
        self::assertTrue(Atomic::valueCompare('lt', new StringValue('Z'), new StringValue('a')));
        self::assertTrue(Atomic::valueCompare('lt', new StringValue('z'), new StringValue("\u{E9}")));
    }

    public function test_integer_div_integer_is_decimal(): void
    {
        $value = Atomic::arithmetic('div', self::int('1'), self::int('2'));

        self::assertInstanceOf(DecimalValue::class, $value);
        self::assertSame('0.5', $value->lexical());
    }

    public function test_integer_plus_integer_is_integer(): void
    {
        $value = Atomic::arithmetic('+', self::int('1'), self::int('1'));

        self::assertInstanceOf(IntegerValue::class, $value);
        self::assertSame('2', $value->lexical());
    }

    public function test_untyped_arithmetic_is_double(): void
    {
        $value = Atomic::arithmetic('+', new UntypedAtomic('0.1'), new UntypedAtomic('0.2'));

        self::assertInstanceOf(DoubleValue::class, $value);
        self::assertSame('0.30000000000000004', $value->lexical());
    }

    public function test_decimal_arithmetic_is_exact(): void
    {
        $value = Atomic::arithmetic('+', new DecimalValue(BigDecimal::of('0.1')), new DecimalValue(BigDecimal::of('0.2')));

        self::assertInstanceOf(DecimalValue::class, $value);
        self::assertSame('0.3', $value->lexical());
        self::assertSame('5', Atomic::arithmetic('*', new DecimalValue(BigDecimal::of('2.5')), self::int('2'))->lexical());
        self::assertSame('-1.5', Atomic::arithmetic('-', self::int('1'), new DecimalValue(BigDecimal::of('2.5')))->lexical());
    }

    public function test_decimal_with_double_is_double(): void
    {
        self::assertInstanceOf(DoubleValue::class, Atomic::arithmetic('*', new DecimalValue(BigDecimal::of('2')), new DoubleValue(1.5)));
    }

    public function test_decimal_division_by_zero_is_foar0001(): void
    {
        self::assertXPathError('FOAR0001', static fn () => Atomic::arithmetic('div', self::int('1'), self::int('0')));
    }

    public function test_double_division_by_zero_is_infinity(): void
    {
        self::assertSame('INF', Atomic::arithmetic('div', new DoubleValue(1.0), new DoubleValue(0.0))->lexical());
        self::assertSame('NaN', Atomic::arithmetic('div', new DoubleValue(0.0), new DoubleValue(0.0))->lexical());
    }

    public function test_arithmetic_on_string_is_xpty0004(): void
    {
        self::assertXPathError('XPTY0004', static fn () => Atomic::arithmetic('+', new StringValue('1'), self::int('1')));
    }

    public function test_decimal_division_keeps_eighteen_fraction_digits(): void
    {
        self::assertSame('0.333333333333333333', Atomic::arithmetic('div', self::int('1'), self::int('3'))->lexical());
    }

    /**
     * Saxon-HE 13: scale max(18, scale(a) - scale(b) + 18) with trailing zeros stripped (integers
     * inside the 32-bit range count as scale 0), rounding HALF_DOWN.
     *
     * @return iterable<string, array{AtomicValue, AtomicValue, string}>
     */
    public static function decimalDivisions(): iterable
    {
        yield 'dividend scale adds digits' => [new DecimalValue(BigDecimal::of('0.000001')), self::int('3'), '0.000000333333333333333333'];
        yield 'trailing zeros do not count' => [new DecimalValue(BigDecimal::of('1.00000000000000000000')), self::int('3'), '0.333333333333333333'];
        yield 'integral decimal divisor' => [self::int('1'), new DecimalValue(BigDecimal::of('300.0')), '0.00333333333333333333'];
        yield '32-bit integer divisor' => [self::int('1'), self::int('300'), '0.003333333333333333'];
        yield 'integer divisor beyond 32 bits' => [self::int('1'), self::int('3000000000'), '0.000000000333333333333333333'];
        yield 'half down' => [self::int('3'), self::int('524288'), '0.000005722045898437'];
    }

    #[DataProvider('decimalDivisions')]
    public function test_decimal_division_scale_matches_saxon(AtomicValue $left, AtomicValue $right, string $quotient): void
    {
        self::assertSame($quotient, Atomic::arithmetic('div', $left, $right)->lexical());
    }

    public function test_short_integral_text_casts_to_decimal_as_integer(): void
    {
        self::assertSame('xs:integer', Atomic::cast(new UntypedAtomic(' 25 '), 'xs:decimal')->type());
        self::assertSame('xs:integer', Atomic::cast(new StringValue('+777777777777777'), 'xs:decimal')->type());
        self::assertSame('xs:decimal', Atomic::cast(new StringValue('-777777777777777'), 'xs:decimal')->type());
        self::assertSame('xs:decimal', Atomic::cast(new StringValue('25.'), 'xs:decimal')->type());
        self::assertSame('xs:decimal', Atomic::cast(new DecimalValue(BigDecimal::of('25')), 'xs:decimal')->type());
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function doubleLexicals(): iterable
    {
        yield 'large' => [1.0E20, '1.0E20'];
        yield 'one million' => [1.0E6, '1.0E6'];
        yield 'below one million' => [999999.0, '999999'];
        yield 'tenth' => [0.1, '0.1'];
        yield 'small' => [1.0E-6, '0.000001'];
        yield 'smaller' => [1.5E-7, '1.5E-7'];
        yield 'negative zero' => [-0.0, '-0'];
        yield 'zero' => [0.0, '0'];
        yield 'integral' => [42.0, '42'];
        yield 'negative' => [-2.5, '-2.5'];
        yield 'nan' => [NAN, 'NaN'];
        yield 'infinity' => [INF, 'INF'];
        yield 'negative infinity' => [-INF, '-INF'];
        yield 'smallest subnormal has two digits' => [4.9E-324, '4.9E-324'];
        yield 'one digit gets a zero fraction' => [1.0E21, '1.0E21'];
    }

    #[DataProvider('doubleLexicals')]
    public function test_double_lexical_form(float $value, string $lexical): void
    {
        self::assertSame($lexical, (new DoubleValue($value))->lexical());
    }

    public function test_double_cast_from_untyped(): void
    {
        self::assertSame('1000', Atomic::cast(new UntypedAtomic(' 1e3 '), 'xs:double')->lexical());
        self::assertSame('-INF', Atomic::cast(new UntypedAtomic('-INF'), 'xs:double')->lexical());
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('inf'), 'xs:double'));
    }

    public function test_double_to_decimal_is_exact_and_rejects_non_finite(): void
    {
        self::assertSame('0.5', Atomic::cast(new DoubleValue(0.5), 'xs:decimal')->lexical());
        self::assertSame('100', Atomic::cast(new DoubleValue(1.0E2), 'xs:decimal')->lexical());
        self::assertXPathError('FOCA0002', static fn () => Atomic::cast(new DoubleValue(NAN), 'xs:decimal'));
        self::assertXPathError('FOCA0002', static fn () => Atomic::cast(new DoubleValue(INF), 'xs:integer'));
    }

    public function test_integer_cast(): void
    {
        self::assertSame('-3', Atomic::cast(new DecimalValue(BigDecimal::of('-3.9')), 'xs:integer')->lexical());
        self::assertSame('12', Atomic::cast(new UntypedAtomic(' +12 '), 'xs:integer')->lexical());
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('1.0'), 'xs:integer'));
    }

    public function test_string_cast(): void
    {
        $value = Atomic::cast(new DecimalValue(BigDecimal::of('1.50')), 'xs:string');

        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame('1.5', $value->lexical());
        self::assertSame('true', Atomic::cast(new BooleanValue(true), 'xs:string')->lexical());
    }

    /**
     * @return iterable<string, array{AtomicValue, bool}>
     */
    public static function booleanCasts(): iterable
    {
        yield 'true' => [new UntypedAtomic('true'), true];
        yield 'one padded' => [new UntypedAtomic(" 1\n"), true];
        yield 'false' => [new StringValue('false'), false];
        yield 'zero' => [new UntypedAtomic('0'), false];
        yield 'integer zero' => [self::int('0'), false];
        yield 'integer five' => [self::int('5'), true];
        yield 'decimal zero' => [new DecimalValue(BigDecimal::of('0.00')), false];
        yield 'double nan' => [new DoubleValue(NAN), false];
        yield 'double negative zero' => [new DoubleValue(-0.0), false];
        yield 'double' => [new DoubleValue(0.1), true];
        yield 'boolean' => [new BooleanValue(true), true];
    }

    #[DataProvider('booleanCasts')]
    public function test_boolean_cast(AtomicValue $source, bool $expected): void
    {
        $value = Atomic::cast($source, 'xs:boolean');

        self::assertInstanceOf(BooleanValue::class, $value);
        self::assertSame($expected, $value->value);
    }

    public function test_invalid_boolean_lexical_is_forg0001(): void
    {
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('TRUE'), 'xs:boolean'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('yes'), 'xs:boolean'));
    }

    public function test_impossible_cast_is_xpty0004(): void
    {
        self::assertXPathError('XPTY0004', static fn () => Atomic::cast(new BooleanValue(true), 'xs:date'));
        self::assertXPathError('XPTY0004', static fn () => Atomic::cast(new DateValue(2026, 1, 1, null), 'xs:decimal'));
    }

    public function test_date_rejects_impossible_calendar_dates(): void
    {
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('2026-02-30'), 'xs:date'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('2026-13-01'), 'xs:date'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('2026-1-01'), 'xs:date'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('20260101'), 'xs:date'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('2025-02-29'), 'xs:date'));
        self::assertXPathError('FORG0001', static fn () => Atomic::cast(new UntypedAtomic('2026-01-01+15:00'), 'xs:date'));
    }

    public function test_date_lexical_forms(): void
    {
        self::assertSame('2024-02-29', Atomic::cast(new UntypedAtomic(' 2024-02-29 '), 'xs:date')->lexical());
        self::assertSame('2026-01-31Z', Atomic::cast(new UntypedAtomic('2026-01-31+00:00'), 'xs:date')->lexical());
        self::assertSame('2026-01-31-05:30', Atomic::cast(new UntypedAtomic('2026-01-31-05:30'), 'xs:date')->lexical());
    }

    public function test_dates_compare_in_calendar_order(): void
    {
        $later = Atomic::cast(new StringValue('2026-01-31'), 'xs:date');
        $earlier = Atomic::cast(new StringValue('2026-01-30'), 'xs:date');

        self::assertTrue(Atomic::generalCompare('>', [$later], [$earlier]));
        self::assertTrue(Atomic::valueCompare('gt', $later, $earlier));
        self::assertTrue(Atomic::generalCompare('<', [new UntypedAtomic('2025-12-31')], [$earlier]));
        self::assertTrue(Atomic::valueCompare('lt', new DateValue(2026, 1, 1, 60), new DateValue(2026, 1, 1, 0)));
    }

    public function test_booleans_compare_false_before_true(): void
    {
        self::assertTrue(Atomic::valueCompare('lt', new BooleanValue(false), new BooleanValue(true)));
        self::assertTrue(Atomic::valueCompare('eq', new BooleanValue(true), new BooleanValue(true)));
    }

    private static function int(string $lexical): IntegerValue
    {
        return new IntegerValue(BigDecimal::of($lexical));
    }

    /**
     * @param  callable(): mixed  $call
     */
    private static function assertXPathError(string $code, callable $call): void
    {
        try {
            $call();
        } catch (XPathError $error) {
            self::assertSame($code, $error->errorCode, $error->getMessage());

            return;
        }

        self::fail("Expected XPathError {$code}.");
    }
}

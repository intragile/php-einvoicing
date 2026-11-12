<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\XPath\Exception\UnsupportedXPath;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;
use IntrAgile\EInvoicing\XPath\Value\BooleanValue;
use IntrAgile\EInvoicing\XPath\Value\DateValue;
use IntrAgile\EInvoicing\XPath\Value\DecimalValue;
use IntrAgile\EInvoicing\XPath\Value\DoubleValue;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\Value\StringValue;
use IntrAgile\EInvoicing\XPath\Value\UntypedAtomic;
use LogicException;

/**
 * XPath 2.0 casting, comparison and arithmetic over atomic values, without a schema
 * (nodes atomize to xs:untypedAtomic).
 *
 * @internal
 */
final class Atomic
{
    /**
     * Number of fractional digits added by xs:decimal division (Saxon's DIVIDE_PRECISION).
     */
    private const DIVIDE_PRECISION = 18;

    /** Operands of at most this many digits multiply within a 64-bit int (9 + 9 < 19). */
    private const SMALL_INTEGER_DIGITS = 9;

    /** Results below this are shared instances: digits, weights and remainders recur constantly. */
    private const SHARED_INTEGERS = 1024;

    /** @var array<int, IntegerValue> immutable values, see SHARED_INTEGERS */
    private static array $sharedIntegers = [];

    /**
     * Longest integral lexical form (without a leading "+") that Saxon casts to xs:decimal as an xs:integer.
     */
    private const SHORT_INTEGRAL_LENGTH = 15;

    private const INT32_MIN = -2147483648;

    private const INT32_MAX = 2147483647;

    private const XML_WHITESPACE = " \t\n\r";

    private const DECIMAL_PATTERN = '/^(?<sign>[+-])?(?:(?<int>[0-9]+)(?:\.(?<frac>[0-9]*))?|\.(?<only>[0-9]+))$/D';

    private const DOUBLE_PATTERN = '/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D';

    private const DATE_PATTERN = '/^(?<year>-?(?:[1-9][0-9]{4,}|[0-9]{4}))-(?<month>[0-9]{2})-(?<day>[0-9]{2})(?<tz>Z|[+-][0-9]{2}:[0-9]{2})?$/D';

    private const VALUE_OPERATORS = ['=' => 'eq', '!=' => 'ne', '<' => 'lt', '<=' => 'le', '>' => 'gt', '>=' => 'ge'];

    /**
     * Casts a value to one of xs:untypedAtomic, xs:string, xs:boolean, xs:decimal, xs:integer, xs:double or xs:date.
     *
     * @throws XPathError FORG0001 for an invalid lexical form, FOCA0002 for a non-finite double to a decimal
     *                    or integer, XPTY0004 when the cast is not allowed between the two types
     * @throws UnsupportedXPath when the target type is outside the supported subset
     */
    public static function cast(AtomicValue $value, string $type): AtomicValue
    {
        return match ($type) {
            'xs:untypedAtomic' => $value instanceof UntypedAtomic ? $value : new UntypedAtomic($value->lexical()),
            'xs:string' => $value instanceof StringValue ? $value : new StringValue($value->lexical()),
            'xs:boolean' => self::toBoolean($value),
            'xs:decimal' => self::toDecimal($value),
            'xs:integer' => self::toInteger($value),
            'xs:double' => new DoubleValue(self::toDouble($value)),
            'xs:date' => self::toDate($value),
            default => throw new UnsupportedXPath("cast to {$type}", $type, 0),
        };
    }

    /**
     * General comparison (=, !=, <, <=, >, >=): true when any pair of items compares true.
     *
     * @param  list<AtomicValue>  $left
     * @param  list<AtomicValue>  $right
     */
    public static function generalCompare(string $op, array $left, array $right): bool
    {
        $valueOp = self::VALUE_OPERATORS[$op] ?? throw new LogicException("Unknown general comparison operator {$op}.");

        foreach ($left as $l) {
            foreach ($right as $r) {
                [$l2, $r2] = self::generalOperands($l, $r);
                if (self::valueCompare($valueOp, $l2, $r2)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Value comparison (eq, ne, lt, le, gt, ge) of two single atomic values.
     *
     * @throws XPathError XPTY0004 when the types are not comparable
     */
    public static function valueCompare(string $op, AtomicValue $left, AtomicValue $right): bool
    {
        if ($left instanceof UntypedAtomic) {
            $left = new StringValue($left->value);
        }
        if ($right instanceof UntypedAtomic) {
            $right = new StringValue($right->value);
        }

        if (self::isNumeric($left) && self::isNumeric($right)) {
            if ($left instanceof DoubleValue || $right instanceof DoubleValue) {
                $l = self::toDouble($left);
                $r = self::toDouble($right);
                if (is_nan($l) || is_nan($r)) {
                    return $op === 'ne';
                }

                return self::holds($op, $l <=> $r);
            }

            return self::holds($op, self::toBigDecimal($left, 'xs:decimal')->compareTo(self::toBigDecimal($right, 'xs:decimal')));
        }

        if ($left instanceof StringValue && $right instanceof StringValue) {
            // Byte order of UTF-8 equals Unicode code point order.
            return self::holds($op, strcmp($left->value, $right->value) <=> 0);
        }

        if ($left instanceof BooleanValue && $right instanceof BooleanValue) {
            return self::holds($op, $left->value <=> $right->value);
        }

        if ($left instanceof DateValue && $right instanceof DateValue) {
            return self::holds($op, self::dateInstant($left) <=> self::dateInstant($right));
        }

        throw new XPathError('XPTY0004', "Cannot compare {$left->type()} with {$right->type()}.");
    }

    /**
     * Arithmetic (+, -, *, div, idiv, mod) on two single atomic values; untyped operands are cast
     * to xs:double.
     *
     * @throws XPathError XPTY0004 for non-numeric operands, FOAR0001 for an integer or decimal
     *                    division by zero and for idiv by zero, FOAR0002 for idiv of NaN or an
     *                    infinite dividend
     */
    public static function arithmetic(string $op, AtomicValue $left, AtomicValue $right): AtomicValue
    {
        if (! in_array($op, ['+', '-', '*', 'div', 'idiv', 'mod'], true)) {
            throw new LogicException("Unknown arithmetic operator {$op}.");
        }

        $left = $left instanceof UntypedAtomic ? new DoubleValue(self::toDouble($left)) : $left;
        $right = $right instanceof UntypedAtomic ? new DoubleValue(self::toDouble($right)) : $right;

        if (! self::isNumeric($left) || ! self::isNumeric($right)) {
            throw new XPathError('XPTY0004', "Arithmetic operator {$op} is not defined for {$left->type()} and {$right->type()}.");
        }

        if ($left instanceof IntegerValue && $right instanceof IntegerValue && in_array($op, ['+', '-', '*', 'mod'], true)) {
            $small = self::smallIntegerArithmetic($op, $left->value->toString(), $right->value->toString());
            if ($small !== null) {
                return $small;
            }
        }

        if ($left instanceof DoubleValue || $right instanceof DoubleValue) {
            $l = self::toDouble($left);
            $r = self::toDouble($right);
            if ($op === 'idiv') {
                return self::doubleIntegerDivision($l, $r);
            }

            return new DoubleValue(match ($op) {
                '+' => $l + $r,
                '-' => $l - $r,
                '*' => $l * $r,
                'div' => fdiv($l, $r),
                // IEEE remainder of truncating division: the sign of the dividend, NaN for a zero divisor.
                'mod' => fmod($l, $r),
            });
        }

        $l = self::toBigDecimal($left, 'xs:decimal');
        $r = self::toBigDecimal($right, 'xs:decimal');

        if (in_array($op, ['div', 'idiv', 'mod'], true) && $r->isZero()) {
            throw new XPathError('FOAR0001', 'Division by zero.');
        }

        if ($op === 'idiv') {
            return new IntegerValue($l->dividedBy($r, 0, RoundingMode::DOWN));
        }

        if ($op === 'div') {
            $scale = max(self::DIVIDE_PRECISION, self::divisionScale($left) - self::divisionScale($right) + self::DIVIDE_PRECISION);

            return new DecimalValue($l->dividedBy($r, $scale, RoundingMode::HALF_DOWN));
        }

        $result = match ($op) {
            '+' => $l->plus($r),
            '-' => $l->minus($r),
            '*' => $l->multipliedBy($r),
            // BigDecimal::remainder() keeps the sign of the dividend, as op:numeric-mod requires.
            'mod' => $l->remainder($r),
        };

        return $left instanceof IntegerValue && $right instanceof IntegerValue
            ? new IntegerValue($result)
            : new DecimalValue($result);
    }

    /**
     * Integer arithmetic on native ints when both operands have at most SMALL_INTEGER_DIGITS
     * digits, so no result can overflow; null when an operand is larger. The result is the same
     * as with BigDecimal, which is several times slower on the loops of check-digit rules.
     *
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     */
    private static function smallIntegerArithmetic(string $op, string $left, string $right): ?IntegerValue
    {
        if (strlen(ltrim($left, '-')) > self::SMALL_INTEGER_DIGITS || strlen(ltrim($right, '-')) > self::SMALL_INTEGER_DIGITS) {
            return null;
        }
        $l = (int) $left;
        $r = (int) $right;
        if ($op === 'mod' && $r === 0) {
            throw new XPathError('FOAR0001', 'Division by zero.');
        }

        $result = match ($op) {
            '+' => $l + $r,
            '-' => $l - $r,
            '*' => $l * $r,
            // PHP's % keeps the sign of the dividend, as op:numeric-mod requires.
            default => $l % $r,
        };
        if ($result >= 0 && $result < self::SHARED_INTEGERS) {
            return self::$sharedIntegers[$result] ??= new IntegerValue(BigDecimal::of($result));
        }

        return new IntegerValue(BigDecimal::of($result));
    }

    /**
     * op:numeric-integer-divide on doubles: the quotient truncated towards zero.
     *
     * @throws XPathError FOAR0001 for a zero divisor, FOAR0002 for a NaN operand, an infinite
     *                    dividend or a quotient outside the finite range
     */
    private static function doubleIntegerDivision(float $left, float $right): IntegerValue
    {
        if ($right === 0.0) {
            throw new XPathError('FOAR0001', 'Integer division by zero.');
        }
        $quotient = $left / $right;
        if (is_nan($left) || is_nan($right) || is_infinite($left) || ! is_finite($quotient)) {
            throw new XPathError('FOAR0002', 'Integer division of NaN or an infinite value.');
        }

        return new IntegerValue(self::exactDecimal($quotient, 'xs:integer')->toScale(0, RoundingMode::DOWN));
    }

    /**
     * The operand scale Saxon-HE uses in decimal division: decimals and integers outside the 32-bit
     * range count with trailing zeros stripped (1.50 has scale 1, 3000000000 has scale -9); integers
     * inside the 32-bit range count as scale 0.
     */
    private static function divisionScale(AtomicValue $operand): int
    {
        $value = self::toBigDecimal($operand, 'xs:decimal');
        if ($operand instanceof IntegerValue && $value->isGreaterThanOrEqualTo(self::INT32_MIN) && $value->isLessThanOrEqualTo(self::INT32_MAX)) {
            return 0;
        }

        if ($value->isZero()) {
            return 0;
        }
        // BigDecimal never has a negative scale, so count the trailing zeros of the unscaled value.
        $unscaled = (string) $value->getUnscaledValue();

        return $value->getScale() - (strlen($unscaled) - strlen(rtrim($unscaled, '0')));
    }

    /**
     * Casts to xs:decimal. Like Saxon-HE, a textual integral value of at most 15 characters (sign
     * included, a leading "+" excluded) yields an xs:integer, which is a subtype of xs:decimal.
     */
    private static function toDecimal(AtomicValue $value): DecimalValue|IntegerValue
    {
        $decimal = self::toBigDecimal($value, 'xs:decimal');
        if (self::isTextual($value)) {
            $lexical = ltrim(self::collapse($value), '+');
            if (! str_contains($lexical, '.') && strlen($lexical) <= self::SHORT_INTEGRAL_LENGTH) {
                return new IntegerValue($decimal);
            }
        }

        return new DecimalValue($decimal);
    }

    /**
     * Applies the general comparison conversion rules for untyped operands.
     *
     * @return array{AtomicValue, AtomicValue}
     */
    private static function generalOperands(AtomicValue $left, AtomicValue $right): array
    {
        if ($left instanceof UntypedAtomic) {
            $left = self::untypedAgainst($left, $right);
        }
        if ($right instanceof UntypedAtomic) {
            $right = self::untypedAgainst($right, $left);
        }

        return [$left, $right];
    }

    private static function untypedAgainst(UntypedAtomic $untyped, AtomicValue $other): AtomicValue
    {
        return match (true) {
            $other instanceof UntypedAtomic, $other instanceof StringValue => new StringValue($untyped->value),
            self::isNumeric($other) => new DoubleValue(self::toDouble($untyped)),
            default => self::cast($untyped, $other->type()),
        };
    }

    private static function holds(string $op, int $comparison): bool
    {
        return match ($op) {
            'eq' => $comparison === 0,
            'ne' => $comparison !== 0,
            'lt' => $comparison < 0,
            'le' => $comparison <= 0,
            'gt' => $comparison > 0,
            'ge' => $comparison >= 0,
            default => throw new LogicException("Unknown value comparison operator {$op}."),
        };
    }

    private static function isNumeric(AtomicValue $value): bool
    {
        return $value instanceof IntegerValue || $value instanceof DecimalValue || $value instanceof DoubleValue;
    }

    private static function isTextual(AtomicValue $value): bool
    {
        return $value instanceof UntypedAtomic || $value instanceof StringValue;
    }

    private static function collapse(AtomicValue $value): string
    {
        return trim($value->lexical(), self::XML_WHITESPACE);
    }

    private static function toBoolean(AtomicValue $value): BooleanValue
    {
        if ($value instanceof BooleanValue) {
            return $value;
        }
        if ($value instanceof DoubleValue) {
            return new BooleanValue($value->value !== 0.0 && ! is_nan($value->value));
        }
        if ($value instanceof IntegerValue || $value instanceof DecimalValue) {
            return new BooleanValue(! $value->value->isZero());
        }
        if (self::isTextual($value)) {
            return match (self::collapse($value)) {
                'true', '1' => new BooleanValue(true),
                'false', '0' => new BooleanValue(false),
                default => throw self::invalidLexical($value, 'xs:boolean'),
            };
        }

        throw self::notCastable($value, 'xs:boolean');
    }

    /**
     * The exact decimal value of a numeric, boolean or textual value.
     */
    private static function toBigDecimal(AtomicValue $value, string $target): BigDecimal
    {
        if ($value instanceof IntegerValue || $value instanceof DecimalValue) {
            return $value->value;
        }
        if ($value instanceof BooleanValue) {
            return BigDecimal::of($value->value ? 1 : 0);
        }
        if ($value instanceof DoubleValue) {
            return self::exactDecimal($value->value, $target);
        }
        if (self::isTextual($value)) {
            if (preg_match(self::DECIMAL_PATTERN, self::collapse($value), $m) !== 1) {
                throw self::invalidLexical($value, $target);
            }
            $integral = ($m['int'] ?? '') === '' ? '0' : $m['int'];
            $fraction = ($m['frac'] ?? '') !== '' ? $m['frac'] : ($m['only'] ?? '');

            return BigDecimal::of((($m['sign'] ?? '') === '-' ? '-' : '').$integral.($fraction === '' ? '' : '.'.$fraction));
        }

        throw self::notCastable($value, $target);
    }

    /**
     * The decimal that is numerically equal to a finite double (every binary64 value has one).
     */
    private static function exactDecimal(float $double, string $target): BigDecimal
    {
        if (! is_finite($double)) {
            throw new XPathError('FOCA0002', sprintf('Cannot cast the double %s to %s.', (new DoubleValue($double))->lexical(), $target));
        }
        if ($double === 0.0) {
            return BigDecimal::zero();
        }

        $unpacked = unpack('J', pack('E', $double));
        $bits = is_array($unpacked) ? ($unpacked[1] ?? null) : null;
        if (! is_int($bits)) {
            throw new LogicException('Cannot read the binary representation of a double.');
        }
        $exponentBits = ($bits >> 52) & 0x7FF;
        $mantissa = $bits & 0xFFFFFFFFFFFFF;
        if ($exponentBits === 0) {
            $exponent = -1074;
        } else {
            $mantissa |= 1 << 52;
            $exponent = $exponentBits - 1075;
        }

        $unscaled = BigInteger::of($mantissa);
        if ($exponent >= 0) {
            $result = BigDecimal::of($unscaled->multipliedBy(BigInteger::of(2)->power($exponent)));
        } else {
            $result = BigDecimal::ofUnscaledValue($unscaled->multipliedBy(BigInteger::of(5)->power(-$exponent)), -$exponent);
        }

        $result = $result->strippedOfTrailingZeros();

        return $double < 0 ? $result->negated() : $result;
    }

    private static function toInteger(AtomicValue $value): IntegerValue
    {
        if (self::isTextual($value)) {
            $lexical = self::collapse($value);
            if (preg_match('/^[+-]?[0-9]+$/D', $lexical) !== 1) {
                throw self::invalidLexical($value, 'xs:integer');
            }

            return new IntegerValue(BigDecimal::of(ltrim($lexical, '+')));
        }

        return new IntegerValue(self::toBigDecimal($value, 'xs:integer')->toScale(0, RoundingMode::DOWN));
    }

    private static function toDouble(AtomicValue $value): float
    {
        if ($value instanceof DoubleValue) {
            return $value->value;
        }
        if ($value instanceof IntegerValue || $value instanceof DecimalValue) {
            // PHP's string-to-float conversion rounds correctly to the nearest double.
            return (float) (string) $value->value;
        }
        if ($value instanceof BooleanValue) {
            return $value->value ? 1.0 : 0.0;
        }
        if (self::isTextual($value)) {
            $lexical = self::collapse($value);

            return match (true) {
                $lexical === 'INF', $lexical === '+INF' => INF,
                $lexical === '-INF' => -INF,
                $lexical === 'NaN' => NAN,
                preg_match(self::DOUBLE_PATTERN, $lexical) === 1 => (float) $lexical,
                default => throw self::invalidLexical($value, 'xs:double'),
            };
        }

        throw self::notCastable($value, 'xs:double');
    }

    private static function toDate(AtomicValue $value): DateValue
    {
        if ($value instanceof DateValue) {
            return $value;
        }
        if (! self::isTextual($value)) {
            throw self::notCastable($value, 'xs:date');
        }

        if (preg_match(self::DATE_PATTERN, self::collapse($value), $m) !== 1) {
            throw self::invalidLexical($value, 'xs:date');
        }

        $year = (int) $m['year'];
        $month = (int) $m['month'];
        $day = (int) $m['day'];
        if ($month < 1 || $month > 12 || $day < 1 || $day > self::daysInMonth($year, $month)) {
            throw self::invalidLexical($value, 'xs:date');
        }

        $timezone = null;
        $tz = $m['tz'] ?? '';
        if ($tz === 'Z') {
            $timezone = 0;
        } elseif ($tz !== '') {
            $hours = (int) substr($tz, 1, 2);
            $minutes = (int) substr($tz, 4, 2);
            if ($minutes > 59 || $hours > 14 || ($hours === 14 && $minutes > 0)) {
                throw self::invalidLexical($value, 'xs:date');
            }
            $timezone = ($tz[0] === '-' ? -1 : 1) * ($hours * 60 + $minutes);
        }

        return new DateValue($year, $month, $day, $timezone);
    }

    private static function daysInMonth(int $year, int $month): int
    {
        if ($month === 2) {
            $leap = ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;

            return $leap ? 29 : 28;
        }

        return in_array($month, [4, 6, 9, 11], true) ? 30 : 31;
    }

    /**
     * Minutes since the epoch at which the date starts; a date without timezone uses UTC as the implicit timezone.
     */
    private static function dateInstant(DateValue $date): int
    {
        return $date->epochDay() * 1440 - ($date->timezone ?? 0);
    }

    private static function invalidLexical(AtomicValue $value, string $type): XPathError
    {
        return new XPathError('FORG0001', sprintf('Cannot cast "%s" to %s.', $value->lexical(), $type));
    }

    private static function notCastable(AtomicValue $value, string $type): XPathError
    {
        return new XPathError('XPTY0004', "Cannot cast {$value->type()} to {$type}.");
    }
}

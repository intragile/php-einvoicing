<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
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
 */
final class Atomic
{
    /**
     * Minimum number of fractional digits kept by xs:decimal division.
     */
    private const DIVIDE_PRECISION = 18;

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
     */
    public static function cast(AtomicValue $value, string $type): AtomicValue
    {
        return match ($type) {
            'xs:untypedAtomic' => $value instanceof UntypedAtomic ? $value : new UntypedAtomic($value->lexical()),
            'xs:string' => $value instanceof StringValue ? $value : new StringValue($value->lexical()),
            'xs:boolean' => self::toBoolean($value),
            'xs:decimal' => new DecimalValue(self::toBigDecimal($value, 'xs:decimal')),
            'xs:integer' => self::toInteger($value),
            'xs:double' => new DoubleValue(self::toDouble($value)),
            'xs:date' => self::toDate($value),
            default => throw new XPathError('XPTY0004', "Casting to {$type} is not supported."),
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
     * Arithmetic (+, -, *, div) on two single atomic values; untyped operands are cast to xs:double.
     *
     * @throws XPathError XPTY0004 for non-numeric operands, FOAR0001 for decimal division by zero
     */
    public static function arithmetic(string $op, AtomicValue $left, AtomicValue $right): AtomicValue
    {
        if (! in_array($op, ['+', '-', '*', 'div'], true)) {
            throw new LogicException("Unknown arithmetic operator {$op}.");
        }

        $left = $left instanceof UntypedAtomic ? new DoubleValue(self::toDouble($left)) : $left;
        $right = $right instanceof UntypedAtomic ? new DoubleValue(self::toDouble($right)) : $right;

        if (! self::isNumeric($left) || ! self::isNumeric($right)) {
            throw new XPathError('XPTY0004', "Arithmetic operator {$op} is not defined for {$left->type()} and {$right->type()}.");
        }

        if ($left instanceof DoubleValue || $right instanceof DoubleValue) {
            $l = self::toDouble($left);
            $r = self::toDouble($right);

            return new DoubleValue(match ($op) {
                '+' => $l + $r,
                '-' => $l - $r,
                '*' => $l * $r,
                'div' => fdiv($l, $r),
            });
        }

        $l = self::toBigDecimal($left, 'xs:decimal');
        $r = self::toBigDecimal($right, 'xs:decimal');

        if ($op === 'div') {
            if ($r->isZero()) {
                throw new XPathError('FOAR0001', 'Division by zero.');
            }
            $scale = max(self::DIVIDE_PRECISION, $l->getScale(), $r->getScale());

            return new DecimalValue($l->dividedBy($r, $scale, RoundingMode::HALF_DOWN));
        }

        $result = match ($op) {
            '+' => $l->plus($r),
            '-' => $l->minus($r),
            '*' => $l->multipliedBy($r),
        };

        return $left instanceof IntegerValue && $right instanceof IntegerValue
            ? new IntegerValue($result)
            : new DecimalValue($result);
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

        $result = $result->stripTrailingZeros();

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

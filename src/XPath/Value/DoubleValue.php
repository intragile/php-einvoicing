<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

/**
 * An xs:double value; PHP's float is the same IEEE 754 binary64 type.
 */
final readonly class DoubleValue implements AtomicValue
{
    public function __construct(public float $value) {}

    public function type(): string
    {
        return 'xs:double';
    }

    /**
     * XPath 2.0 cast to xs:string: plain decimal notation for magnitudes in [1.0E-6, 1.0E6),
     * otherwise the canonical mantissa/exponent form; both use the shortest round-trip digits.
     */
    public function lexical(): string
    {
        $value = $this->value;
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? 'INF' : '-INF';
        }
        if ($value === 0.0) {
            return fdiv(1.0, $value) < 0 ? '-0' : '0';
        }

        [$digits, $exponent] = self::shortestDigits(abs($value));
        $sign = $value < 0 ? '-' : '';
        $magnitude = abs($value);

        if ($magnitude >= 1.0E-6 && $magnitude < 1.0E6) {
            if ($exponent < 0) {
                return $sign.'0.'.str_repeat('0', -$exponent - 1).$digits;
            }

            $integral = str_pad(substr($digits, 0, $exponent + 1), $exponent + 1, '0');
            $fraction = substr($digits, $exponent + 1);

            return $sign.$integral.($fraction === '' ? '' : '.'.$fraction);
        }

        $fraction = substr($digits, 1);

        return $sign.$digits[0].'.'.($fraction === '' ? '0' : $fraction).'E'.$exponent;
    }

    /**
     * The shortest significant digits that read back as the same double, and the decimal
     * exponent of the first digit.
     *
     * @return array{string, int}
     */
    private static function shortestDigits(float $magnitude): array
    {
        // Seventeen significant digits always round-trip a binary64 value.
        $formatted = sprintf('%.16e', $magnitude);
        for ($precision = 0; $precision < 16; $precision++) {
            $candidate = sprintf('%.'.$precision.'e', $magnitude);
            if ((float) $candidate === $magnitude) {
                $formatted = $candidate;
                break;
            }
        }

        [$mantissa, $exponent] = explode('e', $formatted);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        return [$digits === '' ? '0' : $digits, (int) $exponent];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model\Support;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Converts numeric model input to BigDecimal and refuses floats.
 *
 * Model constructors accept float in their native type on purpose: without it, PHP would coerce
 * a float argument from a caller file without strict_types to int (0.1 becomes 0) before the
 * constructor runs. Accepting it lets this helper throw instead.
 *
 * @internal
 */
final class Decimal
{
    /**
     * @param  string  $term  Business term id named in the error message, e.g. "BT-146".
     */
    public static function of(BigDecimal|string|int|float $value, string $term): BigDecimal
    {
        if (is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                '%s must be given as a string, int or BigDecimal, float given.',
                $term,
            ));
        }

        return BigDecimal::of($value);
    }

    public static function ofNullable(BigDecimal|string|int|float|null $value, string $term): ?BigDecimal
    {
        return $value === null ? null : self::of($value, $term);
    }
}

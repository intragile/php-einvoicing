<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Rounding of EN 16931 amounts: two decimals (BR-DEC rules), half up (away from zero).
 */
final class Rounding
{
    public const int SCALE = 2;

    public const RoundingMode MODE = RoundingMode::HALF_UP;

    public static function amount(BigDecimal $value): BigDecimal
    {
        return $value->toScale(self::SCALE, self::MODE);
    }
}

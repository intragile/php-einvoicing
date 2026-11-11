<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * An xs:integer value, held as a BigDecimal at scale 0.
 */
final readonly class IntegerValue implements AtomicValue
{
    public BigDecimal $value;

    public function __construct(BigDecimal $value)
    {
        if ($value->getScale() !== 0) {
            $integral = $value->stripTrailingZeros();
            if ($integral->getScale() > 0) {
                throw new InvalidArgumentException("An xs:integer cannot hold the fractional value {$value}.");
            }
            $value = $integral->toScale(0);
        }

        $this->value = $value;
    }

    public function type(): string
    {
        return 'xs:integer';
    }

    public function lexical(): string
    {
        return (string) $this->value;
    }
}

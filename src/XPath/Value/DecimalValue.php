<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

use Brick\Math\BigDecimal;

final readonly class DecimalValue implements AtomicValue
{
    public function __construct(public BigDecimal $value) {}

    public function type(): string
    {
        return 'xs:decimal';
    }

    /**
     * Canonical form: no trailing fractional zeros, no decimal point for integral values.
     */
    public function lexical(): string
    {
        return (string) $this->value->stripTrailingZeros();
    }
}

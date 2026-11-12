<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

/**
 * @internal
 */
final readonly class BooleanValue implements AtomicValue
{
    public function __construct(public bool $value) {}

    public function type(): string
    {
        return 'xs:boolean';
    }

    public function lexical(): string
    {
        return $this->value ? 'true' : 'false';
    }
}

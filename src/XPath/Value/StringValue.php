<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

final readonly class StringValue implements AtomicValue
{
    public function __construct(public string $value) {}

    public function type(): string
    {
        return 'xs:string';
    }

    public function lexical(): string
    {
        return $this->value;
    }
}

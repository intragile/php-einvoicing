<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

/**
 * The atomized value of a node that carries no schema type annotation.
 *
 * @internal
 */
final readonly class UntypedAtomic implements AtomicValue
{
    public function __construct(public string $value) {}

    public function type(): string
    {
        return 'xs:untypedAtomic';
    }

    public function lexical(): string
    {
        return $this->value;
    }
}

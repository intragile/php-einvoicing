<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Value;

/**
 * An XPath 2.0 atomic value.
 *
 * @internal
 */
interface AtomicValue
{
    /**
     * The prefixed name of the value's type, for example "xs:decimal".
     */
    public function type(): string;

    /**
     * The value cast to xs:string under XPath 2.0 rules.
     */
    public function lexical(): string;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

/**
 * A lexical token of an XPath expression.
 */
final readonly class Token
{
    /** A QName, an NCName, or a wildcard name test of the form prefix:* or *:local. */
    public const NAME = 'name';

    /** Punctuation or an operator symbol; the value holds the symbol itself. */
    public const SYMBOL = 'symbol';

    /** A string literal; the value holds the unescaped string. */
    public const STRING = 'string';

    public const INTEGER = 'integer';

    public const DECIMAL = 'decimal';

    public const DOUBLE = 'double';

    public const END = 'end';

    /**
     * @param  int  $offset  byte offset of the token in the expression
     */
    public function __construct(public string $type, public string $value, public int $offset) {}

    public function is(string $type, ?string $value = null): bool
    {
        return $this->type === $type && ($value === null || $this->value === $value);
    }
}

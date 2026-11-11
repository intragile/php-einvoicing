<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Exception;

use InvalidArgumentException;

/**
 * Thrown when an XPath expression uses a construct outside the supported XPath 2.0 subset.
 */
final class UnsupportedXPath extends InvalidArgumentException
{
    /**
     * @param  int  $offset  byte offset of the construct in the expression
     */
    public function __construct(public readonly string $construct, public readonly string $expression, public readonly int $offset)
    {
        parent::__construct(sprintf('Unsupported XPath construct "%s" at offset %d in "%s".', $construct, $offset, $expression));
    }
}

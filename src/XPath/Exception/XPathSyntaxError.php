<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath\Exception;

use InvalidArgumentException;

/**
 * Thrown when an XPath expression is not well-formed.
 */
final class XPathSyntaxError extends InvalidArgumentException
{
    /**
     * @param  int  $offset  byte offset of the offending token in the expression
     */
    public function __construct(string $reason, public readonly string $expression, public readonly int $offset)
    {
        parent::__construct(sprintf('%s at offset %d in XPath expression "%s".', $reason, $offset, $expression));
    }
}

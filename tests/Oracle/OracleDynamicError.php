<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use RuntimeException;

/**
 * Saxon stopped the official stylesheet with a dynamic XPath error, such as FORG0001 for a
 * non-numeric amount or XPTY0004 for a repeated singleton.
 */
final class OracleDynamicError extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $description)
    {
        parent::__construct("{$errorCode}: {$description}");
    }
}

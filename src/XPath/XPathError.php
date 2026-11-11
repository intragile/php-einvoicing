<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use RuntimeException;

/**
 * A dynamic XPath error, identified by its W3C error code (for example FORG0001).
 */
final class XPathError extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct("{$errorCode}: {$message}");
    }
}

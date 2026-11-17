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
    /**
     * @param  int|null  $lineNumber  line in $module where Saxon located the error, when it gives one
     * @param  string|null  $module  URI of the stylesheet module where Saxon located the error
     */
    public function __construct(
        public readonly string $errorCode,
        string $description,
        public readonly ?int $lineNumber = null,
        public readonly ?string $module = null,
    ) {
        parent::__construct("{$errorCode}: {$description}");
    }
}

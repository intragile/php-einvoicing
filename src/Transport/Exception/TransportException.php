<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use RuntimeException;

/**
 * A transport operation failed. Messages never carry credentials.
 */
abstract class TransportException extends RuntimeException
{
    /**
     * True when repeating the same operation later may succeed and cannot cause a duplicate.
     */
    abstract public function isRetryable(): bool;
}

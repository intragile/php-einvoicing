<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Throwable;

/**
 * The transport could not be reached or failed temporarily, and the operation had no effect
 * (or is safe to repeat), so it can be retried.
 */
final class TransportUnavailable extends TransportException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}

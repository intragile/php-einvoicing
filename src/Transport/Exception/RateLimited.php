<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Throwable;

/**
 * The transport refused the request because too many were made; retry after the given delay.
 */
final class RateLimited extends TransportException
{
    public function __construct(
        string $message,
        /** Seconds to wait before retrying, when the transport said so. */
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}

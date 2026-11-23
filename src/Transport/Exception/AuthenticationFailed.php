<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Throwable;

/**
 * The transport refused the credentials, or they lack the permission for the operation.
 */
final class AuthenticationFailed extends TransportException
{
    public function __construct(
        string $message,
        ?Throwable $previous = null,
        /** The provider's own error code, when it gave one. */
        public readonly ?int $providerCode = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

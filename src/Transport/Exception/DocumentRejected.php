<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Throwable;

/**
 * The transport refused the document, for example because its own validation failed.
 */
final class DocumentRejected extends TransportException
{
    /**
     * @param  array<string, mixed>  $errors  the provider's error details, as reported
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
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

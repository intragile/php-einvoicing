<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use IntrAgile\EInvoicing\Transport\FailureCategory;
use Throwable;

/**
 * The receiver cannot be reached with this document, for example because it is not registered on
 * the network or does not accept the document type.
 */
final class RecipientUnreachable extends TransportException
{
    public function __construct(
        string $message,
        public readonly FailureCategory $category,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

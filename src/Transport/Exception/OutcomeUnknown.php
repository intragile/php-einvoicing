<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Throwable;

/**
 * A request that changes state failed without an answer, so it may or may not have taken effect;
 * for a send, the access point may have accepted the document. Resending blindly risks a
 * duplicate: check the transport's document list first.
 */
final class OutcomeUnknown extends TransportException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

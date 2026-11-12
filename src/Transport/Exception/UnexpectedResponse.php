<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Exception;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The transport answered with something this client does not understand: an unexpected status, a
 * malformed or oversized body. The status and the start of the body are kept for diagnosis.
 */
final class UnexpectedResponse extends TransportException
{
    /** Bytes of the body kept in the excerpt. */
    public const EXCERPT_BYTES = 500;

    public function __construct(
        string $message,
        /** HTTP status of the response, or 0 when there was none. */
        public readonly int $status = 0,
        /** The first bytes of the response body, at most EXCERPT_BYTES. */
        public readonly string $bodyExcerpt = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * An exception for the response, its message naming the reason and the status; the body
     * excerpt is taken from $body, or read from the response when null.
     */
    public static function forResponse(ResponseInterface $response, string $reason, ?string $body = null, ?Throwable $previous = null): self
    {
        if ($body === null) {
            $stream = $response->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $body = $stream->read(self::EXCERPT_BYTES);
        }

        return new self(
            sprintf('%s (HTTP %d).', rtrim($reason, '.'), $response->getStatusCode()),
            $response->getStatusCode(),
            substr($body, 0, self::EXCERPT_BYTES),
            $previous,
        );
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps an unsuccessful answer to a request that only reads, which is safe to repeat.
 *
 * @internal
 */
final class ReadFailure
{
    /**
     * The exception an unsuccessful read answer stands for: 401 and 403 are AuthenticationFailed,
     * 404 is an UnexpectedResponse saying the provider does not know the resource, 429 is
     * RateLimited, a 5xx status is TransportUnavailable and any other status an UnexpectedResponse.
     *
     * @param  string  $provider  the provider, as named in messages, for example "e-invoice.be"
     * @param  string  $operation  what was attempted, phrased to start a sentence
     */
    public static function of(ResponseInterface $response, string $provider, string $operation, Redactor $redactor): TransportException
    {
        $status = $response->getStatusCode();

        if ($status === 401 || $status === 403) {
            return new AuthenticationFailed(sprintf(
                '%s failed: %s refused the API key or its permission (HTTP %d).',
                $operation,
                $provider,
                $status,
            ));
        }
        if ($status === 404) {
            return $redactor->unexpected($response, sprintf('%s failed: %s does not know it', $operation, $provider));
        }
        if ($status === 429) {
            return new RateLimited(
                sprintf('%s failed: %s rate limit reached (HTTP 429).', $operation, $provider),
                self::retryAfter($response),
            );
        }
        if ($status >= 500 && $status <= 599) {
            return new TransportUnavailable(sprintf('%s failed: %s is unavailable (HTTP %d).', $operation, $provider, $status));
        }

        return $redactor->unexpected($response, sprintf('%s failed with an unexpected status from %s', $operation, $provider));
    }

    /**
     * The Retry-After header in seconds, or null when it is absent or not a number of seconds.
     */
    public static function retryAfter(ResponseInterface $response): ?int
    {
        $retryAfter = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($retryAfter) ? (int) $retryAfter : null;
    }
}

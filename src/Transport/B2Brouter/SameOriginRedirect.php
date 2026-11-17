<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Checks the redirects B2Brouter answers with, so that a request is followed only to the https
 * origin of the configured base URL, where the API key may be sent again.
 *
 * @internal
 */
final class SameOriginRedirect
{
    /**
     * The URL a redirect points to, when it stays on the https origin of the base URL; the API key
     * is sent to it again.
     *
     * @throws UnexpectedResponse when the redirect has no usable Location or leaves that origin;
     *                            neither the Location nor the body is quoted, as they may carry a token
     */
    public static function target(ResponseInterface $response, string $requestUrl, string $baseUrl): string
    {
        $location = trim($response->getHeaderLine('Location'));
        $base = parse_url($baseUrl);
        $origin = sprintf('https://%s%s', $base['host'] ?? '', isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($location, '/') && ! str_starts_with($location, '//')) {
            $location = $origin.$location;
        } elseif ($location !== '' && ! str_contains($location, ':') && ! str_starts_with($location, '//')) {
            // A relative path, resolved against the directory of the request path.
            $path = (string) parse_url($requestUrl, PHP_URL_PATH);
            $location = $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
        }

        $target = $location === '' ? false : parse_url($location);
        if (
            $target === false
            || strtolower($target['scheme'] ?? '') !== 'https'
            || strtolower($target['host'] ?? '') !== strtolower($base['host'] ?? '')
            || ($target['port'] ?? 443) !== ($base['port'] ?? 443)
            || isset($target['user'])
            || isset($target['pass'])
        ) {
            throw self::refused($response, 'B2Brouter redirected the request away from its API host or without a usable Location, so the redirect was not followed');
        }

        return $location;
    }

    /**
     * An UnexpectedResponse for a redirect that is not followed. It keeps no body excerpt, because
     * a redirect body usually repeats the Location, which may carry a token.
     */
    public static function refused(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse($response, $reason, '');
    }

    /**
     * Whether the response is a redirect (any 3xx status).
     */
    public static function isRedirect(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 300 && $response->getStatusCode() <= 399;
    }
}

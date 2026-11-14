<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use InvalidArgumentException;

/**
 * Checks the base URL of a provider API.
 *
 * @internal
 */
final class BaseUrl
{
    /**
     * The URL without trailing slashes.
     *
     * @param  string  $label  what the URL is, for messages, for example "Recommand base URL"
     *
     * @throws InvalidArgumentException when the URL is not an https:// URL with a host, or carries
     *                                  user info, a query or a fragment; the message masks any user info
     */
    public static function normalize(string $url, string $label): string
    {
        $parts = parse_url($url);
        if (
            $parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(sprintf(
                'The %s "%s" must be an https:// URL without user info, query or fragment.',
                $label,
                preg_replace('#//[^/@]*@#', '//'.Redactor::REDACTED.'@', $url),
            ));
        }

        return rtrim($url, '/');
    }
}

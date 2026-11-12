<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * The URL policy for SMP addresses taken from DNS or from an SMP redirect: https only, a host, no
 * user info, no query string, no fragment, no whitespace or control characters. A path is allowed.
 *
 * @internal
 */
final class SmpUrl
{
    /**
     * Why the URL is refused, or null when it is acceptable.
     */
    public static function rejectionReason(string $url): ?string
    {
        $shown = addcslashes($url, "\0..\37\177");
        if (preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return sprintf('The SMP URL "%s" contains whitespace or control characters.', $shown);
        }
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
            return sprintf('The SMP URL "%s" does not use https.', $shown);
        }
        if (($parts['host'] ?? '') === '') {
            return sprintf('The SMP URL "%s" has no host.', $shown);
        }
        if (isset($parts['user']) || isset($parts['pass']) || str_contains($url, '@')) {
            return sprintf('The SMP URL "%s" carries user info.', $shown);
        }
        if (str_contains($url, '?')) {
            return sprintf('The SMP URL "%s" carries a query string.', $shown);
        }
        if (str_contains($url, '#')) {
            return sprintf('The SMP URL "%s" carries a fragment.', $shown);
        }

        return null;
    }
}

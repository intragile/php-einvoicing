<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * Runs libxml work with a temporary external entity loader that resolves only local file paths
 * and file:// URIs without a host or with the host "localhost".
 *
 * LIBXML_NONET does not apply to schema compilation or to every document load, so this loader is
 * what keeps schema handling off the network. A file URI with another host (a remote share) and
 * every other scheme are refused: libxml receives no resource, and the refused URI is reported to
 * the caller, which decides how to fail. The caller's loader is restored afterwards, also when the
 * work throws.
 *
 * A caller may map absolute URI prefixes onto local folders, for schemas that import each other
 * by an absolute http location: a system id that starts with a mapped prefix resolves to the
 * remainder below that folder. A remainder that could leave the folder (a ".." segment or a
 * leading slash) is refused like a remote URI.
 *
 * @internal
 */
final class FileOnlyEntityLoader
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @param  list<string>|null  $refused  receives the refused URIs in the order libxml asked for them
     * @param  array<string, string>  $localPrefixes  absolute URI prefix => local folder; a system id
     *                                                starting with a prefix resolves below the folder
     *
     * @param-out list<string> $refused
     *
     * @return T
     */
    public static function run(callable $operation, ?array &$refused, array $localPrefixes = []): mixed
    {
        $refused = [];
        $previousLoader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static function (?string $public, string $system) use (&$refused, $localPrefixes): ?string {
            foreach ($localPrefixes as $prefix => $dir) {
                if ($prefix === '' || ! str_starts_with($system, $prefix)) {
                    continue;
                }
                $remainder = substr($system, strlen($prefix));
                if ($remainder === '' || str_starts_with($remainder, '/') || in_array('..', preg_split('~[/\\\\]~', $remainder) ?: [], true)) {
                    $refused[] = $system;

                    return null;
                }

                return rtrim($dir, '/').'/'.$remainder;
            }

            if (preg_match('~^([a-z][a-z0-9+.-]+):~i', $system, $match) === 1) {
                // A file URI with a host other than "localhost" names a remote share (a UNC path on
                // Windows), which is network access as well.
                $host = preg_match('~^file://([^/]*)~i', $system, $authority) === 1 ? $authority[1] : '';
                if (strtolower($match[1]) !== 'file' || ($host !== '' && strtolower($host) !== 'localhost')) {
                    $refused[] = $system;

                    return null;
                }
                $system = rawurldecode((string) preg_replace('~^file://(localhost)?~i', '', $system));
            }

            return $system;
        });

        try {
            return $operation();
        } finally {
            libxml_set_external_entity_loader($previousLoader);
        }
    }
}

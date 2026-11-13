<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * Runs libxml work with a temporary external entity loader that resolves only local file paths
 * and file:// URIs.
 *
 * LIBXML_NONET does not apply to schema compilation or to every document load, so this loader is
 * what keeps schema handling off the network. Every other scheme is refused: libxml receives no
 * resource, and the refused URI is reported to the caller, which decides how to fail. The
 * caller's loader is restored afterwards, also when the work throws.
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
     *
     * @param-out list<string> $refused
     *
     * @return T
     */
    public static function run(callable $operation, ?array &$refused): mixed
    {
        $refused = [];
        $previousLoader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(static function (?string $public, string $system) use (&$refused): ?string {
            if (preg_match('~^([a-z][a-z0-9+.-]+):~i', $system, $match) === 1) {
                if (strtolower($match[1]) !== 'file') {
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

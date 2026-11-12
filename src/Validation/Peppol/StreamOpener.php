<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use RuntimeException;

/**
 * Opens a single HTTP GET request as a readable stream, without following redirects.
 */
interface StreamOpener
{
    /**
     * @return array{list<string>, resource}|false the response headers (status line(s) first) and
     *                                             the body stream, or false when no connection
     *                                             could be opened
     *
     * @throws RuntimeException when the connection fails with a known reason
     */
    public function open(string $url, int $timeoutSeconds): array|false;
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

/**
 * Keeps the current incremental reading window per key, for example per context and subject
 * type. Without a persistent implementation, a restarted process reads its inbox from the start
 * point again and offers invoices it already returned.
 */
interface KsefCursorStore
{
    /**
     * The stored window, or null when nothing was saved under the key.
     */
    public function window(string $key): ?CursorWindow;

    public function save(string $key, CursorWindow $window): void;
}

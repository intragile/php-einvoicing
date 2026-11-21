<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

/**
 * A cursor store that lives as long as the object: for tests and short-lived processes.
 */
final class InMemoryCursorStore implements KsefCursorStore
{
    /** @var array<string, CursorWindow> */
    private array $windows = [];

    public function window(string $key): ?CursorWindow
    {
        return $this->windows[$key] ?? null;
    }

    public function save(string $key, CursorWindow $window): void
    {
        $this->windows[$key] = $window;
    }
}

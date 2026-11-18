<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * The invoice footer (Stopka).
 */
final readonly class Footer
{
    public const MAX_NOTES = 3;

    public const MAX_REGISTRIES = 100;

    /**
     * @param  list<string>  $notes  Footer texts (Informacje/StopkaFaktury), at most 3.
     * @param  list<Registry>  $registries  Registry entries (Rejestry), at most 100.
     *
     * @throws InvalidArgumentException when either list is longer than FA(3) allows
     */
    public function __construct(
        public array $notes = [],
        public array $registries = [],
    ) {
        Limit::count($notes, 'Informacje', self::MAX_NOTES);
        Limit::count($registries, 'Rejestry', self::MAX_REGISTRIES);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Item classification identifier (BT-158).
 */
final readonly class ItemClassification
{
    public function __construct(
        /** Item classification identifier (BT-158). */
        public string $identifier,
        /** Scheme identifier (BT-158-1), a UNTDID 7143 code such as "STI" or "CV". */
        public string $listIdentifier,
        /** Scheme version identifier (BT-158-2). */
        public ?string $listVersion = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Item attribute (BG-32).
 */
final readonly class ItemAttribute
{
    public function __construct(
        /** Item attribute name (BT-160). */
        public string $name,
        /** Item attribute value (BT-161). */
        public string $value,
    ) {}
}

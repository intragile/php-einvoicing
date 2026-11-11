<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Party contact (BG-6 seller contact, BG-9 buyer contact).
 */
final readonly class Contact
{
    public function __construct(
        /** Contact point (BT-41 / BT-56). */
        public ?string $name = null,
        /** Contact telephone number (BT-42 / BT-57). */
        public ?string $phone = null,
        /** Contact email address (BT-43 / BT-58). */
        public ?string $email = null,
    ) {}
}

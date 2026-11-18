<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use DateTimeImmutable;

/**
 * A contract (Umowy) or order (Zamowienia) reference in the transaction terms.
 */
final readonly class DocumentReference
{
    public function __construct(
        /** Date (DataUmowy / DataZamowienia). */
        public ?DateTimeImmutable $date = null,
        /** Number (NrUmowy / NrZamowienia). */
        public ?string $number = null,
    ) {}
}

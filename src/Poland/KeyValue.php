<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * A key and value pair of additional description (DodatkowyOpis, XSD type TKluczWartosc).
 */
final readonly class KeyValue
{
    public function __construct(
        /** Invoice line number the pair belongs to (NrWiersza); null for the whole invoice. */
        public ?int $lineNumber,
        /** Key (Klucz). */
        public string $key,
        /** Value (Wartosc). */
        public string $value,
    ) {}
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * A key and value pair of additional description (DodatkowyOpis, XSD type TKluczWartosc).
 */
final readonly class KeyValue
{
    /**
     * @throws InvalidArgumentException when the line number is not positive
     */
    public function __construct(
        /** Invoice line number the pair belongs to (NrWiersza), from 1; null for the whole invoice. */
        public ?int $lineNumber,
        /** Key (Klucz). */
        public string $key,
        /** Value (Wartosc). */
        public string $value,
    ) {
        if ($lineNumber !== null && $lineNumber < 1) {
            throw new InvalidArgumentException(sprintf('The line number (NrWiersza) of a description must be 1 or more, got %d.', $lineNumber));
        }
    }
}

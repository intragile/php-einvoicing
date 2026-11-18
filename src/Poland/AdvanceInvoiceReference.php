<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * An advance invoice a settlement invoice settles (FakturaZaliczkowa): its KSeF number, or its
 * number when it was issued outside KSeF (NrKSeFZN = 1).
 */
final readonly class AdvanceInvoiceReference
{
    /**
     * @throws InvalidArgumentException when neither or both identifiers are given
     */
    public function __construct(
        /** KSeF number of the advance invoice (NrKSeFFaZaliczkowej). */
        public ?KsefNumber $ksefNumber = null,
        /** Number of an advance invoice issued outside KSeF (NrFaZaliczkowej). */
        public ?string $number = null,
    ) {
        if (($ksefNumber === null) === ($number === null)) {
            throw new InvalidArgumentException('An advance invoice reference (FakturaZaliczkowa) needs exactly one of a KSeF number and an invoice number.');
        }
    }
}

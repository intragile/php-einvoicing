<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * A natural or legal person on a record, identified by exactly one of a NIF or an IDOtro.
 */
final readonly class TaxParty
{
    public string $name;

    public function __construct(
        string $name,
        public ?Nif $nif,
        public ?ForeignId $foreignId,
    ) {
        $name = trim($name);

        if (mb_strlen($name) > 120) {
            throw new InvalidRecord('NombreRazon must be at most 120 characters.');
        }

        if (($nif === null) === ($foreignId === null)) {
            throw new InvalidRecord('A party needs exactly one of NIF or IDOtro.');
        }

        $this->name = $name;
    }
}

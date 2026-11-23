<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The representative of the issuer in a submission header (Cabecera/Representante).
 */
final readonly class Representative
{
    public string $name;

    public function __construct(string $name, public Nif $nif)
    {
        $name = trim($name);
        if (mb_strlen($name) > 120) {
            throw new InvalidRecord('Representante/NombreRazon must be at most 120 characters.');
        }
        $this->name = $name;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;

/**
 * Identifies one chain of billing records: an issuer, an installation of the billing software
 * (NumeroInstalacion) and an AEAT environment.
 */
final readonly class ChainKey
{
    public function __construct(
        public Nif $issuer,
        public string $installationNumber,
        public VerifactuEnvironment $environment,
    ) {}

    public static function for(Nif $issuer, ComputerSystem $system, VerifactuEnvironment $environment): self
    {
        return new self($issuer, $system->installationNumber, $environment);
    }

    /** A stable storage key: "<nif>|<url-encoded installation number>|<environment>". */
    public function toString(): string
    {
        return $this->issuer->value.'|'.rawurlencode($this->installationNumber).'|'.$this->environment->value;
    }
}

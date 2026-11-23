<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The header (Cabecera) of a VERI*FACTU submission: the obligated issuer, an optional
 * representative and, for voluntary submissions, the VERI*FACTU end date and the incident flag
 * (RemisionVoluntaria).
 */
final readonly class SubmissionHeader
{
    public string $issuerName;

    /**
     * @param  DateTimeImmutable|null  $verifactuEndDate  RemisionVoluntaria/FechaFinVeriFactu
     * @param  bool|null  $incident  RemisionVoluntaria/Incidencia; null leaves it out
     */
    public function __construct(
        public Nif $issuer,
        string $issuerName,
        public ?Representative $representative = null,
        public ?DateTimeImmutable $verifactuEndDate = null,
        public ?bool $incident = null,
    ) {
        $issuerName = trim($issuerName);
        if (mb_strlen($issuerName) > 120) {
            throw new InvalidRecord('ObligadoEmision/NombreRazon must be at most 120 characters.');
        }
        $this->issuerName = $issuerName;
    }
}

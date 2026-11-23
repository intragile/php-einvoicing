<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The billing software (SistemaInformatico) that produced a record.
 *
 * Every field is mandatory and has no default: the producer declares itself.
 */
final readonly class ComputerSystem
{
    public string $name;

    public string $systemName;

    public string $systemId;

    public string $version;

    public string $installationNumber;

    public function __construct(
        string $name,
        public ?Nif $nif,
        public ?ForeignId $foreignId,
        string $systemName,
        string $systemId,
        string $version,
        string $installationNumber,
        public bool $verifactuOnly,
        public bool $multipleTaxpayersPossible,
        public bool $multipleTaxpayersIndicator,
    ) {
        if (($nif === null) === ($foreignId === null)) {
            throw new InvalidRecord('A computer system needs exactly one of NIF or IDOtro.');
        }

        $this->name = self::bounded('NombreRazon', $name, 1, 120);
        $this->systemName = self::bounded('NombreSistemaInformatico', $systemName, 0, 30);
        $this->systemId = self::bounded('IdSistemaInformatico', $systemId, 1, 2);
        $this->version = self::bounded('Version', $version, 0, 50);
        $this->installationNumber = self::bounded('NumeroInstalacion', $installationNumber, 1, 100);
    }

    private static function bounded(string $field, string $value, int $min, int $max): string
    {
        $value = trim($value);
        $length = mb_strlen($value);

        if ($length < $min || $length > $max) {
            throw new InvalidRecord(sprintf('%s must be %d to %d characters.', $field, $min, $max));
        }

        return $value;
    }
}

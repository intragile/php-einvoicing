<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The content of a RegistroAlta apart from its chain link, computer system, generation timestamp
 * and fingerprint.
 *
 * The constructor enforces the cardinalities and lengths of SuministroInformacion.xsd only;
 * the AEAT business rules are checked by the validator.
 */
final readonly class RegistrationDraft
{
    public string $issuerName;

    public string $description;

    public ?string $externalReference;

    public ?string $agreementNumber;

    public ?string $systemAgreementId;

    /**
     * @param  list<InvoiceId>  $corrected  FacturasRectificadas, at most 1000
     * @param  list<InvoiceId>  $substituted  FacturasSustituidas, at most 1000
     * @param  list<TaxParty>  $recipients  Destinatarios, at most 1000; empty when absent
     * @param  list<TaxBreakdownLine>  $breakdown  Desglose, 1 to 12 lines
     */
    public function __construct(
        public InvoiceId $id,
        string $issuerName,
        public InvoiceType $type,
        public ?CorrectionType $correctionType,
        public array $corrected,
        public array $substituted,
        public ?CorrectionAmounts $correctionAmounts,
        public ?DateTimeImmutable $operationDate,
        string $description,
        public bool $simplifiedArt7273,
        public bool $withoutRecipientArt61d,
        public bool $macrodato,
        public ?IssuedBy $issuedBy,
        public ?TaxParty $thirdParty,
        public array $recipients,
        public bool $coupon,
        public array $breakdown,
        public Amount $totalTax,
        public Amount $total,
        ?string $externalReference,
        ?string $agreementNumber,
        ?string $systemAgreementId,
        public AltaOperation $operation = AltaOperation::Initial,
    ) {
        self::countBetween('FacturasRectificadas', $corrected, 0, 1000);
        self::countBetween('FacturasSustituidas', $substituted, 0, 1000);
        self::countBetween('Destinatarios', $recipients, 0, 1000);
        self::countBetween('Desglose', $breakdown, 1, 12);

        $this->issuerName = self::text('NombreRazonEmisor', $issuerName, 120);
        $this->description = self::text('DescripcionOperacion', $description, 500);
        $this->externalReference = self::optionalText('RefExterna', $externalReference, 60);
        $this->agreementNumber = self::optionalText('NumRegistroAcuerdoFacturacion', $agreementNumber, 15);
        $this->systemAgreementId = self::optionalText('IdAcuerdoSistemaInformatico', $systemAgreementId, 16);
    }

    /**
     * @param  list<mixed>  $items
     */
    private static function countBetween(string $element, array $items, int $min, int $max): void
    {
        $count = count($items);

        if ($count < $min || $count > $max) {
            throw new InvalidRecord(sprintf('%s must have %d to %d entries, %d given.', $element, $min, $max, $count));
        }
    }

    private static function text(string $element, string $value, int $max): string
    {
        $value = trim($value);

        if (mb_strlen($value) > $max) {
            throw new InvalidRecord(sprintf('%s must be at most %d characters.', $element, $max));
        }

        return $value;
    }

    private static function optionalText(string $element, ?string $value, int $max): ?string
    {
        return $value === null ? null : self::text($element, $value, $max);
    }
}

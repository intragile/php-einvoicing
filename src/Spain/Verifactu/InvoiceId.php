<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * Identifies an issued invoice: issuer NIF, series and number, and issue date (IDFactura).
 */
final readonly class InvoiceId
{
    public string $number;

    public function __construct(
        public Nif $issuer,
        string $number,
        public DateTimeImmutable $issueDate,
    ) {
        $number = trim($number);
        $length = strlen($number);

        // NumSerieFactura: 1-60 printable ASCII characters, none of " ' < > = (Validaciones v1.2.2 §3.1.3.1, error 1130).
        if ($length < 1 || $length > 60 || preg_match('/\A[ -~]+\z/', $number) !== 1 || strpbrk($number, '"\'<>=') !== false) {
            throw new InvalidRecord(
                'NumSerieFactura must be 1 to 60 printable ASCII characters without " \' < > = '
                .'(Validaciones v1.2.2 §3.1.3.1, error 1130).',
            );
        }

        $this->number = $number;
    }

    /** The issue date as AEAT writes it (dd-mm-yyyy). */
    public function dateText(): string
    {
        return $this->issueDate->format('d-m-Y');
    }

    public function equals(self $other): bool
    {
        return $this->issuer->value === $other->issuer->value
            && $this->number === $other->number
            && $this->dateText() === $other->dateText();
    }
}

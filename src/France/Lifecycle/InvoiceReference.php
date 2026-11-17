<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Siret;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use InvalidArgumentException;

/**
 * The invoice a lifecycle message reports on (the referenced document).
 */
final readonly class InvoiceReference
{
    public function __construct(
        /** Invoice number, BT-1 (MDT-87). */
        public string $number,
        /** Invoice type code, BT-3 (MDT-91). */
        public InvoiceTypeCode $type,
        /** Time the invoice was deposited or received by the platform (MDT-95). */
        public DateTimeImmutable $receivedAt,
        /** Invoice issue date, BT-2 (MDT-100); date-only, absent only for status 501. */
        public ?DateTimeImmutable $issueDate,
        /** Seller's legal identifier, the SIREN of BT-30 (MDT-129). */
        public ?Identifier $issuer,
        /** Reference type code (MDT-97). */
        public ?string $referenceTypeCode = null,
    ) {
        if (trim($number) === '') {
            throw new InvalidArgumentException('Invoice number (MDT-87) must not be empty.');
        }
    }

    /**
     * References an invoice: number, type and issue date from BT-1, BT-3 and BT-2, and the seller's
     * SIREN from BT-30 (scheme 0002) or else from the first SIRET of BT-29 (scheme 0009).
     */
    public static function of(Invoice $invoice, DateTimeImmutable $receivedAt): self
    {
        return new self($invoice->number, $invoice->type, $receivedAt, $invoice->issueDate, self::sellerSiren($invoice));
    }

    private static function sellerSiren(Invoice $invoice): ?Identifier
    {
        $legal = $invoice->seller->legalRegistration;
        if ($legal !== null && $legal->scheme === Siren::SCHEME && Siren::isValid($legal->value)) {
            return Siren::identifier($legal->value);
        }

        foreach ($invoice->seller->identifiers as $identifier) {
            if ($identifier->scheme === Siret::SCHEME && Siret::isValid($identifier->value)) {
                return Siren::identifier(Siret::siren($identifier->value));
            }
        }

        return null;
    }
}

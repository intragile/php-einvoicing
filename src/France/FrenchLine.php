<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * EXTENDED-CTC-FR data of an invoice line, attached to an InvoiceLine through its extension bag
 * or held by an InformationalLine. Written only for an invoice with the EXTENDED-CTC-FR profile.
 */
final readonly class FrenchLine
{
    /** Line VAT amount in the invoice currency (EXT-FR-FE-181). */
    public ?BigDecimal $vatAmount;

    /** Line amount with VAT (EXT-FR-FE-184). */
    public ?BigDecimal $amountWithVat;

    /**
     * @param  BigDecimal|numeric-string|int|null  $vatAmount
     * @param  BigDecimal|numeric-string|int|null  $amountWithVat
     * @param  list<Identifier>  $objectIdentifiers  Further invoiced object identifiers (BT-128)
     *                                               with their schemes, such as AVV or AFL: those
     *                                               after the first on an InvoiceLine, all of them
     *                                               on an InformationalLine.
     *
     * @throws InvalidArgumentException when the sub-type is GROUP or INFORMATION
     */
    public function __construct(
        /** Parent line identifier (EXT-FR-FE-162), the GROUP line this line belongs to. */
        public ?string $parentLineId = null,
        /** Line sub-type (EXT-FR-FE-163): DETAIL or none; GROUP and INFORMATION lines are InformationalLine. */
        public ?LineSubType $subType = null,
        /** Line seller (EXT-FR-FE-164 to EXT-FR-FE-177). */
        public ?LineSeller $seller = null,
        /** Preceding invoice reference of the line (EXT-FR-FE-BG-06: EXT-FR-FE-136, EXT-FR-FE-138). */
        public ?PrecedingInvoiceReference $precedingInvoice = null,
        /** Line delivery (EXT-FR-FE-BG-10, EXT-FR-FE-BG-11). */
        public ?LineDelivery $delivery = null,
        /** Line VAT exemption reason text (EXT-FR-FE-178). */
        public ?string $vatExemptionReason = null,
        /** Line VAT point date code (EXT-FR-FE-180), UNTDID 2005 as the VAT point date code (BT-8). */
        public ?string $vatDueDateCode = null,
        BigDecimal|string|int|float|null $vatAmount = null,
        BigDecimal|string|int|float|null $amountWithVat = null,
        /** Line note subject code (EXT-FR-FE-183). */
        public ?TextSubjectCode $noteSubjectCode = null,
        public array $objectIdentifiers = [],
    ) {
        if ($subType !== null && $subType !== LineSubType::Detail) {
            throw new InvalidArgumentException(sprintf(
                'A %s line (EXT-FR-FE-163) is an InformationalLine, not an invoice line with French data.',
                $subType->value,
            ));
        }
        $this->vatAmount = Decimal::ofNullable($vatAmount, 'EXT-FR-FE-181');
        $this->amountWithVat = Decimal::ofNullable($amountWithVat, 'EXT-FR-FE-184');
    }
}

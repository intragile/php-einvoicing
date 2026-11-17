<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineDelivery;
use IntrAgile\EInvoicing\France\LineSeller;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use InvalidArgumentException;

/**
 * Writes an invoice line (BG-25) as cac:InvoiceLine or cac:CreditNoteLine; the inverse of
 * LineMapper.
 *
 * For an EXTENDED-CTC-FR invoice it also writes the French line data (FrenchLine) of each line and
 * the GROUP and INFORMATION lines (InformationalLine), at the UBL elements of the EXTENDED-CTC-FR
 * specification:
 * - The sub-type (EXT-FR-FE-163) is cac:BillingReference/cac:InvoiceDocumentReference/
 *   cbc:DocumentStatusCode and the parent line (EXT-FR-FE-162) cac:BillingReferenceLine/cbc:ID,
 *   in the billing reference whose document identifier is the invoice number (BT-1), as the
 *   EXTENDED-CTC-FR rules expect. The preceding invoice of the line (EXT-FR-FE-BG-06) is a
 *   further cac:BillingReference, so it may not carry the invoice number.
 * - The line seller (EXT-FR-FE-164 to EXT-FR-FE-177) is cac:Item/cac:ManufacturerParty.
 * - The VAT point date code (EXT-FR-FE-180) is cac:InvoicePeriod/cbc:DescriptionCode in UNTDID
 *   2005 as held by the model; EN 16931 (BR-CO-20) then requires a line period start or end
 *   date, so a code without one is refused. The line VAT amount (EXT-FR-FE-181) is
 *   cac:TaxTotal/cbc:TaxAmount.
 * - The note subject code (EXT-FR-FE-183) leads the note text as "#CODE#", like the document
 *   notes (BT-21).
 * UBL has no element for the line amount with VAT (EXT-FR-FE-184), so a line that has one is
 * refused. Only an EXTENDED-CTC-FR invoice has its French line data written.
 *
 * @internal
 */
final class LineWriter
{
    /**
     * @param  ?string  $extendedInvoiceNumber  invoice number (BT-1) of an EXTENDED-CTC-FR invoice,
     *                                          whose French line data is then written; null for
     *                                          any other invoice
     */
    public function __construct(
        private readonly UblOutput $out,
        private readonly CurrencyCode $currency,
        private readonly bool $creditNote,
        private readonly ?string $extendedInvoiceNumber = null,
    ) {}

    public function line(DOMElement $root, InvoiceLine $line): void
    {
        $out = $this->out;
        if ($line->netAmount === null && ! $this->creditNote) {
            throw new InvalidArgumentException(sprintf(
                'Invoice line "%s" has no net amount (BT-131); complete the invoice with TotalsCalculator::complete() first.',
                $line->id,
            ));
        }
        $french = $this->extendedInvoiceNumber === null ? null : $line->extensions()->get(FrenchLine::class);
        $this->checkFrench($line->id, $french, $line->period);

        $element = $out->add($root, $this->creditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        $out->text($element, 'cbc:ID', $line->id);
        $this->note($element, $line->id, $line->note, $french);
        $out->decimal($element, $this->quantityName(), $line->quantity, ['unitCode' => $line->unit->value]);
        $out->amount($element, 'cbc:LineExtensionAmount', $line->netAmount, $this->currency);
        $out->text($element, 'cbc:AccountingCost', $line->buyerAccountingReference);
        $this->period($element, $line->period, $french?->vatDueDateCode);
        if ($line->orderLineReference !== null) {
            $out->text($out->add($element, 'cac:OrderLineReference'), 'cbc:LineID', $line->orderLineReference);
        }
        $this->billingReference($element, $french?->subType?->value, $french);
        $this->objectIdentifiers($element, [
            ...($line->objectIdentifier === null ? [] : [$line->objectIdentifier]),
            ...($french === null ? [] : $french->objectIdentifiers),
        ]);
        $this->delivery($element, $french?->delivery);
        if ($this->creditNote) {
            $this->taxTotal($element, $french);
        }
        foreach ($line->allowances as $allowance) {
            $this->allowanceCharge($element, false, $allowance->amount, $allowance->baseAmount, $allowance->percentage, $allowance->reason, $allowance->reasonCode);
        }
        foreach ($line->charges as $charge) {
            $this->allowanceCharge($element, true, $charge->amount, $charge->baseAmount, $charge->percentage, $charge->reason, $charge->reasonCode);
        }
        if (! $this->creditNote) {
            $this->taxTotal($element, $french);
        }
        $this->item($element, $line->id, $line->item, $line->vat, $french);
        $this->price($element, $line->price);
    }

    /**
     * Writes a GROUP or INFORMATION line of an EXTENDED-CTC-FR invoice. The line net amount (BT-131)
     * is required in an invoice, because UBL requires cbc:LineExtensionAmount in cac:InvoiceLine.
     */
    public function informational(DOMElement $root, InformationalLine $line): void
    {
        $out = $this->out;
        if ($line->netAmount === null && ! $this->creditNote) {
            throw new InvalidArgumentException(sprintf(
                'Informational line "%s" has no net amount (BT-131), which UBL requires in cac:InvoiceLine.',
                $line->id,
            ));
        }
        $french = $line->french;
        $this->checkFrench($line->id, $french, $line->period);

        $element = $out->add($root, $this->creditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        $out->text($element, 'cbc:ID', $line->id);
        $this->note($element, $line->id, $line->note, $french);
        $out->decimal($element, $this->quantityName(), $line->quantity, ['unitCode' => $line->unit?->value]);
        $out->amount($element, 'cbc:LineExtensionAmount', $line->netAmount, $this->currency);
        $this->period($element, $line->period, $french->vatDueDateCode);
        $this->billingReference($element, $line->subType->value, $french);
        $this->objectIdentifiers($element, $french->objectIdentifiers);
        $this->delivery($element, $french->delivery);
        $this->taxTotal($element, $french);
        $this->item($element, $line->id, $line->item, $line->vat, $french);
        if ($line->price !== null) {
            $this->price($element, $line->price);
        }
    }

    /**
     * Writes cac:InvoicePeriod with the start and end date and the VAT point date code (BT-8 at
     * document level, EXT-FR-FE-180 on a line). Writes nothing when all three are absent.
     */
    public function period(DOMElement $parent, ?Period $period, ?string $descriptionCode = null): void
    {
        if ($period?->start === null && $period?->end === null && $descriptionCode === null) {
            return;
        }
        $element = $this->out->add($parent, 'cac:InvoicePeriod');
        $this->out->date($element, 'cbc:StartDate', $period?->start);
        $this->out->date($element, 'cbc:EndDate', $period?->end);
        $this->out->text($element, 'cbc:DescriptionCode', $descriptionCode);
    }

    /**
     * Writes cac:AllowanceCharge up to and including cbc:BaseAmount; the caller appends the tax
     * category of a document level allowance or charge to the returned element.
     */
    public function allowanceCharge(
        DOMElement $parent,
        bool $isCharge,
        BigDecimal $amount,
        ?BigDecimal $baseAmount,
        ?BigDecimal $percentage,
        ?string $reason,
        AllowanceReasonCode|ChargeReasonCode|null $reasonCode,
    ): DOMElement {
        $out = $this->out;
        $element = $out->add($parent, 'cac:AllowanceCharge');
        $out->text($element, 'cbc:ChargeIndicator', $isCharge ? 'true' : 'false');
        $out->text($element, 'cbc:AllowanceChargeReasonCode', $reasonCode?->value);
        $out->text($element, 'cbc:AllowanceChargeReason', $reason);
        $out->decimal($element, 'cbc:MultiplierFactorNumeric', $percentage);
        $out->amount($element, 'cbc:Amount', $amount, $this->currency);
        $out->amount($element, 'cbc:BaseAmount', $baseAmount, $this->currency);

        return $element;
    }

    private function quantityName(): string
    {
        return $this->creditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity';
    }

    /**
     * Refuses French line data that UBL cannot carry or would read back differently.
     *
     * @throws InvalidArgumentException
     */
    private function checkFrench(string $id, ?FrenchLine $french, ?Period $period): void
    {
        if ($french === null) {
            return;
        }
        if ($french->vatDueDateCode !== null && $period?->start === null && $period?->end === null) {
            throw new InvalidArgumentException(sprintf(
                'Line "%s" has a VAT point date code (EXT-FR-FE-180) but no line period start or end date (BT-134, BT-135); UBL carries the code in the line period, which BR-CO-20 then requires to have a date.',
                $id,
            ));
        }
        if ($french->amountWithVat !== null) {
            throw new InvalidArgumentException(sprintf(
                'Line "%s" has an amount with VAT (EXT-FR-FE-184), which UBL has no element for; write the invoice as CII or remove the amount.',
                $id,
            ));
        }
        if ($french->precedingInvoice?->number === $this->extendedInvoiceNumber) {
            throw new InvalidArgumentException(sprintf(
                'Line "%s" refers to a preceding invoice (EXT-FR-FE-136) with the invoice number (BT-1) "%s"; in UBL a line billing reference to the invoice itself holds the line structure.',
                $id,
                $this->extendedInvoiceNumber,
            ));
        }
    }

    /**
     * Line note (BT-127), led by its subject code (EXT-FR-FE-183) as "#CODE#".
     */
    private function note(DOMElement $element, string $id, ?string $note, ?FrenchLine $french): void
    {
        $code = $french?->noteSubjectCode;
        if ($code !== null && $note === null) {
            throw new InvalidArgumentException(sprintf('Line "%s" has a note subject code (EXT-FR-FE-183) but no note (BT-127).', $id));
        }
        $this->out->text($element, 'cbc:Note', $code === null ? $note : '#'.$code->value.'#'.$note);
    }

    /**
     * The line structure as a cac:BillingReference whose document reference is the invoice itself
     * (BT-1), holding the sub-type (EXT-FR-FE-163) and the parent line (EXT-FR-FE-162), then the
     * preceding invoice of the line (EXT-FR-FE-BG-06) in a cac:BillingReference of its own.
     */
    private function billingReference(DOMElement $element, ?string $subType, ?FrenchLine $french): void
    {
        $out = $this->out;
        $parent = $french?->parentLineId;
        if ($subType !== null || $parent !== null) {
            $billing = $out->add($element, 'cac:BillingReference');
            $reference = $out->add($billing, 'cac:InvoiceDocumentReference');
            $out->text($reference, 'cbc:ID', $this->extendedInvoiceNumber);
            $out->text($reference, 'cbc:DocumentStatusCode', $subType);
            if ($parent !== null) {
                $out->text($out->add($billing, 'cac:BillingReferenceLine'), 'cbc:ID', $parent);
            }
        }

        $preceding = $french?->precedingInvoice;
        if ($preceding !== null) {
            $reference = $out->add($out->add($element, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
            $out->text($reference, 'cbc:ID', $preceding->number);
            $out->date($reference, 'cbc:IssueDate', $preceding->issueDate);
        }
    }

    /**
     * Invoiced object identifiers (BT-128), each as cac:DocumentReference with type code 130.
     *
     * @param  list<Identifier>  $identifiers
     */
    private function objectIdentifiers(DOMElement $element, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $reference = $this->out->add($element, 'cac:DocumentReference');
            $this->out->identifier($reference, 'cbc:ID', $identifier);
            $this->out->text($reference, 'cbc:DocumentTypeCode', '130');
        }
    }

    /**
     * Line deliver-to location (EXT-FR-FE-BG-10) and actual delivery date (EXT-FR-FE-BG-11).
     */
    private function delivery(DOMElement $element, ?LineDelivery $model): void
    {
        if ($model === null) {
            return;
        }
        $out = $this->out;
        $delivery = $out->add($element, 'cac:Delivery');
        $out->date($delivery, 'cbc:ActualDeliveryDate', $model->actualDeliveryDate);
        if ($model->name !== null || $model->address !== null) {
            $location = $out->add($delivery, 'cac:DeliveryLocation');
            $out->text($location, 'cbc:Name', $model->name);
            (new PartyWriter($out))->address($location, 'cac:Address', $model->address);
        }
    }

    /**
     * Line VAT amount (EXT-FR-FE-181) in the invoice currency.
     */
    private function taxTotal(DOMElement $element, ?FrenchLine $french): void
    {
        if ($french?->vatAmount !== null) {
            $this->out->amount($this->out->add($element, 'cac:TaxTotal'), 'cbc:TaxAmount', $french->vatAmount, $this->currency);
        }
    }

    private function item(DOMElement $line, string $id, Item $item, ?LineVat $vat, ?FrenchLine $french): void
    {
        $out = $this->out;
        $element = $out->add($line, 'cac:Item');
        $out->text($element, 'cbc:Description', $item->description);
        $out->text($element, 'cbc:Name', $item->name);
        $this->itemIdentification($element, 'cac:BuyersItemIdentification', $item->buyersIdentifier);
        $this->itemIdentification($element, 'cac:SellersItemIdentification', $item->sellersIdentifier);
        if ($item->standardIdentifier !== null) {
            $out->identifier($out->add($element, 'cac:StandardItemIdentification'), 'cbc:ID', $item->standardIdentifier);
        }
        if ($item->originCountry !== null) {
            $out->text($out->add($element, 'cac:OriginCountry'), 'cbc:IdentificationCode', $item->originCountry->value);
        }
        $this->classifications($element, $item);

        if ($vat !== null) {
            $category = $out->add($element, 'cac:ClassifiedTaxCategory');
            $out->text($category, 'cbc:ID', $vat->category->value);
            $out->decimal($category, 'cbc:Percent', $vat->rate);
            $out->text($category, 'cbc:TaxExemptionReason', $french?->vatExemptionReason);
            $out->taxScheme($category, 'VAT');
        } elseif ($french?->vatExemptionReason !== null) {
            throw new InvalidArgumentException(sprintf('Line "%s" has a VAT exemption reason (EXT-FR-FE-178) but no VAT category (BT-151).', $id));
        }

        foreach ($item->attributes as $attribute) {
            $property = $out->add($element, 'cac:AdditionalItemProperty');
            $out->text($property, 'cbc:Name', $attribute->name);
            $out->text($property, 'cbc:Value', $attribute->value);
        }
        $this->seller($element, $french?->seller);
    }

    /**
     * Line seller (EXT-FR-FE-164 to EXT-FR-FE-177) as cac:ManufacturerParty in PartyType order.
     */
    private function seller(DOMElement $item, ?LineSeller $seller): void
    {
        if ($seller === null || $seller == new LineSeller) {
            return;
        }
        $out = $this->out;
        $party = $out->add($item, 'cac:ManufacturerParty');
        if ($seller->globalId !== null) {
            $out->identifier($out->add($party, 'cac:PartyIdentification'), 'cbc:ID', $seller->globalId);
        }
        if ($seller->country !== null) {
            $out->text($out->add($out->add($party, 'cac:PostalAddress'), 'cac:Country'), 'cbc:IdentificationCode', $seller->country->value);
        }
        if ($seller->vatIdentifier !== null) {
            $scheme = $out->add($party, 'cac:PartyTaxScheme');
            $out->text($scheme, 'cbc:CompanyID', $seller->vatIdentifier);
            $out->taxScheme($scheme, 'VAT');
        }
        if ($seller->name !== null || $seller->legalRegistration !== null) {
            $entity = $out->add($party, 'cac:PartyLegalEntity');
            $out->text($entity, 'cbc:RegistrationName', $seller->name);
            $out->identifier($entity, 'cbc:CompanyID', $seller->legalRegistration);
        }
    }

    private function itemIdentification(DOMElement $item, string $name, ?string $identifier): void
    {
        if ($identifier !== null) {
            $this->out->text($this->out->add($item, $name), 'cbc:ID', $identifier);
        }
    }

    private function classifications(DOMElement $item, Item $model): void
    {
        foreach ($model->classifications as $classification) {
            $this->out->text(
                $this->out->add($item, 'cac:CommodityClassification'),
                'cbc:ItemClassificationCode',
                $classification->identifier,
                ['listID' => $classification->listIdentifier, 'listVersionID' => $classification->listVersion],
            );
        }
    }

    /**
     * BT-146 to BT-150. The price discount (BT-147) and gross price (BT-148) share one
     * cac:AllowanceCharge whose cbc:Amount UBL requires; a gross price without a discount writes
     * the difference to the net price, which is what BT-147 is.
     */
    private function price(DOMElement $line, Price $price): void
    {
        $out = $this->out;
        $element = $out->add($line, 'cac:Price');
        $out->amount($element, 'cbc:PriceAmount', $price->netPrice, $this->currency);
        $out->decimal($element, 'cbc:BaseQuantity', $price->baseQuantity, ['unitCode' => $price->baseQuantityUnit?->value]);

        $discount = $price->discount ?? $price->grossPrice?->minus($price->netPrice);
        if ($discount !== null) {
            $allowance = $out->add($element, 'cac:AllowanceCharge');
            $out->text($allowance, 'cbc:ChargeIndicator', 'false');
            $out->amount($allowance, 'cbc:Amount', $discount, $this->currency);
            $out->amount($allowance, 'cbc:BaseAmount', $price->grossPrice, $this->currency);
        }
    }
}

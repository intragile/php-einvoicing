<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use InvalidArgumentException;

/**
 * Writes an invoice line (BG-25) as cac:InvoiceLine or cac:CreditNoteLine; the inverse of
 * LineMapper.
 *
 * @internal
 */
final class LineWriter
{
    public function __construct(
        private readonly UblOutput $out,
        private readonly CurrencyCode $currency,
        private readonly bool $creditNote,
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

        $element = $out->add($root, $this->creditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
        $out->text($element, 'cbc:ID', $line->id);
        $out->text($element, 'cbc:Note', $line->note);
        $out->decimal($element, $this->creditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity', $line->quantity, ['unitCode' => $line->unit->value]);
        $out->amount($element, 'cbc:LineExtensionAmount', $line->netAmount, $this->currency);
        $out->text($element, 'cbc:AccountingCost', $line->buyerAccountingReference);
        $this->period($element, $line->period);
        if ($line->orderLineReference !== null) {
            $out->text($out->add($element, 'cac:OrderLineReference'), 'cbc:LineID', $line->orderLineReference);
        }
        if ($line->objectIdentifier !== null) {
            $reference = $out->add($element, 'cac:DocumentReference');
            $out->identifier($reference, 'cbc:ID', $line->objectIdentifier);
            $out->text($reference, 'cbc:DocumentTypeCode', '130');
        }
        foreach ($line->allowances as $allowance) {
            $this->allowanceCharge($element, false, $allowance->amount, $allowance->baseAmount, $allowance->percentage, $allowance->reason, $allowance->reasonCode);
        }
        foreach ($line->charges as $charge) {
            $this->allowanceCharge($element, true, $charge->amount, $charge->baseAmount, $charge->percentage, $charge->reason, $charge->reasonCode);
        }
        $this->item($element, $line);
        $this->price($element, $line->price);
    }

    /**
     * Writes cac:InvoicePeriod with the start and end date and, at document level, the VAT point
     * date code (BT-8). Writes nothing when all three are absent.
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

    private function item(DOMElement $line, InvoiceLine $model): void
    {
        $out = $this->out;
        $item = $model->item;
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

        $category = $out->add($element, 'cac:ClassifiedTaxCategory');
        $out->text($category, 'cbc:ID', $model->vat->category->value);
        $out->decimal($category, 'cbc:Percent', $model->vat->rate);
        $out->taxScheme($category, 'VAT');

        foreach ($item->attributes as $attribute) {
            $property = $out->add($element, 'cac:AdditionalItemProperty');
            $out->text($property, 'cbc:Name', $attribute->name);
            $out->text($property, 'cbc:Value', $attribute->value);
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

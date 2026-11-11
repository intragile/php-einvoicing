<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use InvalidArgumentException;

/**
 * Writes an invoice line (BG-25) as ram:IncludedSupplyChainTradeLineItem; the inverse of
 * LineMapper.
 *
 * @internal
 */
final class LineWriter
{
    /** Tax type code of every VAT category element (UNTDID 5153). */
    public const VAT = 'VAT';

    /** Document type code of an invoiced object identifier (BT-18, BT-128). */
    public const OBJECT_IDENTIFIER_TYPE = '130';

    public function __construct(private readonly CiiOutput $out) {}

    public function line(DOMElement $transaction, InvoiceLine $line): void
    {
        $out = $this->out;
        if ($line->netAmount === null) {
            throw new InvalidArgumentException(sprintf(
                'Invoice line "%s" has no net amount (BT-131); complete the invoice with TotalsCalculator::complete() first.',
                $line->id,
            ));
        }

        $element = $out->add($transaction, 'ram:IncludedSupplyChainTradeLineItem');
        $document = $out->add($element, 'ram:AssociatedDocumentLineDocument');
        $out->text($document, 'ram:LineID', $line->id);
        $out->wrapped($document, 'ram:IncludedNote', 'ram:Content', $line->note);

        $this->product($element, $line->item);

        $agreement = $out->add($element, 'ram:SpecifiedLineTradeAgreement');
        $out->wrapped($agreement, 'ram:BuyerOrderReferencedDocument', 'ram:LineID', $line->orderLineReference);
        $this->price($agreement, $line->price);

        $out->decimal($out->add($element, 'ram:SpecifiedLineTradeDelivery'), 'ram:BilledQuantity', $line->quantity, ['unitCode' => $line->unit->value]);

        $settlement = $out->add($element, 'ram:SpecifiedLineTradeSettlement');
        $this->tradeTax($settlement, 'ram:ApplicableTradeTax', $line->vat->category, $line->vat->rate);
        $this->period($settlement, $line->period);
        foreach ($line->allowances as $allowance) {
            $this->allowanceCharge($settlement, false, $allowance->amount, $allowance->baseAmount, $allowance->percentage, $allowance->reason, $allowance->reasonCode);
        }
        foreach ($line->charges as $charge) {
            $this->allowanceCharge($settlement, true, $charge->amount, $charge->baseAmount, $charge->percentage, $charge->reason, $charge->reasonCode);
        }
        $out->decimal($out->add($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation'), 'ram:LineTotalAmount', $line->netAmount);
        if ($line->objectIdentifier !== null) {
            $reference = $out->add($settlement, 'ram:AdditionalReferencedDocument');
            $out->text($reference, 'ram:IssuerAssignedID', $line->objectIdentifier->value);
            $out->text($reference, 'ram:TypeCode', self::OBJECT_IDENTIFIER_TYPE);
            $out->text($reference, 'ram:ReferenceTypeCode', $line->objectIdentifier->scheme);
        }
        $out->wrapped($settlement, 'ram:ReceivableSpecifiedTradeAccountingAccount', 'ram:ID', $line->buyerAccountingReference);
    }

    /**
     * Writes ram:BillingSpecifiedPeriod; nothing when both dates are absent.
     */
    public function period(DOMElement $parent, ?Period $period): void
    {
        if ($period?->start === null && $period?->end === null) {
            return;
        }
        $element = $this->out->add($parent, 'ram:BillingSpecifiedPeriod');
        $this->out->date($element, 'ram:StartDateTime', $period->start);
        $this->out->date($element, 'ram:EndDateTime', $period->end);
    }

    /**
     * Writes ram:SpecifiedTradeAllowanceCharge up to and including ram:Reason; the caller appends
     * the VAT category of a document level allowance or charge to the returned element.
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
        $element = $out->add($parent, 'ram:SpecifiedTradeAllowanceCharge');
        $out->indicator($element, 'ram:ChargeIndicator', $isCharge);
        $out->decimal($element, 'ram:CalculationPercent', $percentage);
        $out->decimal($element, 'ram:BasisAmount', $baseAmount);
        $out->decimal($element, 'ram:ActualAmount', $amount);
        $out->text($element, 'ram:ReasonCode', $reasonCode?->value);
        $out->text($element, 'ram:Reason', $reason);

        return $element;
    }

    /**
     * Writes a VAT category element (line ram:ApplicableTradeTax, allowance or charge
     * ram:CategoryTradeTax) with type code, category code and rate.
     */
    public function tradeTax(DOMElement $parent, string $name, VatCategory $category, ?BigDecimal $rate): void
    {
        $tax = $this->out->add($parent, $name);
        $this->out->text($tax, 'ram:TypeCode', self::VAT);
        $this->out->text($tax, 'ram:CategoryCode', $category->value);
        $this->out->decimal($tax, 'ram:RateApplicablePercent', $rate);
    }

    private function product(DOMElement $line, Item $item): void
    {
        $out = $this->out;
        $product = $out->add($line, 'ram:SpecifiedTradeProduct');
        $out->identifier($product, 'ram:GlobalID', $item->standardIdentifier);
        $out->text($product, 'ram:SellerAssignedID', $item->sellersIdentifier);
        $out->text($product, 'ram:BuyerAssignedID', $item->buyersIdentifier);
        $out->text($product, 'ram:Name', $item->name);
        $out->text($product, 'ram:Description', $item->description);
        foreach ($item->attributes as $attribute) {
            $characteristic = $out->add($product, 'ram:ApplicableProductCharacteristic');
            $out->text($characteristic, 'ram:Description', $attribute->name);
            $out->text($characteristic, 'ram:Value', $attribute->value);
        }
        foreach ($item->classifications as $classification) {
            $out->text($out->add($product, 'ram:DesignatedProductClassification'), 'ram:ClassCode', $classification->identifier, [
                'listID' => $classification->listIdentifier,
                'listVersionID' => $classification->listVersion,
            ]);
        }
        $out->wrapped($product, 'ram:OriginTradeCountry', 'ram:ID', $item->originCountry?->value);
    }

    /**
     * BT-146 to BT-150. CII holds the price discount (BT-147) as an allowance on the gross price,
     * whose amount the schema requires; a discount without a gross price (BT-148) is written with
     * the gross price it implies, net price plus discount. EN 16931 has one base quantity for both
     * prices, so the gross price repeats the net price base quantity.
     */
    private function price(DOMElement $agreement, Price $price): void
    {
        $out = $this->out;
        $baseQuantityUnit = ['unitCode' => $price->baseQuantityUnit?->value];

        $grossPrice = $price->grossPrice ?? $price->discount?->plus($price->netPrice);
        if ($grossPrice !== null) {
            $gross = $out->add($agreement, 'ram:GrossPriceProductTradePrice');
            $out->decimal($gross, 'ram:ChargeAmount', $grossPrice);
            $out->decimal($gross, 'ram:BasisQuantity', $price->baseQuantity, $baseQuantityUnit);
            if ($price->discount !== null) {
                $allowance = $out->add($gross, 'ram:AppliedTradeAllowanceCharge');
                $out->indicator($allowance, 'ram:ChargeIndicator', false);
                $out->decimal($allowance, 'ram:ActualAmount', $price->discount);
            }
        }

        $net = $out->add($agreement, 'ram:NetPriceProductTradePrice');
        $out->decimal($net, 'ram:ChargeAmount', $price->netPrice);
        $out->decimal($net, 'ram:BasisQuantity', $price->baseQuantity, $baseQuantityUnit);
    }
}

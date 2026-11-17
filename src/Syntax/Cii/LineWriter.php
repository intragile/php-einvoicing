<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineDelivery;
use IntrAgile\EInvoicing\France\LineSeller;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use InvalidArgumentException;

/**
 * Writes an invoice line (BG-25) as ram:IncludedSupplyChainTradeLineItem; the inverse of
 * LineMapper.
 *
 * For an EXTENDED-CTC-FR invoice it also writes the French line data (FrenchLine) of each line and
 * the GROUP and INFORMATION lines (InformationalLine), in the element order of the D22B schema.
 * Otherwise French line data is not written.
 *
 * @internal
 */
final class LineWriter
{
    /** Tax type code of every VAT category element (UNTDID 5153). */
    public const VAT = 'VAT';

    /** Document type code of an invoiced object identifier (BT-18, BT-128). */
    public const OBJECT_IDENTIFIER_TYPE = '130';

    /** VAT point date codes (BT-8, EXT-FR-FE-180): UNTDID 2005 in the model, UNTDID 2475 in CII. */
    public const DUE_DATE_TYPE_CODES = ['3' => '5', '35' => '29', '432' => '72'];

    /**
     * @param  ?CurrencyCode  $extendedCurrency  invoice currency (BT-5) of an EXTENDED-CTC-FR
     *                                           invoice, whose French line data is then written;
     *                                           null for any other invoice
     */
    public function __construct(
        private readonly CiiOutput $out,
        private readonly ?CurrencyCode $extendedCurrency = null,
    ) {}

    public function line(DOMElement $transaction, InvoiceLine $line): void
    {
        $out = $this->out;
        if ($line->netAmount === null) {
            throw new InvalidArgumentException(sprintf(
                'Invoice line "%s" has no net amount (BT-131); complete the invoice with TotalsCalculator::complete() first.',
                $line->id,
            ));
        }

        $french = $this->extendedCurrency === null ? null : $line->extensions()->get(FrenchLine::class);

        $element = $out->add($transaction, 'ram:IncludedSupplyChainTradeLineItem');
        $this->document($element, $line->id, $french?->subType?->value, $line->note, $french);

        $this->product($element, $line->item);

        $agreement = $out->add($element, 'ram:SpecifiedLineTradeAgreement');
        $out->wrapped($agreement, 'ram:BuyerOrderReferencedDocument', 'ram:LineID', $line->orderLineReference);
        $this->price($agreement, $line->price);
        $this->seller($agreement, $french?->seller);

        $delivery = $out->add($element, 'ram:SpecifiedLineTradeDelivery');
        $out->decimal($delivery, 'ram:BilledQuantity', $line->quantity, ['unitCode' => $line->unit->value]);
        $this->delivery($delivery, $french?->delivery);

        $settlement = $out->add($element, 'ram:SpecifiedLineTradeSettlement');
        $this->tradeTax($settlement, 'ram:ApplicableTradeTax', $line->vat->category, $line->vat->rate, $french?->vatExemptionReason, $this->dueDateTypeCode($french));
        $this->period($settlement, $line->period);
        foreach ($line->allowances as $allowance) {
            $this->allowanceCharge($settlement, false, $allowance->amount, $allowance->baseAmount, $allowance->percentage, $allowance->reason, $allowance->reasonCode);
        }
        foreach ($line->charges as $charge) {
            $this->allowanceCharge($settlement, true, $charge->amount, $charge->baseAmount, $charge->percentage, $charge->reason, $charge->reasonCode);
        }
        $this->summation($settlement, $line->netAmount, $french);
        $this->precedingInvoice($settlement, $french?->precedingInvoice);
        $this->objectIdentifiers($settlement, [
            ...($line->objectIdentifier === null ? [] : [$line->objectIdentifier]),
            ...($french === null ? [] : $french->objectIdentifiers),
        ]);
        $out->wrapped($settlement, 'ram:ReceivableSpecifiedTradeAccountingAccount', 'ram:ID', $line->buyerAccountingReference);
    }

    /**
     * Writes a GROUP or INFORMATION line of an EXTENDED-CTC-FR invoice; every group but the
     * settlement, which the schema requires, is left out when it holds nothing.
     *
     * @throws InvalidArgumentException when the line has a VAT exemption reason or a VAT point date
     *                                  code but no VAT, since CII carries both in the line VAT
     */
    public function informational(DOMElement $transaction, InformationalLine $line): void
    {
        $out = $this->out;
        $french = $line->french;
        if ($line->vat === null && $french->vatExemptionReason !== null) {
            throw new InvalidArgumentException(sprintf('Line "%s" has a VAT exemption reason (EXT-FR-FE-178) but no VAT category (BT-151).', $line->id));
        }
        if ($line->vat === null && $french->vatDueDateCode !== null) {
            throw new InvalidArgumentException(sprintf(
                'Line "%s" has a VAT point date code (EXT-FR-FE-180) but no VAT category (BT-151); CII carries the code in the line VAT.',
                $line->id,
            ));
        }
        $seller = self::sellerOrNull($french->seller);

        $element = $out->add($transaction, 'ram:IncludedSupplyChainTradeLineItem');
        $this->document($element, $line->id, $line->subType->value, $line->note, $french);

        $this->product($element, $line->item);

        if ($line->price !== null || $seller !== null) {
            $agreement = $out->add($element, 'ram:SpecifiedLineTradeAgreement');
            if ($line->price !== null) {
                $this->price($agreement, $line->price);
            }
            $this->seller($agreement, $seller);
        }

        if ($line->quantity !== null || $french->delivery !== null) {
            $delivery = $out->add($element, 'ram:SpecifiedLineTradeDelivery');
            $out->decimal($delivery, 'ram:BilledQuantity', $line->quantity, ['unitCode' => $line->unit?->value]);
            $this->delivery($delivery, $french->delivery);
        }

        $settlement = $out->add($element, 'ram:SpecifiedLineTradeSettlement');
        if ($line->vat !== null) {
            $this->tradeTax($settlement, 'ram:ApplicableTradeTax', $line->vat->category, $line->vat->rate, $french->vatExemptionReason, $this->dueDateTypeCode($french));
        }
        $this->period($settlement, $line->period);
        if ($line->netAmount !== null || $french->vatAmount !== null || $french->amountWithVat !== null) {
            $this->summation($settlement, $line->netAmount, $french);
        }
        $this->precedingInvoice($settlement, $french->precedingInvoice);
        $this->objectIdentifiers($settlement, $french->objectIdentifiers);
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
    public function tradeTax(
        DOMElement $parent,
        string $name,
        VatCategory $category,
        ?BigDecimal $rate,
        ?string $exemptionReason = null,
        ?string $dueDateTypeCode = null,
    ): void {
        $tax = $this->out->add($parent, $name);
        $this->out->text($tax, 'ram:TypeCode', self::VAT);
        $this->out->text($tax, 'ram:ExemptionReason', $exemptionReason);
        $this->out->text($tax, 'ram:CategoryCode', $category->value);
        $this->out->text($tax, 'ram:DueDateTypeCode', $dueDateTypeCode);
        $this->out->decimal($tax, 'ram:RateApplicablePercent', $rate);
    }

    /**
     * ram:AssociatedDocumentLineDocument: line identifier (BT-126), parent line (EXT-FR-FE-162),
     * sub-type (EXT-FR-FE-163) and the note (BT-127) with its subject code (EXT-FR-FE-183).
     */
    private function document(DOMElement $element, string $id, ?string $subType, ?string $note, ?FrenchLine $french): void
    {
        $out = $this->out;
        $document = $out->add($element, 'ram:AssociatedDocumentLineDocument');
        $out->text($document, 'ram:LineID', $id);
        $out->text($document, 'ram:ParentLineID', $french?->parentLineId);
        $out->text($document, 'ram:LineStatusReasonCode', $subType);
        if ($note !== null) {
            $included = $out->add($document, 'ram:IncludedNote');
            $out->text($included, 'ram:Content', $note);
            $out->text($included, 'ram:SubjectCode', $french?->noteSubjectCode?->value);
        } elseif ($french?->noteSubjectCode instanceof TextSubjectCode) {
            throw new InvalidArgumentException(sprintf('Line "%s" has a note subject code (EXT-FR-FE-183) but no note (BT-127).', $id));
        }
    }

    /**
     * ram:ItemSellerTradeParty (EXT-FR-FE-164 to EXT-FR-FE-177) in TradePartyType order; nothing
     * for a seller without any data.
     */
    private function seller(DOMElement $agreement, ?LineSeller $seller): void
    {
        $seller = self::sellerOrNull($seller);
        if ($seller === null) {
            return;
        }
        $out = $this->out;
        $party = $out->add($agreement, 'ram:ItemSellerTradeParty');
        $out->identifier($party, 'ram:GlobalID', $seller->globalId);
        $out->text($party, 'ram:Name', $seller->name);
        if ($seller->legalRegistration !== null) {
            $out->identifier($out->add($party, 'ram:SpecifiedLegalOrganization'), 'ram:ID', $seller->legalRegistration);
        }
        $out->wrapped($party, 'ram:PostalTradeAddress', 'ram:CountryID', $seller->country?->value);
        if ($seller->vatIdentifier !== null) {
            $out->text($out->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $seller->vatIdentifier, ['schemeID' => 'VA']);
        }
    }

    /**
     * The line seller, or null when it is absent or holds no data.
     */
    private static function sellerOrNull(?LineSeller $seller): ?LineSeller
    {
        return $seller === null || $seller == new LineSeller ? null : $seller;
    }

    /**
     * Line deliver-to location (EXT-FR-FE-BG-10) and actual delivery date (EXT-FR-FE-BG-11).
     */
    private function delivery(DOMElement $delivery, ?LineDelivery $model): void
    {
        if ($model === null) {
            return;
        }
        $out = $this->out;
        if ($model->name !== null || $model->address !== null) {
            $party = $out->add($delivery, 'ram:ShipToTradeParty');
            $out->text($party, 'ram:Name', $model->name);
            (new PartyWriter($out))->address($party, $model->address);
        }
        if ($model->actualDeliveryDate !== null) {
            $out->date($out->add($delivery, 'ram:ActualDeliverySupplyChainEvent'), 'ram:OccurrenceDateTime', $model->actualDeliveryDate);
        }
    }

    /**
     * Line net amount (BT-131), VAT amount (EXT-FR-FE-181) in the invoice currency and amount with
     * VAT (EXT-FR-FE-184).
     */
    private function summation(DOMElement $settlement, ?BigDecimal $netAmount, ?FrenchLine $french): void
    {
        $out = $this->out;
        $summation = $out->add($settlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
        $out->decimal($summation, 'ram:LineTotalAmount', $netAmount);
        $out->decimal($summation, 'ram:TaxTotalAmount', $french?->vatAmount, ['currencyID' => $this->extendedCurrency?->value]);
        $out->decimal($summation, 'ram:GrandTotalAmount', $french?->amountWithVat);
    }

    /**
     * Preceding invoice reference of the line (EXT-FR-FE-BG-06).
     */
    private function precedingInvoice(DOMElement $settlement, ?PrecedingInvoiceReference $reference): void
    {
        if ($reference === null) {
            return;
        }
        $element = $this->out->add($settlement, 'ram:InvoiceReferencedDocument');
        $this->out->text($element, 'ram:IssuerAssignedID', $reference->number);
        $this->out->date($element, 'ram:FormattedIssueDateTime', $reference->issueDate, 'qdt:DateTimeString');
    }

    /**
     * Invoiced object identifiers (BT-128), each as ram:AdditionalReferencedDocument with type
     * code 130 and its scheme in ram:ReferenceTypeCode.
     *
     * @param  list<Identifier>  $identifiers
     */
    private function objectIdentifiers(DOMElement $settlement, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $reference = $this->out->add($settlement, 'ram:AdditionalReferencedDocument');
            $this->out->text($reference, 'ram:IssuerAssignedID', $identifier->value);
            $this->out->text($reference, 'ram:TypeCode', self::OBJECT_IDENTIFIER_TYPE);
            $this->out->text($reference, 'ram:ReferenceTypeCode', $identifier->scheme);
        }
    }

    /**
     * EXT-FR-FE-180 as UNTDID 2475.
     *
     * @throws InvalidArgumentException when the code has no CII equivalent
     */
    private function dueDateTypeCode(?FrenchLine $french): ?string
    {
        $code = $french?->vatDueDateCode;
        if ($code === null) {
            return null;
        }

        return self::DUE_DATE_TYPE_CODES[$code] ?? throw new InvalidArgumentException(sprintf(
            "The line VAT point date code (EXT-FR-FE-180) '%s' has no CII equivalent; expected one of %s.",
            $code,
            implode(', ', array_keys(self::DUE_DATE_TYPE_CODES)),
        ));
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

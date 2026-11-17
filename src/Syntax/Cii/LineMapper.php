<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineDelivery;
use IntrAgile\EInvoicing\France\LineSeller;
use IntrAgile\EInvoicing\France\LineSubType;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\ItemAttribute;
use IntrAgile\EInvoicing\Model\ItemClassification;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;

/**
 * Maps a ram:IncludedSupplyChainTradeLineItem to an invoice line (BG-25).
 *
 * In an EXTENDED-CTC-FR invoice (extendedLine) a GROUP or INFORMATION line becomes an
 * InformationalLine; any other line becomes an invoice line, with a FrenchLine extension when it
 * carries French line data.
 *
 * @internal
 */
final class LineMapper
{
    private const AGREEMENT = 'ram:SpecifiedLineTradeAgreement/';

    private const SETTLEMENT = 'ram:SpecifiedLineTradeSettlement/';

    public function __construct(private readonly CiiDocument $document) {}

    public function line(DOMElement $line): InvoiceLine
    {
        $d = $this->document;
        $quantityExpression = 'ram:SpecifiedLineTradeDelivery/ram:BilledQuantity';

        [$allowances, $charges] = $this->allowancesAndCharges($line);

        return new InvoiceLine(
            id: $d->requiredText('ram:AssociatedDocumentLineDocument/ram:LineID', $line, 'BT-126'),
            quantity: $d->requiredDecimal($quantityExpression, $line, 'BT-129'),
            unit: $d->requiredCode(UnitCode::class, $d->one($quantityExpression, $line), 'unitCode', 'BT-130', $quantityExpression, $line),
            price: $this->price($line),
            item: $this->item($line),
            vat: $this->vat($line),
            netAmount: $d->decimal(self::SETTLEMENT.'ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount', $line, 'BT-131'),
            note: $d->text('ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:Content', $line),
            objectIdentifier: $this->objectIdentifiers($line, false)[0] ?? null,
            orderLineReference: $d->text(self::AGREEMENT.'ram:BuyerOrderReferencedDocument/ram:LineID', $line),
            buyerAccountingReference: $d->text(self::SETTLEMENT.'ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID', $line),
            period: $this->period($d->one(self::SETTLEMENT.'ram:BillingSpecifiedPeriod', $line)),
            allowances: $allowances,
            charges: $charges,
        );
    }

    /**
     * Maps a line of an EXTENDED-CTC-FR invoice.
     */
    public function extendedLine(DOMElement $line): InvoiceLine|InformationalLine
    {
        $d = $this->document;
        $subType = $d->code(LineSubType::class, $d->one('ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode', $line));
        if ($subType === LineSubType::Group || $subType === LineSubType::Information) {
            return $this->informational($line, $subType);
        }

        $invoiceLine = $this->line($line);
        $french = $this->french($line, $subType, array_slice($this->objectIdentifiers($line, true), 1));
        $isEmpty = $french == new FrenchLine;

        return $isEmpty ? $invoiceLine : $invoiceLine->withExtension($french);
    }

    public function period(?DOMElement $period): ?Period
    {
        if ($period === null) {
            return null;
        }
        $start = $this->document->date('ram:StartDateTime/udt:DateTimeString', $period);
        $end = $this->document->date('ram:EndDateTime/udt:DateTimeString', $period);

        return $start === null && $end === null ? null : new Period(start: $start, end: $end);
    }

    /**
     * GROUP or INFORMATION line: every group is optional, the item name (BT-153) is required.
     */
    private function informational(DOMElement $line, LineSubType $subType): InformationalLine
    {
        $d = $this->document;
        $quantity = $d->one('ram:SpecifiedLineTradeDelivery/ram:BilledQuantity', $line);
        $hasPrice = $d->one(self::AGREEMENT.'ram:NetPriceProductTradePrice', $line) !== null;
        $hasVat = $d->one(self::SETTLEMENT.'ram:ApplicableTradeTax/ram:CategoryCode', $line) !== null;

        return new InformationalLine(
            id: $d->requiredText('ram:AssociatedDocumentLineDocument/ram:LineID', $line, 'BT-126'),
            subType: $subType,
            item: $this->item($line),
            quantity: $quantity === null ? null : $d->decimalOf($quantity, 'BT-129'),
            unit: $d->code(UnitCode::class, $quantity, 'unitCode'),
            price: $hasPrice ? $this->price($line) : null,
            vat: $hasVat ? $this->vat($line) : null,
            netAmount: $d->decimal(self::SETTLEMENT.'ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount', $line, 'BT-131'),
            note: $d->text('ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:Content', $line),
            period: $this->period($d->one(self::SETTLEMENT.'ram:BillingSpecifiedPeriod', $line)),
            french: $this->french($line, null, $this->objectIdentifiers($line, true)),
        );
    }

    /**
     * French line data (EXT-FR-FE).
     *
     * @param  list<Identifier>  $objectIdentifiers
     */
    private function french(DOMElement $line, ?LineSubType $subType, array $objectIdentifiers): FrenchLine
    {
        $d = $this->document;
        $summation = self::SETTLEMENT.'ram:SpecifiedTradeSettlementLineMonetarySummation/';

        return new FrenchLine(
            parentLineId: $d->text('ram:AssociatedDocumentLineDocument/ram:ParentLineID', $line),
            subType: $subType,
            seller: $this->seller($d->one(self::AGREEMENT.'ram:ItemSellerTradeParty', $line)),
            precedingInvoice: $this->precedingInvoice($d->one(self::SETTLEMENT.'ram:InvoiceReferencedDocument', $line)),
            delivery: $this->delivery($line),
            vatExemptionReason: $d->text(self::SETTLEMENT.'ram:ApplicableTradeTax/ram:ExemptionReason', $line),
            vatDueDateCode: $this->dueDateCode($d->one(self::SETTLEMENT.'ram:ApplicableTradeTax/ram:DueDateTypeCode', $line)),
            vatAmount: $d->decimal($summation.'ram:TaxTotalAmount', $line, 'EXT-FR-FE-181'),
            amountWithVat: $d->decimal($summation.'ram:GrandTotalAmount', $line, 'EXT-FR-FE-184'),
            noteSubjectCode: $d->code(TextSubjectCode::class, $d->one('ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:SubjectCode', $line)),
            objectIdentifiers: $objectIdentifiers,
        );
    }

    /**
     * ram:ItemSellerTradeParty (EXT-FR-FE-164 to EXT-FR-FE-177).
     */
    private function seller(?DOMElement $party): ?LineSeller
    {
        if ($party === null) {
            return null;
        }
        $d = $this->document;
        $seller = new LineSeller(
            name: $d->text('ram:Name', $party),
            globalId: $d->identifier($d->one('ram:GlobalID', $party)),
            legalRegistration: $d->identifier($d->one('ram:SpecifiedLegalOrganization/ram:ID', $party)),
            vatIdentifier: $d->text('ram:SpecifiedTaxRegistration/ram:ID', $party),
            country: $d->code(CountryCode::class, $d->one('ram:PostalTradeAddress/ram:CountryID', $party)),
        );

        return $seller == new LineSeller ? null : $seller;
    }

    /**
     * Preceding invoice reference of the line (EXT-FR-FE-BG-06).
     */
    private function precedingInvoice(?DOMElement $reference): ?PrecedingInvoiceReference
    {
        if ($reference === null) {
            return null;
        }
        $d = $this->document;
        $number = $d->text('ram:IssuerAssignedID', $reference);
        if ($number === null) {
            $d->reject($reference, 'dropped: no preceding invoice reference');

            return null;
        }

        return new PrecedingInvoiceReference($number, $d->date('ram:FormattedIssueDateTime/qdt:DateTimeString', $reference));
    }

    /**
     * Line deliver-to location (EXT-FR-FE-BG-10) and actual delivery date (EXT-FR-FE-BG-11).
     */
    private function delivery(DOMElement $line): ?LineDelivery
    {
        $d = $this->document;
        $party = $d->one('ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty', $line);
        $delivery = new LineDelivery(
            name: $party === null ? null : $d->text('ram:Name', $party),
            address: $party === null ? null : (new PartyMapper($d))->address($d->one('ram:PostalTradeAddress', $party)),
            actualDeliveryDate: $d->date('ram:SpecifiedLineTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString', $line),
        );

        return $delivery == new LineDelivery ? null : $delivery;
    }

    /**
     * EXT-FR-FE-180: UNTDID 2475 in CII, UNTDID 2005 in the model as for BT-8.
     */
    private function dueDateCode(?DOMElement $element): ?string
    {
        if ($element === null) {
            return null;
        }
        $value = trim($element->textContent);
        $code = array_flip(LineWriter::DUE_DATE_TYPE_CODES)[$value] ?? null;
        if ($code === null) {
            $this->document->reject($element, "invalid code '{$value}'");

            return null;
        }
        $this->document->use($element);

        return (string) $code;
    }

    /**
     * BT-128: line ram:AdditionalReferencedDocument elements with type code 130 or, as in the CEN
     * examples, without a type code; the scheme is in ram:ReferenceTypeCode. EN 16931 has one per
     * line, so only the first is read unless $all is set.
     *
     * @return list<Identifier>
     */
    private function objectIdentifiers(DOMElement $line, bool $all): array
    {
        $d = $this->document;
        $identifiers = [];
        foreach ($d->all(self::SETTLEMENT.'ram:AdditionalReferencedDocument', $line) as $reference) {
            $type = $d->one('ram:TypeCode', $reference);
            if ($type !== null && trim($type->textContent) !== '130') {
                continue;
            }
            $id = $d->one('ram:IssuerAssignedID', $reference);
            if ($id === null || $id->textContent === '') {
                continue;
            }
            if ($type !== null) {
                $d->use($type);
            }
            $d->use($id);

            $identifiers[] = new Identifier(value: $id->textContent, scheme: $d->token('ram:ReferenceTypeCode', $reference));
            if (! $all) {
                break;
            }
        }

        return $identifiers;
    }

    /**
     * @return array{list<LineAllowance>, list<LineCharge>}
     */
    private function allowancesAndCharges(DOMElement $line): array
    {
        $d = $this->document;
        $allowances = [];
        $charges = [];

        foreach ($d->all(self::SETTLEMENT.'ram:SpecifiedTradeAllowanceCharge', $line) as $allowanceCharge) {
            $isCharge = $d->chargeIndicator('ram:ChargeIndicator/udt:Indicator', $allowanceCharge);
            if ($isCharge === null) {
                continue;
            }
            $amount = $d->decimal('ram:ActualAmount', $allowanceCharge, $isCharge ? 'BT-141' : 'BT-136');
            if ($amount === null) {
                $d->reject($allowanceCharge, 'dropped: no valid amount');

                continue;
            }
            $baseAmount = $d->decimal('ram:BasisAmount', $allowanceCharge, $isCharge ? 'BT-142' : 'BT-137');
            $percentage = $d->decimal('ram:CalculationPercent', $allowanceCharge);
            $reason = $d->text('ram:Reason', $allowanceCharge);
            $codeElement = $d->one('ram:ReasonCode', $allowanceCharge);

            if ($isCharge) {
                $charges[] = new LineCharge(
                    amount: $amount,
                    baseAmount: $baseAmount,
                    percentage: $percentage,
                    reason: $reason,
                    reasonCode: $d->code(ChargeReasonCode::class, $codeElement),
                );
            } else {
                $allowances[] = new LineAllowance(
                    amount: $amount,
                    baseAmount: $baseAmount,
                    percentage: $percentage,
                    reason: $reason,
                    reasonCode: $d->code(AllowanceReasonCode::class, $codeElement),
                );
            }
        }

        return [$allowances, $charges];
    }

    /**
     * BG-29. The price discount (BT-147) is the allowance on the gross price. EN 16931 has one
     * base quantity (BT-149, BT-150) for gross and net price: it is read from the net price, or
     * from the gross price when the net price has none; a gross price base quantity that differs
     * from the net one is left unmapped.
     */
    private function price(DOMElement $line): Price
    {
        $d = $this->document;
        $gross = $d->one(self::AGREEMENT.'ram:GrossPriceProductTradePrice', $line);
        $grossPrice = null;
        $discount = null;
        $grossBase = null;

        if ($gross !== null) {
            $grossPrice = $d->decimal('ram:ChargeAmount', $gross, 'BT-148');
            $grossBase = $d->one('ram:BasisQuantity', $gross);
            $priceAllowance = $d->one('ram:AppliedTradeAllowanceCharge', $gross);
            $indicator = $priceAllowance === null ? null : $d->one('ram:ChargeIndicator/udt:Indicator', $priceAllowance);
            if ($priceAllowance !== null && $indicator !== null && in_array(trim($indicator->textContent), ['false', '0'], true)) {
                $d->use($indicator);
                $discount = $d->decimal('ram:ActualAmount', $priceAllowance, 'BT-147');
            }
        }

        $netBase = $d->one(self::AGREEMENT.'ram:NetPriceProductTradePrice/ram:BasisQuantity', $line);
        $base = $netBase ?? $grossBase;
        $baseQuantity = $base === null ? null : $d->decimalOf($base);
        $baseQuantityUnit = $d->code(UnitCode::class, $base, 'unitCode');

        if ($netBase !== null && $grossBase !== null && $baseQuantity !== null
            && $d->peekDecimal($grossBase)?->isEqualTo($baseQuantity) === true
            && $d->attribute($grossBase, 'unitCode') === $d->attribute($netBase, 'unitCode')) {
            $d->use($grossBase);
        }

        return new Price(
            netPrice: $d->requiredDecimal(self::AGREEMENT.'ram:NetPriceProductTradePrice/ram:ChargeAmount', $line, 'BT-146'),
            discount: $discount,
            grossPrice: $grossPrice,
            baseQuantity: $baseQuantity,
            baseQuantityUnit: $baseQuantityUnit,
        );
    }

    private function vat(DOMElement $line): LineVat
    {
        $d = $this->document;
        $expression = self::SETTLEMENT.'ram:ApplicableTradeTax/ram:CategoryCode';
        $category = $d->requiredCode(VatCategory::class, $d->one($expression, $line), null, 'BT-151', $expression, $line);
        $d->token(self::SETTLEMENT.'ram:ApplicableTradeTax/ram:TypeCode', $line);

        return new LineVat(category: $category, rate: $d->decimal(self::SETTLEMENT.'ram:ApplicableTradeTax/ram:RateApplicablePercent', $line));
    }

    private function item(DOMElement $line): Item
    {
        $d = $this->document;
        $product = $d->one('ram:SpecifiedTradeProduct', $line) ?? $line;

        $classifications = [];
        foreach ($d->all('ram:DesignatedProductClassification/ram:ClassCode', $product) as $code) {
            $listIdentifier = $d->attribute($code, 'listID');
            if ($listIdentifier === null) {
                $d->reject($code, 'missing scheme identifier', 'listID');

                continue;
            }
            if ($code->textContent === '') {
                continue;
            }
            $d->use($code);
            $classifications[] = new ItemClassification(
                identifier: $code->textContent,
                listIdentifier: $listIdentifier,
                listVersion: $d->attribute($code, 'listVersionID'),
            );
        }

        $attributes = [];
        foreach ($d->all('ram:ApplicableProductCharacteristic', $product) as $characteristic) {
            $name = $d->one('ram:Description', $characteristic);
            $value = $d->one('ram:Value', $characteristic);
            if ($name === null || $value === null) {
                $d->reject($characteristic, 'dropped: name or value missing');

                continue;
            }
            $d->use($name);
            $d->use($value);
            $attributes[] = new ItemAttribute(name: $name->textContent, value: $value->textContent);
        }

        return new Item(
            name: $d->requiredText('ram:SpecifiedTradeProduct/ram:Name', $line, 'BT-153'),
            description: $d->text('ram:Description', $product),
            sellersIdentifier: $d->text('ram:SellerAssignedID', $product),
            buyersIdentifier: $d->text('ram:BuyerAssignedID', $product),
            standardIdentifier: $d->identifier($d->one('ram:GlobalID', $product)),
            classifications: $classifications,
            originCountry: $d->code(CountryCode::class, $d->one('ram:OriginTradeCountry/ram:ID', $product)),
            attributes: $attributes,
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\ItemAttribute;
use IntrAgile\EInvoicing\Model\ItemClassification;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;

/**
 * Maps a UBL InvoiceLine or CreditNoteLine to an invoice line (BG-25).
 *
 * @internal
 */
final class LineMapper
{
    public function __construct(
        private readonly UblDocument $document,
        private readonly PartyMapper $parties,
    ) {}

    public function line(DOMElement $line): InvoiceLine
    {
        $d = $this->document;
        $quantityExpression = $d->isCreditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity';

        [$allowances, $charges] = $this->allowancesAndCharges($line);

        return new InvoiceLine(
            id: $d->requiredText('cbc:ID', $line, 'BT-126'),
            quantity: $d->requiredDecimal($quantityExpression, $line, 'BT-129'),
            unit: $d->requiredCode(UnitCode::class, $d->one($quantityExpression, $line), 'unitCode', 'BT-130', $quantityExpression, $line),
            price: $this->price($line),
            item: $this->item($line),
            vat: $this->vat($line),
            netAmount: $d->decimal('cbc:LineExtensionAmount', $line),
            note: $d->text('cbc:Note', $line),
            objectIdentifier: $this->objectIdentifier($line),
            orderLineReference: $d->text('cac:OrderLineReference/cbc:LineID', $line),
            buyerAccountingReference: $d->text('cbc:AccountingCost', $line),
            period: $this->period($d->one('cac:InvoicePeriod', $line)),
            allowances: $allowances,
            charges: $charges,
        );
    }

    public function period(?DOMElement $period): ?Period
    {
        if ($period === null) {
            return null;
        }
        $start = $this->document->date('cbc:StartDate', $period);
        $end = $this->document->date('cbc:EndDate', $period);

        return $start === null && $end === null ? null : new Period(start: $start, end: $end);
    }

    /**
     * Reads cbc:ChargeIndicator; a missing or unrecognised value drops the allowance or charge.
     */
    public function chargeIndicator(DOMElement $allowanceCharge): ?bool
    {
        $indicator = match ($this->document->token('cbc:ChargeIndicator', $allowanceCharge)) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };
        if ($indicator === null) {
            $this->document->reject($allowanceCharge, 'dropped: no valid charge indicator');
        }

        return $indicator;
    }

    /**
     * BT-128: the first cac:DocumentReference with type code 130 or, as in the CEN examples,
     * without a type code.
     */
    private function objectIdentifier(DOMElement $line): ?Identifier
    {
        foreach ($this->document->all('cac:DocumentReference', $line) as $reference) {
            $type = $this->document->one('cbc:DocumentTypeCode', $reference);
            if ($type !== null && trim($type->textContent) !== '130') {
                continue;
            }
            $identifier = $this->parties->identifier($this->document->one('cbc:ID', $reference));
            if ($identifier !== null) {
                if ($type !== null) {
                    $this->document->use($type);
                }

                return $identifier;
            }
        }

        return null;
    }

    /**
     * @return array{list<LineAllowance>, list<LineCharge>}
     */
    private function allowancesAndCharges(DOMElement $line): array
    {
        $d = $this->document;
        $allowances = [];
        $charges = [];

        foreach ($d->all('cac:AllowanceCharge', $line) as $allowanceCharge) {
            $isCharge = $this->chargeIndicator($allowanceCharge);
            if ($isCharge === null) {
                continue;
            }
            $amount = $d->decimal('cbc:Amount', $allowanceCharge);
            if ($amount === null) {
                $d->reject($allowanceCharge, 'dropped: no valid amount');

                continue;
            }
            $baseAmount = $d->decimal('cbc:BaseAmount', $allowanceCharge);
            $percentage = $d->decimal('cbc:MultiplierFactorNumeric', $allowanceCharge);
            $reason = $d->text('cbc:AllowanceChargeReason', $allowanceCharge);
            $codeElement = $d->one('cbc:AllowanceChargeReasonCode', $allowanceCharge);

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

    private function price(DOMElement $line): Price
    {
        $d = $this->document;
        $discount = null;
        $grossPrice = null;

        $priceAllowance = $d->one('cac:Price/cac:AllowanceCharge', $line);
        if ($priceAllowance !== null && in_array(trim((string) $d->one('cbc:ChargeIndicator', $priceAllowance)?->textContent), ['false', '0'], true)) {
            $d->token('cbc:ChargeIndicator', $priceAllowance);
            $discount = $d->decimal('cbc:Amount', $priceAllowance);
            $grossPrice = $d->decimal('cbc:BaseAmount', $priceAllowance);
        }

        $baseQuantity = $d->one('cac:Price/cbc:BaseQuantity', $line);

        return new Price(
            netPrice: $d->requiredDecimal('cac:Price/cbc:PriceAmount', $line, 'BT-146'),
            discount: $discount,
            grossPrice: $grossPrice,
            baseQuantity: $baseQuantity === null ? null : $d->decimalOf($baseQuantity),
            baseQuantityUnit: $d->code(UnitCode::class, $baseQuantity, 'unitCode'),
        );
    }

    private function vat(DOMElement $line): LineVat
    {
        $d = $this->document;
        $expression = 'cac:Item/cac:ClassifiedTaxCategory/cbc:ID';
        $category = $d->requiredCode(VatCategory::class, $d->one($expression, $line), null, 'BT-151', $expression, $line);
        $d->token('cac:Item/cac:ClassifiedTaxCategory/cac:TaxScheme/cbc:ID', $line);

        return new LineVat(category: $category, rate: $d->decimal('cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', $line));
    }

    private function item(DOMElement $line): Item
    {
        $d = $this->document;
        $item = $d->one('cac:Item', $line) ?? $line;

        $classifications = [];
        foreach ($d->all('cac:CommodityClassification/cbc:ItemClassificationCode', $item) as $code) {
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
        foreach ($d->all('cac:AdditionalItemProperty', $item) as $property) {
            $name = $d->one('cbc:Name', $property);
            $value = $d->one('cbc:Value', $property);
            if ($name === null || $value === null) {
                $d->reject($property, 'dropped: name or value missing');

                continue;
            }
            $d->use($name);
            $d->use($value);
            $attributes[] = new ItemAttribute(name: $name->textContent, value: $value->textContent);
        }

        return new Item(
            name: $d->requiredText('cac:Item/cbc:Name', $line, 'BT-153'),
            description: $d->text('cbc:Description', $item),
            sellersIdentifier: $d->text('cac:SellersItemIdentification/cbc:ID', $item),
            buyersIdentifier: $d->text('cac:BuyersItemIdentification/cbc:ID', $item),
            standardIdentifier: $this->parties->identifier($d->one('cac:StandardItemIdentification/cbc:ID', $item)),
            classifications: $classifications,
            originCountry: $d->code(CountryCode::class, $d->one('cac:OriginCountry/cbc:IdentificationCode', $item)),
            attributes: $attributes,
        );
    }
}

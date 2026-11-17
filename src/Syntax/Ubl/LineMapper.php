<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

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
 * Maps a UBL InvoiceLine or CreditNoteLine to an invoice line (BG-25).
 *
 * In an EXTENDED-CTC-FR invoice (extendedLine) a GROUP or INFORMATION line becomes an
 * InformationalLine; any other line becomes an invoice line, with a FrenchLine extension when it
 * carries French line data. See LineWriter for the UBL elements of the French line data.
 *
 * @internal
 */
final class LineMapper
{
    public function __construct(private readonly UblDocument $document) {}

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
            netAmount: $d->decimal('cbc:LineExtensionAmount', $line, 'BT-131'),
            note: $d->text('cbc:Note', $line),
            objectIdentifier: $this->objectIdentifiers($line, false)[0] ?? null,
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
        return $this->document->chargeIndicator('cbc:ChargeIndicator', $allowanceCharge);
    }

    /**
     * Maps a line of an EXTENDED-CTC-FR invoice.
     *
     * @param  string  $invoiceNumber  invoice number (BT-1), which a billing reference without a
     *                                 preceding invoice carries as its identifier
     */
    public function extendedLine(DOMElement $line, string $invoiceNumber): InvoiceLine|InformationalLine
    {
        $d = $this->document;
        [$structure] = $this->billingReferences($line, $invoiceNumber);
        $subType = $structure === null ? null : $d->code(LineSubType::class, $d->one('cac:InvoiceDocumentReference/cbc:DocumentStatusCode', $structure));
        if ($subType === LineSubType::Group || $subType === LineSubType::Information) {
            return $this->informational($line, $subType, $invoiceNumber);
        }

        $invoiceLine = $this->line($line);
        [$note, $noteSubjectCode] = $this->note($line);
        if ($note !== $invoiceLine->note) {
            $invoiceLine = $invoiceLine->withNote($note);
        }
        $french = $this->french($line, $subType, array_slice($this->objectIdentifiers($line, true), 1), $noteSubjectCode, $invoiceNumber);

        return $french == new FrenchLine ? $invoiceLine : $invoiceLine->withExtension($french);
    }

    /**
     * GROUP or INFORMATION line: every group is optional, the item name (BT-153) is required.
     */
    private function informational(DOMElement $line, LineSubType $subType, string $invoiceNumber): InformationalLine
    {
        $d = $this->document;
        $quantity = $d->one($d->isCreditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity', $line);
        $hasPrice = $d->one('cac:Price/cbc:PriceAmount', $line) !== null;
        $hasVat = $d->one('cac:Item/cac:ClassifiedTaxCategory/cbc:ID', $line) !== null;
        [$note, $noteSubjectCode] = $this->note($line);

        return new InformationalLine(
            id: $d->requiredText('cbc:ID', $line, 'BT-126'),
            subType: $subType,
            item: $this->item($line),
            quantity: $quantity === null ? null : $d->decimalOf($quantity, 'BT-129'),
            unit: $d->code(UnitCode::class, $quantity, 'unitCode'),
            price: $hasPrice ? $this->price($line) : null,
            vat: $hasVat ? $this->vat($line) : null,
            netAmount: $d->decimal('cbc:LineExtensionAmount', $line, 'BT-131'),
            note: $note,
            period: $this->period($d->one('cac:InvoicePeriod', $line)),
            french: $this->french($line, null, $this->objectIdentifiers($line, true), $noteSubjectCode, $invoiceNumber),
        );
    }

    /**
     * French line data (EXT-FR-FE). UBL has no element for the line amount with VAT
     * (EXT-FR-FE-184).
     *
     * @param  list<Identifier>  $objectIdentifiers
     */
    private function french(
        DOMElement $line,
        ?LineSubType $subType,
        array $objectIdentifiers,
        ?TextSubjectCode $noteSubjectCode,
        string $invoiceNumber,
    ): FrenchLine {
        $d = $this->document;
        [$structure, $preceding] = $this->billingReferences($line, $invoiceNumber);

        return new FrenchLine(
            parentLineId: $structure === null ? null : $d->text('cac:BillingReferenceLine/cbc:ID', $structure),
            subType: $subType,
            seller: $this->seller($d->one('cac:Item/cac:ManufacturerParty', $line)),
            precedingInvoice: $this->precedingInvoice($preceding),
            delivery: $this->delivery($d->one('cac:Delivery', $line)),
            vatExemptionReason: $d->text('cac:Item/cac:ClassifiedTaxCategory/cbc:TaxExemptionReason', $line),
            vatDueDateCode: $d->token('cac:InvoicePeriod/cbc:DescriptionCode', $line),
            vatAmount: $d->decimal('cac:TaxTotal/cbc:TaxAmount', $line, 'EXT-FR-FE-181'),
            noteSubjectCode: $noteSubjectCode,
            objectIdentifiers: $objectIdentifiers,
        );
    }

    /**
     * Line note (BT-127); a leading "#CODE#" carries its subject code (EXT-FR-FE-183), as in the
     * document notes.
     *
     * @return array{?string, ?TextSubjectCode}
     */
    private function note(DOMElement $line): array
    {
        $d = $this->document;
        $text = $d->text('cbc:Note', $line);
        if ($text === null || preg_match('/^#([A-Z]{3})#(.*)$/s', $text, $match) !== 1) {
            return [$text, null];
        }
        $code = TextSubjectCode::tryFrom($match[1]);
        if ($code === null) {
            $element = $d->one('cbc:Note', $line);
            if ($element !== null) {
                $d->reject($element, "invalid code '{$match[1]}'");
            }
        }

        return [$match[2], $code];
    }

    /**
     * cac:ManufacturerParty (EXT-FR-FE-164 to EXT-FR-FE-177).
     */
    private function seller(?DOMElement $party): ?LineSeller
    {
        if ($party === null) {
            return null;
        }
        $d = $this->document;
        $d->token('cac:PartyTaxScheme/cac:TaxScheme/cbc:ID', $party);
        $seller = new LineSeller(
            name: $d->text('cac:PartyLegalEntity/cbc:RegistrationName', $party),
            globalId: $d->identifier($d->one('cac:PartyIdentification/cbc:ID', $party)),
            legalRegistration: $d->identifier($d->one('cac:PartyLegalEntity/cbc:CompanyID', $party)),
            vatIdentifier: $d->text('cac:PartyTaxScheme/cbc:CompanyID', $party),
            country: $d->code(CountryCode::class, $d->one('cac:PostalAddress/cac:Country/cbc:IdentificationCode', $party)),
        );

        return $seller == new LineSeller ? null : $seller;
    }

    /**
     * The line cac:BillingReference holding the line structure, whose document identifier is the
     * invoice number (BT-1), and the first one referring to a preceding invoice. Further billing
     * references stay unmapped and are reported.
     *
     * @return array{?DOMElement, ?DOMElement}
     */
    private function billingReferences(DOMElement $line, string $invoiceNumber): array
    {
        $structure = null;
        $preceding = null;
        foreach ($this->document->all('cac:BillingReference', $line) as $reference) {
            $id = $this->document->one('cac:InvoiceDocumentReference/cbc:ID', $reference);
            if ($id !== null && $id->textContent === $invoiceNumber) {
                $structure ??= $reference;
            } else {
                $preceding ??= $reference;
            }
        }
        if ($structure !== null) {
            $this->document->token('cac:InvoiceDocumentReference/cbc:ID', $structure);
        }

        return [$structure, $preceding];
    }

    /**
     * Preceding invoice reference of the line (EXT-FR-FE-BG-06).
     */
    private function precedingInvoice(?DOMElement $billingReference): ?PrecedingInvoiceReference
    {
        $reference = $billingReference === null ? null : $this->document->one('cac:InvoiceDocumentReference', $billingReference);
        if ($reference === null) {
            return null;
        }
        $d = $this->document;
        $number = $d->text('cbc:ID', $reference);
        if ($number === null) {
            $d->reject($reference, 'dropped: no preceding invoice reference');

            return null;
        }

        return new PrecedingInvoiceReference($number, $d->date('cbc:IssueDate', $reference));
    }

    /**
     * Line deliver-to location (EXT-FR-FE-BG-10) and actual delivery date (EXT-FR-FE-BG-11).
     */
    private function delivery(?DOMElement $element): ?LineDelivery
    {
        if ($element === null) {
            return null;
        }
        $d = $this->document;
        $location = $d->one('cac:DeliveryLocation', $element);
        $delivery = new LineDelivery(
            name: $location === null ? null : $d->text('cbc:Name', $location),
            address: $location === null ? null : (new PartyMapper($d))->address($d->one('cac:Address', $location)),
            actualDeliveryDate: $d->date('cbc:ActualDeliveryDate', $element),
        );

        return $delivery == new LineDelivery ? null : $delivery;
    }

    /**
     * BT-128: cac:DocumentReference elements with type code 130 or, as in the CEN examples,
     * without a type code. EN 16931 has one per line, so only the first is read unless $all is
     * set.
     *
     * @return list<Identifier>
     */
    private function objectIdentifiers(DOMElement $line, bool $all): array
    {
        $identifiers = [];
        foreach ($this->document->all('cac:DocumentReference', $line) as $reference) {
            $type = $this->document->one('cbc:DocumentTypeCode', $reference);
            if ($type !== null && trim($type->textContent) !== '130') {
                continue;
            }
            $identifier = $this->document->identifier($this->document->one('cbc:ID', $reference));
            if ($identifier !== null) {
                if ($type !== null) {
                    $this->document->use($type);
                }
                $identifiers[] = $identifier;
                if (! $all) {
                    break;
                }
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

        foreach ($d->all('cac:AllowanceCharge', $line) as $allowanceCharge) {
            $isCharge = $this->chargeIndicator($allowanceCharge);
            if ($isCharge === null) {
                continue;
            }
            $amount = $d->decimal('cbc:Amount', $allowanceCharge, $isCharge ? 'BT-141' : 'BT-136');
            if ($amount === null) {
                $d->reject($allowanceCharge, 'dropped: no valid amount');

                continue;
            }
            $baseAmount = $d->decimal('cbc:BaseAmount', $allowanceCharge, $isCharge ? 'BT-142' : 'BT-137');
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
            $discount = $d->decimal('cbc:Amount', $priceAllowance, 'BT-147');
            $grossPrice = $d->decimal('cbc:BaseAmount', $priceAllowance, 'BT-148');
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
            standardIdentifier: $d->identifier($d->one('cac:StandardItemIdentification/cbc:ID', $item)),
            classifications: $classifications,
            originCountry: $d->code(CountryCode::class, $d->one('cac:OriginCountry/cbc:IdentificationCode', $item)),
            attributes: $attributes,
        );
    }
}

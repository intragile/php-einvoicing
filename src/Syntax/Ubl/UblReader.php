<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\AdditionalDocument;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Syntax\ReadResult;
use IntrAgile\EInvoicing\Syntax\SafeXml;

/**
 * Reads a UBL 2.1 Invoice or CreditNote into the EN 16931 model, following the CEN UBL syntax
 * binding (EN 16931-3-2) as used by the validation artefacts 1.3.17.
 *
 * Elements that are not mapped are listed in ReadResult::$ignored instead of being dropped
 * silently. A code that its closed code list rejects leaves the term empty and is reported as
 * "(invalid code '...')"; when that term is mandatory inside an optional group, the group
 * occurrence is dropped and reported. Only data the model cannot do without (BT-1, BT-2, BT-3,
 * BT-5, the seller and buyer names, and per line BT-126, BT-129, BT-130, BT-146, BT-151 and
 * BT-153) makes a document unsupported.
 */
final class UblReader
{
    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when it is not a readable UBL invoice or credit note
     */
    public function read(string $xml): ReadResult
    {
        $d = new UblDocument(SafeXml::load($xml));
        $parties = new PartyMapper($d);
        $lines = new LineMapper($d);
        $root = $d->root;

        $currency = $d->requiredCode(CurrencyCode::class, $d->one('cbc:DocumentCurrencyCode'), null, 'BT-5', 'cbc:DocumentCurrencyCode', $root);
        $taxCurrency = $d->code(CurrencyCode::class, $d->one('cbc:TaxCurrencyCode'));
        $typeExpression = $d->isCreditNote ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode';
        [$projectReference, $invoicedObject, $additionalDocuments] = $this->documentReferences($d);
        $salesOrder = $d->text('cac:OrderReference/cbc:SalesOrderID');
        $purchaseOrder = $d->text('cac:OrderReference/cbc:ID');
        if ($salesOrder !== null && $purchaseOrder === Placeholder::ORDER_ID) {
            $purchaseOrder = null;
        }
        [$allowances, $charges] = $this->allowancesAndCharges($d, $lines);
        [$totalVat, $totalVatInAccountingCurrency, $vatBreakdown] = $this->taxTotals($d, $currency, $taxCurrency);

        $invoice = new Invoice(
            number: $d->requiredText('cbc:ID', $root, 'BT-1'),
            issueDate: $d->requiredDate('cbc:IssueDate', $root, 'BT-2'),
            type: $d->requiredCode(InvoiceTypeCode::class, $d->one($typeExpression), null, 'BT-3', $typeExpression, $root),
            currency: $currency,
            seller: $parties->party($this->requiredParty($d, 'cac:AccountingSupplierParty/cac:Party', 'BG-4'), PartyMapper::SELLER),
            buyer: $parties->party($this->requiredParty($d, 'cac:AccountingCustomerParty/cac:Party', 'BG-7'), PartyMapper::BUYER),
            lines: $this->lines($d, $lines),
            taxCurrency: $taxCurrency,
            taxPointDate: $d->date('cbc:TaxPointDate'),
            taxPointDateCode: $d->token('cac:InvoicePeriod/cbc:DescriptionCode'),
            dueDate: $d->isCreditNote ? $d->date('cac:PaymentMeans[1]/cbc:PaymentDueDate') : $d->date('cbc:DueDate'),
            buyerReference: $d->text('cbc:BuyerReference'),
            projectReference: $projectReference,
            contractReference: $d->text('cac:ContractDocumentReference/cbc:ID'),
            purchaseOrderReference: $purchaseOrder,
            salesOrderReference: $salesOrder,
            receivingAdviceReference: $d->text('cac:ReceiptDocumentReference/cbc:ID'),
            despatchAdviceReference: $d->text('cac:DespatchDocumentReference/cbc:ID'),
            tenderOrLotReference: $d->text('cac:OriginatorDocumentReference/cbc:ID'),
            invoicedObjectIdentifier: $invoicedObject,
            buyerAccountingReference: $d->text('cbc:AccountingCost'),
            paymentTerms: $d->text('cac:PaymentTerms/cbc:Note'),
            notes: $this->notes($d),
            processControl: $this->processControl($d),
            precedingInvoices: $this->precedingInvoices($d),
            payee: ($payee = $d->one('cac:PayeeParty')) === null ? null : $parties->party($payee, PartyMapper::PAYEE),
            taxRepresentative: ($representative = $d->one('cac:TaxRepresentativeParty')) === null
                ? null
                : $parties->party($representative, PartyMapper::TAX_REPRESENTATIVE),
            delivery: $this->delivery($d, $parties, $lines),
            payment: (new PaymentMapper($d))->payment(),
            allowances: $allowances,
            charges: $charges,
            totals: $this->totals($d, $totalVat, $totalVatInAccountingCurrency),
            vatBreakdown: $vatBreakdown,
            additionalDocuments: $additionalDocuments,
        );

        return new ReadResult(invoice: $invoice, ignored: $d->ignored());
    }

    private function requiredParty(UblDocument $d, string $expression, string $term): DOMElement
    {
        return $d->one($expression)
            ?? throw new UnsupportedDocument(sprintf('Missing required %s (%s).', $term, $expression));
    }

    /**
     * @return non-empty-list<InvoiceLine>
     */
    private function lines(UblDocument $d, LineMapper $mapper): array
    {
        $result = [];
        foreach ($d->all($d->isCreditNote ? 'cac:CreditNoteLine' : 'cac:InvoiceLine') as $line) {
            $result[] = $mapper->line($line);
        }
        if ($result === []) {
            throw new UnsupportedDocument('The document has no invoice lines (BG-25).');
        }

        return $result;
    }

    /**
     * BG-1. A leading "#CODE#" carries the note subject code (BT-21).
     *
     * @return list<Note>
     */
    private function notes(UblDocument $d): array
    {
        $notes = [];
        foreach ($d->all('cbc:Note') as $element) {
            $d->use($element);
            $text = $element->textContent;
            if ($text === '') {
                continue;
            }
            $subject = null;
            if (preg_match('/^#([A-Z]{3})#(.*)$/s', $text, $match) === 1) {
                $text = $match[2];
                $subject = TextSubjectCode::tryFrom($match[1]);
                if ($subject === null) {
                    $d->reject($element, "invalid code '{$match[1]}'");
                }
            }
            $notes[] = new Note(text: $text, subjectCode: $subject);
        }

        return $notes;
    }

    private function processControl(UblDocument $d): ?ProcessControl
    {
        $specification = $d->text('cbc:CustomizationID');

        return $specification === null
            ? null
            : new ProcessControl(specificationIdentifier: $specification, businessProcessType: $d->text('cbc:ProfileID'));
    }

    /**
     * @return list<PrecedingInvoiceReference>
     */
    private function precedingInvoices(UblDocument $d): array
    {
        $references = [];
        foreach ($d->all('cac:BillingReference/cac:InvoiceDocumentReference') as $reference) {
            $number = $d->text('cbc:ID', $reference);
            if ($number === null) {
                $d->reject($reference, 'dropped: no preceding invoice reference');

                continue;
            }
            $references[] = new PrecedingInvoiceReference(number: $number, issueDate: $d->date('cbc:IssueDate', $reference));
        }

        return $references;
    }

    /**
     * BT-11 (credit note: type code 50), BT-18 (type code 130) and BG-24 (everything else).
     *
     * @return array{?string, ?Identifier, list<AdditionalDocument>}
     */
    private function documentReferences(UblDocument $d): array
    {
        $projectReference = $d->text('cac:ProjectReference/cbc:ID');
        $invoicedObject = null;
        $documents = [];

        foreach ($d->all('cac:AdditionalDocumentReference') as $reference) {
            $type = $d->one('cbc:DocumentTypeCode', $reference);
            $typeCode = $type === null ? null : trim($type->textContent);
            $id = $d->one('cbc:ID', $reference);

            if ($type !== null && $id !== null && $id->textContent !== '') {
                if ($typeCode === '130' && $invoicedObject === null) {
                    $d->use($type);
                    $d->use($id);
                    $invoicedObject = new Identifier(value: $id->textContent, scheme: $d->attribute($id, 'schemeID'));

                    continue;
                }
                if ($typeCode === '50' && $d->isCreditNote && $projectReference === null) {
                    $d->use($type);
                    $d->use($id);
                    $projectReference = $id->textContent;

                    continue;
                }
            }

            $document = $this->additionalDocument($d, $reference);
            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return [$projectReference, $invoicedObject, $documents];
    }

    private function additionalDocument(UblDocument $d, DOMElement $reference): ?AdditionalDocument
    {
        $id = $d->text('cbc:ID', $reference);
        if ($id === null) {
            $d->reject($reference, 'dropped: no supporting document reference');

            return null;
        }

        return new AdditionalDocument(
            reference: $id,
            description: $d->text('cbc:DocumentDescription', $reference),
            externalLocation: $d->text('cac:Attachment/cac:ExternalReference/cbc:URI', $reference),
            attachment: $d->attachment($d->one('cac:Attachment/cbc:EmbeddedDocumentBinaryObject', $reference)),
        );
    }

    /**
     * BG-20 and BG-21.
     *
     * @return array{list<DocumentAllowance>, list<DocumentCharge>}
     */
    private function allowancesAndCharges(UblDocument $d, LineMapper $lines): array
    {
        $allowances = [];
        $charges = [];

        foreach ($d->all('cac:AllowanceCharge') as $allowanceCharge) {
            $isCharge = $lines->chargeIndicator($allowanceCharge);
            if ($isCharge === null) {
                continue;
            }
            $amount = $d->decimal('cbc:Amount', $allowanceCharge);
            $category = $d->code(VatCategory::class, $d->one('cac:TaxCategory/cbc:ID', $allowanceCharge));
            if ($amount === null || $category === null) {
                $d->reject($allowanceCharge, 'dropped: no valid amount or VAT category code');

                continue;
            }
            $d->token('cac:TaxCategory/cac:TaxScheme/cbc:ID', $allowanceCharge);
            $vatRate = $d->decimal('cac:TaxCategory/cbc:Percent', $allowanceCharge);
            $baseAmount = $d->decimal('cbc:BaseAmount', $allowanceCharge);
            $percentage = $d->decimal('cbc:MultiplierFactorNumeric', $allowanceCharge);
            $reason = $d->text('cbc:AllowanceChargeReason', $allowanceCharge);
            $codeElement = $d->one('cbc:AllowanceChargeReasonCode', $allowanceCharge);

            if ($isCharge) {
                $charges[] = new DocumentCharge(
                    amount: $amount,
                    vatCategory: $category,
                    vatRate: $vatRate,
                    baseAmount: $baseAmount,
                    percentage: $percentage,
                    reason: $reason,
                    reasonCode: $d->code(ChargeReasonCode::class, $codeElement),
                );
            } else {
                $allowances[] = new DocumentAllowance(
                    amount: $amount,
                    vatCategory: $category,
                    vatRate: $vatRate,
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
     * BT-110 is the cac:TaxTotal amount in the invoice currency, with its subtotals as BG-23;
     * BT-111 is the cac:TaxTotal amount in the VAT accounting currency (BT-6).
     *
     * @return array{?BigDecimal, ?BigDecimal, list<VatBreakdown>}
     */
    private function taxTotals(UblDocument $d, CurrencyCode $currency, ?CurrencyCode $taxCurrency): array
    {
        $totalVat = null;
        $totalVatInAccountingCurrency = null;
        $breakdown = [];
        $seenMain = false;
        $seenAccounting = false;

        foreach ($d->all('cac:TaxTotal') as $taxTotal) {
            $amount = $d->one('cbc:TaxAmount', $taxTotal);
            $amountCurrency = $amount === null ? null : $d->attribute($amount, 'currencyID');

            if (! $seenMain && ($amountCurrency === null || $amountCurrency === $currency->value)) {
                $seenMain = true;
                $totalVat = $amount === null ? null : $d->decimalOf($amount);
                foreach ($d->all('cac:TaxSubtotal', $taxTotal) as $subtotal) {
                    $entry = $this->vatBreakdown($d, $subtotal);
                    if ($entry !== null) {
                        $breakdown[] = $entry;
                    }
                }

                continue;
            }
            if (! $seenAccounting && $amount !== null && $taxCurrency !== null && $taxCurrency !== $currency && $amountCurrency === $taxCurrency->value) {
                $seenAccounting = true;
                $totalVatInAccountingCurrency = $d->decimalOf($amount);
            }
        }

        return [$totalVat, $totalVatInAccountingCurrency, $breakdown];
    }

    private function vatBreakdown(UblDocument $d, DOMElement $subtotal): ?VatBreakdown
    {
        $taxable = $d->decimal('cbc:TaxableAmount', $subtotal);
        $tax = $d->decimal('cbc:TaxAmount', $subtotal);
        $category = $d->code(VatCategory::class, $d->one('cac:TaxCategory/cbc:ID', $subtotal));
        if ($taxable === null || $tax === null || $category === null) {
            $d->reject($subtotal, 'dropped: no valid taxable amount, tax amount or VAT category code');

            return null;
        }
        $d->token('cac:TaxCategory/cac:TaxScheme/cbc:ID', $subtotal);

        return new VatBreakdown(
            taxableAmount: $taxable,
            taxAmount: $tax,
            category: $category,
            rate: $d->decimal('cac:TaxCategory/cbc:Percent', $subtotal),
            exemptionReason: $d->text('cac:TaxCategory/cbc:TaxExemptionReason', $subtotal),
            exemptionReasonCode: $d->code(VatExemptionReason::class, $d->one('cac:TaxCategory/cbc:TaxExemptionReasonCode', $subtotal), caseInsensitive: true),
        );
    }

    private function totals(UblDocument $d, ?BigDecimal $totalVat, ?BigDecimal $totalVatInAccountingCurrency): ?DocumentTotals
    {
        $monetaryTotal = $d->one('cac:LegalMonetaryTotal');
        if ($monetaryTotal === null) {
            return null;
        }

        $required = [];
        foreach (['LineExtensionAmount', 'TaxExclusiveAmount', 'TaxInclusiveAmount', 'PayableAmount'] as $name) {
            $element = $d->one('cbc:'.$name, $monetaryTotal);
            $value = $element === null ? null : $d->decimalOf($element);
            if ($value === null) {
                $d->reject($monetaryTotal, "dropped: no valid cbc:{$name}");

                return null;
            }
            $required[$name] = $value;
        }

        return new DocumentTotals(
            sumOfLineNetAmounts: $required['LineExtensionAmount'],
            totalWithoutVat: $required['TaxExclusiveAmount'],
            totalWithVat: $required['TaxInclusiveAmount'],
            amountDue: $required['PayableAmount'],
            sumOfAllowances: $d->decimal('cbc:AllowanceTotalAmount', $monetaryTotal),
            sumOfCharges: $d->decimal('cbc:ChargeTotalAmount', $monetaryTotal),
            totalVat: $totalVat,
            totalVatInAccountingCurrency: $totalVatInAccountingCurrency,
            paidAmount: $d->decimal('cbc:PrepaidAmount', $monetaryTotal),
            roundingAmount: $d->decimal('cbc:PayableRoundingAmount', $monetaryTotal),
        );
    }

    /**
     * BG-13 with the invoicing period (BG-14), which the model keeps inside the delivery.
     */
    private function delivery(UblDocument $d, PartyMapper $parties, LineMapper $lines): ?Delivery
    {
        $period = $lines->period($d->one('cac:InvoicePeriod'));
        $delivery = $d->one('cac:Delivery');

        if ($delivery === null) {
            return $period === null ? null : new Delivery(invoicingPeriod: $period);
        }

        $partyName = $d->text('cac:DeliveryParty/cac:PartyName/cbc:Name', $delivery);
        $location = $d->identifier($d->one('cac:DeliveryLocation/cbc:ID', $delivery));
        $date = $d->date('cbc:ActualDeliveryDate', $delivery);
        $address = $parties->address($d->one('cac:DeliveryLocation/cac:Address', $delivery));

        if ($partyName === null && $location === null && $date === null && $address === null && $period === null) {
            return null;
        }

        return new Delivery(
            partyName: $partyName,
            locationIdentifier: $location,
            actualDeliveryDate: $date,
            invoicingPeriod: $period,
            address: $address,
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\FrenchGuard;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\WriterGuard;
use InvalidArgumentException;

/**
 * Writes the EN 16931 model as a UBL 2.1 Invoice or CreditNote, following the CEN UBL syntax
 * binding (EN 16931-3-2). It is the inverse of UblReader: reading the output gives back an equal
 * invoice.
 *
 * Elements appear in the order of the UBL 2.1 schema sequence of the root document. Amounts are
 * written as the model holds them, in the invoice currency (BT-5), except the VAT total in
 * accounting currency (BT-111), which carries BT-6.
 *
 * Where an element is mandatory but the model holds no value for it, a placeholder is written:
 * - cbc:CustomizationID: SPECIFICATION_IDENTIFIER when the invoice has no process control. UBL
 *   2.1 declares the element optional, but EN 16931 makes BT-24 mandatory.
 * - cac:OrderReference/cbc:ID: "NA" when only a sales order reference (BT-14) exists, because UBL
 *   makes the order identifier mandatory.
 * - cac:PartyTaxScheme/cac:TaxScheme/cbc:ID: "TAX" for a seller tax registration (BT-32) without
 *   a scheme, because UBL makes cac:TaxScheme mandatory.
 * - cac:CardAccount/cbc:NetworkID: "NA", because UBL makes it mandatory.
 * - cac:PaymentMeans with means code 1 for the due date (BT-9) of a credit note without payment
 *   instructions, because UBL makes cbc:PaymentMeansCode mandatory (see PaymentWriter).
 *
 * UblReader recognises these placeholders (see Placeholder) and does not read them as data.
 *
 * An EXTENDED-CTC-FR invoice (FrenchInvoice with FrenchProfile::ExtendedCtcFr) is written with its
 * French line data and its GROUP and INFORMATION lines, in the French line order (see LineWriter).
 */
final class UblWriter
{
    /**
     * Specification identifier (BT-24) written when the invoice carries no process control: EN
     * 16931 makes BT-24 mandatory, although UBL 2.1 declares cbc:CustomizationID optional.
     */
    public const SPECIFICATION_IDENTIFIER = 'urn:cen.eu:en16931:2017';

    /**
     * @throws InvalidArgumentException when the totals (BG-22), a line net amount (BT-131) or the
     *                                  VAT total (BT-110) of a VAT breakdown are missing, when
     *                                  BT-111 has no VAT accounting currency (BT-6) other than
     *                                  BT-5, when a buyer or payee has more than one identifier,
     *                                  when text cannot be written as XML 1.0, or when the purchase
     *                                  order reference (BT-13) is the order placeholder beside a
     *                                  sales order reference (BT-14); also when a French invoice
     *                                  holds more decimals than the French rules allow (see
     *                                  FrenchGuard), or when an EXTENDED-CTC-FR line holds what
     *                                  UBL cannot carry, such as the line amount with VAT
     *                                  (EXT-FR-FE-184; see LineWriter)
     */
    public function write(Invoice $invoice): string
    {
        if ($invoice->totals === null) {
            throw new InvalidArgumentException('The invoice has no document totals (BG-22); complete it with TotalsCalculator::complete() first.');
        }
        WriterGuard::accountingCurrencyVat($invoice);
        WriterGuard::xmlText($invoice);
        FrenchGuard::check($invoice);
        if ($invoice->purchaseOrderReference === Placeholder::ORDER_ID && $invoice->salesOrderReference !== null) {
            throw new InvalidArgumentException(sprintf(
                "The purchase order reference (BT-13) '%s' beside a sales order reference (BT-14) is the placeholder UBL writes when only BT-14 exists; it would be read back as absent.",
                Placeholder::ORDER_ID,
            ));
        }
        $creditNote = $invoice->type->isCreditNote();
        $out = new UblOutput($creditNote);
        $root = $out->root;
        $parties = new PartyWriter($out);
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        $extended = $french?->profile === FrenchProfile::ExtendedCtcFr ? $french : null;
        $lines = new LineWriter($out, $invoice->currency, $creditNote, $extended === null ? null : $invoice->number);

        $this->header($out, $root, $invoice, $creditNote);
        $lines->period($root, $invoice->delivery?->invoicingPeriod, $invoice->taxPointDateCode);
        $this->references($out, $root, $invoice, $creditNote);

        $creditorIdentifier = $invoice->payment?->directDebit?->creditorIdentifier;
        $parties->party(
            $out->add($out->add($root, 'cac:AccountingSupplierParty'), 'cac:Party'),
            $invoice->seller,
            PartyMapper::SELLER,
            $invoice->payee === null ? $creditorIdentifier : null,
        );
        $parties->party($out->add($out->add($root, 'cac:AccountingCustomerParty'), 'cac:Party'), $invoice->buyer, PartyMapper::BUYER);
        if ($invoice->payee !== null) {
            $parties->party($out->add($root, 'cac:PayeeParty'), $invoice->payee, PartyMapper::PAYEE, $creditorIdentifier);
        }
        if ($invoice->taxRepresentative !== null) {
            $parties->party($out->add($root, 'cac:TaxRepresentativeParty'), $invoice->taxRepresentative, PartyMapper::TAX_REPRESENTATIVE);
        }

        $this->delivery($out, $parties, $root, $invoice->delivery);
        (new PaymentWriter($out))->payment($root, $invoice->payment, $creditNote ? $invoice->dueDate : null);
        if ($invoice->paymentTerms !== null) {
            $out->text($out->add($root, 'cac:PaymentTerms'), 'cbc:Note', $invoice->paymentTerms);
        }
        $this->allowancesAndCharges($out, $lines, $root, $invoice);
        $this->taxTotals($out, $root, $invoice);
        $this->monetaryTotal($out, $root, $invoice->totals, $invoice->currency);

        foreach ($extended?->linesInDocumentOrder($invoice) ?? $invoice->lines as $line) {
            if ($line instanceof InformationalLine) {
                $lines->informational($root, $line);
            } else {
                $lines->line($root, $line);
            }
        }

        return $out->xml();
    }

    private function header(UblOutput $out, DOMElement $root, Invoice $invoice, bool $creditNote): void
    {
        $out->text($root, 'cbc:CustomizationID', $invoice->processControl->specificationIdentifier ?? self::SPECIFICATION_IDENTIFIER);
        $out->text($root, 'cbc:ProfileID', $invoice->processControl?->businessProcessType);
        $out->text($root, 'cbc:ID', $invoice->number);
        $out->date($root, 'cbc:IssueDate', $invoice->issueDate);
        if ($creditNote) {
            $out->date($root, 'cbc:TaxPointDate', $invoice->taxPointDate);
            $out->text($root, 'cbc:CreditNoteTypeCode', $invoice->type->value);
        } else {
            $out->date($root, 'cbc:DueDate', $invoice->dueDate);
            $out->text($root, 'cbc:InvoiceTypeCode', $invoice->type->value);
        }
        foreach ($invoice->notes as $note) {
            $out->text($root, 'cbc:Note', $note->subjectCode === null ? $note->text : '#'.$note->subjectCode->value.'#'.$note->text);
        }
        if (! $creditNote) {
            $out->date($root, 'cbc:TaxPointDate', $invoice->taxPointDate);
        }
        $out->text($root, 'cbc:DocumentCurrencyCode', $invoice->currency->value);
        $out->text($root, 'cbc:TaxCurrencyCode', $invoice->taxCurrency?->value);
        $out->text($root, 'cbc:AccountingCost', $invoice->buyerAccountingReference);
        $out->text($root, 'cbc:BuyerReference', $invoice->buyerReference);
    }

    /**
     * Order, billing and document references (BT-11 to BT-18, BG-3, BG-24). A credit note has no
     * cac:ProjectReference and carries BT-11 as a document reference with type code 50.
     */
    private function references(UblOutput $out, DOMElement $root, Invoice $invoice, bool $creditNote): void
    {
        if ($invoice->purchaseOrderReference !== null || $invoice->salesOrderReference !== null) {
            $order = $out->add($root, 'cac:OrderReference');
            $out->text($order, 'cbc:ID', $invoice->purchaseOrderReference ?? Placeholder::ORDER_ID);
            $out->text($order, 'cbc:SalesOrderID', $invoice->salesOrderReference);
        }
        foreach ($invoice->precedingInvoices as $preceding) {
            $reference = $out->add($out->add($root, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
            $out->text($reference, 'cbc:ID', $preceding->number);
            $out->date($reference, 'cbc:IssueDate', $preceding->issueDate);
        }
        $this->documentReference($out, $root, 'cac:DespatchDocumentReference', $invoice->despatchAdviceReference);
        $this->documentReference($out, $root, 'cac:ReceiptDocumentReference', $invoice->receivingAdviceReference);
        if (! $creditNote) {
            $this->documentReference($out, $root, 'cac:OriginatorDocumentReference', $invoice->tenderOrLotReference);
        }
        $this->documentReference($out, $root, 'cac:ContractDocumentReference', $invoice->contractReference);

        if ($invoice->invoicedObjectIdentifier !== null) {
            $reference = $out->add($root, 'cac:AdditionalDocumentReference');
            $out->identifier($reference, 'cbc:ID', $invoice->invoicedObjectIdentifier);
            $out->text($reference, 'cbc:DocumentTypeCode', '130');
        }
        if ($creditNote && $invoice->projectReference !== null) {
            $reference = $out->add($root, 'cac:AdditionalDocumentReference');
            $out->text($reference, 'cbc:ID', $invoice->projectReference);
            $out->text($reference, 'cbc:DocumentTypeCode', '50');
        }
        foreach ($invoice->additionalDocuments as $document) {
            $reference = $out->add($root, 'cac:AdditionalDocumentReference');
            $out->text($reference, 'cbc:ID', $document->reference);
            $out->text($reference, 'cbc:DocumentDescription', $document->description);
            if ($document->attachment !== null || $document->externalLocation !== null) {
                $attachment = $out->add($reference, 'cac:Attachment');
                if ($document->attachment !== null) {
                    $out->text($attachment, 'cbc:EmbeddedDocumentBinaryObject', base64_encode($document->attachment->content), [
                        'mimeCode' => $document->attachment->mimeCode->value,
                        'filename' => $document->attachment->filename,
                    ]);
                }
                if ($document->externalLocation !== null) {
                    $out->text($out->add($attachment, 'cac:ExternalReference'), 'cbc:URI', $document->externalLocation);
                }
            }
        }

        if ($creditNote) {
            $this->documentReference($out, $root, 'cac:OriginatorDocumentReference', $invoice->tenderOrLotReference);
        } else {
            $this->documentReference($out, $root, 'cac:ProjectReference', $invoice->projectReference);
        }
    }

    private function documentReference(UblOutput $out, DOMElement $root, string $name, ?string $identifier): void
    {
        if ($identifier !== null) {
            $out->text($out->add($root, $name), 'cbc:ID', $identifier);
        }
    }

    /**
     * BG-13. The invoicing period (BG-14), which the model keeps in the delivery, is written as
     * the document level cac:InvoicePeriod instead.
     */
    private function delivery(UblOutput $out, PartyWriter $parties, DOMElement $root, ?Delivery $delivery): void
    {
        if ($delivery === null
            || ($delivery->partyName === null && $delivery->locationIdentifier === null && $delivery->actualDeliveryDate === null && $delivery->address === null)) {
            return;
        }
        $element = $out->add($root, 'cac:Delivery');
        $out->date($element, 'cbc:ActualDeliveryDate', $delivery->actualDeliveryDate);
        if ($delivery->locationIdentifier !== null || $delivery->address !== null) {
            $location = $out->add($element, 'cac:DeliveryLocation');
            $out->identifier($location, 'cbc:ID', $delivery->locationIdentifier);
            $parties->address($location, 'cac:Address', $delivery->address);
        }
        if ($delivery->partyName !== null) {
            $out->text($out->add($out->add($element, 'cac:DeliveryParty'), 'cac:PartyName'), 'cbc:Name', $delivery->partyName);
        }
    }

    private function allowancesAndCharges(UblOutput $out, LineWriter $lines, DOMElement $root, Invoice $invoice): void
    {
        foreach ([...$invoice->allowances, ...$invoice->charges] as $entry) {
            $element = $lines->allowanceCharge(
                $root,
                $entry instanceof DocumentCharge,
                $entry->amount,
                $entry->baseAmount,
                $entry->percentage,
                $entry->reason,
                $entry->reasonCode,
            );
            $this->taxCategory($out, $element, 'cac:TaxCategory', $entry);
        }
    }

    private function taxCategory(UblOutput $out, DOMElement $parent, string $name, DocumentAllowance|DocumentCharge $entry): void
    {
        $category = $out->add($parent, $name);
        $out->text($category, 'cbc:ID', $entry->vatCategory->value);
        $out->decimal($category, 'cbc:Percent', $entry->vatRate);
        $out->taxScheme($category, 'VAT');
    }

    /**
     * BT-110 with the VAT breakdown (BG-23) in the invoice currency, then BT-111 in the VAT
     * accounting currency (BT-6).
     */
    private function taxTotals(UblOutput $out, DOMElement $root, Invoice $invoice): void
    {
        $totalVat = $invoice->totals?->totalVat;
        if ($totalVat === null && $invoice->vatBreakdown !== []) {
            throw new InvalidArgumentException('The invoice has a VAT breakdown (BG-23) but no invoice total VAT amount (BT-110).');
        }

        if ($totalVat !== null) {
            $taxTotal = $out->add($root, 'cac:TaxTotal');
            $out->amount($taxTotal, 'cbc:TaxAmount', $totalVat, $invoice->currency);
            foreach ($invoice->vatBreakdown as $entry) {
                $subtotal = $out->add($taxTotal, 'cac:TaxSubtotal');
                $out->amount($subtotal, 'cbc:TaxableAmount', $entry->taxableAmount, $invoice->currency);
                $out->amount($subtotal, 'cbc:TaxAmount', $entry->taxAmount, $invoice->currency);
                $category = $out->add($subtotal, 'cac:TaxCategory');
                $out->text($category, 'cbc:ID', $entry->category->value);
                $out->decimal($category, 'cbc:Percent', $entry->rate);
                $out->text($category, 'cbc:TaxExemptionReasonCode', $entry->exemptionReasonCode?->value);
                $out->text($category, 'cbc:TaxExemptionReason', $entry->exemptionReason);
                $out->taxScheme($category, 'VAT');
            }
        }

        $accountingVat = $invoice->totals?->totalVatInAccountingCurrency;
        if ($accountingVat !== null) {
            $out->amount($out->add($root, 'cac:TaxTotal'), 'cbc:TaxAmount', $accountingVat, $invoice->taxCurrency ?? $invoice->currency);
        }
    }

    private function monetaryTotal(UblOutput $out, DOMElement $root, DocumentTotals $totals, CurrencyCode $currency): void
    {
        $element = $out->add($root, 'cac:LegalMonetaryTotal');
        $out->amount($element, 'cbc:LineExtensionAmount', $totals->sumOfLineNetAmounts, $currency);
        $out->amount($element, 'cbc:TaxExclusiveAmount', $totals->totalWithoutVat, $currency);
        $out->amount($element, 'cbc:TaxInclusiveAmount', $totals->totalWithVat, $currency);
        $out->amount($element, 'cbc:AllowanceTotalAmount', $totals->sumOfAllowances, $currency);
        $out->amount($element, 'cbc:ChargeTotalAmount', $totals->sumOfCharges, $currency);
        $out->amount($element, 'cbc:PrepaidAmount', $totals->paidAmount, $currency);
        $out->amount($element, 'cbc:PayableRoundingAmount', $totals->roundingAmount, $currency);
        $out->amount($element, 'cbc:PayableAmount', $totals->amountDue, $currency);
    }
}

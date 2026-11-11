<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\WriterGuard;
use InvalidArgumentException;

/**
 * Writes the EN 16931 model as a UN/CEFACT Cross Industry Invoice (D16B), following the CEN CII
 * syntax binding (EN 16931-3-3). It is the inverse of CiiReader: reading the output gives back an
 * equal invoice.
 *
 * Elements appear in the order of the D16B schema sequences. Amounts are written as the model
 * holds them, without a currency, except the two ram:TaxTotalAmount elements: the VAT total
 * (BT-110) carries the invoice currency (BT-5), the VAT total in accounting currency (BT-111)
 * carries BT-6. Invoices and credit notes share the root element; the type code (BT-3) tells
 * them apart.
 *
 * Where the binding needs a value the model does not hold, the writer supplies it:
 * - ram:GuidelineSpecifiedDocumentContextParameter/ram:ID: SPECIFICATION_IDENTIFIER when the
 *   invoice has no process control, because EN 16931 makes BT-24 mandatory.
 * - ram:SpecifiedProcuringProject/ram:Name: PROJECT_NAME, because the schema requires a name
 *   beside the project reference (BT-11).
 * - ram:ApplicableHeaderTradeDelivery: always present, because the schema requires it.
 * - The price discount (BT-147) without a gross price (BT-148) is written with the gross price it
 *   implies (see LineWriter).
 *
 * The VAT point date (BT-7) and its code (BT-8) are written in the first VAT breakdown entry,
 * where CiiReader reads them. Unlike UBL, CII keeps the VAT breakdown apart from the VAT total, so
 * a breakdown without BT-110 is written as it is (CEN CII example 7 has one).
 */
final class CiiWriter
{
    /**
     * Specification identifier (BT-24) written when the invoice carries no process control.
     */
    public const SPECIFICATION_IDENTIFIER = 'urn:cen.eu:en16931:2017';

    /**
     * Name written beside the project reference (BT-11): the schema requires it, EN 16931 does not
     * model it. It is the value the CEN examples use.
     */
    public const PROJECT_NAME = 'Project reference';

    /**
     * VAT point date codes (BT-8): the model holds UNTDID 2005 codes as UBL does, CII uses
     * UNTDID 2475.
     */
    private const TAX_POINT_DATE_CODES = ['3' => '5', '35' => '29', '432' => '72'];

    /** Document type code of a tender or lot reference (BT-17). */
    private const TENDER_OR_LOT_TYPE = '50';

    /** Document type code of a supporting document (BG-24). */
    private const SUPPORTING_DOCUMENT_TYPE = '916';

    /**
     * @throws InvalidArgumentException when the totals (BG-22) or a line net amount (BT-131) are
     *                                  missing, or when the invoice holds what CII D16B cannot
     *                                  carry: more than one preceding invoice (BG-3), a VAT point
     *                                  date code (BT-8) outside the three the binding maps, or a
     *                                  VAT point date without a VAT breakdown; also when BT-111 has
     *                                  no VAT accounting currency (BT-6) other than BT-5, when a
     *                                  buyer or payee has more than one identifier, or when text
     *                                  cannot be written as XML 1.0
     */
    public function write(Invoice $invoice): string
    {
        $totals = $invoice->totals
            ?? throw new InvalidArgumentException('The invoice has no document totals (BG-22); complete it with TotalsCalculator::complete() first.');
        WriterGuard::accountingCurrencyVat($invoice);
        if (count($invoice->precedingInvoices) > 1) {
            throw new InvalidArgumentException(sprintf(
                'CII D16B carries one preceding invoice reference (BG-3); the invoice has %d.',
                count($invoice->precedingInvoices),
            ));
        }

        $out = new CiiOutput;
        $root = $out->root;
        $parties = new PartyWriter($out);
        $lines = new LineWriter($out);

        $this->context($out, $root, $invoice);
        $this->exchangedDocument($out, $root, $invoice);

        $transaction = $out->add($root, 'rsm:SupplyChainTradeTransaction');
        foreach ($invoice->lines as $line) {
            $lines->line($transaction, $line);
        }
        $this->agreement($out, $parties, $transaction, $invoice);
        $this->delivery($out, $parties, $transaction, $invoice);
        $this->settlement($out, $parties, $lines, $transaction, $invoice, $totals);

        return $out->xml();
    }

    private function context(CiiOutput $out, DOMElement $root, Invoice $invoice): void
    {
        $context = $out->add($root, 'rsm:ExchangedDocumentContext');
        $out->wrapped($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter', 'ram:ID', $invoice->processControl?->businessProcessType);
        $out->wrapped(
            $context,
            'ram:GuidelineSpecifiedDocumentContextParameter',
            'ram:ID',
            $invoice->processControl->specificationIdentifier ?? self::SPECIFICATION_IDENTIFIER,
        );
    }

    /**
     * BT-1, BT-3, BT-2 and the notes (BG-1) with their subject code (BT-21) in its own element.
     */
    private function exchangedDocument(CiiOutput $out, DOMElement $root, Invoice $invoice): void
    {
        $document = $out->add($root, 'rsm:ExchangedDocument');
        $out->text($document, 'ram:ID', $invoice->number);
        $out->text($document, 'ram:TypeCode', $invoice->type->value);
        $out->date($document, 'ram:IssueDateTime', $invoice->issueDate);
        foreach ($invoice->notes as $note) {
            $element = $out->add($document, 'ram:IncludedNote');
            $out->text($element, 'ram:Content', $note->text);
            $out->text($element, 'ram:SubjectCode', $note->subjectCode?->value);
        }
    }

    private function agreement(CiiOutput $out, PartyWriter $parties, DOMElement $transaction, Invoice $invoice): void
    {
        $agreement = $out->add($transaction, 'ram:ApplicableHeaderTradeAgreement');
        $out->text($agreement, 'ram:BuyerReference', $invoice->buyerReference);
        $parties->party($out->add($agreement, 'ram:SellerTradeParty'), $invoice->seller, PartyMapper::SELLER);
        $parties->party($out->add($agreement, 'ram:BuyerTradeParty'), $invoice->buyer, PartyMapper::BUYER);
        if ($invoice->taxRepresentative !== null) {
            $parties->party($out->add($agreement, 'ram:SellerTaxRepresentativeTradeParty'), $invoice->taxRepresentative, PartyMapper::TAX_REPRESENTATIVE);
        }
        $out->wrapped($agreement, 'ram:SellerOrderReferencedDocument', 'ram:IssuerAssignedID', $invoice->salesOrderReference);
        $out->wrapped($agreement, 'ram:BuyerOrderReferencedDocument', 'ram:IssuerAssignedID', $invoice->purchaseOrderReference);
        $out->wrapped($agreement, 'ram:ContractReferencedDocument', 'ram:IssuerAssignedID', $invoice->contractReference);

        foreach ($invoice->additionalDocuments as $document) {
            $reference = $out->add($agreement, 'ram:AdditionalReferencedDocument');
            $out->text($reference, 'ram:IssuerAssignedID', $document->reference);
            $out->text($reference, 'ram:URIID', $document->externalLocation);
            $out->text($reference, 'ram:TypeCode', self::SUPPORTING_DOCUMENT_TYPE);
            $out->text($reference, 'ram:Name', $document->description);
            if ($document->attachment !== null) {
                $out->text($reference, 'ram:AttachmentBinaryObject', base64_encode($document->attachment->content), [
                    'mimeCode' => $document->attachment->mimeCode->value,
                    'filename' => $document->attachment->filename,
                ]);
            }
        }
        if ($invoice->tenderOrLotReference !== null) {
            $reference = $out->add($agreement, 'ram:AdditionalReferencedDocument');
            $out->text($reference, 'ram:IssuerAssignedID', $invoice->tenderOrLotReference);
            $out->text($reference, 'ram:TypeCode', self::TENDER_OR_LOT_TYPE);
        }
        if ($invoice->invoicedObjectIdentifier !== null) {
            $reference = $out->add($agreement, 'ram:AdditionalReferencedDocument');
            $out->text($reference, 'ram:IssuerAssignedID', $invoice->invoicedObjectIdentifier->value);
            $out->text($reference, 'ram:TypeCode', LineWriter::OBJECT_IDENTIFIER_TYPE);
            $out->text($reference, 'ram:ReferenceTypeCode', $invoice->invoicedObjectIdentifier->scheme);
        }

        if ($invoice->projectReference !== null) {
            $project = $out->add($agreement, 'ram:SpecifiedProcuringProject');
            $out->text($project, 'ram:ID', $invoice->projectReference);
            $out->text($project, 'ram:Name', self::PROJECT_NAME);
        }
    }

    /**
     * BG-13 with the despatch and receiving advice references (BT-16, BT-15). The invoicing period
     * (BG-14), which the model keeps in the delivery, is written at settlement level.
     */
    private function delivery(CiiOutput $out, PartyWriter $parties, DOMElement $transaction, Invoice $invoice): void
    {
        $element = $out->add($transaction, 'ram:ApplicableHeaderTradeDelivery');
        $delivery = $invoice->delivery;
        if ($delivery !== null && ($delivery->partyName !== null || $delivery->locationIdentifier !== null || $delivery->address !== null)) {
            $shipTo = $out->add($element, 'ram:ShipToTradeParty');
            $parties->identifiers($shipTo, $delivery->locationIdentifier === null ? [] : [$delivery->locationIdentifier]);
            $out->text($shipTo, 'ram:Name', $delivery->partyName);
            $parties->address($shipTo, $delivery->address);
        }
        if ($delivery?->actualDeliveryDate !== null) {
            $out->date($out->add($element, 'ram:ActualDeliverySupplyChainEvent'), 'ram:OccurrenceDateTime', $delivery->actualDeliveryDate);
        }
        $out->wrapped($element, 'ram:DespatchAdviceReferencedDocument', 'ram:IssuerAssignedID', $invoice->despatchAdviceReference);
        $out->wrapped($element, 'ram:ReceivingAdviceReferencedDocument', 'ram:IssuerAssignedID', $invoice->receivingAdviceReference);
    }

    private function settlement(CiiOutput $out, PartyWriter $parties, LineWriter $lines, DOMElement $transaction, Invoice $invoice, DocumentTotals $totals): void
    {
        $settlement = $out->add($transaction, 'ram:ApplicableHeaderTradeSettlement');
        $payment = $invoice->payment;
        $out->text($settlement, 'ram:CreditorReferenceID', $payment?->directDebit?->creditorIdentifier);
        $out->text($settlement, 'ram:PaymentReference', $payment?->remittanceInformation);
        $out->text($settlement, 'ram:TaxCurrencyCode', $invoice->taxCurrency?->value);
        $out->text($settlement, 'ram:InvoiceCurrencyCode', $invoice->currency->value);
        if ($invoice->payee !== null) {
            $parties->party($out->add($settlement, 'ram:PayeeTradeParty'), $invoice->payee, PartyMapper::PAYEE);
        }
        if ($payment !== null) {
            (new PaymentWriter($out))->means($settlement, $payment);
        }

        $this->vatBreakdown($out, $settlement, $invoice);
        $lines->period($settlement, $invoice->delivery?->invoicingPeriod);

        foreach ([...$invoice->allowances, ...$invoice->charges] as $entry) {
            $element = $lines->allowanceCharge(
                $settlement,
                $entry instanceof DocumentCharge,
                $entry->amount,
                $entry->baseAmount,
                $entry->percentage,
                $entry->reason,
                $entry->reasonCode,
            );
            $lines->tradeTax($element, 'ram:CategoryTradeTax', $entry->vatCategory, $entry->vatRate);
        }

        $mandate = $payment?->directDebit?->mandateReference;
        if ($invoice->paymentTerms !== null || $invoice->dueDate !== null || $mandate !== null) {
            $terms = $out->add($settlement, 'ram:SpecifiedTradePaymentTerms');
            $out->text($terms, 'ram:Description', $invoice->paymentTerms);
            $out->date($terms, 'ram:DueDateDateTime', $invoice->dueDate);
            $out->text($terms, 'ram:DirectDebitMandateID', $mandate);
        }

        $this->monetarySummation($out, $settlement, $invoice, $totals);

        foreach ($invoice->precedingInvoices as $preceding) {
            $reference = $out->add($settlement, 'ram:InvoiceReferencedDocument');
            $out->text($reference, 'ram:IssuerAssignedID', $preceding->number);
            $out->date($reference, 'ram:FormattedIssueDateTime', $preceding->issueDate, 'qdt:DateTimeString');
        }
        $out->wrapped($settlement, 'ram:ReceivableSpecifiedTradeAccountingAccount', 'ram:ID', $invoice->buyerAccountingReference);
    }

    /**
     * BG-23, one ram:ApplicableTradeTax per entry; the first also carries BT-7 and BT-8.
     */
    private function vatBreakdown(CiiOutput $out, DOMElement $settlement, Invoice $invoice): void
    {
        if ($invoice->vatBreakdown === [] && ($invoice->taxPointDate !== null || $invoice->taxPointDateCode !== null)) {
            throw new InvalidArgumentException('CII carries the VAT point date (BT-7) and its code (BT-8) in the VAT breakdown (BG-23), which the invoice does not have.');
        }
        $dueDateTypeCode = null;
        if ($invoice->taxPointDateCode !== null) {
            $dueDateTypeCode = self::TAX_POINT_DATE_CODES[$invoice->taxPointDateCode] ?? throw new InvalidArgumentException(sprintf(
                "The VAT point date code (BT-8) '%s' has no CII equivalent; expected one of %s.",
                $invoice->taxPointDateCode,
                implode(', ', array_keys(self::TAX_POINT_DATE_CODES)),
            ));
        }

        foreach ($invoice->vatBreakdown as $index => $entry) {
            $tax = $out->add($settlement, 'ram:ApplicableTradeTax');
            $out->decimal($tax, 'ram:CalculatedAmount', $entry->taxAmount);
            $out->text($tax, 'ram:TypeCode', LineWriter::VAT);
            $out->text($tax, 'ram:ExemptionReason', $entry->exemptionReason);
            $out->decimal($tax, 'ram:BasisAmount', $entry->taxableAmount);
            $out->text($tax, 'ram:CategoryCode', $entry->category->value);
            $out->text($tax, 'ram:ExemptionReasonCode', $entry->exemptionReasonCode?->value);
            if ($index === 0) {
                $out->date($tax, 'ram:TaxPointDate', $invoice->taxPointDate, 'udt:DateString');
                $out->text($tax, 'ram:DueDateTypeCode', $dueDateTypeCode);
            }
            $out->decimal($tax, 'ram:RateApplicablePercent', $entry->rate);
        }
    }

    private function monetarySummation(CiiOutput $out, DOMElement $settlement, Invoice $invoice, DocumentTotals $totals): void
    {
        $element = $out->add($settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $out->decimal($element, 'ram:LineTotalAmount', $totals->sumOfLineNetAmounts);
        $out->decimal($element, 'ram:ChargeTotalAmount', $totals->sumOfCharges);
        $out->decimal($element, 'ram:AllowanceTotalAmount', $totals->sumOfAllowances);
        $out->decimal($element, 'ram:TaxBasisTotalAmount', $totals->totalWithoutVat);
        $out->decimal($element, 'ram:TaxTotalAmount', $totals->totalVat, ['currencyID' => $invoice->currency->value]);
        $out->decimal($element, 'ram:TaxTotalAmount', $totals->totalVatInAccountingCurrency, ['currencyID' => ($invoice->taxCurrency ?? $invoice->currency)->value]);
        $out->decimal($element, 'ram:RoundingAmount', $totals->roundingAmount);
        $out->decimal($element, 'ram:GrandTotalAmount', $totals->totalWithVat);
        $out->decimal($element, 'ram:TotalPrepaidAmount', $totals->paidAmount);
        $out->decimal($element, 'ram:DuePayableAmount', $totals->amountDue);
    }
}

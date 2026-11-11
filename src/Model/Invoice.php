<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use InvalidArgumentException;

/**
 * An EN 16931-1:2017+A1 invoice or credit note.
 *
 * Every business term and group of the semantic model is represented. The constructor checks
 * structure only (a non-empty number and at least one line); business rules are the validator's
 * job. Dates carry date-only semantics: the time of day and time zone of a DateTimeImmutable are
 * ignored by writers, which emit the Y-m-d calendar date as given.
 */
final readonly class Invoice
{
    /**
     * @param  list<InvoiceLine>  $lines  Invoice lines (BG-25).
     * @param  list<Note>  $notes  Invoice notes (BG-1).
     * @param  list<PrecedingInvoiceReference>  $precedingInvoices  Preceding invoice references (BG-3).
     * @param  list<DocumentAllowance>  $allowances  Document level allowances (BG-20).
     * @param  list<DocumentCharge>  $charges  Document level charges (BG-21).
     * @param  list<VatBreakdown>  $vatBreakdown  VAT breakdown (BG-23).
     * @param  list<AdditionalDocument>  $additionalDocuments  Additional supporting documents (BG-24).
     */
    public function __construct(
        /** Invoice number (BT-1). */
        public string $number,
        /** Invoice issue date (BT-2). */
        public DateTimeImmutable $issueDate,
        /** Invoice type code (BT-3). */
        public InvoiceTypeCode $type,
        /** Invoice currency code (BT-5). */
        public CurrencyCode $currency,
        /** Seller (BG-4). */
        public Party $seller,
        /** Buyer (BG-7). */
        public Party $buyer,
        public array $lines,
        /** VAT accounting currency code (BT-6). */
        public ?CurrencyCode $taxCurrency = null,
        /** Value added tax point date (BT-7). */
        public ?DateTimeImmutable $taxPointDate = null,
        /** Value added tax point date code (BT-8), UNTDID 2005 subset: "3", "35" or "432". */
        public ?string $taxPointDateCode = null,
        /** Payment due date (BT-9). */
        public ?DateTimeImmutable $dueDate = null,
        /** Buyer reference (BT-10). */
        public ?string $buyerReference = null,
        /** Project reference (BT-11). */
        public ?string $projectReference = null,
        /** Contract reference (BT-12). */
        public ?string $contractReference = null,
        /** Purchase order reference (BT-13). */
        public ?string $purchaseOrderReference = null,
        /** Sales order reference (BT-14). */
        public ?string $salesOrderReference = null,
        /** Receiving advice reference (BT-15). */
        public ?string $receivingAdviceReference = null,
        /** Despatch advice reference (BT-16). */
        public ?string $despatchAdviceReference = null,
        /** Tender or lot reference (BT-17). */
        public ?string $tenderOrLotReference = null,
        /** Invoiced object identifier (BT-18) with scheme (BT-18-1). */
        public ?Identifier $invoicedObjectIdentifier = null,
        /** Buyer accounting reference (BT-19). */
        public ?string $buyerAccountingReference = null,
        /** Payment terms (BT-20). */
        public ?string $paymentTerms = null,
        public array $notes = [],
        /** Process control (BG-2). */
        public ?ProcessControl $processControl = null,
        public array $precedingInvoices = [],
        /** Payee (BG-10). */
        public ?Party $payee = null,
        /** Seller tax representative party (BG-11). */
        public ?Party $taxRepresentative = null,
        /** Delivery information (BG-13), including the invoicing period (BG-14). */
        public ?Delivery $delivery = null,
        /** Payment instructions (BG-16). */
        public ?PaymentInstructions $payment = null,
        public array $allowances = [],
        public array $charges = [],
        /** Document totals (BG-22); null until the calculator fills them. */
        public ?DocumentTotals $totals = null,
        public array $vatBreakdown = [],
        public array $additionalDocuments = [],
        private ?Extensions $extensions = null,
    ) {
        if (trim($number) === '') {
            throw new InvalidArgumentException('Invoice number (BT-1) must not be empty.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('An invoice needs at least one line (BG-25).');
        }
    }

    /**
     * Syntax or national data attached to this invoice beyond EN 16931.
     */
    public function extensions(): Extensions
    {
        return $this->extensions ?? Extensions::none();
    }

    public function withExtension(object $extension): self
    {
        return $this->copy(['extensions' => $this->extensions()->with($extension)]);
    }

    public function withExtensions(Extensions $extensions): self
    {
        return $this->copy(['extensions' => $extensions]);
    }

    public function withNumber(string $number): self
    {
        return $this->copy(['number' => $number]);
    }

    public function withIssueDate(DateTimeImmutable $issueDate): self
    {
        return $this->copy(['issueDate' => $issueDate]);
    }

    public function withType(InvoiceTypeCode $type): self
    {
        return $this->copy(['type' => $type]);
    }

    public function withCurrency(CurrencyCode $currency): self
    {
        return $this->copy(['currency' => $currency]);
    }

    public function withSeller(Party $seller): self
    {
        return $this->copy(['seller' => $seller]);
    }

    public function withBuyer(Party $buyer): self
    {
        return $this->copy(['buyer' => $buyer]);
    }

    /**
     * @param  list<InvoiceLine>  $lines
     */
    public function withLines(array $lines): self
    {
        return $this->copy(['lines' => $lines]);
    }

    public function withLine(InvoiceLine $line): self
    {
        return $this->copy(['lines' => [...$this->lines, $line]]);
    }

    public function withTaxCurrency(?CurrencyCode $taxCurrency): self
    {
        return $this->copy(['taxCurrency' => $taxCurrency]);
    }

    public function withTaxPointDate(?DateTimeImmutable $taxPointDate): self
    {
        return $this->copy(['taxPointDate' => $taxPointDate]);
    }

    public function withTaxPointDateCode(?string $taxPointDateCode): self
    {
        return $this->copy(['taxPointDateCode' => $taxPointDateCode]);
    }

    public function withDueDate(?DateTimeImmutable $dueDate): self
    {
        return $this->copy(['dueDate' => $dueDate]);
    }

    public function withBuyerReference(?string $buyerReference): self
    {
        return $this->copy(['buyerReference' => $buyerReference]);
    }

    public function withProjectReference(?string $projectReference): self
    {
        return $this->copy(['projectReference' => $projectReference]);
    }

    public function withContractReference(?string $contractReference): self
    {
        return $this->copy(['contractReference' => $contractReference]);
    }

    public function withPurchaseOrderReference(?string $purchaseOrderReference): self
    {
        return $this->copy(['purchaseOrderReference' => $purchaseOrderReference]);
    }

    public function withSalesOrderReference(?string $salesOrderReference): self
    {
        return $this->copy(['salesOrderReference' => $salesOrderReference]);
    }

    public function withReceivingAdviceReference(?string $receivingAdviceReference): self
    {
        return $this->copy(['receivingAdviceReference' => $receivingAdviceReference]);
    }

    public function withDespatchAdviceReference(?string $despatchAdviceReference): self
    {
        return $this->copy(['despatchAdviceReference' => $despatchAdviceReference]);
    }

    public function withTenderOrLotReference(?string $tenderOrLotReference): self
    {
        return $this->copy(['tenderOrLotReference' => $tenderOrLotReference]);
    }

    public function withInvoicedObjectIdentifier(?Identifier $invoicedObjectIdentifier): self
    {
        return $this->copy(['invoicedObjectIdentifier' => $invoicedObjectIdentifier]);
    }

    public function withBuyerAccountingReference(?string $buyerAccountingReference): self
    {
        return $this->copy(['buyerAccountingReference' => $buyerAccountingReference]);
    }

    public function withPaymentTerms(?string $paymentTerms): self
    {
        return $this->copy(['paymentTerms' => $paymentTerms]);
    }

    public function withNote(Note $note): self
    {
        return $this->copy(['notes' => [...$this->notes, $note]]);
    }

    /**
     * @param  list<Note>  $notes
     */
    public function withNotes(array $notes): self
    {
        return $this->copy(['notes' => $notes]);
    }

    public function withProcessControl(?ProcessControl $processControl): self
    {
        return $this->copy(['processControl' => $processControl]);
    }

    public function withPrecedingInvoice(PrecedingInvoiceReference $precedingInvoice): self
    {
        return $this->copy(['precedingInvoices' => [...$this->precedingInvoices, $precedingInvoice]]);
    }

    /**
     * @param  list<PrecedingInvoiceReference>  $precedingInvoices
     */
    public function withPrecedingInvoices(array $precedingInvoices): self
    {
        return $this->copy(['precedingInvoices' => $precedingInvoices]);
    }

    public function withPayee(?Party $payee): self
    {
        return $this->copy(['payee' => $payee]);
    }

    public function withTaxRepresentative(?Party $taxRepresentative): self
    {
        return $this->copy(['taxRepresentative' => $taxRepresentative]);
    }

    public function withDelivery(?Delivery $delivery): self
    {
        return $this->copy(['delivery' => $delivery]);
    }

    public function withPayment(?PaymentInstructions $payment): self
    {
        return $this->copy(['payment' => $payment]);
    }

    public function withAllowance(DocumentAllowance $allowance): self
    {
        return $this->copy(['allowances' => [...$this->allowances, $allowance]]);
    }

    /**
     * @param  list<DocumentAllowance>  $allowances
     */
    public function withAllowances(array $allowances): self
    {
        return $this->copy(['allowances' => $allowances]);
    }

    public function withCharge(DocumentCharge $charge): self
    {
        return $this->copy(['charges' => [...$this->charges, $charge]]);
    }

    /**
     * @param  list<DocumentCharge>  $charges
     */
    public function withCharges(array $charges): self
    {
        return $this->copy(['charges' => $charges]);
    }

    public function withTotals(?DocumentTotals $totals): self
    {
        return $this->copy(['totals' => $totals]);
    }

    /**
     * @param  list<VatBreakdown>  $vatBreakdown
     */
    public function withVatBreakdown(array $vatBreakdown): self
    {
        return $this->copy(['vatBreakdown' => $vatBreakdown]);
    }

    public function withAdditionalDocument(AdditionalDocument $document): self
    {
        return $this->copy(['additionalDocuments' => [...$this->additionalDocuments, $document]]);
    }

    /**
     * @param  list<AdditionalDocument>  $additionalDocuments
     */
    public function withAdditionalDocuments(array $additionalDocuments): self
    {
        return $this->copy(['additionalDocuments' => $additionalDocuments]);
    }

    /**
     * @param  array{
     *     number?: string, issueDate?: DateTimeImmutable, type?: InvoiceTypeCode, currency?: CurrencyCode,
     *     seller?: Party, buyer?: Party, lines?: list<InvoiceLine>, taxCurrency?: CurrencyCode|null,
     *     taxPointDate?: DateTimeImmutable|null, taxPointDateCode?: string|null, dueDate?: DateTimeImmutable|null,
     *     buyerReference?: string|null, projectReference?: string|null, contractReference?: string|null,
     *     purchaseOrderReference?: string|null, salesOrderReference?: string|null,
     *     receivingAdviceReference?: string|null, despatchAdviceReference?: string|null,
     *     tenderOrLotReference?: string|null, invoicedObjectIdentifier?: Identifier|null,
     *     buyerAccountingReference?: string|null, paymentTerms?: string|null, notes?: list<Note>,
     *     processControl?: ProcessControl|null, precedingInvoices?: list<PrecedingInvoiceReference>,
     *     payee?: Party|null, taxRepresentative?: Party|null, delivery?: Delivery|null,
     *     payment?: PaymentInstructions|null, allowances?: list<DocumentAllowance>,
     *     charges?: list<DocumentCharge>, totals?: DocumentTotals|null, vatBreakdown?: list<VatBreakdown>,
     *     additionalDocuments?: list<AdditionalDocument>, extensions?: Extensions
     * }  $changes
     */
    private function copy(array $changes): self
    {
        return new self(
            number: $changes['number'] ?? $this->number,
            issueDate: $changes['issueDate'] ?? $this->issueDate,
            type: $changes['type'] ?? $this->type,
            currency: $changes['currency'] ?? $this->currency,
            seller: $changes['seller'] ?? $this->seller,
            buyer: $changes['buyer'] ?? $this->buyer,
            lines: $changes['lines'] ?? $this->lines,
            taxCurrency: array_key_exists('taxCurrency', $changes) ? $changes['taxCurrency'] : $this->taxCurrency,
            taxPointDate: array_key_exists('taxPointDate', $changes) ? $changes['taxPointDate'] : $this->taxPointDate,
            taxPointDateCode: array_key_exists('taxPointDateCode', $changes) ? $changes['taxPointDateCode'] : $this->taxPointDateCode,
            dueDate: array_key_exists('dueDate', $changes) ? $changes['dueDate'] : $this->dueDate,
            buyerReference: array_key_exists('buyerReference', $changes) ? $changes['buyerReference'] : $this->buyerReference,
            projectReference: array_key_exists('projectReference', $changes) ? $changes['projectReference'] : $this->projectReference,
            contractReference: array_key_exists('contractReference', $changes) ? $changes['contractReference'] : $this->contractReference,
            purchaseOrderReference: array_key_exists('purchaseOrderReference', $changes) ? $changes['purchaseOrderReference'] : $this->purchaseOrderReference,
            salesOrderReference: array_key_exists('salesOrderReference', $changes) ? $changes['salesOrderReference'] : $this->salesOrderReference,
            receivingAdviceReference: array_key_exists('receivingAdviceReference', $changes) ? $changes['receivingAdviceReference'] : $this->receivingAdviceReference,
            despatchAdviceReference: array_key_exists('despatchAdviceReference', $changes) ? $changes['despatchAdviceReference'] : $this->despatchAdviceReference,
            tenderOrLotReference: array_key_exists('tenderOrLotReference', $changes) ? $changes['tenderOrLotReference'] : $this->tenderOrLotReference,
            invoicedObjectIdentifier: array_key_exists('invoicedObjectIdentifier', $changes) ? $changes['invoicedObjectIdentifier'] : $this->invoicedObjectIdentifier,
            buyerAccountingReference: array_key_exists('buyerAccountingReference', $changes) ? $changes['buyerAccountingReference'] : $this->buyerAccountingReference,
            paymentTerms: array_key_exists('paymentTerms', $changes) ? $changes['paymentTerms'] : $this->paymentTerms,
            notes: $changes['notes'] ?? $this->notes,
            processControl: array_key_exists('processControl', $changes) ? $changes['processControl'] : $this->processControl,
            precedingInvoices: $changes['precedingInvoices'] ?? $this->precedingInvoices,
            payee: array_key_exists('payee', $changes) ? $changes['payee'] : $this->payee,
            taxRepresentative: array_key_exists('taxRepresentative', $changes) ? $changes['taxRepresentative'] : $this->taxRepresentative,
            delivery: array_key_exists('delivery', $changes) ? $changes['delivery'] : $this->delivery,
            payment: array_key_exists('payment', $changes) ? $changes['payment'] : $this->payment,
            allowances: $changes['allowances'] ?? $this->allowances,
            charges: $changes['charges'] ?? $this->charges,
            totals: array_key_exists('totals', $changes) ? $changes['totals'] : $this->totals,
            vatBreakdown: $changes['vatBreakdown'] ?? $this->vatBreakdown,
            additionalDocuments: $changes['additionalDocuments'] ?? $this->additionalDocuments,
            extensions: $changes['extensions'] ?? $this->extensions,
        );
    }
}

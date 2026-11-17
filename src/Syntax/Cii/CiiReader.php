<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
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
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
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
 * Reads a UN/CEFACT Cross Industry Invoice (D16B or D22B) into the EN 16931 model, following the
 * CEN CII syntax binding (EN 16931-3-3) as used by the validation artefacts 1.3.17.
 *
 * Invoices and credit notes share the root element; the type code (BT-3) tells them apart.
 *
 * The policies are those of the UBL reader: elements that are not mapped are listed in
 * ReadResult::$ignored instead of being dropped silently; a code that its closed code list
 * rejects leaves the term empty and is reported as "(invalid code '...')"; when that term is
 * mandatory inside an optional group, the group occurrence is dropped and reported. Only data the
 * model cannot do without (BT-1, BT-2, BT-3, BT-5, the seller and buyer names, and per line
 * BT-126, BT-129, BT-130, BT-146, BT-151 and BT-153) makes a document unsupported.
 *
 * A French invoice gets the FrenchInvoice extension (see FrenchInvoice::fromProcessControl). In an
 * EXTENDED-CTC-FR invoice, GROUP and INFORMATION lines become FrenchInvoice::$informationalLines,
 * French line data becomes a FrenchLine extension of the invoice line, and when informational
 * lines exist FrenchInvoice::$lineOrder records the document order of every line.
 */
final class CiiReader
{
    /**
     * VAT point date codes (BT-8): CII uses UNTDID 2475, the model UNTDID 2005 as UBL does.
     */
    private const TAX_POINT_DATE_CODES = ['5' => '3', '29' => '35', '72' => '432'];

    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when it is not a readable CII invoice
     */
    public function read(string $xml): ReadResult
    {
        $d = new CiiDocument(SafeXml::load($xml));
        $parties = new PartyMapper($d);
        $lines = new LineMapper($d);
        $root = $d->root;

        $agreement = $this->required($d, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement');
        $settlement = $this->required($d, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement');
        $delivery = $d->one('rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery');
        if ($delivery !== null) {
            // The schema requires this group even when it holds no business term.
            $d->use($delivery);
        }
        $terms = $d->one('ram:SpecifiedTradePaymentTerms', $settlement);

        $currency = $d->requiredCode(CurrencyCode::class, $d->one('ram:InvoiceCurrencyCode', $settlement), null, 'BT-5', 'ram:InvoiceCurrencyCode', $settlement);
        $d->expectCurrency($currency->value);
        $taxCurrency = $d->code(CurrencyCode::class, $d->one('ram:TaxCurrencyCode', $settlement));
        [$tenderOrLot, $invoicedObject, $additionalDocuments] = $this->documentReferences($d, $agreement);
        [$allowances, $charges] = $this->allowancesAndCharges($d, $settlement);
        [$taxPointDate, $taxPointDateCode] = $this->taxPoint($d, $settlement);
        $processControl = $this->processControl($d);
        $french = FrenchInvoice::fromProcessControl($processControl);
        [$invoiceLines, $informationalLines, $lineOrder] = $this->lines($d, $lines, $french?->profile === FrenchProfile::ExtendedCtcFr);

        $invoice = new Invoice(
            number: $d->requiredText('rsm:ExchangedDocument/ram:ID', $root, 'BT-1'),
            issueDate: $d->requiredDate('rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString', $root, 'BT-2'),
            type: $d->requiredCode(InvoiceTypeCode::class, $d->one('rsm:ExchangedDocument/ram:TypeCode'), null, 'BT-3', 'rsm:ExchangedDocument/ram:TypeCode', $root),
            currency: $currency,
            seller: $parties->party($this->required($d, 'ram:SellerTradeParty', $agreement, 'BG-4'), PartyMapper::SELLER),
            buyer: $parties->party($this->required($d, 'ram:BuyerTradeParty', $agreement, 'BG-7'), PartyMapper::BUYER),
            lines: $invoiceLines,
            taxCurrency: $taxCurrency,
            taxPointDate: $taxPointDate,
            taxPointDateCode: $taxPointDateCode,
            dueDate: $terms === null ? null : $d->date('ram:DueDateDateTime/udt:DateTimeString', $terms),
            buyerReference: $d->text('ram:BuyerReference', $agreement),
            projectReference: $this->projectReference($d, $agreement),
            contractReference: $d->text('ram:ContractReferencedDocument/ram:IssuerAssignedID', $agreement),
            purchaseOrderReference: $d->text('ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID', $agreement),
            salesOrderReference: $d->text('ram:SellerOrderReferencedDocument/ram:IssuerAssignedID', $agreement),
            receivingAdviceReference: $delivery === null ? null : $d->text('ram:ReceivingAdviceReferencedDocument/ram:IssuerAssignedID', $delivery),
            despatchAdviceReference: $delivery === null ? null : $d->text('ram:DespatchAdviceReferencedDocument/ram:IssuerAssignedID', $delivery),
            tenderOrLotReference: $tenderOrLot,
            invoicedObjectIdentifier: $invoicedObject,
            buyerAccountingReference: $d->text('ram:ReceivableSpecifiedTradeAccountingAccount/ram:ID', $settlement),
            paymentTerms: $terms === null ? null : $d->text('ram:Description', $terms),
            notes: $this->notes($d),
            processControl: $processControl,
            precedingInvoices: $this->precedingInvoices($d, $settlement),
            payee: ($payee = $d->one('ram:PayeeTradeParty', $settlement)) === null ? null : $parties->party($payee, PartyMapper::PAYEE),
            taxRepresentative: ($representative = $d->one('ram:SellerTaxRepresentativeTradeParty', $agreement)) === null
                ? null
                : $parties->party($representative, PartyMapper::TAX_REPRESENTATIVE),
            delivery: $this->delivery($d, $parties, $lines, $delivery, $settlement),
            payment: (new PaymentMapper($d))->payment($settlement, $terms),
            allowances: $allowances,
            charges: $charges,
            totals: $this->totals($d, $settlement, $currency, $taxCurrency),
            vatBreakdown: $this->vatBreakdown($d, $settlement),
            additionalDocuments: $additionalDocuments,
        );

        if ($french !== null) {
            $invoice = $invoice->withExtension($informationalLines === []
                ? $french
                : new FrenchInvoice($french->profile, $informationalLines, $lineOrder));
        }

        return new ReadResult(invoice: $invoice, ignored: $d->ignored());
    }

    private function required(CiiDocument $d, string $expression, ?DOMElement $context = null, ?string $term = null): DOMElement
    {
        return $d->one($expression, $context) ?? throw new UnsupportedDocument(
            sprintf('Missing required %s in %s.', $term === null ? $expression : "{$term} ({$expression})", $d->path($context ?? $d->root)),
        );
    }

    /**
     * The invoice lines, the informational lines of an EXTENDED-CTC-FR invoice and the document
     * order of every line identifier.
     *
     * @return array{non-empty-list<InvoiceLine>, list<InformationalLine>, list<string>}
     */
    private function lines(CiiDocument $d, LineMapper $mapper, bool $extended): array
    {
        $invoiceLines = [];
        $informationalLines = [];
        $order = [];
        foreach ($d->all('rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem') as $element) {
            $line = $extended ? $mapper->extendedLine($element) : $mapper->line($element);
            if ($line instanceof InformationalLine) {
                $informationalLines[] = $line;
            } else {
                $invoiceLines[] = $line;
            }
            $order[] = $line->id;
        }
        if ($invoiceLines === []) {
            throw new UnsupportedDocument('The document has no invoice lines (BG-25).');
        }

        return [$invoiceLines, $informationalLines, $order];
    }

    /**
     * BG-1: ram:Content is the note (BT-22), ram:SubjectCode its subject (BT-21).
     *
     * @return list<Note>
     */
    private function notes(CiiDocument $d): array
    {
        $notes = [];
        foreach ($d->all('rsm:ExchangedDocument/ram:IncludedNote') as $note) {
            $text = $d->text('ram:Content', $note);
            if ($text === null) {
                $d->reject($note, 'dropped: no note content');

                continue;
            }
            $notes[] = new Note(text: $text, subjectCode: $d->code(TextSubjectCode::class, $d->one('ram:SubjectCode', $note)));
        }

        return $notes;
    }

    private function processControl(CiiDocument $d): ?ProcessControl
    {
        $context = 'rsm:ExchangedDocumentContext/';
        $specification = $d->text($context.'ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

        return $specification === null ? null : new ProcessControl(
            specificationIdentifier: $specification,
            businessProcessType: $d->text($context.'ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID'),
        );
    }

    /**
     * BT-11. The schema requires a project name, which has no business term; it is consumed with
     * the identifier.
     */
    private function projectReference(CiiDocument $d, DOMElement $agreement): ?string
    {
        $project = $d->one('ram:SpecifiedProcuringProject', $agreement);
        $reference = $project === null ? null : $d->text('ram:ID', $project);
        if ($project !== null && $reference !== null) {
            $d->text('ram:Name', $project);
        }

        return $reference;
    }

    /**
     * @return list<PrecedingInvoiceReference>
     */
    private function precedingInvoices(CiiDocument $d, DOMElement $settlement): array
    {
        $references = [];
        foreach ($d->all('ram:InvoiceReferencedDocument', $settlement) as $reference) {
            $number = $d->text('ram:IssuerAssignedID', $reference);
            if ($number === null) {
                $d->reject($reference, 'dropped: no preceding invoice reference');

                continue;
            }
            $references[] = new PrecedingInvoiceReference(
                number: $number,
                issueDate: $d->date('ram:FormattedIssueDateTime/qdt:DateTimeString', $reference),
            );
        }

        return $references;
    }

    /**
     * ram:AdditionalReferencedDocument by type code: 50 is BT-17, 130 is BT-18 (scheme in
     * ram:ReferenceTypeCode), 916 and anything else BG-24. A type code other than 916 on a
     * supporting document is left unmapped.
     *
     * @return array{?string, ?Identifier, list<AdditionalDocument>}
     */
    private function documentReferences(CiiDocument $d, DOMElement $agreement): array
    {
        $tenderOrLot = null;
        $invoicedObject = null;
        $documents = [];

        foreach ($d->all('ram:AdditionalReferencedDocument', $agreement) as $reference) {
            $type = $d->one('ram:TypeCode', $reference);
            $typeCode = $type === null ? null : trim($type->textContent);
            $id = $d->one('ram:IssuerAssignedID', $reference);

            if ($type !== null && $id !== null && $id->textContent !== '') {
                if ($typeCode === '130' && $invoicedObject === null) {
                    $d->use($type);
                    $d->use($id);
                    $invoicedObject = new Identifier(value: $id->textContent, scheme: $d->token('ram:ReferenceTypeCode', $reference));

                    continue;
                }
                if ($typeCode === '50' && $tenderOrLot === null) {
                    $d->use($type);
                    $d->use($id);
                    $tenderOrLot = $id->textContent;

                    continue;
                }
            }

            $document = $this->additionalDocument($d, $reference);
            if ($document !== null) {
                if ($type !== null && $typeCode === '916') {
                    $d->use($type);
                }
                $documents[] = $document;
            }
        }

        return [$tenderOrLot, $invoicedObject, $documents];
    }

    private function additionalDocument(CiiDocument $d, DOMElement $reference): ?AdditionalDocument
    {
        $id = $d->text('ram:IssuerAssignedID', $reference);
        if ($id === null) {
            $d->reject($reference, 'dropped: no supporting document reference');

            return null;
        }

        return new AdditionalDocument(
            reference: $id,
            description: $d->text('ram:Name', $reference),
            externalLocation: $d->text('ram:URIID', $reference),
            attachment: $d->attachment($d->one('ram:AttachmentBinaryObject', $reference)),
        );
    }

    /**
     * BG-20 and BG-21.
     *
     * @return array{list<DocumentAllowance>, list<DocumentCharge>}
     */
    private function allowancesAndCharges(CiiDocument $d, DOMElement $settlement): array
    {
        $allowances = [];
        $charges = [];

        foreach ($d->all('ram:SpecifiedTradeAllowanceCharge', $settlement) as $allowanceCharge) {
            $isCharge = $d->chargeIndicator('ram:ChargeIndicator/udt:Indicator', $allowanceCharge);
            if ($isCharge === null) {
                continue;
            }
            $amount = $d->decimal('ram:ActualAmount', $allowanceCharge, $isCharge ? 'BT-99' : 'BT-92');
            $category = $d->code(VatCategory::class, $d->one('ram:CategoryTradeTax/ram:CategoryCode', $allowanceCharge));
            if ($amount === null || $category === null) {
                $d->reject($allowanceCharge, 'dropped: no valid amount or VAT category code');

                continue;
            }
            $d->token('ram:CategoryTradeTax/ram:TypeCode', $allowanceCharge);
            $vatRate = $d->decimal('ram:CategoryTradeTax/ram:RateApplicablePercent', $allowanceCharge);
            $baseAmount = $d->decimal('ram:BasisAmount', $allowanceCharge, $isCharge ? 'BT-100' : 'BT-93');
            $percentage = $d->decimal('ram:CalculationPercent', $allowanceCharge);
            $reason = $d->text('ram:Reason', $allowanceCharge);
            $codeElement = $d->one('ram:ReasonCode', $allowanceCharge);

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
     * BT-7 and BT-8 sit in the header ram:ApplicableTradeTax entries. The first value of each
     * counts; a repeated equal BT-8 code is consumed, a different one left unmapped.
     *
     * @return array{?DateTimeImmutable, ?string}
     */
    private function taxPoint(CiiDocument $d, DOMElement $settlement): array
    {
        $date = $d->date('ram:ApplicableTradeTax/ram:TaxPointDate/udt:DateString', $settlement);

        $code = null;
        $first = null;
        foreach ($d->all('ram:ApplicableTradeTax/ram:DueDateTypeCode', $settlement) as $element) {
            $value = trim($element->textContent);
            if ($first === null) {
                $first = $value;
                $code = self::TAX_POINT_DATE_CODES[$value] ?? null;
                if ($code === null) {
                    $d->reject($element, "invalid code '{$value}'");
                } else {
                    $d->use($element);
                }

                continue;
            }
            if ($code !== null && $value === $first) {
                $d->use($element);
            }
        }

        return [$date, $code];
    }

    /**
     * @return list<VatBreakdown>
     */
    private function vatBreakdown(CiiDocument $d, DOMElement $settlement): array
    {
        $breakdown = [];
        foreach ($d->all('ram:ApplicableTradeTax', $settlement) as $tax) {
            $taxable = $d->decimal('ram:BasisAmount', $tax, 'BT-116');
            $amount = $d->decimal('ram:CalculatedAmount', $tax, 'BT-117');
            $category = $d->code(VatCategory::class, $d->one('ram:CategoryCode', $tax));
            if ($taxable === null || $amount === null || $category === null) {
                $d->reject($tax, 'dropped: no valid basis amount, calculated amount or VAT category code');

                continue;
            }
            $d->token('ram:TypeCode', $tax);

            $breakdown[] = new VatBreakdown(
                taxableAmount: $taxable,
                taxAmount: $amount,
                category: $category,
                rate: $d->decimal('ram:RateApplicablePercent', $tax),
                exemptionReason: $d->text('ram:ExemptionReason', $tax),
                exemptionReasonCode: $d->code(VatExemptionReason::class, $d->one('ram:ExemptionReasonCode', $tax), caseInsensitive: true),
            );
        }

        return $breakdown;
    }

    /**
     * BG-22. BT-110 is the ram:TaxTotalAmount in the invoice currency, BT-111 the one in the VAT
     * accounting currency (BT-6).
     */
    private function totals(CiiDocument $d, DOMElement $settlement, CurrencyCode $currency, ?CurrencyCode $taxCurrency): ?DocumentTotals
    {
        $summation = $d->one('ram:SpecifiedTradeSettlementHeaderMonetarySummation', $settlement);
        if ($summation === null) {
            return null;
        }

        $required = [];
        foreach (['LineTotalAmount' => 'BT-106', 'TaxBasisTotalAmount' => 'BT-109', 'GrandTotalAmount' => 'BT-112', 'DuePayableAmount' => 'BT-115'] as $name => $term) {
            $element = $d->one('ram:'.$name, $summation);
            $value = $element === null ? null : $d->decimalOf($element, $term);
            if ($value === null) {
                $d->reject($summation, "dropped: no valid ram:{$name}");

                return null;
            }
            $required[$name] = $value;
        }

        [$totalVat, $totalVatInAccountingCurrency] = $this->taxTotals($d, $summation, $currency, $taxCurrency);

        return new DocumentTotals(
            sumOfLineNetAmounts: $required['LineTotalAmount'],
            totalWithoutVat: $required['TaxBasisTotalAmount'],
            totalWithVat: $required['GrandTotalAmount'],
            amountDue: $required['DuePayableAmount'],
            sumOfAllowances: $d->decimal('ram:AllowanceTotalAmount', $summation, 'BT-107'),
            sumOfCharges: $d->decimal('ram:ChargeTotalAmount', $summation, 'BT-108'),
            totalVat: $totalVat,
            totalVatInAccountingCurrency: $totalVatInAccountingCurrency,
            paidAmount: $d->decimal('ram:TotalPrepaidAmount', $summation, 'BT-113'),
            roundingAmount: $d->decimal('ram:RoundingAmount', $summation, 'BT-114'),
        );
    }

    /**
     * @return array{?BigDecimal, ?BigDecimal}
     */
    private function taxTotals(CiiDocument $d, DOMElement $summation, CurrencyCode $currency, ?CurrencyCode $taxCurrency): array
    {
        $totalVat = null;
        $totalVatInAccountingCurrency = null;
        $seenMain = false;
        $seenAccounting = false;

        foreach ($d->all('ram:TaxTotalAmount', $summation) as $amount) {
            $amountCurrency = $d->attribute($amount, 'currencyID');
            if (! $seenMain && ($amountCurrency === null || $amountCurrency === $currency->value)) {
                $seenMain = true;
                $totalVat = $d->decimalOf($amount, 'BT-110');

                continue;
            }
            if (! $seenAccounting && $taxCurrency !== null && $taxCurrency !== $currency && $amountCurrency === $taxCurrency->value) {
                $seenAccounting = true;
                $totalVatInAccountingCurrency = $d->decimalOf($amount, 'BT-111', $taxCurrency->value);
            }
        }

        return [$totalVat, $totalVatInAccountingCurrency];
    }

    /**
     * BG-13 with the invoicing period (BG-14), which the model keeps inside the delivery.
     */
    private function delivery(CiiDocument $d, PartyMapper $parties, LineMapper $lines, ?DOMElement $delivery, DOMElement $settlement): ?Delivery
    {
        $period = $lines->period($d->one('ram:BillingSpecifiedPeriod', $settlement));
        if ($delivery === null) {
            return $period === null ? null : new Delivery(invoicingPeriod: $period);
        }

        $shipTo = $d->one('ram:ShipToTradeParty', $delivery);
        $partyName = $shipTo === null ? null : $d->text('ram:Name', $shipTo);
        $location = $shipTo === null ? null : ($parties->identifiers($shipTo, false)[0] ?? null);
        $address = $shipTo === null ? null : $parties->address($d->one('ram:PostalTradeAddress', $shipTo));
        $date = $d->date('ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString', $delivery);

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

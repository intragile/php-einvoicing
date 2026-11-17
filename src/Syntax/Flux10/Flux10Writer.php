<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Flux10;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\EReporting\DailyPayments;
use IntrAgile\EInvoicing\France\EReporting\DailyTransactions;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\InvoicePayment;
use IntrAgile\EInvoicing\France\EReporting\PaymentsReport;
use IntrAgile\EInvoicing\France\EReporting\ReportPeriod;
use IntrAgile\EInvoicing\France\EReporting\TransactionsReport;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Syntax\Cii\LineWriter;
use InvalidArgumentException;

/**
 * Writes a Flux 10 e-reporting transmission, the file an approved platform (PA) sends to the
 * French tax administration for transactions and payments that are not e-invoiced.
 *
 * The document has no namespace; elements appear in the order of the DGFiP XSD sequences
 * (ereporting.xsd, report.xsd, transaction.xsd, payment.xsd). Dates are written as CCYYMMDD, the
 * creation time as CCYYMMDDHHMMSS in French local time. Amounts keep the scale they were given
 * with.
 *
 * Invoices reported one by one are written from the invoice model:
 *
 * - ID, IssueDate, TypeCode, CurrencyCode, DueDate from BT-1, BT-2, BT-3, BT-5, BT-9; the VAT
 *   due date code from BT-8 in its UNTDID 2475 form (3 → 5, 35 → 29, 432 → 72); notes from BG-1;
 *   the billing framework from BT-23; preceding invoices from BG-3.
 * - The seller and buyer identifier is the legal registration (BT-30, BT-47) when its scheme is
 *   0002, 0223, 0227, 0228 or 0229; otherwise, for a party in another EU member state, its VAT
 *   identifier with scheme 0223; otherwise, for a party outside the EU, its country code and the
 *   first 16 characters of its name with scheme 0227. A buyer without any of these is written
 *   without an identifier; a seller without one is refused. VAT identifiers come from BT-31 and
 *   BT-48, countries from BT-40 and BT-55, the tax representative from BT-63.
 * - Delivery from BT-72 and BG-15, the invoicing period from BG-14, allowances and charges from
 *   BG-20 and BG-21, the totals from BT-109 and the VAT total in euros (BT-110 for a euro
 *   invoice, BT-111 otherwise), the VAT breakdown from BG-23.
 * - Lines from BG-25: note (BT-127, with the French note subject code), quantity and unit
 *   (BT-129, BT-130), the French preceding invoice and delivery, period (BG-26), allowances and
 *   charges (BG-27, BG-28), prices (BT-146 to BT-148) and item name (BT-153). GROUP and
 *   INFORMATION lines are not invoice lines and are never written.
 *
 * Payments are always in euros.
 */
final class Flux10Writer
{
    /** Profile identifier of a Flux 10 invoice (TT-29). */
    public const PROFILE = 'urn.cpro.gouv.fr:1p0:ereporting';

    /** Seller and buyer identifier schemes Flux 10 accepts (TT-33-1, TT-37). */
    private const COMPANY_ID_SCHEMES = ['0002', '0223', '0227', '0228', '0229'];

    /** Scheme of an EU VAT identifier used as company identifier. */
    private const EU_VAT_SCHEME = '0223';

    /** Scheme of a non-EU company identified by its country code and name. */
    private const NON_EU_SCHEME = '0227';

    /** Characters of the name kept in a non-EU company identifier. */
    private const NON_EU_NAME_LENGTH = 16;

    /** Qualifier of a seller or buyer VAT identifier (TT-34-0, TT-38-0). */
    private const VAT = 'VAT';

    /** EU member states (ISO 3166-1 alpha-2). */
    private const EU_MEMBER_STATES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
        'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    private const FRANCE = 'FR';

    /**
     * @throws InvalidArgumentException when an invoice has no billing framework (BT-23), no
     *                                  totals (BG-22), no identifier Flux 10 accepts for its
     *                                  seller, a VAT point date code without a UNTDID 2475
     *                                  equivalent, or, for a non-euro invoice, no VAT total in euros
     *                                  (BT-111)
     */
    public function write(EReport $report): string
    {
        $output = new Flux10Output;

        $this->reportDocument($output, $report);
        if ($report->transactions !== null) {
            $this->transactionsReport($output, $report->transactions);
        }
        if ($report->payments !== null) {
            $this->paymentsReport($output, $report->payments);
        }

        return $output->xml();
    }

    private function reportDocument(Flux10Output $output, EReport $report): void
    {
        $document = $output->add($output->root, 'ReportDocument');
        $output->text($document, 'Id', $report->id);
        $output->text($document, 'Name', $report->name);
        $output->timestamp($output->add($document, 'IssueDateTime'), 'DateTimeString', $report->issuedAt);
        $output->text($document, 'TypeCode', $report->type->value);

        $platform = $report->platform;
        $sender = $output->add($document, 'Sender');
        $output->text($sender, 'Id', $platform->globalId?->value, ['schemeId' => $platform->globalId?->scheme]);
        $output->text($sender, 'Name', $platform->name);
        $output->text($sender, 'RoleCode', $platform->role->value);
        $this->uri($output, $sender, $platform->uri?->value);

        $declarant = $report->declarant;
        $issuer = $output->add($document, 'Issuer');
        $output->text($issuer, 'Id', $declarant->siren, ['schemeId' => Siren::SCHEME]);
        $output->text($issuer, 'Name', $declarant->name);
        $output->text($issuer, 'RoleCode', $declarant->role->value);
        $this->uri($output, $issuer, $declarant->uri);
    }

    private function uri(Flux10Output $output, DOMElement $parent, ?string $uri): void
    {
        if ($uri !== null) {
            $output->text($output->add($parent, 'URIUniversalCommunication'), 'URIID', $uri);
        }
    }

    private function period(Flux10Output $output, DOMElement $parent, ReportPeriod $period): void
    {
        $element = $output->add($parent, 'ReportPeriod');
        $output->date($element, 'StartDate', $period->start);
        $output->date($element, 'EndDate', $period->end);
    }

    private function transactionsReport(Flux10Output $output, TransactionsReport $report): void
    {
        $element = $output->add($output->root, 'TransactionsReport');
        $this->period($output, $element, $report->period);
        foreach ($report->invoices as $invoice) {
            $this->invoice($output, $element, $invoice);
        }
        foreach ($report->days as $day) {
            $this->dailyTransactions($output, $element, $day);
        }
    }

    private function invoice(Flux10Output $output, DOMElement $parent, Invoice $invoice): void
    {
        $framework = $invoice->processControl->businessProcessType ?? throw new InvalidArgumentException(sprintf(
            'Invoice %s has no billing framework (BT-23), which a Flux 10 invoice requires (TT-28).',
            $invoice->number,
        ));
        $totals = $invoice->totals ?? throw new InvalidArgumentException(sprintf(
            'Invoice %s has no document totals (BG-22); complete it with TotalsCalculator::complete() first.',
            $invoice->number,
        ));

        $element = $output->add($parent, 'Invoice');
        $output->text($element, 'ID', $invoice->number);
        $output->date($element, 'IssueDate', $invoice->issueDate);
        $output->text($element, 'TypeCode', $invoice->type->value);
        $output->text($element, 'CurrencyCode', $invoice->currency->value);
        $output->date($element, 'DueDate', $invoice->dueDate);
        $output->text($element, 'TaxDueDateTypeCode', self::dueDateTypeCode($invoice->taxPointDateCode, 'VAT point date code (BT-8)'));
        foreach ($invoice->notes as $note) {
            $included = $output->add($element, 'IncludedNote');
            $output->text($included, 'Subject', $note->subjectCode?->value);
            $output->text($included, 'Content', $note->text);
        }

        $process = $output->add($element, 'BusinessProcess');
        $output->text($process, 'ID', $framework);
        $output->text($process, 'TypeID', self::PROFILE);

        foreach ($invoice->precedingInvoices as $preceding) {
            $reference = $output->add($element, 'ReferencedDocument');
            $output->text($reference, 'ID', $preceding->number);
            $output->date($reference, 'IssueDate', $preceding->issueDate);
        }

        $sellerId = self::companyId($invoice->seller) ?? throw new InvalidArgumentException(sprintf(
            'The seller of invoice %s has no identifier Flux 10 accepts (TT-33): a legal registration (BT-30) with scheme %s, a VAT identifier (BT-31) of another EU member state, or a postal address outside the EU.',
            $invoice->number,
            implode(', ', self::COMPANY_ID_SCHEMES),
        ));
        $this->party($output, $element, 'Seller', $invoice->seller, $sellerId);
        $this->party($output, $element, 'Buyer', $invoice->buyer, self::companyId($invoice->buyer));

        $representative = $invoice->taxRepresentative?->vatIdentifier;
        if ($representative !== null) {
            $output->text($output->add($element, 'SellerTaxRepresentative'), 'TaxRegistrationId', $representative, ['schemeId' => self::VAT]);
        }

        $this->delivery($output, $element, $invoice->delivery);
        $this->invoicePeriod($output, $element, $invoice->delivery?->invoicingPeriod);
        foreach ($invoice->allowances as $allowance) {
            $this->documentAllowanceCharge($output, $element, false, $allowance->amount, $allowance->vatCategory->value, $allowance->vatRate);
        }
        foreach ($invoice->charges as $charge) {
            $this->documentAllowanceCharge($output, $element, true, $charge->amount, $charge->vatCategory->value, $charge->vatRate);
        }

        $monetary = $output->add($element, 'MonetaryTotal');
        $output->decimal($monetary, 'TaxExclusiveAmount', $totals->totalWithoutVat);
        $output->decimal($monetary, 'TaxAmount', self::vatTotalInEuros($invoice, $totals), ['CurrencyCode' => CurrencyCode::EUR->value]);

        foreach ($invoice->vatBreakdown as $entry) {
            $subtotal = $output->add($element, 'TaxSubTotal');
            $output->decimal($subtotal, 'TaxableAmount', $entry->taxableAmount);
            $output->decimal($subtotal, 'TaxAmount', $entry->taxAmount);
            $category = $output->add($subtotal, 'TaxCategory');
            $output->text($category, 'Code', $entry->category->value);
            $output->decimal($category, 'Percent', $entry->rate ?? BigDecimal::zero());
            $output->text($category, 'TaxExemptionReason', $entry->exemptionReason);
            $output->text($category, 'TaxExemptionReasonCode', $entry->exemptionReasonCode?->value);
        }

        foreach ($invoice->lines as $line) {
            $this->line($output, $element, $line);
        }
    }

    private function party(Flux10Output $output, DOMElement $parent, string $name, Party $party, ?Identifier $companyId): void
    {
        if ($companyId === null && $party->vatIdentifier === null && $party->address === null) {
            return;
        }
        $element = $output->add($parent, $name);
        if ($companyId !== null) {
            $output->text($element, 'CompanyId', $companyId->value, ['schemeId' => $companyId->scheme]);
        }
        $output->text($element, 'TaxRegistrationId', $party->vatIdentifier, ['qualifyingId' => self::VAT]);
        if ($party->address !== null) {
            $output->text($output->add($element, 'PostalAddress'), 'CountryId', $party->address->country->value);
        }
    }

    /**
     * The seller or buyer identifier (TT-33, TT-36), or null when the party has none Flux 10
     * accepts.
     */
    private static function companyId(Party $party): ?Identifier
    {
        $registration = $party->legalRegistration;
        if ($registration !== null && in_array($registration->scheme, self::COMPANY_ID_SCHEMES, true)) {
            return $registration;
        }
        $country = $party->address?->country->value;
        if ($country === null || $country === self::FRANCE) {
            return null;
        }
        if (in_array($country, self::EU_MEMBER_STATES, true)) {
            return $party->vatIdentifier === null ? null : new Identifier($party->vatIdentifier, self::EU_VAT_SCHEME);
        }

        return new Identifier($country.mb_substr($party->name, 0, self::NON_EU_NAME_LENGTH), self::NON_EU_SCHEME);
    }

    /**
     * BT-110 for a euro invoice (the VAT breakdown total when absent), BT-111 otherwise.
     */
    private static function vatTotalInEuros(Invoice $invoice, DocumentTotals $totals): BigDecimal
    {
        if ($invoice->currency === CurrencyCode::EUR) {
            if ($totals->totalVat !== null) {
                return $totals->totalVat;
            }

            return array_reduce($invoice->vatBreakdown, static fn (BigDecimal $sum, VatBreakdown $entry): BigDecimal => $sum->plus($entry->taxAmount), BigDecimal::zero());
        }
        if ($invoice->taxCurrency !== null && $invoice->taxCurrency !== CurrencyCode::EUR) {
            throw new InvalidArgumentException(sprintf(
                'Invoice %s is in %s with VAT accounting currency (BT-6) %s; Flux 10 needs the VAT total in EUR, so BT-6 must be EUR.',
                $invoice->number,
                $invoice->currency->value,
                $invoice->taxCurrency->value,
            ));
        }

        return $totals->totalVatInAccountingCurrency ?? throw new InvalidArgumentException(sprintf(
            'Invoice %s is in %s; Flux 10 needs its VAT total in EUR (BT-111, with BT-6 EUR).',
            $invoice->number,
            $invoice->currency->value,
        ));
    }

    private function delivery(Flux10Output $output, DOMElement $parent, ?Delivery $delivery): void
    {
        if ($delivery === null || ($delivery->actualDeliveryDate === null && $delivery->address === null)) {
            return;
        }
        $element = $output->add($parent, 'Delivery');
        $output->date($element, 'Date', $delivery->actualDeliveryDate);
        $address = $delivery->address;
        if ($address !== null) {
            $location = $output->add($element, 'Location');
            $output->text($location, 'LineOne', $address->line1);
            $output->text($location, 'LineTwo', $address->line2);
            $output->text($location, 'LineThree', $address->line3);
            $output->text($location, 'CityName', $address->city);
            $output->text($location, 'PostalZone', $address->postCode);
            $output->text($location, 'CountrySubentity', $address->subdivision);
            $output->text($location, 'CountryId', $address->country->value);
        }
    }

    private function invoicePeriod(Flux10Output $output, DOMElement $parent, ?Period $period): void
    {
        if ($period === null || ($period->start === null && $period->end === null)) {
            return;
        }
        $element = $output->add($parent, 'InvoicePeriod');
        $output->date($element, 'StartDate', $period->start);
        $output->date($element, 'EndDate', $period->end);
    }

    private function documentAllowanceCharge(Flux10Output $output, DOMElement $parent, bool $charge, BigDecimal $amount, string $category, ?BigDecimal $rate): void
    {
        $element = $output->add($parent, 'AllowanceCharge');
        $element->setAttribute('ChargeIndicator', $charge ? 'true' : 'false');
        $output->decimal($element, 'Amount', $amount);
        $output->text($element, 'TaxCategoryCode', $category);
        $output->decimal($element, 'TaxPercent', $rate);
    }

    private function line(Flux10Output $output, DOMElement $parent, InvoiceLine $line): void
    {
        $french = $line->extensions()->get(FrenchLine::class);
        $element = $output->add($parent, 'Line');

        if ($line->note !== null || $french?->noteSubjectCode !== null) {
            $note = $output->add($element, 'Note');
            $output->text($note, 'Code', $french?->noteSubjectCode?->value);
            $output->text($note, 'Comment', $line->note);
        }
        $output->decimal($element, 'BilledQuantity', $line->quantity, ['UnitCode' => $line->unit->value]);

        $preceding = $french?->precedingInvoice;
        if ($preceding !== null) {
            $reference = $output->add($element, 'ReferencedDocument');
            $output->text($reference, 'ID', $preceding->number);
            $output->date($reference, 'IssueDate', $preceding->issueDate);
        }

        $delivery = $french?->delivery;
        if ($delivery !== null && ($delivery->name !== null || $delivery->address !== null)) {
            $lineDelivery = $output->add($element, 'Delivery');
            $output->text($lineDelivery, 'Name', $delivery->name);
            if ($delivery->address !== null) {
                $this->lineLocation($output, $lineDelivery, $delivery->address);
            }
        }

        $this->invoicePeriod($output, $element, $line->period);
        foreach ($line->allowances as $allowance) {
            $this->lineAllowanceCharge($output, $element, false, $allowance->amount);
        }
        foreach ($line->charges as $charge) {
            $this->lineAllowanceCharge($output, $element, true, $charge->amount);
        }

        $price = $output->add($element, 'Price');
        $output->decimal($price, 'PriceAmount', $line->price->netPrice);
        $output->decimal($price, 'AllowanceChargeAmount', $line->price->discount);
        $output->decimal($price, 'AllowanceChargeBaseAmount', $line->price->grossPrice);

        $output->text($output->add($element, 'Product'), 'Name', $line->item->name);
    }

    private function lineLocation(Flux10Output $output, DOMElement $parent, PostalAddress $address): void
    {
        $location = $output->add($parent, 'Location');
        $output->text($location, 'LineOne', $address->line1);
        $output->text($location, 'CityName', $address->city);
        $output->text($location, 'PostalZone', $address->postCode);
        $output->text($location, 'CountrySubentity', $address->subdivision);
        $output->text($location, 'CountryId', $address->country->value);
    }

    private function lineAllowanceCharge(Flux10Output $output, DOMElement $parent, bool $charge, BigDecimal $amount): void
    {
        $element = $output->add($parent, 'AllowanceCharge');
        $element->setAttribute('ChargeIndicator', $charge ? 'true' : 'false');
        $output->decimal($element, 'Amount', $amount);
    }

    private function dailyTransactions(Flux10Output $output, DOMElement $parent, DailyTransactions $day): void
    {
        $element = $output->add($parent, 'Transactions');
        $output->date($element, 'Date', $day->date);
        $output->text($element, 'TransactionsCurrency', $day->currency->value);
        $output->text($element, 'TaxDueDateTypeCode', self::dueDateTypeCode($day->vatDueDateCode, 'VAT payment option (TT-80)'));
        $output->text($element, 'CategoryCode', $day->category->value);
        $output->decimal($element, 'TaxExclusiveAmount', $day->taxExclusive);
        $output->decimal($element, 'TaxTotal', $day->taxTotal);
        $output->text($element, 'TransactionsCount', $day->count === null ? null : (string) $day->count);
        foreach ($day->subtotals as $subtotal) {
            $tax = $output->add($element, 'TaxSubtotal');
            $output->decimal($tax, 'TaxPercent', $subtotal->rate);
            $output->decimal($tax, 'TaxableAmount', $subtotal->taxable);
            $output->decimal($tax, 'TaxTotal', $subtotal->tax);
        }
    }

    private function paymentsReport(Flux10Output $output, PaymentsReport $report): void
    {
        $element = $output->add($output->root, 'PaymentsReport');
        $this->period($output, $element, $report->period);
        foreach ($report->invoices as $payment) {
            $this->invoicePayment($output, $element, $payment);
        }
        foreach ($report->days as $day) {
            $this->dailyPayments($output, $element, $day);
        }
    }

    private function invoicePayment(Flux10Output $output, DOMElement $parent, InvoicePayment $payment): void
    {
        $element = $output->add($parent, 'Invoice');
        $output->text($element, 'InvoiceID', $payment->invoiceNumber);
        $output->date($element, 'IssueDate', $payment->invoiceDate);
        $this->payment($output, $element, $payment->paidOn, $payment->amounts);
    }

    private function dailyPayments(Flux10Output $output, DOMElement $parent, DailyPayments $day): void
    {
        $this->payment($output, $output->add($parent, 'Transactions'), $day->date, $day->amounts);
    }

    /**
     * @param  list<CollectedAmount>  $amounts
     */
    private function payment(Flux10Output $output, DOMElement $parent, DateTimeImmutable $date, array $amounts): void
    {
        $element = $output->add($parent, 'Payment');
        $output->date($element, 'Date', $date);
        foreach ($amounts as $amount) {
            $subtotal = $output->add($element, 'SubTotals');
            $output->decimal($subtotal, 'TaxPercent', $amount->vatRate);
            $output->text($subtotal, 'CurrencyCode', CurrencyCode::EUR->value);
            $output->decimal($subtotal, 'Amount', $amount->amount);
        }
    }

    /**
     * A UNTDID 2005 VAT date code of the model in its UNTDID 2475 form.
     *
     * @throws InvalidArgumentException when the code has no UNTDID 2475 equivalent
     */
    private static function dueDateTypeCode(?string $code, string $term): ?string
    {
        if ($code === null) {
            return null;
        }

        return LineWriter::DUE_DATE_TYPE_CODES[$code] ?? throw new InvalidArgumentException(sprintf(
            "The %s '%s' has no Flux 10 equivalent; expected one of %s.",
            $term,
            $code,
            implode(', ', array_keys(LineWriter::DUE_DATE_TYPE_CODES)),
        ));
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;

/**
 * The fixed set of release invoices: one source for the offline release gate, the live Peppol
 * tests and the import into accounting software.
 *
 * Every invoice is a German domestic sale (10 hours of software development at 95.00 EUR, 19 %
 * VAT, 1130.50 EUR due) with its totals completed by TotalsCalculator.
 */
final class ReleaseInvoices
{
    /** Number of the release invoice, which the release credit note refers to. */
    public const INVOICE_NUMBER = 'RE-2026-0815';

    /** Number of the release credit note. */
    public const CREDIT_NOTE_NUMBER = 'GS-2026-0042';

    /** Buyer reference (BT-10): a Leitweg-ID, which XRechnung requires. */
    private const LEITWEG_ID = '04011000-12345-34';

    /** An XRechnung 3.0 CIUS invoice whose seller and buyer are reached by e-mail. */
    public static function germanDomestic(string $number = self::INVOICE_NUMBER): Invoice
    {
        return self::invoice(
            $number,
            new ElectronicAddress('rechnung@muster-software.example', ElectronicAddressScheme::EM),
            new ElectronicAddress('eingang@beispiel-handel.example', ElectronicAddressScheme::EM),
            XRechnung::processControl(),
        );
    }

    /** An XRechnung 3.0 CIUS credit note (type 381) for the release invoice. */
    public static function germanCreditNote(string $number = self::CREDIT_NOTE_NUMBER): Invoice
    {
        return self::germanDomestic($number)
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoice(new PrecedingInvoiceReference(self::INVOICE_NUMBER));
    }

    /** The release invoice under the XRechnung 3.0 Extension. */
    public static function germanExtension(): Invoice
    {
        return self::germanDomestic()->withProcessControl(XRechnung::processControl(XRechnung::EXTENSION));
    }

    /**
     * The release invoice under Peppol BIS Billing 3.0, with the Peppol endpoints of
     * withPeppolEndpoints().
     */
    public static function bis3(string $number): Invoice
    {
        return self::withPeppolEndpoints(self::germanDomestic($number))
            ->withProcessControl(new ProcessControl(PeppolBis3::CUSTOMIZATION, PeppolBis3::PROCESS));
    }

    /**
     * A release invoice whose seller and buyer are reached over Peppol instead of by e-mail: the
     * seller by its VAT number (9930), the buyer by its Leitweg-ID (0204).
     */
    public static function withPeppolEndpoints(Invoice $invoice): Invoice
    {
        return $invoice
            ->withSeller(self::seller(new ElectronicAddress('DE123456789', ElectronicAddressScheme::C_9930)))
            ->withBuyer(self::buyer(new ElectronicAddress(self::LEITWEG_ID, ElectronicAddressScheme::C_0204)));
    }

    /**
     * The XRechnung release documents as written by UblWriter and CiiWriter.
     *
     * @return array<string, string> document key => XML
     */
    public static function documents(): array
    {
        $ubl = new UblWriter;
        $cii = new CiiWriter;

        return [
            'xrechnung-ubl-invoice' => $ubl->write(self::germanDomestic()),
            'xrechnung-ubl-creditnote' => $ubl->write(self::germanCreditNote()),
            'xrechnung-cii' => $cii->write(self::germanDomestic()),
            'xrechnung-extension-ubl' => $ubl->write(self::germanExtension()),
            'xrechnung-extension-cii' => $cii->write(self::germanExtension()),
        ];
    }

    private static function invoice(string $number, ElectronicAddress $seller, ElectronicAddress $buyer, ProcessControl $processControl): Invoice
    {
        return TotalsCalculator::complete(new Invoice(
            number: $number,
            issueDate: new DateTimeImmutable('2026-11-13'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: self::seller($seller),
            buyer: self::buyer($buyer),
            lines: [
                new InvoiceLine(
                    id: '1',
                    quantity: '10',
                    unit: UnitCode::HUR,
                    price: new Price(netPrice: '95.00'),
                    item: new Item(name: 'Softwareentwicklung'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '19'),
                ),
            ],
            dueDate: new DateTimeImmutable('2026-12-13'),
            buyerReference: self::LEITWEG_ID,
            paymentTerms: 'Zahlbar innerhalb von 30 Tagen ohne Abzug.',
            processControl: $processControl,
            payment: new PaymentInstructions(
                meansCode: PaymentMeansCode::C_58,
                remittanceInformation: $number,
                creditTransfers: [new CreditTransfer(accountIdentifier: 'DE75512108001245126199', accountName: 'Muster Software GmbH')],
            ),
        ));
    }

    private static function seller(ElectronicAddress $electronicAddress): Party
    {
        return new Party(
            name: 'Muster Software GmbH',
            vatIdentifier: 'DE123456789',
            electronicAddress: $electronicAddress,
            address: new PostalAddress(country: CountryCode::DE, line1: 'Lindenstraße 12', city: 'Berlin', postCode: '10115'),
            contact: new Contact(name: 'Erika Mustermann', phone: '+49 30 1234567', email: 'erika.mustermann@muster-software.example'),
        );
    }

    private static function buyer(ElectronicAddress $electronicAddress): Party
    {
        return new Party(
            name: 'Beispiel Handel AG',
            electronicAddress: $electronicAddress,
            address: new PostalAddress(country: CountryCode::DE, line1: 'Marktplatz 3', city: 'Köln', postCode: '50667'),
        );
    }
}

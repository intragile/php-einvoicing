<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\Verifactu\ForeignId;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;

/**
 * Spanish invoices for VERI*FACTU mapping tests, with synthetic parties and checksum-valid NIFs.
 *
 * Every factory returns an invoice completed with TotalsCalculator. The seller name is the one of
 * the sandbox account the live records were captured with; the buyer is fictitious.
 */
final class SpanishInvoices
{
    public const SELLER_VAT = 'ESB64829179';

    public const SELLER_NIF = 'B64829179';

    public const SELLER_NAME = 'IntrAgile Test Espana S.L.';

    public const BUYER_VAT = 'ESA58818501';

    public const BUYER_NIF = 'A58818501';

    public const BUYER_NAME = 'Cliente Prueba S.A.';

    public const NUMBER = 'PROBE-ES-UBL-0002';

    public const ISSUE_DATE = '2026-11-23';

    /** Domestic F1: one line of 100.00 at the standard 21 % rate. */
    public static function domestic(): Invoice
    {
        return self::invoice([self::line('1', '100.00', VatCategory::StandardRate, '21')]);
    }

    /** Export of goods to a US buyer without a VAT identifier, identified by an official document. */
    public static function export(): Invoice
    {
        return self::invoice(
            [self::line('1', '500.00', VatCategory::ExportOutsideEu, '0')],
            buyer: new Party(
                name: 'Buyer Inc.',
                address: new PostalAddress(country: CountryCode::US, line1: '1 Main Street', city: 'Boston', postCode: '02108'),
            ),
            spanish: new SpanishInvoice(recipients: [
                new TaxParty('Buyer Inc.', null, new ForeignId(ForeignIdType::OfficialDocument, '123456789', CountryCode::US)),
            ]),
        );
    }

    /** Intra-Community supply of goods to a German buyer. */
    public static function intraCommunity(): Invoice
    {
        return self::invoice(
            [self::line('1', '250.00', VatCategory::IntraCommunitySupply, '0')],
            buyer: new Party(
                name: 'Käufer GmbH',
                vatIdentifier: 'DE123456789',
                address: new PostalAddress(country: CountryCode::DE, line1: 'Hauptstraße 5', city: 'Berlin', postCode: '10115'),
            ),
        );
    }

    /** Domestic reverse charge (inversión del sujeto pasivo). */
    public static function reverseCharge(): Invoice
    {
        return self::invoice([self::line('1', '300.00', VatCategory::ReverseCharge, '0')]);
    }

    /** Exempt supply; the LIVA article comes from the breakdown override of the rateless E group. */
    public static function exempt(ExemptOperation $article): Invoice
    {
        return self::invoice(
            [self::line('1', '400.00', VatCategory::Exempt, null)],
            spanish: new SpanishInvoice(breakdown: ['E|-' => new BreakdownOverride(exemption: $article)]),
        );
    }

    /** Credit note (381) by difference (R1, I) for the domestic invoice. */
    public static function creditNote(): Invoice
    {
        return self::invoice(
            [self::line('1', '100.00', VatCategory::StandardRate, '21')],
            number: 'PROBE-ES-UBL-0003',
            type: InvoiceTypeCode::C_381,
            spanish: new SpanishInvoice(type: InvoiceType::R1, correctionType: CorrectionType::Difference),
        )->withPrecedingInvoice(new PrecedingInvoiceReference(self::NUMBER, new DateTimeImmutable(self::ISSUE_DATE)));
    }

    /** Canary Islands invoice taxed with IGIC at 7 %. */
    public static function igic(): Invoice
    {
        return self::invoice([self::line('1', '200.00', VatCategory::CanaryIslands, '7')]);
    }

    /** Domestic invoice issued in US dollars, without EUR amounts. */
    public static function usd(): Invoice
    {
        return self::invoice(
            [self::line('1', '100.00', VatCategory::StandardRate, '21')],
            currency: CurrencyCode::USD,
        );
    }

    /** Thirteen standard-rated lines at thirteen different rates: one breakdown group too many. */
    public static function thirteenRates(): Invoice
    {
        $lines = [];
        foreach (range(1, 13) as $index) {
            $lines[] = self::line((string) $index, '10.00', VatCategory::StandardRate, (string) $index);
        }

        return self::invoice($lines);
    }

    /**
     * @param  numeric-string  $price
     * @param  numeric-string|null  $rate
     */
    public static function line(string $id, string $price, VatCategory $category, ?string $rate): InvoiceLine
    {
        return new InvoiceLine(
            id: $id,
            quantity: '1',
            unit: UnitCode::C62,
            price: new Price($price),
            item: new Item('Servicio de consultoría'),
            vat: new LineVat($category, $rate),
        );
    }

    public static function seller(): Party
    {
        return new Party(
            name: self::SELLER_NAME,
            vatIdentifier: self::SELLER_VAT,
            address: new PostalAddress(country: CountryCode::ES, line1: 'Calle Mayor 1', city: 'Madrid', postCode: '28013'),
        );
    }

    public static function buyer(): Party
    {
        return new Party(
            name: self::BUYER_NAME,
            vatIdentifier: self::BUYER_VAT,
            address: new PostalAddress(country: CountryCode::ES, line1: 'Avenida Diagonal 100', city: 'Barcelona', postCode: '08019'),
        );
    }

    /**
     * An invoice completed with TotalsCalculator.
     *
     * @param  list<InvoiceLine>  $lines
     */
    public static function invoice(
        array $lines,
        ?Party $buyer = null,
        ?SpanishInvoice $spanish = null,
        string $number = self::NUMBER,
        InvoiceTypeCode $type = InvoiceTypeCode::C_380,
        CurrencyCode $currency = CurrencyCode::EUR,
        ?Party $seller = null,
    ): Invoice {
        $invoice = new Invoice(
            number: $number,
            issueDate: new DateTimeImmutable(self::ISSUE_DATE),
            type: $type,
            currency: $currency,
            seller: $seller ?? self::seller(),
            buyer: $buyer ?? self::buyer(),
            lines: $lines,
        );

        if ($spanish !== null) {
            $invoice = $invoice->withExtension($spanish);
        }

        return TotalsCalculator::complete($invoice);
    }

    private function __construct() {}
}

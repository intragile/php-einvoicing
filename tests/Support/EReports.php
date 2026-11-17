<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\EReporting\DailyPayments;
use IntrAgile\EInvoicing\France\EReporting\DailyTransactions;
use IntrAgile\EInvoicing\France\EReporting\Declarant;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\InvoicePayment;
use IntrAgile\EInvoicing\France\EReporting\PaymentsReport;
use IntrAgile\EInvoicing\France\EReporting\ReportPeriod;
use IntrAgile\EInvoicing\France\EReporting\ReportType;
use IntrAgile\EInvoicing\France\EReporting\TransactionCategory;
use IntrAgile\EInvoicing\France\EReporting\TransactionsReport;
use IntrAgile\EInvoicing\France\EReporting\VatSubtotal;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\LineDelivery;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\VatBreakdown;

/**
 * Flux 10 e-reporting transmissions for tests. The two fixture factories rebuild the values of
 * tests/fixtures/france/flux10-base.xml and flux10-payments-base.xml.
 */
final class EReports
{
    public static function platform(): LifecycleParty
    {
        return LifecycleParty::platform('PA01', 'PLATEFORME EXEMPLE');
    }

    public static function seller(): Declarant
    {
        return new Declarant(FrenchInvoices::SELLER_SIREN, 'LE VENDEUR', DeclarantRole::Seller);
    }

    public static function issuedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-12-01 09:30:00', new DateTimeZone('Europe/Paris'));
    }

    public static function november(): ReportPeriod
    {
        return ReportPeriod::month(2026, 11);
    }

    /**
     * The transactions report of flux10-base.xml: one invoice and one day of aggregated sales.
     */
    public static function fixtureTransactions(): EReport
    {
        $day = new DailyTransactions(
            date: new DateTimeImmutable('2026-11-15'),
            currency: CurrencyCode::EUR,
            category: TransactionCategory::Goods,
            taxExclusive: '500.00',
            taxTotal: '100.00',
            subtotals: [new VatSubtotal('20', '500.00', '100.00')],
            count: 3,
            vatDueDateCode: '3',
        );

        return EReport::transactions(
            'TR-2026-0001',
            self::issuedAt(),
            ReportType::Initial,
            self::platform(),
            self::seller(),
            new TransactionsReport(self::november(), [self::fixtureInvoice()], [$day]),
            'E-REPORTING NOVEMBRE',
        );
    }

    /**
     * The payments report of flux10-payments-base.xml: one invoice payment and one day of
     * aggregated payments.
     */
    public static function fixturePayments(): EReport
    {
        return EReport::payments(
            'PA-2026-0001',
            self::issuedAt(),
            ReportType::Initial,
            self::platform(),
            self::seller(),
            new PaymentsReport(
                self::november(),
                [new InvoicePayment('F202600003', new DateTimeImmutable('2026-11-02'), new DateTimeImmutable('2026-11-20'), [new CollectedAmount('1188.00', '20')])],
                [new DailyPayments(new DateTimeImmutable('2026-11-21'), [new CollectedAmount('600.00', '20')])],
            ),
        );
    }

    /**
     * A 10.1 transmission: the domestic invoice sold to a German business.
     */
    public static function internationalSale(): EReport
    {
        return self::invoices('TR-2026-0002', [FrenchInvoices::domesticB2b()->withBuyer(self::germanBuyer())]);
    }

    /**
     * A 10.1 transmission of a US dollar invoice whose VAT total is also given in euros (BT-111).
     */
    public static function nonEurSale(): EReport
    {
        return self::invoices('TR-2026-0003', [self::nonEurInvoice()]);
    }

    /**
     * A corrective transmission (RE) by a buyer declarant, with a credit note and aggregated
     * services sales of two rates.
     */
    public static function correctiveByBuyer(): EReport
    {
        $day = new DailyTransactions(
            date: new DateTimeImmutable('2026-11-03'),
            currency: CurrencyCode::EUR,
            category: TransactionCategory::Services,
            taxExclusive: '300.00',
            taxTotal: '45.00',
            subtotals: [new VatSubtotal('20', '200.00', '40.00'), new VatSubtotal('5.5', '100.00', '5.00')],
        );

        return EReport::transactions(
            'TR-2026-0004',
            self::issuedAt(),
            ReportType::Corrective,
            self::platform(),
            new Declarant(FrenchInvoices::BUYER_SIREN, 'LE CLIENT', DeclarantRole::Buyer, 'client@example.fr'),
            new TransactionsReport(
                ReportPeriod::decade(2026, 11, 1),
                [FrenchInvoices::creditNoteWithTwoPrecedingInvoices()->withBuyer(self::germanBuyer())],
                [$day],
            ),
        );
    }

    /**
     * Every factory, by name.
     *
     * @return array<string, EReport>
     */
    public static function all(): array
    {
        return [
            'fixtureTransactions' => self::fixtureTransactions(),
            'fixturePayments' => self::fixturePayments(),
            'internationalSale' => self::internationalSale(),
            'nonEurSale' => self::nonEurSale(),
            'correctiveByBuyer' => self::correctiveByBuyer(),
        ];
    }

    /**
     * A transactions transmission of the given invoices for November 2026.
     *
     * @param  list<Invoice>  $invoices
     */
    public static function invoices(string $id, array $invoices): EReport
    {
        return EReport::transactions(
            $id,
            self::issuedAt(),
            ReportType::Initial,
            self::platform(),
            self::seller(),
            new TransactionsReport(self::november(), $invoices),
        );
    }

    /**
     * A transactions transmission of aggregated days for November 2026.
     *
     * @param  list<DailyTransactions>  $days
     */
    public static function days(string $id, array $days): EReport
    {
        return EReport::transactions(
            $id,
            self::issuedAt(),
            ReportType::Initial,
            self::platform(),
            self::seller(),
            new TransactionsReport(self::november(), [], $days),
        );
    }

    /**
     * A sale to a consumer (B2C) under the given billing framework, completed with its totals and
     * VAT breakdown.
     *
     * @param  list<InvoiceLine>  $lines
     * @param  list<DocumentAllowance>  $allowances
     */
    public static function consumerSale(
        BillingFramework $framework,
        string $number,
        string $issueDate,
        array $lines,
        InvoiceTypeCode $type = InvoiceTypeCode::C_380,
        array $allowances = [],
    ): Invoice {
        $invoice = FrenchInvoices::domesticB2b()
            ->withNumber($number)
            ->withIssueDate(new DateTimeImmutable($issueDate))
            ->withType($type)
            ->withBuyer(self::consumer())
            ->withLines($lines)
            ->withAllowances($allowances)
            ->withVatBreakdown([])
            ->withTotals(null);

        return France::mark(TotalsCalculator::complete($invoice), $framework);
    }

    /**
     * A one-unit line of the given net amount, standard rated unless the rate is null (then not
     * subject to VAT).
     *
     * @param  numeric-string  $netAmount
     * @param  numeric-string|null  $rate
     */
    public static function consumerLine(string $id, string $name, string $netAmount, ?string $rate): InvoiceLine
    {
        return new InvoiceLine(
            id: $id,
            quantity: '1',
            unit: UnitCode::C62,
            price: new Price(netPrice: $netAmount),
            item: new Item(name: $name),
            vat: $rate === null ? new LineVat(VatCategory::NotSubjectToVat) : new LineVat(VatCategory::StandardRate, $rate),
        );
    }

    public static function consumer(): Party
    {
        return new Party(
            name: 'PARTICULIER',
            address: new PostalAddress(country: CountryCode::FR, city: 'LYON'),
        );
    }

    public static function germanBuyer(): Party
    {
        return new Party(
            name: 'KUNDE GMBH',
            vatIdentifier: 'DE123456789',
            address: new PostalAddress(country: CountryCode::DE, city: 'BERLIN'),
        );
    }

    public static function usBuyer(): Party
    {
        return new Party(
            name: 'AMERICAN CUSTOMER INCORPORATED',
            address: new PostalAddress(country: CountryCode::US, city: 'BOSTON'),
        );
    }

    public static function nonEurInvoice(): Invoice
    {
        return FrenchInvoices::domesticB2b()
            ->withNumber('F202600010')
            ->withBuyer(self::usBuyer())
            ->withCurrency(CurrencyCode::USD)
            ->withTaxCurrency(CurrencyCode::EUR)
            ->withTotals(new DocumentTotals(
                sumOfLineNetAmounts: '10000.00',
                totalWithoutVat: '10000.00',
                totalWithVat: '12000.00',
                amountDue: '12000.00',
                totalVat: '2000.00',
                totalVatInAccountingCurrency: '1850.00',
            ));
    }

    private static function fixtureInvoice(): Invoice
    {
        $line = new InvoiceLine(
            id: '1',
            quantity: '10',
            unit: UnitCode::C62,
            price: new Price(netPrice: '100.00', discount: '5.00', grossPrice: '105.00'),
            item: new Item(name: 'SERVICE'),
            vat: new LineVat(VatCategory::StandardRate, '20'),
            netAmount: '995.00',
            period: new Period(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')),
            allowances: [new LineAllowance('5.00')],
            extensions: Extensions::none()->with(new FrenchLine(
                precedingInvoice: new PrecedingInvoiceReference('F202600001', new DateTimeImmutable('2026-10-01')),
                delivery: new LineDelivery(address: new PostalAddress(CountryCode::FR)),
            )),
        );

        $invoice = new Invoice(
            number: 'F202600003',
            issueDate: new DateTimeImmutable('2026-11-02'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party(
                name: 'LE VENDEUR',
                legalRegistration: Siren::identifier(FrenchInvoices::SELLER_SIREN),
                vatIdentifier: 'FR88100000009',
                address: new PostalAddress(CountryCode::FR),
            ),
            buyer: new Party(
                name: 'LE CLIENT',
                legalRegistration: Siren::identifier(FrenchInvoices::BUYER_SIREN),
                vatIdentifier: 'FR37200000008',
                address: new PostalAddress(CountryCode::FR),
            ),
            lines: [$line],
            taxPointDateCode: '3',
            dueDate: new DateTimeImmutable('2026-12-02'),
            precedingInvoices: [new PrecedingInvoiceReference('F202600001', new DateTimeImmutable('2026-10-01'))],
            delivery: new Delivery(
                actualDeliveryDate: new DateTimeImmutable('2026-10-31'),
                invoicingPeriod: new Period(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31')),
                address: new PostalAddress(CountryCode::FR),
            ),
            allowances: [new DocumentAllowance('10.00', VatCategory::StandardRate, '20')],
            totals: new DocumentTotals(
                sumOfLineNetAmounts: '995.00',
                totalWithoutVat: '990.00',
                totalWithVat: '1188.00',
                amountDue: '1188.00',
                sumOfAllowances: '10.00',
                totalVat: '198.00',
            ),
            vatBreakdown: [new VatBreakdown('990.00', '198.00', VatCategory::StandardRate, '20')],
        );

        return France::mark($invoice, BillingFramework::B1);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Flux10;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Syntax\Flux10\Flux10Writer;
use IntrAgile\EInvoicing\Tests\Support\CanonicalXml;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Flux10WriterTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/france/';

    public function test_transactions_report_matches_fixture_shape(): void
    {
        $fixture = file_get_contents(self::FIXTURES.'flux10-base.xml');
        self::assertIsString($fixture);

        $written = (new Flux10Writer)->write(EReports::fixtureTransactions());

        self::assertSame(CanonicalXml::of($fixture), CanonicalXml::of($written));
    }

    public function test_payments_report_matches_fixture(): void
    {
        $fixture = file_get_contents(self::FIXTURES.'flux10-payments-base.xml');
        self::assertIsString($fixture);

        $written = (new Flux10Writer)->write(EReports::fixturePayments());

        self::assertSame(CanonicalXml::of($fixture), CanonicalXml::of($written));
    }

    public function test_output_has_no_namespace(): void
    {
        $xml = (new Flux10Writer)->write(EReports::fixtureTransactions());

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString("\n<Report>\n", $xml);
        self::assertStringNotContainsString('xmlns', $xml);
    }

    public function test_issue_time_is_written_in_paris_time(): void
    {
        $report = EReports::fixturePayments();
        $utc = new DateTimeImmutable('2026-12-31 23:30:00', new DateTimeZone('UTC'));
        $report = EReport::payments($report->id, $utc, $report->type, $report->platform, $report->declarant, $report->payments ?? self::fail());

        $xpath = self::xpath((new Flux10Writer)->write($report));

        self::assertSame('20270101003000', $xpath->evaluate('string(/Report/ReportDocument/IssueDateTime/DateTimeString)'));
    }

    public function test_non_eur_invoice_needs_vat_total_in_eur(): void
    {
        $xpath = self::xpath((new Flux10Writer)->write(EReports::nonEurSale()));
        self::assertSame('USD', $xpath->evaluate('string(//Invoice/CurrencyCode)'));
        self::assertSame('1850.00', $xpath->evaluate('string(//Invoice/MonetaryTotal/TaxAmount)'));
        self::assertSame('EUR', $xpath->evaluate('string(//Invoice/MonetaryTotal/TaxAmount/@CurrencyCode)'));

        $invoice = EReports::nonEurInvoice();
        $totals = $invoice->totals ?? self::fail();
        $withoutEur = $invoice->withTotals(new DocumentTotals(
            $totals->sumOfLineNetAmounts,
            $totals->totalWithoutVat,
            $totals->totalWithVat,
            $totals->amountDue,
            totalVat: $totals->totalVat,
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-111');

        (new Flux10Writer)->write(EReports::invoices('TR-X', [$withoutEur]));
    }

    public function test_vat_total_in_another_accounting_currency_is_refused(): void
    {
        $invoice = EReports::nonEurInvoice()->withTaxCurrency(CurrencyCode::GBP);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-6');

        (new Flux10Writer)->write(EReports::invoices('TR-X', [$invoice]));
    }

    public function test_foreign_buyer_company_id_schemes(): void
    {
        $german = self::xpath((new Flux10Writer)->write(EReports::internationalSale()));
        self::assertSame('0223', $german->evaluate('string(//Invoice/Buyer/CompanyId/@schemeId)'));
        self::assertSame('DE123456789', $german->evaluate('string(//Invoice/Buyer/CompanyId)'));
        self::assertSame('DE123456789', $german->evaluate('string(//Invoice/Buyer/TaxRegistrationId)'));
        self::assertSame('VAT', $german->evaluate('string(//Invoice/Buyer/TaxRegistrationId/@qualifyingId)'));
        self::assertSame('DE', $german->evaluate('string(//Invoice/Buyer/PostalAddress/CountryId)'));

        $us = self::xpath((new Flux10Writer)->write(EReports::invoices('TR-X', [FrenchInvoices::domesticB2b()->withBuyer(EReports::usBuyer())])));
        self::assertSame('0227', $us->evaluate('string(//Invoice/Buyer/CompanyId/@schemeId)'));
        self::assertSame('USAMERICAN CUSTOME', $us->evaluate('string(//Invoice/Buyer/CompanyId)'));
        self::assertSame(0.0, $us->evaluate('count(//Invoice/Buyer/TaxRegistrationId)'));
    }

    public function test_eu_buyer_without_vat_identifier_gets_no_company_id(): void
    {
        $buyer = EReports::germanBuyer();
        $buyer = new Party(name: $buyer->name, address: $buyer->address);

        $xpath = self::xpath((new Flux10Writer)->write(EReports::invoices('TR-X', [FrenchInvoices::domesticB2b()->withBuyer($buyer)])));

        self::assertSame(0.0, $xpath->evaluate('count(//Invoice/Buyer/CompanyId)'));
        self::assertSame('DE', $xpath->evaluate('string(//Invoice/Buyer/PostalAddress/CountryId)'));
    }

    public function test_seller_without_reportable_identifier_is_refused(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $seller = new Party(name: 'LE VENDEUR', address: $invoice->seller->address);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TT-33');

        (new Flux10Writer)->write(EReports::invoices('TR-X', [$invoice->withSeller($seller)]));
    }

    public function test_invoice_without_billing_framework_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-23');

        (new Flux10Writer)->write(EReports::invoices('TR-X', [FrenchInvoices::domesticB2b()->withProcessControl(null)]));
    }

    public function test_informational_lines_are_not_written(): void
    {
        $xpath = self::xpath((new Flux10Writer)->write(EReports::invoices('TR-X', [FrenchInvoices::extendedCtcFr()->withBuyer(EReports::germanBuyer())])));

        self::assertSame(2.0, $xpath->evaluate('count(//Invoice/Line)'));
        self::assertSame('LIVRE', $xpath->evaluate('string(//Invoice/Line[1]/Product/Name)'));
        self::assertSame('JOUET', $xpath->evaluate('string(//Invoice/Line[2]/Product/Name)'));
        self::assertSame('AAI', $xpath->evaluate('string(//Invoice/Line[2]/Note/Code)'));
        self::assertSame('Livraison partielle', $xpath->evaluate('string(//Invoice/Line[2]/Note/Comment)'));
        self::assertSame('ENTREPOT NORD', $xpath->evaluate('string(//Invoice/Line[2]/Delivery/Name)'));
        self::assertSame('LILLE', $xpath->evaluate('string(//Invoice/Line[2]/Delivery/Location/CityName)'));
        self::assertSame('F202500099', $xpath->evaluate('string(//Invoice/Line[2]/ReferencedDocument/ID)'));
    }

    public function test_invoice_notes_are_written(): void
    {
        $xpath = self::xpath((new Flux10Writer)->write(EReports::internationalSale()));

        self::assertSame(4.0, $xpath->evaluate('count(//Invoice/IncludedNote)'));
        self::assertSame('PMT', $xpath->evaluate('string(//Invoice/IncludedNote[1]/Subject)'));
        self::assertSame(FrenchInvoices::RECOVERY_FEE, $xpath->evaluate('string(//Invoice/IncludedNote[1]/Content)'));
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));

        return new DOMXPath($document);
    }
}

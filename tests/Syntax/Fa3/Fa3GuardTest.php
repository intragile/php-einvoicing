<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Guard;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Fa3GuardTest extends TestCase
{
    use CapturesThrowable;

    public function test_factories_pass_the_guard(): void
    {
        foreach (PolishInvoices::all() as $invoice) {
            Fa3Guard::check($invoice);
        }
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function nonPolishSellerIds(): array
    {
        return ['foreign VAT id' => ['DE123456789'], 'malformed NIP' => ['PL12345'], 'none' => [null]];
    }

    #[DataProvider('nonPolishSellerIds')]
    public function test_seller_without_polish_nip_is_refused(?string $vatIdentifier): void
    {
        $invoice = PolishInvoices::domestic();
        $seller = $invoice->seller;
        $invoice = $invoice->withSeller(new Party(name: $seller->name, vatIdentifier: $vatIdentifier, address: $seller->address));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-31: ', $message);
        self::assertStringContainsString('NIP', $message);
    }

    public function test_unmappable_rate_names_the_line(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[2] = PolishInvoices::line('3', 'Lek', '1', UnitCode::H87, '10.00', VatCategory::StandardRate, '4');
        $invoice = TotalsCalculator::complete($invoice->withLines(array_values($lines))->withVatBreakdown([]));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-151 (line "3"): ', $message);
        self::assertStringContainsString('P_13', $message);
    }

    public function test_bucketless_rate_code_override_names_the_line(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withExtension(new PolishLine(rateCode: VatRateCode::Rate4));

        self::assertStringContainsString('P_12 (line "1"): ', self::refusal($invoice->withLines($lines)));
    }

    public function test_rows_whose_lines_map_to_different_codes_are_refused(): void
    {
        $invoice = PolishInvoices::domesticReverseCharge();
        $second = PolishInvoices::line('2', 'Usługa budowlana', '1', UnitCode::C62, '100.00', VatCategory::ReverseCharge, '0')
            ->withExtension(new PolishLine(rateCode: VatRateCode::EuServicesArticle100));
        $invoice = TotalsCalculator::complete($invoice->withLine($second)->withVatBreakdown([]));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BG-23 (AE 0): ', $message);
        self::assertStringContainsString('"oo"', $message);
        self::assertStringContainsString('"np II"', $message);
    }

    public function test_guard_names_every_refusal(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withPrice(new Price('1.123456789'));
        $seller = $invoice->seller;
        $invoice = $invoice
            ->withLines($lines)
            ->withPayee(new Party(name: 'Faktor S.A.'))
            ->withAllowance(new DocumentAllowance('10.00', VatCategory::StandardRate, '23', reason: 'Rabat'))
            ->withSeller(new Party(
                name: $seller->name,
                vatIdentifier: $seller->vatIdentifier,
                electronicAddress: new ElectronicAddress('faktury@sprzedawca.example', ElectronicAddressScheme::EM),
                address: $seller->address,
            ));

        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        $message = $exception->getMessage();
        foreach (['BG-10: ', 'BG-20: ', 'BT-34: ', 'BT-146 (line "1"): '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_every_listed_term_is_refused(): void
    {
        $invoice = PolishInvoices::domestic();
        $buyer = $invoice->buyer;
        $invoice = $invoice
            ->withTaxRepresentative(new Party(name: 'Przedstawiciel'))
            ->withTaxCurrency(CurrencyCode::EUR)
            ->withTaxPointDateCode('3')
            ->withDelivery(new Delivery(partyName: 'Magazyn', address: new PostalAddress(CountryCode::PL, line1: 'ul. Składowa 1')))
            ->withBuyer(new Party(
                name: $buyer->name,
                vatIdentifier: $buyer->vatIdentifier,
                electronicAddress: new ElectronicAddress('zakupy@nabywca.example', ElectronicAddressScheme::EM),
                address: $buyer->address,
            ));
        $message = self::refusal($invoice);

        foreach (['BG-11: ', 'BT-6: ', 'BT-8: ', 'BT-70: ', 'BG-15: ', 'BT-49: '] as $term) {
            self::assertStringContainsString($term, $message);
        }
    }

    public function test_missing_totals_are_refused(): void
    {
        self::assertStringContainsString('complete the invoice with TotalsCalculator first', self::refusal(PolishInvoices::domestic()->withTotals(null)));
    }

    public function test_decimals_beyond_the_schema_are_refused(): void
    {
        $invoice = PolishInvoices::domestic();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withQuantity('1.1234567')->withNetAmount('10.123');
        $message = self::refusal($invoice->withLines($lines));

        self::assertStringContainsString('BT-129 (line "1"): 1.1234567 has more than 6 decimals', $message);
        self::assertStringContainsString('BT-131 (line "1"): 10.123 has more than 2 decimals', $message);
    }

    public function test_half_open_invoicing_period_is_refused(): void
    {
        $invoice = PolishInvoices::domestic()->withDelivery(new Delivery(invoicingPeriod: new Period(start: new DateTimeImmutable('2026-10-01'))));

        self::assertStringContainsString('BG-14: ', self::refusal($invoice));
    }

    public function test_different_delivery_and_tax_point_dates_are_refused(): void
    {
        $invoice = PolishInvoices::domestic()
            ->withDelivery(new Delivery(actualDeliveryDate: new DateTimeImmutable('2026-11-10')))
            ->withTaxPointDate(new DateTimeImmutable('2026-11-11'));

        $message = self::refusal($invoice);
        self::assertStringContainsString('BT-72: ', $message);
        self::assertStringContainsString('BT-7', $message);
    }

    public function test_type_code_without_kind_is_refused(): void
    {
        self::assertStringContainsString('BT-3: ', self::refusal(PolishInvoices::domestic()->withType(InvoiceTypeCode::C_389)));
    }

    public function test_misnamed_new_transport_fragment_is_refused(): void
    {
        $fragment = new RawFragment('<Transport xmlns="'.Fa3Format::NS.'"/>');
        $invoice = PolishInvoices::domestic()->withExtension(new PolishInvoice(annotations: new Annotations(newTransport: $fragment)));

        self::assertStringContainsString('NoweSrodkiTransportu: ', self::refusal($invoice));
    }

    public function test_writer_runs_the_guard(): void
    {
        $exception = self::capture(static fn () => (new Fa3Writer)->write(PolishInvoices::domestic()->withTotals(null)));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('TotalsCalculator', $exception->getMessage());
    }

    private static function refusal(Invoice $invoice): string
    {
        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        return $exception->getMessage();
    }
}

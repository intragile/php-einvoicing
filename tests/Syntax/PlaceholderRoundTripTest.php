<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentCard;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\TaxRegistration;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where a syntax requires an element EN 16931 does not model, the writers fill in a placeholder.
 * The readers recognise their own placeholders, so they neither become data nor show up as
 * ignored content.
 */
final class PlaceholderRoundTripTest extends TestCase
{
    /**
     * @return array<string, array{Invoice}>
     */
    public static function invoicesWithPlaceholders(): array
    {
        $base = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $seller = $base->seller;

        return [
            'sales order reference only (UBL order id NA)' => [$base->withSalesOrderReference('SO-77')],
            'payment card (UBL card network NA)' => [$base->withPayment(new PaymentInstructions(
                meansCode: PaymentMeansCode::C_54,
                card: new PaymentCard(primaryAccountNumber: '1234', holderName: 'Card Holder'),
            ))],
            'tax registration without scheme (UBL TAX, CII FC)' => [$base->withSeller(new Party(
                name: $seller->name,
                vatIdentifier: $seller->vatIdentifier,
                taxRegistration: new TaxRegistration(identifier: '12345676-2-41'),
                address: $seller->address,
            ))],
        ];
    }

    #[DataProvider('invoicesWithPlaceholders')]
    public function test_ubl_round_trip(Invoice $invoice): void
    {
        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertEquals($invoice, $read->invoice);
        self::assertSame([], $read->ignored);
    }

    #[DataProvider('invoicesWithPlaceholders')]
    public function test_cii_round_trip(Invoice $invoice): void
    {
        $read = (new CiiReader)->read((new CiiWriter)->write($invoice));

        self::assertEquals($invoice, $read->invoice);
        self::assertSame([], $read->ignored);
    }

    #[DataProvider('invoicesWithPlaceholders')]
    public function test_ubl_to_cii_conversion(Invoice $invoice): void
    {
        $fromUbl = (new UblReader)->read((new UblWriter)->write($invoice))->invoice;

        $cii = (new CiiWriter)->write($fromUbl);
        $read = (new CiiReader)->read($cii);

        self::assertEquals($invoice, $read->invoice);
        self::assertSame([], $read->ignored);
        self::assertStringNotContainsString('>NA<', $cii);
        self::assertStringNotContainsString('>TAX<', $cii);
    }

    #[DataProvider('invoicesWithPlaceholders')]
    public function test_cii_to_ubl_conversion(Invoice $invoice): void
    {
        $fromCii = (new CiiReader)->read((new CiiWriter)->write($invoice))->invoice;

        $read = (new UblReader)->read((new UblWriter)->write($fromCii));

        self::assertEquals($invoice, $read->invoice);
        self::assertSame([], $read->ignored);
    }

    public function test_sales_order_only_writes_no_purchase_order_in_cii(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withSalesOrderReference('SO-77');
        $fromUbl = (new UblReader)->read((new UblWriter)->write($invoice))->invoice;

        self::assertNull($fromUbl->purchaseOrderReference);
        self::assertStringNotContainsString('BuyerOrderReferencedDocument', (new CiiWriter)->write($fromUbl));
    }

    public function test_a_real_purchase_order_named_na_is_kept(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withPurchaseOrderReference('NA');

        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertSame('NA', $read->invoice->purchaseOrderReference);
    }

    public function test_an_explicit_tax_scheme_is_kept(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $seller = $invoice->seller;
        $invoice = $invoice->withSeller(new Party(
            name: $seller->name,
            vatIdentifier: $seller->vatIdentifier,
            taxRegistration: new TaxRegistration(identifier: '12345676-2-41', taxScheme: 'LOC'),
            address: $seller->address,
        ));

        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertSame('LOC', $read->invoice->seller->taxRegistration?->taxScheme);
    }
}

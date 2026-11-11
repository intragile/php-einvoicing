<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\TestCase;

/**
 * The model holds every amount in the invoice currency (BT-5), except BT-111. An amount whose
 * currencyID names another currency is read as is but reported, so the mismatch is not lost.
 */
final class AmountCurrencyTest extends TestCase
{
    public function test_ubl_amount_in_a_foreign_currency_is_reported(): void
    {
        $xml = (new UblWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));
        $xml = preg_replace('/<cbc:PriceAmount currencyID="EUR">120.00</', '<cbc:PriceAmount currencyID="USD">120.00<', $xml, 1);
        self::assertIsString($xml);

        $read = (new UblReader)->read($xml);

        self::assertSame('120.00', (string) $read->invoice->lines[0]->price->netPrice);
        self::assertSame(
            ["/Invoice/cac:InvoiceLine[1]/cac:Price/cbc:PriceAmount/@currencyID (currency 'USD' of BT-146 differs from the invoice currency EUR (BT-5); amount read as EUR)"],
            $read->ignored,
        );
    }

    public function test_cii_amount_in_a_foreign_currency_is_reported(): void
    {
        $xml = (new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));
        $xml = preg_replace('/<ram:LineTotalAmount>1100.00</', '<ram:LineTotalAmount currencyID="USD">1100.00<', $xml, 1);
        self::assertIsString($xml);

        $read = (new CiiReader)->read($xml);

        self::assertSame('1100.00', (string) $read->invoice->lines[0]->netAmount);
        self::assertCount(1, $read->ignored);
        self::assertStringEndsWith(
            "/ram:LineTotalAmount/@currencyID (currency 'USD' of BT-131 differs from the invoice currency EUR (BT-5); amount read as EUR)",
            $read->ignored[0],
        );
    }

    public function test_cii_amount_with_the_invoice_currency_is_not_reported(): void
    {
        $xml = (new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));
        $xml = preg_replace('/<ram:LineTotalAmount>1100.00</', '<ram:LineTotalAmount currencyID="EUR">1100.00<', $xml, 1);
        self::assertIsString($xml);

        self::assertSame([], (new CiiReader)->read($xml)->ignored);
    }
}

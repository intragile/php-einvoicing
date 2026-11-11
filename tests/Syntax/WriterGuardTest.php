<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\MimeCode;
use IntrAgile\EInvoicing\Model\AdditionalDocument;
use IntrAgile\EInvoicing\Model\Attachment;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Both writers refuse a model they cannot turn into a document the official rules accept,
 * instead of writing it anyway or dropping data.
 */
final class WriterGuardTest extends TestCase
{
    /**
     * @return array<string, array{UblWriter|CiiWriter, UblReader|CiiReader}>
     */
    public static function syntaxes(): array
    {
        return [
            'UBL' => [new UblWriter, new UblReader],
            'CII' => [new CiiWriter, new CiiReader],
        ];
    }

    #[DataProvider('syntaxes')]
    public function test_bt111_without_a_tax_currency_is_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-111');

        $writer->write($this->withAccountingVat($this->invoice(), '299.50'));
    }

    #[DataProvider('syntaxes')]
    public function test_bt111_in_the_invoice_currency_is_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-111');

        $writer->write($this->withAccountingVat($this->invoice()->withTaxCurrency(CurrencyCode::EUR), '299.50'));
    }

    #[DataProvider('syntaxes')]
    public function test_bt111_in_a_different_tax_currency_round_trips(UblWriter|CiiWriter $writer, UblReader|CiiReader $reader): void
    {
        $invoice = $this->withAccountingVat($this->invoice()->withTaxCurrency(CurrencyCode::HUF), '117000.00');

        $read = $reader->read($writer->write($invoice));

        self::assertSame('117000.00', (string) $read->invoice->totals?->totalVatInAccountingCurrency);
        self::assertSame([], $read->ignored);
    }

    #[DataProvider('syntaxes')]
    public function test_control_character_in_text_is_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/U\+0001, which is not allowed in XML 1.0, in element (cbc:Note|ram:Content)/');

        $writer->write($this->invoice()->withNote(new Note("a\x01b\x0Bc")));
    }

    #[DataProvider('syntaxes')]
    public function test_invalid_utf8_in_text_is_refused(UblWriter|CiiWriter $writer): void
    {
        $invoice = $this->invoice();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not valid UTF-8 in element (cbc:RegistrationName|ram:Name)/');

        $writer->write($invoice->withSeller(new Party(
            name: "S\xC3\x28",
            vatIdentifier: $invoice->seller->vatIdentifier,
            address: $invoice->seller->address,
        )));
    }

    #[DataProvider('syntaxes')]
    public function test_invalid_text_in_an_attribute_is_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename');

        $writer->write($this->invoice()->withAdditionalDocument(new AdditionalDocument(
            reference: 'DOC-1',
            attachment: new Attachment(content: 'x', mimeCode: MimeCode::text_csv, filename: "a\x0Cb.csv"),
        )));
    }

    #[DataProvider('syntaxes')]
    public function test_tab_line_feed_and_carriage_return_are_written(UblWriter|CiiWriter $writer, UblReader|CiiReader $reader): void
    {
        $read = $reader->read($writer->write($this->invoice()->withNote(new Note("a\tb\nc"))));

        self::assertSame("a\tb\nc", $read->invoice->notes[0]->text);
    }

    #[DataProvider('syntaxes')]
    public function test_two_buyer_identifiers_are_refused(UblWriter|CiiWriter $writer): void
    {
        $invoice = $this->invoice();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-46');

        $writer->write($invoice->withBuyer(new Party(
            name: $invoice->buyer->name,
            identifiers: [new Identifier('B-1'), new Identifier('B-2')],
            vatIdentifier: $invoice->buyer->vatIdentifier,
            address: $invoice->buyer->address,
        )));
    }

    #[DataProvider('syntaxes')]
    public function test_two_payee_identifiers_are_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-60');

        $writer->write($this->invoice()->withPayee(new Party(
            name: 'Payee Ltd',
            identifiers: [new Identifier('P-1'), new Identifier('P-2')],
        )));
    }

    #[DataProvider('syntaxes')]
    public function test_one_buyer_and_one_payee_identifier_round_trip(UblWriter|CiiWriter $writer, UblReader|CiiReader $reader): void
    {
        $invoice = $this->invoice();
        $invoice = $invoice
            ->withBuyer(new Party(
                name: $invoice->buyer->name,
                identifiers: [new Identifier('B-1')],
                vatIdentifier: $invoice->buyer->vatIdentifier,
                address: $invoice->buyer->address,
            ))
            ->withPayee(new Party(name: 'Payee Ltd', identifiers: [new Identifier('P-1')]));

        $read = $reader->read($writer->write($invoice));

        self::assertEquals([new Identifier('B-1')], $read->invoice->buyer->identifiers);
        self::assertEquals([new Identifier('P-1')], $read->invoice->payee?->identifiers);
        self::assertSame([], $read->ignored);
    }

    private function invoice(): Invoice
    {
        return TotalsCalculator::complete(InvoiceFactory::mixedVat());
    }

    /**
     * @param  numeric-string  $amount
     */
    private function withAccountingVat(Invoice $invoice, string $amount): Invoice
    {
        $t = $invoice->totals;
        self::assertNotNull($t);

        return $invoice->withTotals(new DocumentTotals(
            sumOfLineNetAmounts: $t->sumOfLineNetAmounts,
            totalWithoutVat: $t->totalWithoutVat,
            totalWithVat: $t->totalWithVat,
            amountDue: $t->amountDue,
            sumOfAllowances: $t->sumOfAllowances,
            sumOfCharges: $t->sumOfCharges,
            totalVat: $t->totalVat,
            totalVatInAccountingCurrency: $amount,
        ));
    }
}

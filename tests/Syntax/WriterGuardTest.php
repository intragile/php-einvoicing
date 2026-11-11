<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use Closure;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\MimeCode;
use IntrAgile\EInvoicing\Model\AdditionalDocument;
use IntrAgile\EInvoicing\Model\Attachment;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
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
        $this->expectExceptionMessageMatches('/BT-111.*BT-6\b/');

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
        $this->expectExceptionMessage('notes[0].text (BT-22) contains U+0001, which is not allowed in XML 1.0');

        $writer->write($this->invoice()->withNote(new Note("a\x01b\x0Bc")));
    }

    #[DataProvider('syntaxes')]
    public function test_invalid_utf8_in_text_is_refused(UblWriter|CiiWriter $writer): void
    {
        $invoice = $this->invoice();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('seller.name (BT-27) is not valid UTF-8');

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
        $this->expectExceptionMessage('additionalDocuments[0].attachment.filename (BT-125-2) contains U+000C');

        $writer->write($this->invoice()->withAdditionalDocument(new AdditionalDocument(
            reference: 'DOC-1',
            attachment: new Attachment(content: 'x', mimeCode: MimeCode::text_csv, filename: "a\x0Cb.csv"),
        )));
    }

    #[DataProvider('syntaxes')]
    public function test_invalid_utf8_in_a_note_is_refused(UblWriter|CiiWriter $writer): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('notes[0].text (BT-22) is not valid UTF-8');

        $writer->write($this->invoice()->withNote(new Note("caf\xE9")));
    }

    /**
     * @return array<string, array{UblWriter|CiiWriter, Closure(Invoice): Invoice, string}>
     */
    public static function invalidTextByRole(): array
    {
        $cases = [
            'buyer city' => [
                static fn (Invoice $i): Invoice => $i->withBuyer(self::party($i->buyer, address: new PostalAddress(country: CountryCode::DE, city: "M\x01"))),
                'buyer.address.city (BT-52)',
            ],
            'deliver to city' => [
                static fn (Invoice $i): Invoice => $i->withDelivery(new Delivery(address: new PostalAddress(country: CountryCode::DE, city: "M\x01"))),
                'delivery.address.city (BT-77)',
            ],
            'tax representative address line' => [
                static fn (Invoice $i): Invoice => $i->withTaxRepresentative(new Party(
                    name: 'Rep Kft.',
                    vatIdentifier: 'HU11111111',
                    address: new PostalAddress(country: CountryCode::HU, line1: "U\x01"),
                )),
                'taxRepresentative.address.line1 (BT-64)',
            ],
            'tax representative VAT identifier' => [
                static fn (Invoice $i): Invoice => $i->withTaxRepresentative(new Party(
                    name: 'Rep Kft.',
                    vatIdentifier: "HU\x01",
                    address: new PostalAddress(country: CountryCode::HU),
                )),
                'taxRepresentative.vatIdentifier (BT-63)',
            ],
            'buyer contact email' => [
                static fn (Invoice $i): Invoice => $i->withBuyer(self::party($i->buyer, contact: new Contact(email: "a\x01@b.de"))),
                'buyer.contact.email (BT-58)',
            ],
            'buyer legal registration' => [
                static fn (Invoice $i): Invoice => $i->withBuyer(self::party($i->buyer, legalRegistration: new Identifier("HRB\x01"))),
                'buyer.legalRegistration.value (BT-47)',
            ],
            'buyer legal registration scheme' => [
                static fn (Invoice $i): Invoice => $i->withBuyer(self::party($i->buyer, legalRegistration: new Identifier('HRB 1', "00\x01"))),
                'buyer.legalRegistration.scheme (BT-47-1)',
            ],
            'seller identifier' => [
                static fn (Invoice $i): Invoice => $i->withSeller(self::party($i->seller, identifiers: [new Identifier('S-1'), new Identifier("S\x01")])),
                'seller.identifiers[1].value (BT-29)',
            ],
            'payee name' => [
                static fn (Invoice $i): Invoice => $i->withPayee(new Party(name: "P\x01")),
                'payee.name (BT-59)',
            ],
            'line item name' => [
                static fn (Invoice $i): Invoice => $i->withLines([
                    $i->lines[0],
                    (new InvoiceLine(
                        id: '2',
                        quantity: $i->lines[1]->quantity,
                        unit: $i->lines[1]->unit,
                        price: $i->lines[1]->price,
                        item: new Item(name: "I\x01"),
                        vat: $i->lines[1]->vat,
                    ))->withNetAmount($i->lines[1]->netAmount),
                ]),
                'lines[1].item.name (BT-153)',
            ],
        ];

        $matrix = [];
        foreach (self::syntaxes() as $syntax => [$writer]) {
            foreach ($cases as $name => [$change, $expected]) {
                $matrix[$syntax.': '.$name] = [$writer, $change, $expected];
            }
        }

        return $matrix;
    }

    /**
     * @param  Closure(Invoice): Invoice  $change
     */
    #[DataProvider('invalidTextByRole')]
    public function test_invalid_text_names_the_business_term_of_its_role(UblWriter|CiiWriter $writer, Closure $change, string $expected): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expected.' contains U+0001');

        $writer->write($change($this->invoice()));
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

    /**
     * @param  list<Identifier>|null  $identifiers
     */
    private static function party(
        Party $party,
        ?PostalAddress $address = null,
        ?Contact $contact = null,
        ?Identifier $legalRegistration = null,
        ?array $identifiers = null,
    ): Party {
        return new Party(
            name: $party->name,
            identifiers: $identifiers ?? $party->identifiers,
            legalRegistration: $legalRegistration ?? $party->legalRegistration,
            vatIdentifier: $party->vatIdentifier,
            address: $address ?? $party->address,
            contact: $contact ?? $party->contact,
        );
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

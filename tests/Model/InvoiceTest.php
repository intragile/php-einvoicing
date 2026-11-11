<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Model;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\MimeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use IntrAgile\EInvoicing\Model\AdditionalDocument;
use IntrAgile\EInvoicing\Model\Attachment;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DirectDebit;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\ItemAttribute;
use IntrAgile\EInvoicing\Model\ItemClassification;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentCard;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Model\TaxRegistration;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use stdClass;
use TypeError;

final class InvoiceTest extends TestCase
{
    public function test_ninety_percent_case_is_short(): void
    {
        $seller = new Party('Eladó Kft.', vatIdentifier: 'HU12345678', address: new PostalAddress(CountryCode::HU, city: 'Budapest', postCode: '1011', line1: 'Fő utca 1.'));
        $buyer = new Party('Vevő Zrt.', vatIdentifier: 'HU87654321', address: new PostalAddress(CountryCode::HU, city: 'Szeged', postCode: '6720'));
        $vat = new LineVat(VatCategory::StandardRate, '27');
        $invoice = new Invoice(
            number: 'INV-2026-0001',
            issueDate: new DateTimeImmutable('2026-11-11'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::HUF,
            seller: $seller,
            buyer: $buyer,
            lines: [
                new InvoiceLine('1', '2', UnitCode::C62, new Price('10000'), new Item('Consulting'), $vat),
                new InvoiceLine('2', '1.5', UnitCode::HUR, new Price('8000'), new Item('Support'), $vat),
            ],
        );

        self::assertSame('INV-2026-0001', $invoice->number);
        self::assertSame('2026-11-11', $invoice->issueDate->format('Y-m-d'));
        self::assertSame(CurrencyCode::HUF, $invoice->currency);
        self::assertSame('HU12345678', $invoice->seller->vatIdentifier);
        self::assertSame('Szeged', $invoice->buyer->address?->city);
        self::assertCount(2, $invoice->lines);
        self::assertTrue($invoice->lines[1]->quantity->isEqualTo('1.5'));
        self::assertTrue($invoice->lines[0]->vat->rate?->isEqualTo(27));
        self::assertNull($invoice->lines[0]->netAmount);
        self::assertNull($invoice->totals);
        self::assertNull($invoice->extensions()->get(stdClass::class));
    }

    public function test_withers_return_new_instances(): void
    {
        $original = $this->minimalInvoice();
        $changed = $original->withNote(new Note('Paid in advance.', TextSubjectCode::AAI));

        self::assertNotSame($original, $changed);
        self::assertSame([], $original->notes);
        self::assertCount(1, $changed->notes);
        self::assertSame('Paid in advance.', $changed->notes[0]->text);
        self::assertSame($original->lines, $changed->lines);
    }

    public function test_rejects_float_like_misuse(): void
    {
        $type = (new \ReflectionMethod(Price::class, '__construct'))->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionUnionType::class, $type);
        $names = array_map(static fn (ReflectionNamedType|ReflectionIntersectionType $t): string => (string) $t, $type->getTypes());
        sort($names);
        self::assertSame([BigDecimal::class, 'int', 'string'], $names);

        self::assertTrue((new Price('0.1'))->netPrice->isEqualTo(BigDecimal::of('0.1')));

        $this->expectException(TypeError::class);
        $float = 0.1;
        new Price($float); // @phpstan-ignore argument.type
    }

    public function test_requires_a_non_empty_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->minimalInvoice(number: '  ');
    }

    public function test_requires_at_least_one_line(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->minimalInvoice(lines: []);
    }

    public function test_every_business_group_is_representable(): void
    {
        $date = new DateTimeImmutable('2026-03-01');
        $seller = new Party(
            name: 'Seller Kft.',
            tradingName: 'Seller',
            identifiers: [new Identifier('5790000435951', IdentifierScheme::C_0088)],
            legalRegistration: new Identifier('01-09-123456'),
            vatIdentifier: 'HU12345678',
            taxRegistration: new TaxRegistration('12345678-2-42', 'FC'),
            additionalLegalInformation: 'Registered at the Budapest court',
            electronicAddress: new ElectronicAddress('seller@example.hu', ElectronicAddressScheme::EM),
            address: new PostalAddress(CountryCode::HU, 'Fő utca 1.', '2. emelet', 'Ajtó 3', 'Budapest', '1011', 'Pest'),
            contact: new Contact('Anna', '+36 1 234 5678', 'anna@example.hu'),
        );
        $line = new InvoiceLine(
            id: '1',
            quantity: '-2.5000',
            unit: UnitCode::C62,
            price: new Price('9.5', '0.5', '10', '2', UnitCode::C62),
            item: new Item(
                name: 'Widget',
                description: 'Blue widget',
                sellersIdentifier: 'W-1',
                buyersIdentifier: 'B-1',
                standardIdentifier: new Identifier('4000001234561', '0160'),
                classifications: [new ItemClassification('12345678', 'STI', '2026')],
                originCountry: CountryCode::DE,
                attributes: [new ItemAttribute('Colour', 'Blue')],
            ),
            vat: new LineVat(VatCategory::StandardRate, '27'),
            netAmount: '-11.88',
            note: 'Returned goods',
            objectIdentifier: new Identifier('OBJ-1', 'AAA'),
            orderLineReference: '10',
            buyerAccountingReference: 'ACC-1',
            period: new Period($date, $date),
            allowances: [new LineAllowance('1', '10', '10', 'Discount', AllowanceReasonCode::C_95)],
            charges: [new LineCharge('0.5', null, null, 'Packing', ChargeReasonCode::ABL)],
        );

        $invoice = new Invoice(
            number: 'CN-1',
            issueDate: $date,
            type: InvoiceTypeCode::C_381,
            currency: CurrencyCode::EUR,
            seller: $seller,
            buyer: new Party('Buyer GmbH', electronicAddress: new ElectronicAddress('DE123', ElectronicAddressScheme::C_9930)),
            lines: [$line],
            taxCurrency: CurrencyCode::HUF,
            taxPointDate: $date,
            taxPointDateCode: '3',
            dueDate: $date,
            buyerReference: 'BR',
            projectReference: 'PR',
            contractReference: 'CR',
            purchaseOrderReference: 'PO',
            salesOrderReference: 'SO',
            receivingAdviceReference: 'RA',
            despatchAdviceReference: 'DA',
            tenderOrLotReference: 'TL',
            invoicedObjectIdentifier: new Identifier('METER-1', 'AAA'),
            buyerAccountingReference: 'BAR',
            paymentTerms: '30 days',
            notes: [new Note('Note', TextSubjectCode::AAI)],
            processControl: new ProcessControl('urn:cen.eu:en16931:2017', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'),
            precedingInvoices: [new PrecedingInvoiceReference('INV-0', $date)],
            payee: new Party('Factor Ltd', identifiers: [new Identifier('PAYEE-1')], legalRegistration: new Identifier('LR-1')),
            taxRepresentative: new Party('Rep', vatIdentifier: 'HU99999999', address: new PostalAddress(CountryCode::HU)),
            delivery: new Delivery('Warehouse', new Identifier('LOC-1', '0088'), $date, new Period($date, $date), new PostalAddress(CountryCode::AT)),
            payment: new PaymentInstructions(
                PaymentMeansCode::C_59,
                'SEPA direct debit',
                'CN-1',
                [new CreditTransfer('HU42117730161111101800000000', 'Seller', 'OTPVHUHB')],
                new PaymentCard('1234', 'Anna'),
                new DirectDebit('MANDATE-1', 'DE98ZZZ09999999999', 'DE89370400440532013000'),
            ),
            allowances: [new DocumentAllowance('5', VatCategory::StandardRate, '27', '50', '10', 'Loyalty', AllowanceReasonCode::C_95)],
            charges: [new DocumentCharge('2', VatCategory::StandardRate, '27', reason: 'Freight', reasonCode: ChargeReasonCode::FC)],
            totals: new DocumentTotals('-11.88', '-14.88', '-18.90', '-18.90', '5', '2', '-4.02', '-1580', '0', '0'),
            vatBreakdown: [
                new VatBreakdown('-14.88', '-4.02', VatCategory::StandardRate, '27'),
                new VatBreakdown('0', '0', VatCategory::Exempt, '0', 'Exempt', VatExemptionReason::VATEX_EU_132_1A),
            ],
            additionalDocuments: [new AdditionalDocument('DOC-1', 'Timesheet', 'https://example.com/t.pdf', new Attachment('%PDF-1.7', MimeCode::application_pdf, 't.pdf'))],
        );

        self::assertTrue($invoice->type->isCreditNote());
        self::assertSame('0088', $invoice->seller->identifiers[0]->scheme);
        self::assertNull($invoice->seller->legalRegistration?->scheme);
        self::assertSame('DE98ZZZ09999999999', $invoice->payment?->directDebit?->creditorIdentifier);
        self::assertTrue($invoice->lines[0]->quantity->isEqualTo('-2.5'));
        self::assertSame(4, $invoice->lines[0]->quantity->getScale());
        self::assertTrue($invoice->lines[0]->price->baseQuantity?->isEqualTo(2));
        self::assertSame(UnitCode::C62, $invoice->lines[0]->price->baseQuantityUnit);
        self::assertTrue($invoice->totals?->totalVatInAccountingCurrency?->isEqualTo(-1580));
        self::assertSame(VatExemptionReason::VATEX_EU_132_1A, $invoice->vatBreakdown[1]->exemptionReasonCode);
        self::assertSame('2026', $invoice->lines[0]->item->classifications[0]->listVersion);
        self::assertSame('t.pdf', $invoice->additionalDocuments[0]->attachment?->filename);
        self::assertSame('Warehouse', $invoice->delivery?->partyName);
        self::assertSame($date, $invoice->delivery->invoicingPeriod?->start);
    }

    public function test_nullable_withers_can_clear_and_list_withers_append(): void
    {
        $invoice = $this->minimalInvoice()->withDueDate(new DateTimeImmutable('2026-02-15'))->withDueDate(null);
        self::assertNull($invoice->dueDate);

        $line = new InvoiceLine('2', '3', UnitCode::C62, new Price('1'), new Item('Extra'), new LineVat(VatCategory::ZeroRated, 0));
        $grown = $invoice->withLine($line)->withTotals(new DocumentTotals(1, 1, 1, 1));

        self::assertCount(1, $invoice->lines);
        self::assertCount(2, $grown->lines);
        self::assertTrue($grown->totals?->amountDue->isEqualTo(1));
        self::assertSame('A-1', $grown->number);
    }

    public function test_line_withers_fill_net_amount_without_touching_the_original(): void
    {
        $line = $this->minimalInvoice()->lines[0];
        $filled = $line->withNetAmount('100.00')->withAllowance(new LineAllowance('5'));

        self::assertNull($line->netAmount);
        self::assertSame([], $line->allowances);
        self::assertSame('100.00', (string) $filled->netAmount);
        self::assertCount(1, $filled->allowances);
        self::assertSame($line->item, $filled->item);
        self::assertNull($filled->withNetAmount(null)->netAmount);
    }

    public function test_identifier_scheme_accepts_enum_or_free_code(): void
    {
        self::assertSame('0088', (new Identifier('x', IdentifierScheme::C_0088))->scheme);
        self::assertSame('AAA', (new Identifier('x', 'AAA'))->scheme);
        self::assertNull((new Identifier('x'))->scheme);
    }

    public function test_extensions_are_attached_by_class(): void
    {
        $extension = new stdClass;
        $original = $this->minimalInvoice();
        $extended = $original->withExtension($extension);

        self::assertNull($original->extensions()->get(stdClass::class));
        self::assertSame($extension, $extended->extensions()->get(stdClass::class));
        self::assertSame($extension, $extended->withNote(new Note('x'))->extensions()->get(stdClass::class));
    }

    /**
     * @param  list<InvoiceLine>|null  $lines
     */
    private function minimalInvoice(string $number = 'A-1', ?array $lines = null): Invoice
    {
        return new Invoice(
            number: $number,
            issueDate: new DateTimeImmutable('2026-01-15'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party('Seller'),
            buyer: new Party('Buyer'),
            lines: $lines ?? [new InvoiceLine('1', 1, UnitCode::C62, new Price(100), new Item('Thing'), new LineVat(VatCategory::StandardRate, 27))],
        );
    }
}

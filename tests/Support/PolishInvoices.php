<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\Exemption;
use IntrAgile\EInvoicing\Poland\ExemptionBasis;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;

/**
 * Polish FA(3) invoices for tests, with synthetic parties and checksum-valid synthetic NIPs.
 *
 * Every factory returns an invoice completed with TotalsCalculator and carrying the Polish
 * invoice extension.
 */
final class PolishInvoices
{
    public const SELLER_VAT = 'PL2222222222';

    public const BUYER_VAT = 'PL1111111111';

    public const JST_UNIT_NIP = '3333333333';

    public const ACCOUNT = 'PL61109010140000071219812874';

    /**
     * Domestic B2B invoice in PLN: two lines at 23 %, one at 8 %, due date and credit transfer.
     */
    public static function domestic(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [
                self::line('1', 'Usługa wdrożeniowa', '10', UnitCode::HUR, '150.00', VatCategory::StandardRate, '23'),
                self::line('2', 'Kabel sieciowy', '4', UnitCode::H87, '12.50', VatCategory::StandardRate, '23'),
                self::line('3', 'Podręcznik', '2', UnitCode::H87, '45.90', VatCategory::StandardRate, '8'),
            ],
            polish: new PolishInvoice(placeOfIssue: 'Warszawa'),
        ));
    }

    /**
     * Consumer invoice: the buyer has no identifier (BrakID).
     */
    public static function consumer(): Invoice
    {
        return self::complete(self::draft(
            buyer: new Party(
                name: 'Jan Kowalski',
                address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Polna 1', city: 'Kraków', postCode: '30-001'),
            ),
            lines: [self::line('1', 'Czajnik elektryczny', '1', UnitCode::H87, '99.00', VatCategory::StandardRate, '23')],
            polish: new PolishInvoice,
        ));
    }

    /**
     * Intra-Community supply of goods to a German buyer (0 WDT).
     */
    public static function euBuyer(): Invoice
    {
        return self::complete(self::draft(
            buyer: new Party(
                name: 'Käufer GmbH',
                vatIdentifier: 'DE123456789',
                address: new PostalAddress(country: CountryCode::DE, line1: 'Hauptstraße 5', city: 'Berlin', postCode: '10115'),
            ),
            lines: [self::line('1', 'Palety drewniane', '20', UnitCode::H87, '40.00', VatCategory::IntraCommunitySupply, '0')],
            polish: new PolishInvoice(seller: new PolishParty(vatPrefix: CountryCode::PL)),
        ));
    }

    /**
     * Export of goods to a US buyer identified by a non-EU tax number (0 EX).
     */
    public static function exportBuyer(): Invoice
    {
        return self::complete(self::draft(
            buyer: new Party(
                name: 'Buyer Inc.',
                vatIdentifier: 'US-123',
                address: new PostalAddress(country: CountryCode::US, line1: '1 Main Street', city: 'Boston', postCode: '02108'),
            ),
            lines: [self::line('1', 'Maszyna pakująca', '1', UnitCode::H87, '25000.00', VatCategory::ExportOutsideEu, '0')],
            polish: new PolishInvoice,
        ));
    }

    /**
     * Domestic reverse charge (oo) with the P_18 annotation.
     */
    public static function domesticReverseCharge(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Złom stalowy', '1000', UnitCode::KGM, '1.20', VatCategory::ReverseCharge, '0')],
            polish: new PolishInvoice(annotations: new Annotations(reverseCharge: true)),
        ));
    }

    /**
     * VAT-exempt supply (zw) with the legal basis of the exemption.
     */
    public static function exempt(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Ubezpieczenie mienia', '1', UnitCode::C62, '1200.00', VatCategory::Exempt, '0')],
            polish: new PolishInvoice(annotations: new Annotations(
                exemption: new Exemption(ExemptionBasis::Act, 'Art. 43 ust. 1 pkt 37 ustawy'),
            )),
        ));
    }

    /**
     * Invoice for a subordinate unit of a local government body: JST = 1 and the unit as a third
     * party with role 8.
     */
    public static function jstBuyer(): Invoice
    {
        return self::complete(self::draft(
            buyer: new Party(
                name: 'Gmina Przykładowo',
                vatIdentifier: self::BUYER_VAT,
                address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Ratuszowa 1', city: 'Przykładowo', postCode: '00-950'),
            ),
            lines: [self::line('1', 'Materiały biurowe', '5', UnitCode::H87, '20.00', VatCategory::StandardRate, '23')],
            polish: new PolishInvoice(
                buyerIsJstUnit: true,
                thirdParties: [new ThirdParty(
                    party: new Party(
                        name: 'Szkoła Podstawowa nr 1',
                        vatIdentifier: 'PL'.self::JST_UNIT_NIP,
                        address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Szkolna 2', city: 'Przykładowo', postCode: '00-950'),
                    ),
                    role: ThirdPartyRole::JstRecipient,
                )],
            ),
        ));
    }

    /**
     * @return array<string, Invoice>
     */
    public static function all(): array
    {
        return [
            'domestic' => self::domestic(),
            'consumer' => self::consumer(),
            'euBuyer' => self::euBuyer(),
            'exportBuyer' => self::exportBuyer(),
            'domesticReverseCharge' => self::domesticReverseCharge(),
            'exempt' => self::exempt(),
            'jstBuyer' => self::jstBuyer(),
        ];
    }

    /**
     * @param  numeric-string  $quantity
     * @param  numeric-string  $price
     * @param  numeric-string|null  $rate
     */
    public static function line(
        string $id,
        string $name,
        string $quantity,
        UnitCode $unit,
        string $price,
        VatCategory $category,
        ?string $rate,
    ): InvoiceLine {
        return new InvoiceLine(
            id: $id,
            quantity: $quantity,
            unit: $unit,
            price: new Price($price),
            item: new Item($name),
            vat: new LineVat($category, $rate),
        );
    }

    public static function seller(): Party
    {
        return new Party(
            name: 'Sprzedawca Sp. z o.o.',
            vatIdentifier: self::SELLER_VAT,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Kwiatowa 1 m. 2', city: 'Warszawa', postCode: '00-001'),
            contact: new Contact(phone: '221234567', email: 'faktury@sprzedawca.example'),
        );
    }

    private static function polishBuyer(): Party
    {
        return new Party(
            name: 'Nabywca S.A.',
            vatIdentifier: self::BUYER_VAT,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Polna 10', city: 'Poznań', postCode: '60-001'),
        );
    }

    /**
     * @param  list<InvoiceLine>  $lines
     */
    private static function draft(Party $buyer, array $lines, PolishInvoice $polish): Invoice
    {
        return (new Invoice(
            number: 'FV/2026/11/001',
            issueDate: new DateTimeImmutable('2026-11-18'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::PLN,
            seller: self::seller(),
            buyer: $buyer,
            lines: $lines,
            dueDate: new DateTimeImmutable('2026-12-02'),
            payment: new PaymentInstructions(
                meansCode: PaymentMeansCode::C_30,
                creditTransfers: [new CreditTransfer(self::ACCOUNT)],
            ),
        ))->withExtension($polish);
    }

    private static function complete(Invoice $invoice): Invoice
    {
        return TotalsCalculator::complete($invoice);
    }

    private function __construct() {}
}

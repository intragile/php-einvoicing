<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Siret;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;

/**
 * French domestic invoices for tests, modelled on the FNFE use case 1 example.
 *
 * Every factory returns a completed invoice marked with the French extension.
 */
final class FrenchInvoices
{
    public const SELLER_SIREN = '100000009';

    public const SELLER_SIRET = '10000000900017';

    public const BUYER_SIREN = '200000008';

    public const RECOVERY_FEE = 'Indemnité forfaitaire pour frais de recouvrement en cas de retard de paiement : 40 €.';

    public const LATE_PAYMENT = "Tout retard de paiement engendre une pénalité exigible à compter de la date d'échéance, calculée sur la base de trois fois le taux d'intérêt légal.";

    public const EARLY_PAYMENT = "Les règlements reçus avant la date d'échéance ne donneront pas lieu à escompte.";

    /**
     * Domestic B2B goods invoice (BT-23 B1), 20 % VAT, SIREN, SIRET and 0225 endpoints.
     */
    public static function domesticB2b(): Invoice
    {
        return self::complete(self::draft(BillingFramework::B1));
    }

    /**
     * VAT on debits: BT-8 holds UNTDID 2005 code 3, written as 5 (UNTDID 2475) in CII.
     */
    public static function vatOnDebits(): Invoice
    {
        return self::complete(self::draft(BillingFramework::S1)->withTaxPointDateCode('3'));
    }

    /**
     * Domestic invoice with a deliver-to address (BG-15).
     */
    public static function withDeliveryAddress(): Invoice
    {
        return self::complete(self::draft(BillingFramework::B1)->withDelivery(new Delivery(
            partyName: 'LE CLIENT ENTREPOT',
            address: new PostalAddress(country: CountryCode::FR, line1: '12 impasse plus loin', city: 'NICE', postCode: '06000'),
        )));
    }

    /**
     * Domestic services invoice (BT-23 S1).
     */
    public static function services(): Invoice
    {
        return self::complete(self::draft(BillingFramework::S1));
    }

    /**
     * Credit note (381) referring to two preceding invoices with their issue dates.
     */
    public static function creditNoteWithTwoPrecedingInvoices(): Invoice
    {
        return self::complete(self::draft(BillingFramework::B1)
            ->withNumber('AV202600001')
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoices([
                new PrecedingInvoiceReference('F202600001', new DateTimeImmutable('2026-10-01')),
                new PrecedingInvoiceReference('F202600002', new DateTimeImmutable('2026-10-15')),
            ]));
    }

    /**
     * Every factory, by name.
     *
     * @return array<string, Invoice>
     */
    public static function all(): array
    {
        return [
            'domesticB2b' => self::domesticB2b(),
            'vatOnDebits' => self::vatOnDebits(),
            'withDeliveryAddress' => self::withDeliveryAddress(),
            'services' => self::services(),
            'creditNoteWithTwoPrecedingInvoices' => self::creditNoteWithTwoPrecedingInvoices(),
        ];
    }

    private static function complete(Invoice $invoice): Invoice
    {
        return TotalsCalculator::complete($invoice);
    }

    private static function draft(BillingFramework $framework): Invoice
    {
        $seller = new Party(
            name: 'LE VENDEUR',
            identifiers: [Siret::identifier(self::SELLER_SIRET)],
            legalRegistration: Siren::identifier(self::SELLER_SIREN),
            vatIdentifier: 'FR88100000009',
            electronicAddress: new ElectronicAddress(self::SELLER_SIREN, ElectronicAddressScheme::C_0225),
            address: new PostalAddress(country: CountryCode::FR, line1: "35 rue d'ici", city: 'PARIS', postCode: '75018'),
        );
        $buyer = new Party(
            name: 'LE CLIENT',
            legalRegistration: Siren::identifier(self::BUYER_SIREN),
            vatIdentifier: 'FR37200000008',
            electronicAddress: new ElectronicAddress(self::BUYER_SIREN, ElectronicAddressScheme::C_0225),
            address: new PostalAddress(country: CountryCode::FR, line1: '55 avenue de là-bas', city: 'NICE', postCode: '06000'),
        );

        $invoice = new Invoice(
            number: 'F202600003',
            issueDate: new DateTimeImmutable('2026-11-02'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: $seller,
            buyer: $buyer,
            dueDate: new DateTimeImmutable('2026-12-02'),
            buyerReference: 'BU_2516',
            paymentTerms: 'PAIEMENT 30 JOURS NET',
            notes: [
                France::recoveryFeeNote(self::RECOVERY_FEE),
                France::latePaymentPenaltyNote(self::LATE_PAYMENT),
                France::earlyPaymentDiscountNote(self::EARLY_PAYMENT),
                France::treatmentNote(Treatment::B2B),
            ],
            lines: [
                new InvoiceLine(
                    id: '1',
                    quantity: '200',
                    unit: UnitCode::HUR,
                    price: new Price(netPrice: '40.00'),
                    item: new Item(name: 'SERVICE_FOURNI1'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '20'),
                ),
                new InvoiceLine(
                    id: '2',
                    quantity: '5',
                    unit: UnitCode::DAY,
                    price: new Price(netPrice: '400.00'),
                    item: new Item(name: 'SERVICE_FOURNI2'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '20'),
                ),
            ],
        );

        return France::mark($invoice, $framework);
    }
}

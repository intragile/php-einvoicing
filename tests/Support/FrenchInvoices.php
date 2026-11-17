<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineDelivery;
use IntrAgile\EInvoicing\France\LineSeller;
use IntrAgile\EInvoicing\France\LineSubType;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Siret;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Period;
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
     * EXTENDED-CTC-FR invoice modelled on the FNFE sub-line example: GROUP line 1 holds DETAIL
     * lines 2 and 3, INFORMATION line 4 follows. Line 2 has its own seller, VAT extras and two
     * invoiced object identifiers; line 3 a preceding invoice, a delivery and a note subject code.
     *
     * @param  bool  $amountWithVat  whether line 2 carries its amount with VAT (EXT-FR-FE-184),
     *                               which UBL has no element for
     */
    public static function extendedCtcFr(bool $amountWithVat = true): Invoice
    {
        $draft = self::draft(BillingFramework::B1);
        $seller = new LineSeller(
            name: 'LE VENDEUR',
            globalId: new Identifier('587451236587', '0088'),
            legalRegistration: Siren::identifier(self::SELLER_SIREN),
            vatIdentifier: 'FR88100000009',
            country: CountryCode::FR,
        );

        $lines = [
            new InvoiceLine(
                id: '2',
                quantity: '20',
                unit: UnitCode::C62,
                price: new Price(netPrice: '10.00'),
                item: new Item(name: 'LIVRE', description: 'MA PETITE HISTOIRE'),
                vat: new LineVat(category: VatCategory::StandardRate, rate: '10'),
                objectIdentifier: new Identifier('S1', 'AVV'),
                period: new Period(start: new DateTimeImmutable('2026-10-01'), end: new DateTimeImmutable('2026-10-31')),
                extensions: Extensions::none()->with(new FrenchLine(
                    parentLineId: '1',
                    subType: LineSubType::Detail,
                    seller: $seller,
                    vatExemptionReason: 'TVA SUR LES DEBITS',
                    vatDueDateCode: '3',
                    vatAmount: '20.00',
                    amountWithVat: $amountWithVat ? '220.00' : null,
                    objectIdentifiers: [new Identifier('F202600040', 'AFL')],
                )),
            ),
            new InvoiceLine(
                id: '3',
                quantity: '20',
                unit: UnitCode::C62,
                price: new Price(netPrice: '15.00'),
                item: new Item(name: 'JOUET', description: 'DOUDOU'),
                vat: new LineVat(category: VatCategory::StandardRate, rate: '20'),
                note: 'Livraison partielle',
                extensions: Extensions::none()->with(new FrenchLine(
                    parentLineId: '1',
                    subType: LineSubType::Detail,
                    precedingInvoice: new PrecedingInvoiceReference('F202500099', new DateTimeImmutable('2025-12-15')),
                    delivery: new LineDelivery(
                        name: 'ENTREPOT NORD',
                        address: new PostalAddress(country: CountryCode::FR, line1: '5 rue du quai', city: 'LILLE', postCode: '59000'),
                        actualDeliveryDate: new DateTimeImmutable('2025-12-20'),
                    ),
                    noteSubjectCode: TextSubjectCode::AAI,
                )),
            ),
        ];
        $informational = [
            new InformationalLine(
                id: '1',
                subType: LineSubType::Group,
                item: new Item(name: 'LIVRE-JOUET', description: 'MA PETITE HISTOIRE ET DOUDOU'),
                quantity: '20',
                unit: UnitCode::C62,
                price: new Price(netPrice: '25.00'),
                netAmount: '500.00',
            ),
            new InformationalLine(
                id: '4',
                subType: LineSubType::Information,
                item: new Item(name: 'CADEAU', description: 'PAQUET CADEAU'),
                quantity: '1',
                unit: UnitCode::C62,
                netAmount: '0.00',
            ),
        ];

        $invoice = France::mark($draft->withLines($lines), BillingFramework::B1, FrenchProfile::ExtendedCtcFr)
            ->withExtension(new FrenchInvoice(FrenchProfile::ExtendedCtcFr, $informational, ['1', '2', '3', '4']));

        return self::complete($invoice);
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

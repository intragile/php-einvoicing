<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Model\VatBreakdown;

/**
 * Shared, internally consistent invoices for tests.
 */
final class InvoiceFactory
{
    /**
     * An invoice with one line per VAT situation that EN 16931 allows together: S 27 %, S 5 %,
     * E, AE, K and Z.
     *
     * Line net amounts, the S and Z breakdown groups and the totals are left for
     * TotalsCalculator::complete(). The caller-supplied breakdown entries carry the exemption
     * reasons that E, AE and K require (BR-E-10, BR-AE-10, BR-IC-10); their amounts equal what
     * the calculator derives from the lines. Seller and Buyer VAT identifiers satisfy BR-AE-02 and
     * BR-IC-02; the actual delivery date and deliver-to country satisfy BR-IC-11 and BR-IC-12.
     *
     * Expected after complete(): BT-106 = BT-109 = 2300.00, BT-110 = 299.50, BT-112 = BT-115 = 2599.50.
     */
    public static function mixedVat(): Invoice
    {
        $seller = new Party(
            name: 'Példa Gépgyártó Kft.',
            vatIdentifier: 'HU12345676',
            address: new PostalAddress(country: CountryCode::HU, line1: 'Fő utca 1.', city: 'Budapest', postCode: '1011'),
        );
        $buyer = new Party(
            name: 'Beispiel Maschinenbau GmbH',
            vatIdentifier: 'DE123456789',
            address: new PostalAddress(country: CountryCode::DE, line1: 'Hauptstraße 5', city: 'München', postCode: '80331'),
        );

        return new Invoice(
            number: 'MIX-2026-0001',
            issueDate: new DateTimeImmutable('2026-11-11'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: $seller,
            buyer: $buyer,
            dueDate: new DateTimeImmutable('2026-12-11'),
            buyerReference: 'PO-4711',
            processControl: new ProcessControl(specificationIdentifier: 'urn:cen.eu:en16931:2017'),
            delivery: new Delivery(
                actualDeliveryDate: new DateTimeImmutable('2026-11-09'),
                address: new PostalAddress(country: CountryCode::DE, city: 'München', postCode: '80331'),
            ),
            lines: [
                new InvoiceLine(
                    id: '1',
                    quantity: '10',
                    unit: UnitCode::HUR,
                    price: new Price(netPrice: '120.00'),
                    item: new Item(name: 'On-site commissioning in Budapest'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '27'),
                    allowances: [new LineAllowance(amount: '100.00', reason: 'Loyalty discount')],
                ),
                new InvoiceLine(
                    id: '2',
                    quantity: '4',
                    unit: UnitCode::H87,
                    price: new Price(netPrice: '12.50'),
                    item: new Item(name: 'Operator handbook (printed)'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '5'),
                ),
                new InvoiceLine(
                    id: '3',
                    quantity: '1',
                    unit: UnitCode::C62,
                    price: new Price(netPrice: '300.00'),
                    item: new Item(name: 'Accredited safety training course'),
                    vat: new LineVat(category: VatCategory::Exempt, rate: '0'),
                ),
                new InvoiceLine(
                    id: '4',
                    quantity: '8',
                    unit: UnitCode::HUR,
                    price: new Price(netPrice: '45.00'),
                    item: new Item(name: 'Remote maintenance service'),
                    vat: new LineVat(category: VatCategory::ReverseCharge, rate: '0'),
                ),
                new InvoiceLine(
                    id: '5',
                    quantity: '25',
                    unit: UnitCode::H87,
                    price: new Price(netPrice: '18.40'),
                    item: new Item(name: 'Spare bearing set'),
                    vat: new LineVat(category: VatCategory::IntraCommunitySupply, rate: '0'),
                ),
                new InvoiceLine(
                    id: '6',
                    quantity: '2',
                    unit: UnitCode::H87,
                    price: new Price(netPrice: '15.00'),
                    item: new Item(name: 'Zero rated safety signage'),
                    vat: new LineVat(category: VatCategory::ZeroRated, rate: '0'),
                ),
            ],
            vatBreakdown: [
                new VatBreakdown(
                    taxableAmount: '300.00',
                    taxAmount: '0.00',
                    category: VatCategory::Exempt,
                    rate: '0',
                    exemptionReason: 'Exempt under Article 132(1)(i) of Directive 2006/112/EC',
                ),
                new VatBreakdown(
                    taxableAmount: '360.00',
                    taxAmount: '0.00',
                    category: VatCategory::ReverseCharge,
                    rate: '0',
                    exemptionReason: 'Reverse charge',
                    exemptionReasonCode: VatExemptionReason::VATEX_EU_AE,
                ),
                new VatBreakdown(
                    taxableAmount: '460.00',
                    taxAmount: '0.00',
                    category: VatCategory::IntraCommunitySupply,
                    rate: '0',
                    exemptionReason: 'Intra-community supply',
                    exemptionReasonCode: VatExemptionReason::VATEX_EU_IC,
                ),
            ],
        );
    }

    /**
     * An invoice whose lines and document charge are all "Not subject to VAT" (O).
     *
     * Satisfies BR-O-01 to BR-O-14: no Seller, tax representative or Buyer VAT identifier, no VAT
     * rate on O lines or charges, a single O breakdown with the exemption reason (BR-O-10) and no
     * other category. The Seller is identified by its legal registration (BR-CO-26).
     *
     * Expected after complete(): BT-106 = 211.00, BT-108 = 5.00, BT-109 = BT-112 = BT-115 = 216.00,
     * BT-110 = 0.00.
     */
    public static function notSubjectToVat(): Invoice
    {
        return new Invoice(
            number: 'NSV-2026-0001',
            issueDate: new DateTimeImmutable('2026-11-11'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party(
                name: 'Példa Egyesület',
                legalRegistration: new Identifier(value: '01-02-0001234'),
                address: new PostalAddress(country: CountryCode::HU, line1: 'Kossuth tér 2.', city: 'Debrecen', postCode: '4024'),
            ),
            buyer: new Party(
                name: 'Kovács Anna',
                address: new PostalAddress(country: CountryCode::HU, city: 'Szeged', postCode: '6720'),
            ),
            dueDate: new DateTimeImmutable('2026-11-25'),
            processControl: new ProcessControl(specificationIdentifier: 'urn:cen.eu:en16931:2017'),
            lines: [
                new InvoiceLine(
                    id: '1',
                    quantity: '1',
                    unit: UnitCode::C62,
                    price: new Price(netPrice: '120.00'),
                    item: new Item(name: 'Annual membership fee 2027'),
                    vat: new LineVat(category: VatCategory::NotSubjectToVat),
                ),
                new InvoiceLine(
                    id: '2',
                    quantity: '2',
                    unit: UnitCode::C62,
                    price: new Price(netPrice: '45.50'),
                    item: new Item(name: 'Members-only workshop ticket'),
                    vat: new LineVat(category: VatCategory::NotSubjectToVat),
                ),
            ],
            charges: [
                new DocumentCharge(amount: '5.00', vatCategory: VatCategory::NotSubjectToVat, reason: 'Postage'),
            ],
            vatBreakdown: [
                new VatBreakdown(
                    taxableAmount: '216.00',
                    taxAmount: '0.00',
                    category: VatCategory::NotSubjectToVat,
                    exemptionReason: 'Not subject to VAT',
                    exemptionReasonCode: VatExemptionReason::VATEX_EU_O,
                ),
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use DOMDocument;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\CorrectionEffect;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\Exemption;
use IntrAgile\EInvoicing\Poland\ExemptionBasis;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\MarginScheme;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\OrderLine;
use IntrAgile\EInvoicing\Poland\PartialAdvance;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\TransactionTerms;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use LogicException;

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

    /** KSeF number of the corrected invoice of brochure example 14, with its checksum recomputed. */
    public const CORRECTED_KSEF_NUMBER = '9999999999-20260714-D5FB0C9ED490-19';

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
     * Corrective invoice (KOR, derived from type code 384) for goods returned under invoice
     * FV/110/07/2026, written as state-before and state-after rows. The before rows are held in
     * storno form, so the totals are the differences. The corrected invoice is also the preceding
     * invoice reference (BG-3).
     */
    public static function correctionBeforeAfter(): Invoice
    {
        $before = static fn (InvoiceLine $line): InvoiceLine => $line
            ->withQuantity($line->quantity->negated())
            ->withExtension(new PolishLine(unitLabel: 'szt.', stateBefore: true));
        $after = static fn (InvoiceLine $line): InvoiceLine => $line->withExtension(new PolishLine(unitLabel: 'szt.'));

        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [
                $before(self::line('1', 'Koc polarowy', '3', UnitCode::H87, '90.00', VatCategory::StandardRate, '23')),
                $after(self::line('2', 'Koc polarowy', '2', UnitCode::H87, '90.00', VatCategory::StandardRate, '23')),
                $before(self::line('3', 'Pościel bawełniana', '5', UnitCode::H87, '120.00', VatCategory::StandardRate, '23')),
                $after(self::line('4', 'Pościel bawełniana', '3', UnitCode::H87, '120.00', VatCategory::StandardRate, '23')),
            ],
            polish: new PolishInvoice(correction: new Correction(
                invoices: [new CorrectedInvoice('FV/110/07/2026', new DateTimeImmutable('2026-07-14'), KsefNumber::parse(self::CORRECTED_KSEF_NUMBER))],
                reason: 'Zwrot towaru',
                effect: CorrectionEffect::CorrectionDate,
            )),
            number: 'FK/1/09/2026',
            issueDate: '2026-09-10',
            type: InvoiceTypeCode::C_384,
        )->withPrecedingInvoices([new PrecedingInvoiceReference('FV/110/07/2026', new DateTimeImmutable('2026-07-14'))]));
    }

    /**
     * Advance invoice (ZAL) in euro documenting three partial advance payments with their
     * exchange rates; no FaWiersz, the 23 % bucket is carried by one bucket line, and the VAT in
     * PLN is supplied (P_14_1W, with VAT accounting currency PLN and BT-111).
     */
    public static function advanceInEuro(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::bucketLine('3252.03', '23')],
            polish: new PolishInvoice(
                kind: InvoiceKind::Zal,
                partialAdvances: [
                    new PartialAdvance(new DateTimeImmutable('2026-09-10'), '500', '4.4512'),
                    new PartialAdvance(new DateTimeImmutable('2026-09-15'), '2500', '4.4724'),
                    new PartialAdvance(new DateTimeImmutable('2026-09-20'), '1000', '4.5148'),
                ],
                order: new Order('10000.00', [new OrderLine(
                    name: 'Linia do pakowania',
                    unitLabel: 'szt.',
                    quantity: '1',
                    unitNetPrice: '8130.08',
                    netAmount: '8130.08',
                    vatAmount: '1869.92',
                    rateCode: VatRateCode::Rate23,
                )]),
                vatInPln: [1 => '3351.17'],
            ),
            number: 'FZ/7/09/2026',
            issueDate: '2026-09-20',
            type: InvoiceTypeCode::C_386,
            currency: CurrencyCode::EUR,
        )->withTaxCurrency(CurrencyCode::PLN)->withTotals(new DocumentTotals('0', '0', '0', '0', totalVatInAccountingCurrency: '3351.17')));
    }

    /**
     * Settlement invoice (ROZ) closing two advance invoices issued outside KSeF; P_15 is the
     * amount still due after the advances (BT-115).
     */
    public static function settlementOfAdvances(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Regał magazynowy', '10', UnitCode::H87, '1000.00', VatCategory::StandardRate, '23')],
            polish: new PolishInvoice(
                kind: InvoiceKind::Roz,
                advanceInvoices: [
                    new AdvanceInvoiceReference(number: 'FZ/123/07/2025'),
                    new AdvanceInvoiceReference(number: 'FZ/133/08/2025'),
                ],
            ),
            number: 'FR/1/09/2026',
            issueDate: '2026-09-10',
        ), '8610.00');
    }

    /**
     * Supply of a used car under the margin scheme (P_PMarzy_3_1): the line carries no VAT rate
     * and feeds P_13_11.
     */
    public static function usedCarMargin(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Samochód osobowy używany', '1', UnitCode::H87, '50000.00', VatCategory::NotSubjectToVat, null)
                ->withExtension(new PolishLine(margin: true))],
            polish: new PolishInvoice(annotations: new Annotations(margin: MarginScheme::UsedGoods)),
        ));
    }

    /**
     * Legal service of 200.00 net at 23 % with a charge of 17 and a deduction of 300 in
     * Rozliczenie: 246 + 17 - 300 leaves 37 to settle (DoRozliczenia).
     */
    public static function withSettlement(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Stała obsługa prawna i biurowa', '1', UnitCode::C62, '200.00', VatCategory::StandardRate, '23')],
            polish: new PolishInvoice(settlement: new Settlement(
                charges: [new SettlementEntry('17.00', 'Zwrot kosztów - opłata skarbowa')],
                deductions: [new SettlementEntry('300.00', 'Rozliczenie salda klienta')],
            )),
        ));
    }

    /**
     * Split payment (P_18A) for annex 15 goods (P_12_Zal_15) with a gross total above 15000 PLN.
     */
    public static function splitPayment(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Komputer przenośny', '5', UnitCode::H87, '2500.00', VatCategory::StandardRate, '23')
                ->withExtension(new PolishLine(annex15: true))],
            polish: new PolishInvoice(annotations: new Annotations(splitPayment: true)),
        ));
    }

    /**
     * Transaction terms with the transport block and the attachment (Zalacznik) of the official
     * templates, carried verbatim; the listed order is also the purchase order reference (BT-13).
     */
    public static function withTransportAndAttachment(): Invoice
    {
        return self::complete(self::draft(
            buyer: self::polishBuyer(),
            lines: [self::line('1', 'Energia elektryczna czynna', '20', UnitCode::KWH, '0.90', VatCategory::StandardRate, '23')],
            polish: new PolishInvoice(
                transactionTerms: new TransactionTerms(
                    orders: [new DocumentReference(new DateTimeImmutable('2026-01-26'), '4354343')],
                    batchNumbers: ['2312323/2026'],
                    deliveryTerms: 'CIP',
                    transports: [self::officialFragment('invoice-template-fa-3-with-custom-Subject3.xml', 'Transport')],
                ),
                attachment: self::officialFragment('invoice-template-fa-3-with-attachment.xml', 'Zalacznik'),
            ),
        )->withPurchaseOrderReference('4354343'));
    }

    /**
     * The invoices of every FA(3) invoice kind and special block.
     *
     * @return array<string, Invoice>
     */
    public static function kinds(): array
    {
        return [
            'correctionBeforeAfter' => self::correctionBeforeAfter(),
            'advanceInEuro' => self::advanceInEuro(),
            'settlementOfAdvances' => self::settlementOfAdvances(),
            'usedCarMargin' => self::usedCarMargin(),
            'withSettlement' => self::withSettlement(),
            'splitPayment' => self::splitPayment(),
            'withTransportAndAttachment' => self::withTransportAndAttachment(),
        ];
    }

    /**
     * A synthetic line standing for one P_13 bucket of an invoice without FaWiersz rows.
     *
     * @param  numeric-string  $net
     * @param  numeric-string  $rate
     */
    public static function bucketLine(string $net, string $rate): InvoiceLine
    {
        return self::line('1', 'P_13_1', '1', UnitCode::C62, $net, VatCategory::StandardRate, $rate)
            ->withExtension(new PolishLine(bucketLine: true));
    }

    /**
     * One element of an official template, as a verbatim fragment in the FA(3) namespace.
     */
    public static function officialFragment(string $template, string $name): RawFragment
    {
        $source = new DOMDocument;
        $source->loadXML(PolishSamples::official($template));
        $element = $source->getElementsByTagNameNS(Fa3Format::NS, $name)->item(0);
        if ($element === null) {
            throw new LogicException(sprintf('The template %s has no %s element.', $template, $name));
        }
        $fragment = new DOMDocument;
        $fragment->appendChild($fragment->importNode($element, true));

        return new RawFragment((string) $fragment->saveXML($fragment->documentElement));
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
    private static function draft(
        Party $buyer,
        array $lines,
        PolishInvoice $polish,
        string $number = 'FV/2026/11/001',
        string $issueDate = '2026-11-18',
        InvoiceTypeCode $type = InvoiceTypeCode::C_380,
        CurrencyCode $currency = CurrencyCode::PLN,
    ): Invoice {
        return (new Invoice(
            number: $number,
            issueDate: new DateTimeImmutable($issueDate),
            type: $type,
            currency: $currency,
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

    /**
     * @param  numeric-string|null  $paidAmount
     */
    private static function complete(Invoice $invoice, ?string $paidAmount = null): Invoice
    {
        return TotalsCalculator::complete($invoice, $paidAmount);
    }

    private function __construct() {}
}

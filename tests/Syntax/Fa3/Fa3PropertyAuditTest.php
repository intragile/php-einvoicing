<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Every constructor property of the model classes the FA(3) writer reads is classified: written
 * to an FA(3) element, carried as a fixed-key additional description (DodatkowyOpis), refused by
 * the writer guard, or a mix of these depending on the party or the value. A property added to
 * one of these classes fails this test until it is classified, so no new value can be dropped
 * silently by the FA(3) writer.
 *
 * Each entry reads "<written|carried|refused|marker>[ or ...]: <where or why>".
 */
final class Fa3PropertyAuditTest extends TestCase
{
    private const CLASSIFICATION = [
        Party::class => [
            'name' => 'written: Nazwa',
            'tradingName' => 'carried: BT-28 and BT-45 for seller and buyer; refused: for a third party or a party before the correction',
            'identifiers' => 'refused: BT-29, BT-46 and those of third parties',
            'legalRegistration' => 'written: NrID of a buyer or third party without a VAT identifier; refused: for the seller, next to a VAT identifier or an internal identifier (IDWew)',
            'vatIdentifier' => 'written: NIP, KodUE with NrVatUE, or NrID; refused: when malformed or next to an internal identifier (IDWew)',
            'taxRegistration' => 'refused: BT-32 and its buyer and third-party counterparts',
            'additionalLegalInformation' => 'refused: BT-33 and its buyer and third-party counterparts',
            'electronicAddress' => 'refused: BT-34, BT-49 and the third-party electronic address',
            'address' => 'written: Adres (KodKraju, AdresL1, AdresL2)',
            'contact' => 'written: DaneKontaktowe (e-mail, phone); carried: the contact name of seller and buyer (BT-41, BT-56); refused: the contact name of a third party, the contact of a party before the correction',
        ],
        PostalAddress::class => [
            'country' => 'written: KodKraju',
            'line1' => 'written: AdresL1',
            'line2' => 'written: AdresL1, joined with ", "',
            'line3' => 'written: AdresL1, joined with ", "',
            'city' => 'written: AdresL2 with the post code',
            'postCode' => 'written: AdresL2 with the city',
            'subdivision' => 'refused: BT-39, BT-54 and every other FA(3) address',
        ],
        Contact::class => [
            'name' => 'carried: BT-41, BT-56; refused: for a third party',
            'phone' => 'written: DaneKontaktowe/Telefon',
            'email' => 'written: DaneKontaktowe/Email',
        ],
        PaymentInstructions::class => [
            'meansCode' => 'written: FormaPlatnosci, else PlatnoscInna with OpisPlatnosci; PolishInvoice::$paymentForm replaces it by design',
            'meansText' => 'written: OpisPlatnosci of PlatnoscInna; refused: when a payment form is written',
            'remittanceInformation' => 'carried: BT-83',
            'creditTransfers' => 'written: RachunekBankowy; PolishInvoice::$bankAccounts replaces them by design',
            'card' => 'refused: BG-18',
            'directDebit' => 'refused: BG-19',
        ],
        InvoiceLine::class => [
            'id' => 'written: UU_ID when it differs from NrWierszaFa',
            'quantity' => 'written: P_8B; refused: other than 1 on a bucket line',
            'unit' => 'written: P_8A; carried: BT-130 beside a unit label; refused: other than C62 on a bucket line',
            'price' => 'written: P_9A (see Price)',
            'item' => 'written: P_7, Indeks, GTIN (see Item)',
            'vat' => 'written: P_12 and the summary bucket P_13_x',
            'netAmount' => 'written: P_11',
            'note' => 'carried: BT-127; refused: on a bucket line',
            'objectIdentifier' => 'carried: BT-128; refused: its scheme, or on a bucket line',
            'orderLineReference' => 'carried: BT-132; refused: on a bucket line',
            'buyerAccountingReference' => 'carried: BT-133; refused: on a bucket line',
            'period' => 'refused: BG-26',
            'allowances' => 'written: P_10; carried: BT-136 of several allowances, BT-137 to BT-140; refused: on a bucket line',
            'charges' => 'refused: BG-28',
            'extensions' => 'written: the PolishLine extension (see PolishLine)',
        ],
        Item::class => [
            'name' => 'written: P_7; refused: other than the bucket element on a bucket line',
            'description' => 'carried: BT-154; refused: on a bucket line',
            'sellersIdentifier' => 'written: Indeks; refused: on a bucket line',
            'buyersIdentifier' => 'carried: BT-156; refused: on a bucket line',
            'standardIdentifier' => 'written: GTIN (scheme 0160); refused: any other scheme, or on a bucket line',
            'classifications' => 'refused: BT-158',
            'originCountry' => 'carried: BT-159; refused: on a bucket line',
            'attributes' => 'refused: BG-32',
        ],
        Price::class => [
            'netPrice' => 'written: P_9A',
            'discount' => 'refused: BT-147',
            'grossPrice' => 'refused: BT-148',
            'baseQuantity' => 'refused: BT-149 other than 1',
            'baseQuantityUnit' => 'refused: BT-150 other than the line unit',
        ],
        PolishInvoice::class => [
            'kind' => 'written: RodzajFaktury',
            'annotations' => 'written: Adnotacje',
            'seller' => 'written: Podmiot1 extras (see PolishParty)',
            'buyer' => 'written: Podmiot2 extras (see PolishParty)',
            'buyerIsJstUnit' => 'written: JST; refused: without a third party in role 8',
            'buyerIsGvMember' => 'written: GV; refused: without a third party in role 10',
            'thirdParties' => 'written: Podmiot3 (see ThirdParty)',
            'placeOfIssue' => 'written: P_1M',
            'warehouseDocuments' => 'written: WZ',
            'correction' => 'written: the correction block; refused: on a kind that is not a correction',
            'partialAdvances' => 'written: ZaliczkaCzesciowa; refused: on other kinds',
            'advanceInvoices' => 'written: FakturaZaliczkowa; refused: on other kinds',
            'order' => 'written: Zamowienie; refused: on other kinds',
            'vatInPln' => 'written: P_14_xW; refused: on a PLN invoice or for an unused bucket',
            'exchangeRate' => 'written: KursWalutyZ; refused: on other kinds or a PLN invoice',
            'descriptions' => 'written: DodatkowyOpis',
            'settlement' => 'written: Rozliczenie',
            'paymentForm' => 'written: FormaPlatnosci',
            'bankAccounts' => 'written: RachunekBankowy',
            'factorBankAccounts' => 'written: RachunekBankowyFaktora',
            'footer' => 'written: Stopka',
            'transactionTerms' => 'written: WarunkiTransakcji',
            'authorizedParty' => 'written: PodmiotUpowazniony (verbatim)',
            'attachment' => 'written: Zalacznik (verbatim)',
            'createdAt' => 'written: DataWytworzeniaFa',
            'systemInfo' => 'written: SystemInfo',
            'article109Invoice' => 'written: FP',
            'relatedParties' => 'written: TP',
            'exciseRefund' => 'written: ZwrotAkcyzy',
        ],
        PolishLine::class => [
            'rateCode' => 'written: P_12; refused: when it stands for another category or rate bucket',
            'unitLabel' => 'written: P_8A; refused: on a bucket line',
            'supplyDate' => 'written: P_6A; refused: on a bucket line',
            'grossUnitPrice' => 'written: P_9B; refused: on a bucket line',
            'grossAmount' => 'written: P_11A; refused: on a bucket line',
            'vatAmount' => 'written: P_11Vat; refused: on a bucket line',
            'ossRate' => 'written: P_12_XII with the bucket P_13_5; refused: with an override, a margin flag, another category or rate, or on a bucket line',
            'annex15' => 'written: P_12_Zal_15; refused: on a bucket line',
            'excise' => 'written: KwotaAkcyzy; refused: on a bucket line',
            'gtu' => 'written: GTU; refused: on a bucket line',
            'procedure' => 'written: Procedura; refused: on a bucket line',
            'exchangeRate' => 'written: KursWaluty; refused: on a bucket line',
            'stateBefore' => 'written: StanPrzed; refused: on other kinds',
            'margin' => 'written: no P_12 and the bucket P_13_11; refused: without the margin annotation',
            'bucketLine' => 'written: the bucket amounts only (P_13_x), never a row; refused: on kinds with rows',
            'pkwiu' => 'written: PKWiU; refused: on a bucket line',
            'cn' => 'written: CN; refused: on a bucket line',
            'pkob' => 'written: PKOB; refused: on a bucket line',
            'unitOmitted' => 'marker: leaves P_8A out',
            'quantityOmitted' => 'marker: leaves P_8B out',
            'unitPriceOmitted' => 'marker: leaves P_9A out',
            'rateCodeOmitted' => 'marker: leaves P_12 out',
        ],
        PolishParty::class => [
            'eori' => 'written: NrEORI',
            'contacts' => 'written: DaneKontaktowe',
            'customerNumber' => 'written: NrKlienta of buyer and third party; refused: for the seller',
            'buyerId' => 'written: IDNabywcy of buyer and third party; refused: for the seller',
            'vatPrefix' => 'written: PrefiksPodatnika of the seller (PL); refused: other prefixes, for the buyer or a third party',
            'status' => 'written: StatusInfoPodatnika of the seller; refused: for the buyer or a third party',
            'correspondenceAddress' => 'written: AdresKoresp; refused: its subdivision',
            'gln' => 'written: Adres/GLN; refused: for a party without an address',
        ],
        ThirdParty::class => [
            'party' => 'written: Podmiot3 identification, address and contacts (see Party)',
            'role' => 'written: Rola',
            'otherRole' => 'written: RolaInna with OpisRoli',
            'internalId' => 'written: IDWew; refused: next to a VAT identifier or a legal registration',
            'share' => 'written: Udzial',
            'extras' => 'written: Podmiot3 extras (see PolishParty)',
        ],
    ];

    /**
     * @return array<string, array{class-string}>
     */
    public static function classes(): array
    {
        $classes = [];
        foreach (array_keys(self::CLASSIFICATION) as $class) {
            $classes[substr($class, (int) strrpos($class, '\\') + 1)] = [$class];
        }

        return $classes;
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('classes')]
    public function test_every_constructor_property_is_written_carried_or_refused(string $class): void
    {
        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod($class, '__construct'))->getParameters(),
        );
        $classified = self::CLASSIFICATION[$class];

        self::assertSame([], array_values(array_diff($parameters, array_keys($classified))), 'Unclassified properties of '.$class);
        self::assertSame([], array_values(array_diff(array_keys($classified), $parameters)), 'Classified properties that no longer exist in '.$class);
        foreach ($classified as $property => $entry) {
            self::assertMatchesRegularExpression('/\A(written|carried|refused|marker): /', $entry, $class.'::$'.$property);
        }
    }
}

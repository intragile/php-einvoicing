<?php

declare(strict_types=1);

// Negative cases for Fa3Rules; the format is documented on Fa3RulesTest. Every edited document
// stays valid against the FA(3) schema.

$fa = '/fa:Faktura/fa:Fa';
$annotations = "{$fa}/fa:Adnotacje";
$settlement = "{$fa}/fa:Rozliczenie";
$correctedInvoice = '<fa:DaneFaKorygowanej><fa:DataWystFaKorygowanej>2026-07-14</fa:DataWystFaKorygowanej>'
    .'<fa:NrFaKorygowanej>FV/110/07/2026</fa:NrFaKorygowanej><fa:NrKSeFN>1</fa:NrKSeFN></fa:DaneFaKorygowanej>';

$case = static fn (string $rule, string $base, array $edits): array => [
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
];

return [
    // A corrective invoice without the data of the invoice it corrects.
    $case('PL-FA3-01', 'correction-before-after.xml', [
        ["{$fa}/fa:PrzyczynaKorekty", 'remove'],
        ["{$fa}/fa:TypKorekty", 'remove'],
        ["{$fa}/fa:DaneFaKorygowanej", 'remove'],
    ]),
    // A basic invoice carrying correction data.
    $case('PL-FA3-02', 'domestic', [
        ["{$fa}/fa:FaWiersz[1]", 'before', '<fa:PrzyczynaKorekty>Zwrot towaru</fa:PrzyczynaKorekty>'.$correctedInvoice],
    ]),
    // A settlement invoice carrying the amount before a correction.
    $case('PL-FA3-02', 'settlement-roz.xml', [
        ["{$fa}/fa:FakturaZaliczkowa[1]", 'before', $correctedInvoice.'<fa:P_15ZK>3690.00</fa:P_15ZK>'],
    ]),
    // The amount before the correction on a plain corrective invoice.
    $case('PL-FA3-03', 'correction-before-after.xml', [
        ["{$fa}/fa:FaWiersz[1]", 'before', '<fa:P_15ZK>1230.00</fa:P_15ZK>'],
    ]),
    // A state-before row on a basic invoice.
    $case('PL-FA3-04', 'domestic', [
        ["{$fa}/fa:FaWiersz[1]", 'append', '<fa:StanPrzed>1</fa:StanPrzed>'],
    ]),
    // A basic invoice without rows.
    $case('PL-FA3-05', 'domestic', [
        ["{$fa}/fa:FaWiersz", 'remove'],
    ]),
    // A settlement invoice without rows.
    $case('PL-FA3-05', 'settlement-roz.xml', [
        ["{$fa}/fa:FaWiersz", 'remove'],
    ]),
    // A corrective invoice without rows that names no discount period.
    $case('PL-FA3-06', 'correction-before-after.xml', [
        ["{$fa}/fa:FaWiersz", 'remove'],
    ]),
    // An advance invoice without the order.
    $case('PL-FA3-07', 'advance-eur.xml', [
        ["{$fa}/fa:Zamowienie", 'remove'],
    ]),
    // A settlement invoice that names no advance invoice.
    $case('PL-FA3-08', 'settlement-roz.xml', [
        ["{$fa}/fa:FakturaZaliczkowa", 'remove'],
    ]),
    // A EUR invoice without the PLN VAT of its standard-rate bucket.
    $case('PL-FA3-09', 'advance-eur.xml', [
        ["{$fa}/fa:P_14_1W", 'remove'],
    ]),
    // A PLN invoice with a PLN VAT field.
    $case('PL-FA3-10', 'consumer', [
        ["{$fa}/fa:P_15", 'before', '<fa:P_14_1W>22.77</fa:P_14_1W>'],
    ]),
    // A charge total that is not the sum of the charges.
    $case('PL-FA3-11', 'rozliczenie.xml', [
        ["{$settlement}/fa:SumaObciazen", 'set', '18.00'],
    ]),
    // A deduction total that is not the sum of the deductions.
    $case('PL-FA3-11', 'invoice-template-fa-3-with-custom-Subject3.xml', [
        ["{$settlement}/fa:SumaOdliczen", 'set', '999'],
    ]),
    // Brochure example 24 with the balance off by one.
    $case('PL-FA3-12', 'rozliczenie.xml', [
        ["{$settlement}/fa:DoRozliczenia", 'set', '36.00'],
    ]),
    // An overpaid balance reported as an amount due.
    $case('PL-FA3-12', 'rozliczenie.xml', [
        ["{$settlement}/fa:DoRozliczenia", 'rename', 'fa:DoZaplaty'],
    ]),
    // An amount due that ignores the deduction.
    $case('PL-FA3-12', 'invoice-template-fa-3-with-custom-Subject3.xml', [
        ["{$settlement}/fa:DoZaplaty", 'set', '64279.92'],
    ]),
    // A subordinate unit of a local government with no third party in role 8.
    $case('PL-FA3-13', 'jstBuyer', [
        ['/fa:Faktura/fa:Podmiot3/fa:Rola', 'set', '2'],
    ]),
    // A VAT group member with no third party in role 10.
    $case('PL-FA3-13', 'domestic', [
        ['/fa:Faktura/fa:Podmiot2/fa:GV', 'set', '1'],
    ]),
    // A self-billed invoice naming the buyer as issuer in Podmiot3.
    $case('PL-FA3-14', 'jstBuyer', [
        ['/fa:Faktura/fa:Podmiot2/fa:JST', 'set', '2'],
        ['/fa:Faktura/fa:Podmiot3/fa:Rola', 'set', '5'],
        ["{$annotations}/fa:P_17", 'set', '1'],
    ]),
    // The corrected invoice's KSeF number as printed in brochure example 14 (checksum fails).
    $case('PL-FA3-15', 'correction-before-after.xml', [
        ["{$fa}/fa:DaneFaKorygowanej/fa:NrKSeFFaKorygowanej", 'set', '9999999999-20260714-D5FB0C9ED490-9A'],
    ]),
    // An advance invoice reference whose KSeF number fails the checksum.
    $case('PL-FA3-15', 'settlement-roz.xml', [
        ["{$fa}/fa:FakturaZaliczkowa[1]", 'before', '<fa:FakturaZaliczkowa><fa:NrKSeFFaZaliczkowej>9999999999-20260714-D5FB0C9ED490-9A</fa:NrKSeFFaZaliczkowej></fa:FakturaZaliczkowa>'],
    ]),
    // A common delivery date beside per-row dates.
    $case('PL-FA3-16', 'correction-before-after.xml', [
        ["{$fa}/fa:P_13_1", 'before', '<fa:P_6>2026-09-12</fa:P_6>'],
    ]),
    // A row at 8 % without the reduced-rate bucket.
    $case('PL-FA3-17', 'domestic', [
        ["{$fa}/fa:P_13_2", 'remove'],
        ["{$fa}/fa:P_14_2", 'remove'],
    ]),
    // An intra-Community supply row without the WDT bucket.
    $case('PL-FA3-17', 'euBuyer', [
        ["{$fa}/fa:P_13_6_2", 'rename', 'fa:P_13_6_1'],
    ]),
    // Exempt sales without the exemption annotation.
    $case('PL-FA3-18', 'domestic', [
        ["{$fa}/fa:P_15", 'before', '<fa:P_13_7>0.00</fa:P_13_7>'],
    ]),
    // A domestic reverse-charge row without the reverse-charge annotation.
    $case('PL-FA3-19', 'domesticReverseCharge', [
        ["{$annotations}/fa:P_18", 'set', '2'],
    ]),
    // Margin-scheme sales without the margin annotation.
    $case('PL-FA3-20', 'domestic', [
        ["{$fa}/fa:P_15", 'before', '<fa:P_13_11>0.00</fa:P_13_11>'],
    ]),
    // The split payment annotation on a PLN invoice of exactly 15,000.
    $case('PL-FA3-21', 'splitPayment', [
        ["{$fa}/fa:P_15", 'set', '15000.00'],
    ]),
];

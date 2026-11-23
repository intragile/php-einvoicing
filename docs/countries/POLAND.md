# Poland

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

How this package builds, reads and validates Polish structured invoices in the FA(3) format of
KSeF, how the shared EN 16931 model maps onto FA(3), how it sends and receives them through the
KSeF API 2.0, including offline mode, KSeF certificates and the QR codes, and what is not covered
yet. This page is a
technical guide, not legal or tax advice.

## KSeF and FA(3)

KSeF (*Krajowy System e-Faktur*) is the Polish state's central e-invoicing system. One API
receives every structured invoice, gives it a KSeF number, stores it and serves it to the buyer.
The invoice format is the national **FA(3)** XML (form code `FA (3)`, schema version `1-0E`,
namespace `http://crd.gov.pl/wzor/2025/06/25/13775/`). FA(3) is not EN 16931: it has its own
elements (`P_1`, `P_13_1`, `FaWiersz` …), its own invoice kinds and its own VAT rate codes.

The dates below come from our research notes, which cite the Ministry of Finance pages
(ksef.podatki.gov.pl). Check them against the official pages before you rely on them in a product
or a contract.

| What | Date |
|---|---|
| KSeF 2.0 is the only system; taxpayers with 2024 sales above PLN 200 million must **issue** through it, and every taxpayer must **receive** through it | 1 February 2026 |
| Every other VAT taxpayer must **issue** through KSeF | 1 April 2026 |
| Sellers with at most PLN 10,000 of invoiced sales a month must **issue** through KSeF too (until then they may issue outside it) | 1 January 2027 |
| FA(3) in force, replacing FA(2) | 1 February 2026 |

Penalties for KSeF errors apply from 1 January 2027 under the current law; a bill postponing them
to the end of 2027 had not been enacted when this page was written.

## What this release verifies

The FA(3) format is **verified offline against the official FA(3) XSD and the published brochure
rules, no official executable reference.** The KSeF transport is **live-verified on KSeF TE (API
2.10 RC); DEMO and PRODUCTION are not verified** (see
[What is verified for the KSeF transport](#what-is-verified-for-the-ksef-transport)).

- The four Ministry of Finance schema files (FA(3) 1-0E and its three base schemas) ship in
  `resources/poland/fa3-1-0E/`, byte-identical to the Ministry's documentation repository
  (CIRFMF/ksef-api, commit `d4460b6c77616c423924254d8be1cbc2858027c6`, MIT licence) and pinned by
  SHA-256 in `SOURCE.md`. They resolve without network access.
- No official executable FA(3) rules exist (no Schematron, no reference validator). The brochure
  rules the schema cannot express are implemented as 21 PHP rules, each citing the page of the
  brochure ("Broszura informacyjna dotycząca struktury logicznej FA(3)", Ministry of Finance,
  August 2026) it comes from; see [Validate](#validate).
- A valid corpus of 22 documents passes the schema and the rules with no finding: the three FA(3)
  sample invoices of the Ministry's C# client, our re-creations of brochure examples 13, 14 + 23,
  16, 21 and 24, and 14 invoices written by `Fa3Writer` from the test factories (one per kind and
  special block). The samples and the re-creations read into the model and write back with the
  same field values; each of the 21 rules has negative cases that fire it alone.
- FA(3) invoices written by `Fa3Writer` were accepted by KSeF TE with a KSeF number, including a
  correction that references the KSeF number of the corrected invoice.

## Build a Polish invoice

An `Invoice` in PLN with a Polish seller is enough for a plain VAT invoice. `Fa3Writer` writes
it; the seller NIP is the seller VAT identifier (BT-31) without its `PL` prefix. The Polish data
EN 16931 does not carry goes into two extensions:

- `PolishInvoice` (on the invoice): the invoice kind, the annotations (`Annotations`: cash
  method, self-billing, reverse charge, split payment, exemption with its legal basis, new means
  of transport, margin scheme), Polish party data (`PolishParty`), third parties (`ThirdParty`,
  Podmiot3), the place of issue, warehouse documents, correction data, partial advances, settled
  advance invoices, the order of an advance invoice, the VAT in PLN, additional descriptions,
  charges and deductions (`Settlement`), the payment form, bank accounts, the footer, transaction
  terms, verbatim fragments and the header data.
- `PolishLine` (on a line): the P_12 rate code override, a free-text unit (P_8A), the line
  supply date, gross unit price and amount, line VAT, the OSS rate (see
  [One-stop shop](#one-stop-shop-oss)), annex 15, excise, GTU, procedure, line exchange rate,
  PKWiU, CN and PKOB, the flags for state-before rows, margin lines and synthetic bucket lines,
  and the omission markers the reader sets (see [Read](#read)).

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;

$invoice = new Invoice(
    number: 'FV/2026/11/001',
    issueDate: new DateTimeImmutable('2026-11-18'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::PLN,
    seller: new Party(
        name: 'Sprzedawca Sp. z o.o.',
        vatIdentifier: 'PL2222222222', // NIP 2222222222
        address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Kwiatowa 1 m. 2', city: 'Warszawa', postCode: '00-001'),
    ),
    buyer: new Party(
        name: 'Nabywca S.A.',
        vatIdentifier: 'PL1111111111',
        address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Polna 10', city: 'Poznań', postCode: '60-001'),
    ),
    lines: [
        new InvoiceLine(
            id: '1',
            quantity: '10',
            unit: UnitCode::HUR,
            price: new Price('150.00'),
            item: new Item('Usługa wdrożeniowa'),
            vat: new LineVat(VatCategory::StandardRate, '23'),
        ),
        (new InvoiceLine(
            id: '2',
            quantity: '2',
            unit: UnitCode::H87,
            price: new Price('45.90'),
            item: new Item('Podręcznik'),
            vat: new LineVat(VatCategory::StandardRate, '8'),
        ))->withExtension(new PolishLine(unitLabel: 'szt.')), // P_8A "szt." instead of H87
    ],
    dueDate: new DateTimeImmutable('2026-12-02'),
    payment: new PaymentInstructions(
        meansCode: PaymentMeansCode::C_30, // FormaPlatnosci 6 (transfer)
        creditTransfers: [new CreditTransfer('PL61109010140000071219812874')],
    ),
);

$invoice = TotalsCalculator::complete($invoice)
    ->withExtension(new PolishInvoice(placeOfIssue: 'Warszawa'));

$xml = (new Fa3Writer)->write($invoice); // P_13_1/P_14_1 for 23 %, P_13_2/P_14_2 for 8 %, P_15 1944.14
file_put_contents('faktura.xml', $xml);
```

Without a `PolishInvoice`, the invoice is written as a plain VAT invoice with every annotation
negative. `DataWytworzeniaFa` is `PolishInvoice::$createdAt`, else the writer clock
(`new Fa3Writer(static fn () => new DateTimeImmutable('…'))`), written in UTC. `SystemInfo` is
written only when `PolishInvoice::$systemInfo` is set.

Before anything is written, the writer guard checks the whole invoice and throws one
`InvalidArgumentException` that names every value FA(3) cannot carry (see
[Mapping](#mapping)). Every model value the writer reads is written to an FA(3) element, carried
as a fixed-key additional description, or refused; a test keeps that classification complete for
the party, line, item, price, payment, address, contact and Polish extension classes. The only
values replaced by design are the EN 16931 ones a Polish override supersedes (see below).

The guard also refuses what the FA(3) rules would reject: a corrective invoice without the
corrected invoice (neither BG-3 with its issue date nor correction data, PL-FA3-01), a buyer marked
JST or GV without the third party in role 8 or 10 (PL-FA3-13), and a domestic reverse-charge row
(`oo`) without the reverse-charge annotation (PL-FA3-19), which is never set for you. As a safety
net, the writer then validates its own output offline with the bundled schema and the fatal
PL-FA3 rules and, instead of returning the document, throws one `InvalidArgumentException` listing
every schema error and fatal finding. That catches the limits the guard does not check itself,
such as the text lengths of the schema (256 characters for most texts, 512 for names, 50 for
`UU_ID`). `ContactDetails` refuses an empty e-mail address or phone number and `KeyValue` a line
number below 1; the guard refuses an empty phone number or e-mail address of an EN 16931 contact
(BT-42, BT-43, BT-57, BT-58, or a third party's).

The invoice kind (`RodzajFaktury`) is `PolishInvoice::$kind`, else derived from the invoice type
code (BT-3): 380 is VAT, 381 and 384 are KOR, 386 is ZAL. Any other BT-3 needs an explicit kind.

### Polish overrides replace the EN 16931 values

Three `PolishInvoice` values replace, not extend, what the writer would derive from the EN 16931
model:

- `bankAccounts`: when given, they are the bank accounts (`RachunekBankowy`, with bank name and
  own-bank marking); the credit transfers (BG-17) are then not written.
- `paymentForm`: overrides the form derived from the payment means code (BT-81). It is needed
  for forms without an EN 16931 code: 3 voucher, 5 credit, 7 mobile.
- `PolishParty::$contacts` (on `seller` or `buyer`): when given, they are the party's
  `DaneKontaktowe`; the EN 16931 contact phone and e-mail of that party are then not written.

## Read

`Fa3Reader` reads an FA(3) invoice into the same model, with the Polish data in the
`PolishInvoice` and `PolishLine` extensions. Elements that are not mapped are listed in
`ReadResult::$ignored`. A document whose root is not an FA(3) `Faktura`, or without invoice number,
issue date, currency or seller NIP, is refused with `UnsupportedDocument`; a document with a DOCTYPE
declaration is refused with `UnsafeXml` before anything is read.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Reader;

$result = (new Fa3Reader)->read((string) file_get_contents('faktura.xml'));
$invoice = $result->invoice;

// The type code (BT-3) follows the kind; the exact kind is kept in the extension.
$polish = $invoice->extensions()->get(PolishInvoice::class);
echo $invoice->number, ' ', $invoice->type->value, ' ', $polish?->kind?->value, "\n";

foreach ($invoice->lines as $line) {
    $unit = $line->extensions()->get(PolishLine::class)?->unitLabel ?? $line->unit->value;
    echo $line->id, ' ', $line->item->name, ' ', $line->quantity, ' ', $unit, "\n";
}

foreach ($result->ignored as $path) {
    echo 'Not mapped: ', $path, "\n";
}
```

Some EN 16931 values of a read invoice are derived from Polish data, and the writer does not
write them back on their own. To change them, change the Polish data:

- **BG-3** (preceding invoices) of a corrective invoice comes from the corrected invoices
  (`DaneFaKorygowanej`). When those carry more than BG-3 holds (a KSeF number, the reason, the
  correction type, the parties before …), the correction data is also kept in
  `PolishInvoice::$correction`, which the writer uses instead of BG-3.
- **BT-13** (purchase order reference) is the first order number of `WarunkiTransakcji/Zamowienia`.
- **BT-6** (VAT accounting currency) is PLN and **BT-111** the sum of `P_14_1W` to `P_14_4W` on an
  invoice in a foreign currency; the per-bucket amounts are kept in `PolishInvoice::$vatInPln`.

Rows are read as written. Where a row leaves a value open, the reader takes it only from what the
invoice states, never from a guess:

- **A row without `P_12`** (allowed on simplified invoices and in the cases of p. 92) that is not a
  margin row is marked `PolishLine::$rateCodeOmitted` and written back without `P_12`. When the
  invoice has exactly one summary bucket with a default code, the line takes the category and rate
  of that code (for example S 23 for `P_13_1`). Otherwise its bucket is unknown because `P_12` was
  absent: the line is category O without a rate, and writing it back is refused with a message
  saying so, unless `P_13_8` is present.
- **A row with `P_12_XII` and without `P_12`** is a one-stop-shop line (category S at that rate).
  A row with both keeps `P_12` as `PolishLine::$rateCode` and `P_12_XII` as the OSS rate, and is
  written back with both, in the bucket of its `P_12` code.
- **A description whose `NrWiersza` several rows share** (rows numbered 1, 1, 2, 2) is not
  attached to any of them: it stays a `PolishInvoice::$descriptions` entry and the ambiguity is
  listed in `ignored`.
- A description with a line number below 1 is listed in `ignored`.

## Validate

`Fa3Validator` checks an FA(3) invoice against the bundled XSD and the 21 brochure rules. Nothing
needs to be installed and no network is used. The rules run even when the schema check fails, so
the report lists every problem at once. The report is a `MessageValidationReport` with
`MessageType::Fa3`, with the same `isValid()`, `fatal()`, `warnings()` and `verdict()` as an
invoice report; schema violations are in `schemaErrors`.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;

$report = Fa3Validator::create()->validate((string) file_get_contents('faktura.xml'));

foreach ($report->schemaErrors as $error) {
    echo "XSD line {$error->line}: {$error->message}\n";
}
foreach ([...$report->fatal(), ...$report->warnings()] as $finding) {
    // The test names the brochure page the rule comes from.
    echo $finding->ruleId, ' (', $finding->test, '): ', $finding->message, "\n";
}

exit($report->isValid() ? 0 : 1);
```

### Rules

FA(3) has no official rule codes, so the ids are ours. Only rules with a primary source are
implemented. The table is `Fa3Rules::rules()`; "Broszura FA(3) 2026-08" is the August 2026
brochure, cited by its printed page numbers.

| Id | Severity | Source | Rule |
|---|---|---|---|
| PL-FA3-01 | fatal | Broszura FA(3) 2026-08, pp. 53, 67 | A corrective invoice (KOR, KOR_ZAL, KOR_ROZ) must give the data of the corrected invoice (DaneFaKorygowanej). |
| PL-FA3-02 | fatal | Broszura FA(3) 2026-08, pp. 67-68 | Correction data (PrzyczynaKorekty, TypKorekty, DaneFaKorygowanej, OkresFaKorygowanej, NrFaKorygowany, Podmiot1K, Podmiot2K, P_15ZK, KursWalutyZK) belongs to corrective invoices only. |
| PL-FA3-03 | warning | Broszura FA(3) 2026-08, p. 68 | The amount before the correction (P_15ZK) belongs to corrections of advance or settlement invoices (KOR_ZAL, KOR_ROZ), and the exchange rate before the correction (KursWalutyZK) to corrections of advance invoices (KOR_ZAL). |
| PL-FA3-04 | fatal | Broszura FA(3) 2026-08, p. 99 | The state-before marker (StanPrzed) belongs to rows of corrective invoices only. |
| PL-FA3-05 | fatal | Broszura FA(3) 2026-08, p. 86 | A basic, settlement or simplified invoice (VAT, ROZ, UPR) must list its rows (FaWiersz). |
| PL-FA3-06 | warning | Broszura FA(3) 2026-08, pp. 86, 67 | A corrective invoice without rows (FaWiersz) is a discount for a period and should name that period (OkresFaKorygowanej). |
| PL-FA3-07 | fatal | Broszura FA(3) 2026-08, pp. 79, 4 | An advance invoice (ZAL) must give the order or contract (Zamowienie). |
| PL-FA3-08 | warning | Broszura FA(3) 2026-08, pp. 85, 54 | A settlement invoice (ROZ) should name the advance invoices it settles (FakturaZaliczkowa), unless no advance invoice was issued (art. 106b ust. 1a). |
| PL-FA3-09 | fatal | Broszura FA(3) 2026-08, pp. 47-49 | An invoice in a foreign currency must give the VAT of each rate total in PLN (P_14_1W to P_14_4W). |
| PL-FA3-10 | warning | Broszura FA(3) 2026-08, pp. 47-49 | The VAT in PLN of a rate total (P_14_1W to P_14_4W) is given only on invoices in a foreign currency. |
| PL-FA3-11 | fatal | Broszura FA(3) 2026-08, p. 105 | The total of charges (SumaObciazen) and of deductions (SumaOdliczen) must equal the sum of their amounts (Kwota). |
| PL-FA3-12 | fatal | Broszura FA(3) 2026-08, pp. 105-107 | The amount due (DoZaplaty) or overpaid (DoRozliczenia) must equal P_15 plus the charges minus the deductions, as DoZaplaty when that balance is not negative and as DoRozliczenia (its absolute value) when it is. |
| PL-FA3-13 | fatal | Broszura FA(3) 2026-08, p. 17 | A buyer marked as a subordinate unit of a local government (JST = 1) or a VAT group member (GV = 1) needs a third party (Podmiot3) in role 8 or 10 respectively. |
| PL-FA3-14 | warning | Broszura FA(3) 2026-08, p. 55 | On a self-billed invoice (P_17 = 1) the buyer is not repeated as a third party (Podmiot3) in role 5 (issuer). |
| PL-FA3-15 | fatal | ksef-api faktury/numer-ksef.md | A KSeF number (NrKSeFFaKorygowanej, NrKSeFFaZaliczkowej) must be 35 characters with a valid CRC-8 checksum. |
| PL-FA3-16 | warning | Broszura FA(3) 2026-08, pp. 45, 88 | A common delivery date (P_6) and per-row dates (P_6A) exclude each other. |
| PL-FA3-17 | fatal | Broszura FA(3) 2026-08, pp. 46-52, 92 | On a basic or settlement invoice (VAT, ROZ) each row rate (P_12) needs its total (P_13_x); a simplified invoice (UPR) may omit the per-rate sums (art. 106e ust. 5 pkt 3). |
| PL-FA3-18 | fatal | Broszura FA(3) 2026-08, pp. 51, 57 | Exempt sales (P_13_7) need the exemption annotation (Zwolnienie/P_19 = 1). |
| PL-FA3-19 | fatal | Broszura FA(3) 2026-08, p. 55 | A domestic reverse-charge row (P_12 = oo) needs the reverse-charge annotation (P_18 = 1). |
| PL-FA3-20 | fatal | Broszura FA(3) 2026-08, pp. 52, 63 | Margin-scheme sales (P_13_11) need the margin annotation (PMarzy/P_PMarzy = 1). |
| PL-FA3-21 | warning | Broszura FA(3) 2026-08, pp. 55-56, 52 | On a basic or simplified invoice (VAT, UPR) the split payment annotation (P_18A = 1) applies only above 15,000 PLN gross (P_15); other kinds are not checked because their P_15 is not the invoice total (p. 52). |

Not checked, because no primary source states it as a rule: that the partial advances (`P_15Z`)
add up to `P_15`, and the NIP check digit (see [NIP](#nip)).

## Corrections

A corrective invoice (KOR, KOR_ZAL, KOR_ROZ) carries the corrected invoices in
`PolishInvoice::$correction`. With the second method of the brochure, the rows before the
correction (`StanPrzed`) and after it are both listed. In the model a **state-before row is held
in storno form**: its quantity and net amount are negated and it is flagged
`PolishLine::$stateBefore`. `TotalsCalculator` then gives the differences that FA(3) puts in
`P_13_x`, `P_14_x` and `P_15`. The writer restores the sign and writes `StanPrzed` = 1; the
reader negates again.

```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\CorrectionEffect;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;

// $seller and $buyer as in the first sample. Three blankets returned: 3 × 90.00 becomes 2 × 90.00.
$blanket = static fn (string $id, string $quantity): InvoiceLine => new InvoiceLine(
    id: $id,
    quantity: $quantity,
    unit: UnitCode::H87,
    price: new Price('90.00'),
    item: new Item('Koc polarowy'),
    vat: new LineVat(VatCategory::StandardRate, '23'),
);

$invoice = new Invoice(
    number: 'FK/1/09/2026',
    issueDate: new DateTimeImmutable('2026-09-10'),
    type: InvoiceTypeCode::C_384, // kind KOR
    currency: CurrencyCode::PLN,
    seller: $seller,
    buyer: $buyer,
    lines: [
        $blanket('1', '-3')->withExtension(new PolishLine(stateBefore: true)), // before, storno form
        $blanket('2', '2'),                                                     // after
    ],
);

$invoice = TotalsCalculator::complete($invoice)->withExtension(new PolishInvoice(
    correction: new Correction(
        invoices: [new CorrectedInvoice(
            'FV/110/07/2026',
            new DateTimeImmutable('2026-07-14'),
            KsefNumber::parse('9999999999-20260714-D5FB0C9ED490-19'),
        )],
        reason: 'Zwrot towaru',
        effect: CorrectionEffect::CorrectionDate,
    ),
));

$xml = (new Fa3Writer)->write($invoice); // P_13_1 -90.00, P_14_1 -20.70, P_15 -110.70
```

The storno convention covers every amount of the row. On a state-before row the writer flips the
sign of the quantity (`P_8B`), the allowance total (`P_10`, so line allowances on a before-row are
held negated too), the net amount (`P_11`), the gross amount (`P_11A`, `PolishLine::$grossAmount`),
the line VAT (`P_11Vat`, `PolishLine::$vatAmount`) and the excise (`KwotaAkcyzy`,
`PolishLine::$excise`); the reader negates the same six. Unit prices are kept as written. A
corrective invoice built by hand must therefore hold all of these negated on its before-rows.

Without correction data, the preceding invoice references (BG-3) of a corrective invoice are
written as corrected invoices issued outside KSeF (`NrKSeFN` = 1); each then needs its issue date
(BT-26). When correction data is given, every BG-3 must name one of its corrected invoices. BG-3
on a non-corrective kind is refused.

The seller and buyers before the correction (`Correction::$sellerBefore`, `$buyersBefore`, written
as `Podmiot1K` and `Podmiot2K`) are plain `Party` objects. `Podmiot1K/PrefiksPodatnika` and
`Podmiot2K/IDNabywcy` are optional in FA(3) and not supported: the model has no field for them, so
they are never written, and a read value is listed in `ignored`.

### Invoices without rows

Advance invoices (ZAL), their corrections (KOR_ZAL) and period-discount corrections (KOR) may have
no `FaWiersz`. The model needs at least one line, so such an invoice carries one synthetic line per
`P_13` bucket, flagged `PolishLine::$bucketLine` (item name = the bucket element name, quantity 1,
net = the bucket amount). The writer never writes a bucket line as `FaWiersz`; its amounts reach
`P_13_x` and `P_14_x` through the VAT breakdown. The reader creates bucket lines when a ZAL, KOR_ZAL
or KOR has no rows. Bucket lines are refused on other kinds.

A bucket line carries nothing but its identifier, quantity 1, price, net amount and VAT, with the
bucket element name or an empty item name: any other line data (unit other than C62, description,
identifiers, note, references, allowances, unit label, GTU and the other row fields of
`PolishLine`) would not be written, so the guard refuses it. The taxi lump-sum (`P_13_4`) and
one-stop-shop (`P_13_5`) buckets have no rate code, so an invoice without rows that uses them is
read (with a category S line without a rate) but cannot be written back.

An invoice may mix bucket lines with real lines; nothing refuses it, because FA(3) allows rows on
these kinds. Both kinds of line feed the buckets, so do not describe the same amount twice.

## Advances and foreign currency

An advance invoice (ZAL) needs the order (`PolishInvoice::$order`, `Zamowienie`). Partial advances
(`ZaliczkaCzesciowa`) are allowed on ZAL, KOR_ZAL and ROZ.

On an invoice in a foreign currency, FA(3) needs the VAT of each bucket 1 to 4 also in PLN
(`P_14_1W` to `P_14_4W`). The conversion follows art. 106e ust. 11 and chapter VI of the VAT Act and
is the issuer's legal responsibility, so this package **never computes it**: the caller supplies it
in `PolishInvoice::$vatInPln`, keyed by bucket number, and sets the VAT accounting currency (BT-6)
to PLN with BT-111 equal to the sum. The writer refuses a foreign-currency invoice without it, and
VAT in PLN on an invoice in PLN.

```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\OrderLine;
use IntrAgile\EInvoicing\Poland\PartialAdvance;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;

// $seller and $buyer as in the first sample. 4000.00 EUR received in three advances.
$invoice = (new Invoice(
    number: 'FZ/7/09/2026',
    issueDate: new DateTimeImmutable('2026-09-20'),
    type: InvoiceTypeCode::C_386, // kind ZAL
    currency: CurrencyCode::EUR,
    seller: $seller,
    buyer: $buyer,
    lines: [(new InvoiceLine(
        id: '1',
        quantity: '1',
        unit: UnitCode::C62,
        price: new Price('3252.03'),
        item: new Item('P_13_1'),
        vat: new LineVat(VatCategory::StandardRate, '23'),
    ))->withExtension(new PolishLine(bucketLine: true))],
))->withTaxCurrency(CurrencyCode::PLN)
    ->withTotals(new DocumentTotals('0', '0', '0', '0', totalVatInAccountingCurrency: '3351.17'));

$invoice = TotalsCalculator::complete($invoice)->withExtension(new PolishInvoice(
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
    vatInPln: [1 => '3351.17'], // P_14_1W, computed by the issuer
));

$xml = (new Fa3Writer)->write($invoice);
```

This sample re-creates brochure example 16. The PLN VAT **3351.17 is our figure, not the
brochure's**: the brochure prints no PLN amount. We took the VAT of each advance in EUR, rounded it
to 2 decimals, multiplied it by that advance's exchange rate and rounded the products
(93.50 × 4.4512 + 467.48 × 4.4724 + 186.99 × 4.5148 = 416.19 + 2090.76 + 844.22). Converting the
unrounded EUR VAT gives 3351.16. Which method the law requires is the issuer's call.

On read, BT-6 is PLN and BT-111 is the sum of `P_14_1W` to `P_14_4W`.

## Settlement invoices and charges

A settlement invoice (ROZ) names the advance invoices it settles (`PolishInvoice::$advanceInvoices`,
`FakturaZaliczkowa`, by KSeF number or as issued outside KSeF). It keeps the full order values in
the rows and in `P_13_x` / `P_14_x`; `P_15` is the amount still due (BT-115). Pass the advances as
the paid amount (BT-113) to `TotalsCalculator::complete()`; BT-113 itself is not written.

```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;

// $draft: an invoice for 10 shelves at 1000.00 + 23 % (12300.00), of which 8610.00 was paid in advances.
$invoice = TotalsCalculator::complete($draft, '8610.00')->withExtension(new PolishInvoice(
    kind: InvoiceKind::Roz,
    advanceInvoices: [
        new AdvanceInvoiceReference(number: 'FZ/123/07/2025'),
        new AdvanceInvoiceReference(number: 'FZ/133/08/2025'),
    ],
));

$xml = (new Fa3Writer)->write($invoice); // P_13_1 10000.00, P_14_1 2300.00, P_15 3690.00
```

Charges and deductions (`Rozliczenie`) are a `Settlement` of `SettlementEntry` values. The writer
computes `SumaObciazen`, `SumaOdliczen` and `DoZaplaty` (or `DoRozliczenia` when the balance is
negative) from `P_15` and the entries:

```php
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;

// P_15 246.00 + 17.00 - 300.00: DoRozliczenia 37.00
$polish = new PolishInvoice(settlement: new Settlement(
    charges: [new SettlementEntry('17.00', 'Zwrot kosztów - opłata skarbowa')],
    deductions: [new SettlementEntry('300.00', 'Rozliczenie salda klienta')],
));
```

## Margin scheme

A margin-scheme line has no VAT rate (`P_12`): flag it `PolishLine::$margin` and declare the scheme
in the annotations. It feeds `P_13_11`.

```php
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\MarginScheme;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;

$line = (new InvoiceLine(
    id: '1',
    quantity: '1',
    unit: UnitCode::H87,
    price: new Price('50000.00'),
    item: new Item('Samochód osobowy używany'),
    vat: new LineVat(VatCategory::NotSubjectToVat),
))->withExtension(new PolishLine(margin: true));

$polish = new PolishInvoice(annotations: new Annotations(margin: MarginScheme::UsedGoods)); // P_PMarzy_3_1
```

## One-stop shop (OSS)

A supply under the one-stop-shop procedure (VAT Act, dział XII rozdział 6a) is taxed at the rate of
the buyer's member state. Give the line VAT category S at that rate and the same rate as
`PolishLine::$ossRate`. The line is written with `P_12_XII` and without `P_12` (the code `np I`
excludes OSS, p. 92) and feeds the OSS summary `P_13_5` with its VAT in `P_14_5` (pp. 49-50).

```php
$line = (new InvoiceLine(
    id: '1',
    quantity: '1',
    unit: UnitCode::C62,
    price: new Price('100.00'),
    item: new Item('E-book'),
    vat: new LineVat(VatCategory::StandardRate, '19'),
))->withExtension(new PolishLine(ossRate: '19')); // P_12_XII 19, P_13_5 100.00, P_14_5 19.00
```

A line with both an OSS rate and a rate code override is not an OSS line: it is written with both
`P_12` and `P_12_XII`, in the bucket of the override (as a read row with both elements comes
back). The guard refuses an OSS line with a margin flag, or another category or rate than S at its
OSS rate. On read, such rows come back as S at the OSS rate with the OSS rate
kept, and the `P_13_5` breakdown row is S at that rate (without a rate when the rows' rates
differ, which then cannot be written back). FA(3) has no `P_14_5W`, so no VAT in PLN is needed.

## Mapping

### Header, parties and payment

| EN 16931 | FA(3) | Notes |
|---|---|---|
| BT-1, BT-2, BT-5 | `P_2`, `P_1`, `KodWaluty` | required on read |
| BT-3 | `RodzajFaktury` | see [Build](#build-a-polish-invoice); on read VAT, ROZ, UPR → 380, KOR, KOR_ZAL, KOR_ROZ → 384, ZAL → 386 |
| BT-72, BT-7 | `P_6` | the actual delivery date, else the tax point date, when it is not the issue date |
| BG-14 | `OkresFa` | alternative to `P_6`; both together are refused |
| BT-9 | `Platnosc/TerminPlatnosci/Termin` | |
| BT-12, BT-13 | `WarunkiTransakcji/Umowy/NrUmowy`, `Zamowienia/NrZamowienia` | conflicting transaction terms refused |
| BT-16 | first `WZ` | further WZ in `PolishInvoice::$warehouseDocuments` |
| BT-31 | `Podmiot1/DaneIdentyfikacyjne/NIP` | must be `PL` + a NIP of the schema pattern |
| BT-48, else BT-47 | `Podmiot2`: `NIP`, or `KodUE` + `NrVatUE`, or `KodKraju` + `NrID`, or `BrakID` | `PL` + NIP → `NIP`; another EU prefix → `KodUE` + `NrVatUE`; any other id → `KodKraju` (BT-55) + `NrID`; none → `BrakID` = 1 |
| BG-5, BG-8 address | `Adres/AdresL1`, `AdresL2` | `AdresL1` = lines 1–3 joined by ", "; `AdresL2` = "postCode city" |
| BT-81 | `FormaPlatnosci` | 10 → 1 cash, 48/54/55 → 2 card, 20 → 4 cheque, 30/31/42/58 → 6 transfer; other codes → `PlatnoscInna` + `OpisPlatnosci` (BT-82, else the code); reader 1 → 10, 2 → 48, 4 → 20, 6 → 30, 3/5/7 → 1 with the form kept in the extension |
| BG-17 | `RachunekBankowy` | `NrRB` = BT-84, `SWIFT` = BT-86, `OpisRachunku` = BT-85 |
| BT-106 … BT-112 | `P_13_x`, `P_14_x`, `P_15` | `P_15` = BT-112; on ROZ = BT-115 |

Address reading (P12, amended): for `KodKraju` PL and an `AdresL2` of the form `NN-NNN City`, the
post code and city are split. Any other `AdresL2`, including every foreign address, is read
**whole as the city**, with no post code. Write, read and write again are then byte-stable, but a
foreign post code and city come back joined in BT-37 / BT-52 (for example city `10115 Berlin`).

### VAT rate codes

| Line VAT (BT-151, BT-152) | `P_12` | Bucket |
|---|---|---|
| S 23 / 22 | `23` / `22` | `P_13_1` |
| S 8 / 7 | `8` / `7` | `P_13_2` |
| S 5 | `5` | `P_13_3` |
| Z | `0 KR` | `P_13_6_1` |
| K | `0 WDT` | `P_13_6_2` |
| G | `0 EX` | `P_13_6_3` |
| E | `zw` | `P_13_7` |
| O | `np I` | `P_13_8` |
| AE, buyer in PL or no buyer country | `oo` | `P_13_10` |
| AE, buyer abroad | `np II` | `P_13_9` |
| margin line (`PolishLine::$margin`) | none | `P_13_11` |
| one-stop-shop line (`PolishLine::$ossRate`) | none, `P_12_XII` | `P_13_5` |

`PolishLine::$rateCode` overrides the derived code, but only within the line's category and rate
bucket: 22 for S 23 (or 23 for S 22), 7 for S 8 (or 8 for S 7), `np II` or `oo` for AE, and the
zero and exempt codes for their own categories. Any other override (for example `zw` on an S 23
line) is refused, and so is a VAT amount (BT-117) other than zero on a breakdown row whose bucket
has no VAT element (`P_13_6_x` to `P_13_11`), so VAT is never lost through an override.

S at 4 % or 3 %, and the categories L, M and B, are refused: the brochure ties no rate code to a
summary bucket for rates 4 and 3 (`P_13_4` is described only as the taxi lump sum, p. 49).
**Taxi lump-sum invoices** (`P_13_4`, rows with `P_12` = `4`) are therefore read (lines S 4 with
the code kept, a breakdown row S without a rate) but refused on write with a message naming the
taxi lump-sum summary. Every written row's bucket must be written too: a line whose bucket no VAT
breakdown row feeds is refused. On read, Z, K, G, E and AE get rate 0 and O no rate; a bucket
holding rows of two codes (23 and 22) reads as one VAT breakdown row with category S and no rate.

### Lines

| EN 16931 | FA(3) | Notes |
|---|---|---|
| BT-126 | `NrWierszaFa`, `UU_ID` | rows numbered 1…n in output order; BT-126 goes to `UU_ID` when it differs. Reader: `UU_ID`, else `NrWierszaFa`; duplicates accepted |
| BT-153 | `P_7` | |
| BT-130 | `P_8A` | `PolishLine::$unitLabel`, else the UN/ECE code |
| BT-129 | `P_8B` | |
| BT-146 | `P_9A` | |
| BG-27 (sum) | `P_10` | the sum of the line allowances; with several, each amount is also a description (`Kwota rabatu (BT-136)`) |
| BT-131 | `P_11` | |

Units (P13, amended): a free-text unit label is written as `P_8A`. When the line's unit code
(BT-130) is not `C62`, the writer also writes it as a row description `Jednostka miary UN/ECE
(BT-130)`, so the code survives next to the label. On read, a `P_8A` that is a UN/ECE
Recommendation 20 code is the unit code; any other text is the unit label with unit `C62`, unless
that row description gives the code.

### Additional descriptions (fixed keys)

EN 16931 text terms without an FA(3) element are written as `DodatkowyOpis` with these fixed keys
(`Fa3Format::DESCRIPTION_KEYS`) after the extension's own descriptions, and read back to their
terms. The line terms carry the row number (`NrWiersza`). BT-22 is written once per note.

| Term | Key | Level |
|---|---|---|
| BT-10 | `Referencja nabywcy (BT-10)` | invoice |
| BT-11 | `Projekt (BT-11)` | invoice |
| BT-14 | `Zamówienie sprzedaży (BT-14)` | invoice |
| BT-15 | `Przyjęcie dostawy (BT-15)` | invoice |
| BT-17 | `Przetarg lub partia (BT-17)` | invoice |
| BT-19 | `Konto księgowe nabywcy (BT-19)` | invoice |
| BT-20 | `Warunki płatności (BT-20)` | invoice |
| BT-22 | `Uwagi (BT-22)` | invoice |
| BT-28 | `Nazwa handlowa sprzedawcy (BT-28)` | invoice |
| BT-41 | `Osoba kontaktowa sprzedawcy (BT-41)` | invoice |
| BT-45 | `Nazwa handlowa nabywcy (BT-45)` | invoice |
| BT-56 | `Osoba kontaktowa nabywcy (BT-56)` | invoice |
| BT-83 | `Tytuł płatności (BT-83)` | invoice |
| BT-127 | `Uwagi do pozycji (BT-127)` | line |
| BT-154 | `Opis (BT-154)` | line |
| BT-128 | `Identyfikator obiektu (BT-128)` | line |
| BT-132 | `Pozycja zamówienia (BT-132)` | line |
| BT-133 | `Konto księgowe pozycji (BT-133)` | line |
| BT-156 | `Indeks nabywcy (BT-156)` | line |
| BT-159 | `Kraj pochodzenia (BT-159)` | line |
| BT-130 | `Jednostka miary UN/ECE (BT-130)` | line, only beside a unit label |
| BT-136 | `Kwota rabatu (BT-136)` | line allowance, only on a line with several allowances; starts the terms of its allowance |
| BT-139 | `Powód rabatu (BT-139)` | line allowance |
| BT-137 | `Podstawa rabatu (BT-137)` | line allowance |
| BT-138 | `Procent rabatu (BT-138)` | line allowance |
| BT-140 | `Kod powodu rabatu (BT-140)` | line allowance |

On read, any other pair, a repeated single-value key, or a value its term cannot hold stays a
`PolishInvoice::$descriptions` entry.

### Not carried and refused

Refused, in one `InvalidArgumentException` naming each (FA(3) has no element for them): the payee
(BG-10), the tax representative (BG-11), the deliver-to party name, location and address (BT-70,
BT-71, BG-15), document allowances and charges (BG-20, BG-21), additional supporting documents
(BG-24), line charges (BG-28), the invoiced object identifier (BT-18), party identifiers (BT-29,
BT-46), the seller legal registration (BT-30) and the buyer's next to a VAT identifier (BT-47), the
seller tax registration and additional legal information (BT-32, BT-33), electronic addresses
(BT-34, BT-49), a VAT accounting currency other than PLN (BT-6), the tax point date code (BT-8), the
paid amount (BT-113, except on ROZ) and the rounding amount (BT-114) when not zero, payment card and
direct debit (BG-18, BG-19), note subject codes (BT-21), country subdivisions (BT-39, BT-54), line
periods (BG-26), item price discounts and gross prices (BT-147, BT-148), item classifications
(BT-158), item attributes (BG-32), scheme identifiers FA(3) has no field for (BT-128-1, BT-47-1), a
price base quantity unit (BT-150) other than the line unit, the payment means text (BT-82) when a
payment form is written, a third party with an internal identifier (`IDWew`) next to a VAT
identifier or a legal registration, a GLN (`PolishParty::$gln`) of a party without an address, a
rate code override outside the line's category or rate bucket, and data on a bucket line beyond its
amounts (see [Invoices without rows](#invoices-without-rows)). VAT exemption reasons (BT-120,
BT-121) are accepted only with the exemption annotation (`Annotations::$exemption`, `P_19` with its
legal basis), which supersedes them; the legal basis is never guessed.

**Not carried: BT-23 and BT-24** (BG-2, process control). FA(3) identifies itself by its form code
and has no business process element, so these are neither written nor refused, and a read invoice
has none.

### Known round-trip differences

Write → read → write keeps every FA(3) field. Going the other way, from an EN 16931 invoice through
FA(3) and back, these differences remain:

- **Several allowances on one line** keep their amounts: FA(3) has only the total `P_10`, so each
  allowance is written as the description `Kwota rabatu (BT-136)` followed by its reason, base,
  percentage and code, and the reader rebuilds the allowances when at least two such amounts add
  up to `P_10`. A file whose amounts do not add up (or are malformed) reads as one allowance of
  `P_10`, with the amounts kept as descriptions. A line with one allowance carries no amount key.
  On a state-before row (`StanPrzed`), the amounts are written as the line holds them, in storno
  form (negative), while `P_10` is written with its sign restored; the reader compares their sum
  with `P_10` negated.
- **An allowance read from `P_10` alone has the reason `P_10`** (`Fa3Format::ALLOWANCE_REASON`).
  The writer does not repeat that reason as a BT-139 description, so an allowance whose real
  reason is the literal text `P_10` loses it.
- **BT-115 with `Rozliczenie`.** On a kind other than ROZ, the read amount due (BT-115) is
  `DoZaplaty` (or `DoRozliczenia` negated) when `Rozliczenie` is present, else `P_15`. With charges
  or deductions, BT-115 then differs from BT-112 − BT-113 + BT-114, so the read invoice can fail
  the EN 16931 rule BR-CO-16 if you validate it as an EN 16931 invoice.
- **Recomputed sums.** The writer recomputes `SumaObciazen`, `SumaOdliczen` and `DoZaplaty` /
  `DoRozliczenia` from the entries and `P_15`, at 2 decimals. A source that wrote them at another
  scale (for example `37` for `37.00`) comes back normalised; a source whose sum differs from its
  entries has that sum listed in `ignored` and the computed one used.
- **Omitted `P_8A`, `P_8B`, `P_9A`, `P_12`.** A row read without `P_12` is marked `rateCodeOmitted`
  and written back without it (see [Read](#read)). A row read without the others is marked
  (`unitOmitted`, `quantityOmitted`, `unitPriceOmitted`) and written back without them while the
  line still holds the values read from their absence. For `P_9A` the check is by value: a net price
  changed to an equal value at another scale (for example `45.9` and `45.90`) keeps `P_9A` omitted,
  so the price comes back at the reader's scale.
- **Foreign addresses** come back with post code and city joined in the city (see above).

### Verbatim blocks

`WarunkiTransakcji/Transport`, the new means of transport details, `PodmiotUpowazniony` and
`Zalacznik` are carried as `RawFragment` (one FA(3) element, re-emitted in its schema position).
The writer imports the parsed element, never splices the string; the XSD checks hand-written
fragments.

## KSeF number and verification link

`KsefNumber::parse()` checks the number KSeF assigns to an accepted invoice:
`<issuer>-<YYYYMMDD>-<12 hex>-<CRC-8>`, exactly 35 characters, upper-case hex, the last two
characters the CRC-8 (polynomial 0x07, initial value 0x00) of the first 32. The 36-character form
with a hyphen inside the technical part, which the schema pattern also admits, is refused.
The KSeF number printed in brochure example 14 (`…-9A`) fails the checksum; the correct one ends in
`-19`.

`VerificationLink` builds KOD I, the verification link printed as a QR code on an invoice
visualisation: `<base>/invoice/<seller NIP>/<issue date DD-MM-YYYY>/<SHA-256, Base64URL>`. The hash
covers the exact bytes sent to KSeF; any re-serialisation, added newline or byte order mark gives
another link. `KsefEnvironment` gives the base URL of TEST, DEMO and PRODUCTION. For the captions,
KOD II of offline invoices and optional rendering as an image, see [QR codes](#qr-codes).

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\VerificationLink;

// The exact bytes sent to KSeF.
$xml = (string) file_get_contents('faktura.xml');

// https://qr-test.ksef.mf.gov.pl/invoice/2222222222/18-11-2026/<hash>
echo VerificationLink::forInvoiceXml(KsefEnvironment::Test, $xml), "\n";

// The example number of the KSeF documentation.
$number = KsefNumber::parse('5265877635-20250826-0100001AF629-AF');
echo $number->issuerIdentifier, ' ', $number->acceptedOn->format('Y-m-d'), "\n"; // 5265877635 2025-08-26

var_dump(KsefNumber::isValid('9999999999-20260714-D5FB0C9ED490-9A')); // bool(false)
```

`VerificationLink::invoice()` takes the NIP, the issue date and the invoice XML (it hashes the
XML); `invoiceFromHash()` takes the NIP, the issue date and a hash you computed with
`VerificationLink::invoiceHash()`.

## NIP

`Nip::normalize()` removes spaces, hyphens and a leading `PL`; `Nip::isWellFormed()` applies the
schema pattern; `Nip::isValid()` also checks the check digit (weights 6, 5, 7, 2, 3, 4, 5, 6, 7,
modulo 11). That checksum is the Ministry of Finance algorithm as commonly published, but **its
legal source was not verified offline**, so neither the writer nor the validator refuses on it:
both rely on the schema pattern alone. A NIP with a wrong check digit passes this package and may
be refused only by KSeF.

## Send to KSeF

`KsefTransport` talks to the KSeF API 2.0: it authenticates, encrypts and sends FA(3) invoices in
online and batch sessions, reads their status and receipt (UPO), and reads the invoices addressed
to you. It is not a `Transport` like the Peppol drivers: KSeF takes FA(3) files, which an
`OutboundDocument` cannot carry, so it has its own `send()` and `status()` and implements
`InboundSource` for receiving.

> **Live-verified on KSeF TE (API 2.10 RC) on 2026-11-21 and 2026-11-22; DEMO and PRODUCTION are not verified.**
> See [What is verified for the KSeF transport](#what-is-verified-for-the-ksef-transport).

The transport needs `ext-openssl` and `phpseclib/phpseclib` (both required by this package: RSA-OAEP
with SHA-256 goes through phpseclib on every PHP version), a PSR-18 client wrapped in `HttpClient`
([TRANSPORT.md](../TRANSPORT.md#http-client); for exports with `maxResponseBytes` of at least
`KsefExport::MAX_PART_BYTES`, see [Export](#export)), and `ext-zip` for batch sessions and exports.

### Credentials

A `KsefConfig` names the environment, the context you act for and the credentials:

- **`CertificateCredentials`**: a certificate and its private key, PEM-encoded (the key optionally
  encrypted, with its passphrase). The library signs KSeF's authentication challenge with XAdES-BES
  (enveloped, exclusive canonicalisation, RSA-SHA256 or ECDSA-SHA256). A qualified seal, a
  qualified signature or a KSeF certificate; KSeF reads the identity from the certificate subject.
  On TE, self-signed certificates from `SelfSignedCertificate` stand in for them (see
  [Testing on KSeF TE](#testing-on-ksef-te)).
- **`TokenCredentials`**: a KSeF token, as generated in the KSeF web application or with
  `tokens()->generate()`. The token is encrypted with the Ministry of Finance public key together
  with the challenge's server timestamp, never the local clock.

`ContextIdentifier::nip()`, `internalId()` (a NIP, a hyphen and five digits) and `nipVatUe()` name
the context. Access tokens are refreshed when they expire; when KSeF refuses the refresh (for
example after the authentication session was ended), the transport authenticates again from the
credentials. The Ministry of Finance public keys are fetched once per instance and fetched again
when the key in use expires or KSeF reports an unknown key id (error 21470).

Credentials, private keys, passphrases and every access, refresh and authentication token are kept
out of exception messages and of `var_dump()`, `print_r()` and `var_export()` output, and the
objects that hold them refuse to be serialized, so they cannot end up in a queue or a cache.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;

// TE only: a self-signed company seal for a random, synthetic NIP.
$nip = SelfSignedCertificate::randomNip();
$seal = SelfSignedCertificate::seal($nip, 'Test Organization');

$config = new KsefConfig(KsefEnvironment::Test, ContextIdentifier::nip($nip), $seal);

echo $config->baseUrl, "\n"; // https://api-test.ksef.mf.gov.pl/v2

// The private key never shows up in debug output.
$debug = print_r($seal, true);
echo str_contains($debug, 'PRIVATE KEY') ? "leaked\n" : "redacted\n"; // redacted
```

With a real certificate on DEMO or PRODUCTION, and with a KSeF token:

```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;

$seal = new CertificateCredentials(
    (string) file_get_contents('/run/secrets/ksef-seal.crt.pem'),
    (string) file_get_contents('/run/secrets/ksef-seal.key.pem'),
    (string) getenv('KSEF_KEY_PASSPHRASE'),
);
$config = new KsefConfig(KsefEnvironment::Production, ContextIdentifier::nip('5265877635'), $seal);

$byToken = new KsefConfig(
    KsefEnvironment::Production,
    ContextIdentifier::nip('5265877635'),
    new TokenCredentials((string) getenv('KSEF_TOKEN')),
);
```

`KsefConfig` also holds the polling bounds (`authPollAttempts` 30 × `authPollWaitMs` 1000,
`statusPollAttempts` 30 × `statusPollWaitMs` 2000, `exportPollAttempts` 60 × `exportPollWaitMs`
2000), the batch part size (`batchPartBytes`, 100 MB before encryption) and an injectable sleep. No
loop of this package polls without a bound.

### Online send, status and UPO

`send()` takes an FA(3) file or an `Invoice`. A file is checked with `Fa3Validator` first: a schema
error or a fatal FA(3) rule throws `DocumentRejected` and nothing is sent. An `Invoice` is written
with `Fa3Writer`, which validates its own output. The invoice is encrypted (AES-256-CBC with a
fresh key per session, the key wrapped with RSA-OAEP for the Ministry of Finance key) and sent in
the online session of this transport instance.

The session is opened on the first `send()` and reused until 60 seconds before its `validUntil`
(about 12 hours on TE) or until 10,000 invoices, then replaced. `close()` closes it. The object
never makes a network call when it is destroyed, so a long-running process calls `close()` itself.

```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\KsefTransport;

$ksef = KsefTransport::create($config, $http);

$sent = $ksef->send((string) file_get_contents('faktura.xml'));
echo $sent->documentId, "\n"; // "<session reference>/<invoice reference>", state Pending

$status = $ksef->awaitFinal($sent->documentId); // bounded polling, KsefInvoiceStatus
if ($status->ksefNumber !== null) {
    echo $status->ksefNumber, "\n"; // 3932061639-20261121-5B0B1C000000-B4

    $upo = $ksef->upo($sent->documentId);
    file_put_contents($status->ksefNumber.'.upo.xml', $upo->xml); // keep the raw bytes as proof
    $report = $upo->validate(KsefEnvironment::Production);
} else {
    echo $status->failure()?->message, "\n";
}

$ksef->close();
```

- **Document id.** `SendResult::$documentId` is the opaque string `<session reference>/<invoice
  reference>`. `status()` reads it once and maps it to a `DeliveryStatus`; `invoiceStatus()` and
  `awaitFinal()` return the full `KsefInvoiceStatus` with the KSeF number and its acquisition date.
  `awaitFinal()` returns the last status read when the polling bounds run out, possibly still
  pending; it never loops forever.
- **Status mapping** (`KsefInvoiceStatus::deliveryState()` and `failure()`):

  | KSeF invoice status | Delivery state |
  |---|---|
  | 100 accepted for processing, 150 processing | Pending |
  | 200 success, KSeF number assigned | Delivered |
  | 440 duplicate | Failed / Duplicate; the message names the original KSeF number |
  | 430 file verification, 450 semantic verification | Failed / Validation |
  | 410 permission scope, 415 attachments not allowed | Failed / DocumentNotSupported |
  | 405 cancelled by a session error, 435 decryption error, 550 cancelled by the system | Failed / Transport |
  | 500 unknown error, any code this version does not know | Failed / Other |

  `providerCode` is the KSeF code as a string; the message joins KSeF's description and details.
- **Duplicates (440).** KSeF recognises a duplicate by the seller NIP (`Podmiot1/NIP`), the invoice
  type (`RodzajFaktury`) and the invoice number (`P_2`), not by a file hash (MF documentation
  `faktury/weryfikacja-faktury.md`). A 440 therefore proves only that an invoice with that number
  exists, not that it is the file you resent. `KsefInvoiceStatus::$originalSessionReferenceNumber`
  carries the session of the original (next to `originalKsefNumber`) when KSeF reports it.
  - `isSameDocument($status, $invoiceXml)` answers the question. It reads the receipt (UPO) of the
    original from its session and compares the receipt's `SkrotDokumentu` with the SHA-256 digest
    of the bytes you pass, which are the exact FA(3) bytes that were sent. `true` means the original
    is this file, so the earlier send was accepted under the original KSeF number. `false` means
    another invoice with the same number is in KSeF. It throws `LogicException`, before any request,
    for a status that is not a duplicate naming both the original KSeF number and its session.
  - **Any other exception means "not provable now, retry later", never "different".** For example,
    duplicate detection is global per seller NIP, so the original may have been sent from another
    context, whose receipt this context cannot read (HTTP 403, or error 21178); and the receipt of
    the original session may not be issued yet (also 21178). A receipt that does not list the
    original alone, or whose digest is missing or unreadable, throws `UnexpectedResponse`.
  - `upoByKsefNumber($sessionReference, $ksefNumber)` fetches the receipt of any session's invoice
    without reading its status first. `isSameDocument()` uses it.
  - Live-verified on TE on 2026-11-22: the same bytes resent gave `true`; a different file with the
    same seller, type and `P_2` gave `false`; the receipt of the original was fetchable while its
    session was still open.
- **UPO.** `upo()` fetches the receipt by the KSeF number the status reports, and throws
  `LogicException` without a request while the invoice has no KSeF number. On TE the receipt of an
  invoice was available before the session was closed. `Upo` keeps the raw bytes unchanged and
  parses the session reference, the context NIP, the KSeF number(s), the document hash and the
  acceptance date. `Upo::validate()` checks the receipt against the bundled UPO 4-3 schema after
  removing the enveloped signature, and accepts the receiver name TE writes on TEST only. **The
  Ministry of Finance signature of the UPO is not verified**; the receipt can be fetched from KSeF
  again at any time.
- **A session KSeF closed early.** When KSeF has ended the online session before its expiry, it
  refuses the next send with error 21180 (`UnexpectedResponse`). The invoice was not accepted; the
  transport forgets the session, so sending it again opens a new one. `close()` also drops the
  local session, even when the request fails.
- **Rotated sessions.** When a session is replaced, the old one is closed best-effort and a close
  error is ignored (KSeF closes it itself); the UPOs of its invoices may arrive later than usual.

### KSeF tokens

`tokens()` returns `KsefTokens` for the authenticated context: `generate()` (permissions, a 5 to
256 character description, optional `validFrom` and `validTo`), `status()`, `approve()` and
`revoke()`. KSeF shows a generated token only once, in `GeneratedToken::token()`.

```php
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenPermission;

$generated = $ksef->tokens()->generate([TokenPermission::InvoiceWrite, TokenPermission::InvoiceRead], 'ERP integration');
// Store $generated->token() in your secret store; KSeF never shows it again.
echo $ksef->tokens()->status($generated->referenceNumber), "\n"; // Pending, Active, AwaitingApproval …
```

- The token API is the rev2 one (`/tokens/rev2`), available on TE since API 2.10. **PRODUCTION ran
  API 2.9 when this was written, without rev2, so `generate()`, `status()` and `approve()` fail
  there until 2.10 reaches it.** Tokens created in the KSeF web application still authenticate:
  token login does not use rev2 (verified on TE only).
- A token generated in a context that logged in with a KSeF token is `AwaitingApproval`. It must be
  approved with `approve()` from a context authenticated with a certificate (within 24 hours,
  according to the KSeF documentation; not observed live); approving from the token-authenticated
  context is refused with error 26005 (observed on TE).
- `status()` reads the rev2 status. The legacy status endpoint reports a token awaiting approval as
  Active, so it is not used.

## Batch

`sendBatch()` sends several invoices in a batch session of its own and returns the session
reference. Each invoice is validated or written as in `send()` before anything is sent. The
invoices are zipped, the zip is split into parts of at most `batchPartBytes` (100 MB by default),
each part is encrypted and uploaded, and the session is closed.

```php
$session = $ksef->sendBatch([
    'FV-2026-001.xml' => (string) file_get_contents('FV-2026-001.xml'),
    'FV-2026-002.xml' => $invoice, // an Invoice, written as FA(3)
]);

$result = $ksef->awaitBatch($session); // bounded polling, BatchResult
foreach ($result->invoices as $fileName => $status) {
    echo $fileName, ': ', $status->ksefNumber ?? $status->failure()?->message, "\n";
}
```

- Limits checked before sending: at most 10,000 invoices, 1,000,000 bytes per invoice, 50 parts
  and 5 GB in total; file names of letters, digits, `.`, `_` and `-` ending in `.xml`, no two
  differing only in case.
- **The whole package is held in memory** (the zip and its encrypted parts). Size your batches to
  the memory of the process.
- TE accepted a 2,976-byte zip split into two parts of 1,488 bytes: no minimum part size was observed.
- `BatchResult::isSuccessful()` means KSeF processed the session (status 200); single invoices may
  still have failed, see `$failed` and `$invoices`. Session status 445 (no valid invoice) is
  Failed / Validation; 405, 415, 430 and 435 are Failed / Transport; 420, 440, 500 and unknown codes
  are Failed / Other.
- `sendBatch()` and exports need `ext-zip`; without it they throw `LogicException`.

## Receive

`KsefTransport` implements `InboundSource`: `pending()` lists the invoices addressed to the
context (the buyer, `Subject2`) that are not acknowledged yet, `fetch()` downloads one exactly as
KSeF stores it, and `acknowledge()` marks it processed.

**Document id.** `pending()` returns the id of a received invoice as `<KSeF number>@<PermanentStorage
date>`, with the date in UTC as `Y-m-d\TH:i:s.u\Z`, for example
`5265877635-20250626-010080DD2B5E-26@2025-09-18T12:24:01.015432Z`. `fetch()` takes everything it
needs from the id, so a process that did not run `pending()`, such as a fresh queue worker, spends
no metadata query. The date KSeF stored an invoice never changes, so the id repeats on every
listing that returns the invoice's metadata.

- **Fallback.** The cursor keeps only KSeF numbers. An invoice still open in the stored window that
  the current read no longer returns (KSeF stopped listing it in the window's query, for example
  after the context lost access to it) is offered under its plain KSeF number. One invoice can
  therefore appear under both forms.
- **Deduplicate by `KsefTransport::ksefNumberOf($id)`**, which returns the KSeF number of either
  form; never by the id itself. It throws `InvalidArgumentException` for a malformed id.
- A plain KSeF number still fetches, at the cost of one metadata query. `acknowledge()` accepts
  both forms. An id that is not a KSeF inbound id is ignored by `acknowledge()`.
- `fetch()` refuses a malformed id (bad checksum, a date that does not round-trip, a date more than
  a day before the date in the KSeF number) with `InvalidArgumentException`, before any request.
- Live-verified on TE on 2026-11-22: `fetch()` of a compound id on a fresh transport made no
  metadata query and returned content byte-equal to the sent invoice; a plain KSeF number fetched
  too, at one metadata query.

```php
foreach ($ksef->pending() as $reference) {
    $document = $ksef->fetch($reference->documentId);
    // … store $document->xml, then:
    $ksef->acknowledge($reference->documentId);
}
```

**How the cursor works.** `pending()` queries the invoice metadata by PermanentStorage date from
the cursor, restricted to KSeF's high water mark (HWM), pages through the results, and stores the
window with the ids it returned. Once every id of the window is acknowledged, the cursor moves to
the window's HWM. The cursor is kept in a `KsefCursorStore` under
`<environment>:<context>:Subject2`.

- **First window.** It starts at the `$inboxStart` passed to `KsefTransport::create()`, or 30 days
  before now when none is given (`DEFAULT_INBOX_LOOKBACK_DAYS`). A first sync does not see older
  invoices unless you pass an earlier start.
- **Chunks.** KSeF refuses date ranges over 100 days, so each query spans at most 90 days
  (`KsefTransport::INBOX_CHUNK_DAYS`) and an older window catches up chunk by chunk.
- **One unacknowledged invoice holds the cursor.** The cursor moves only when every invoice of the
  window is acknowledged. `resetInbox($from)` restarts the inbox at a given point; invoices of the
  old window that were not acknowledged are dropped from the cursor and offered again only if they
  fall after the new start.
- **Duplicates.** Invoices at window boundaries can be offered again; deduplicate by KSeF number
  (`KsefTransport::ksefNumberOf($id)`).
- **Store.** `InMemoryCursorStore` (the default) keeps nothing across restarts and gives no
  protection against two consumers of the same inbox. A restarted process reads from the start
  point again. Implement `KsefCursorStore` (two methods: `window()` and `save()`) on your database
  for a persistent cursor; a persistent store ships later.
- **Lag.** On TE an invoice appeared in the buyer's inbox about two minutes after it was accepted.
  The lag on PRODUCTION is unknown.
- **Limits.** More than 10,000 invoices with one PermanentStorage timestamp cannot be paged past
  (KSeF offers no further page) and throw `UnexpectedResponse`.
- `fetch()` of a plain KSeF number (the fallback form, or one you built yourself) looks its
  PermanentStorage date up by a metadata query from the date in the number. A compound id needs no
  lookup.

**Rate limits.** KSeF counts requests per context and IP address in sliding windows; all three
thresholds apply at once (MF documentation `limity/limity-api.md`, 2026-09-14):

| Request | Per second | Per minute | Per hour |
|---|---|---|---|
| `POST /invoices/query/metadata` (`pending()`, the lookup in `fetch()` of a plain KSeF number) | 8 | 16 | 20 |
| `POST /invoices/exports` (each `export()` round) | 8 | 16 | 20 |
| `GET /invoices/ksef/{ksefNumber}` (`fetch()`) | 8 | 16 | 64 |

- Each `pending()` call queries one window of at most 90 days and spends one metadata query per
  page of 250 records it reads: at least one, at most 10. A result longer than 10 pages ends the
  window at its last record, like a truncated one, and the next call continues from there.
  Catching up an inbox start older than 90 days therefore takes at least one call per 90-day chunk.
- `fetch()` spends one of the 64 downloads per hour, plus one metadata query when you pass a plain
  KSeF number instead of the id `pending()` returned. `pending()` with `fetch()` therefore
  handles about 64 invoices an hour; for higher volumes use `export()`, as the Ministry of Finance
  recommends.
- The Ministry of Finance recommends polling no more often than **every 15 minutes** for each
  subject of an invoice on PRODUCTION.
- A metadata query refused with 429 is a POST, so it is not repeated: `pending()` throws
  `RateLimited` with the `Retry-After` delay (TE answered 3600 seconds). When earlier pages of the
  same call were read, the stored window is first narrowed to the last invoice listed, so the
  cursor still progresses once those invoices are acknowledged.

### Export

`export()` runs a KSeF invoice export for any filter: it starts the export with a fresh AES key,
polls within the configured bounds, downloads every part, checks both the encrypted and the
decrypted part hash, decrypts each part (KSeF encrypts the parts individually) and unzips the
package. Decrypted parts are streamed to a temporary file, not joined in memory.

```php
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;

$result = $ksef->export(InvoiceQueryFilters::incoming(new DateTimeImmutable('2026-11-01T00:00:00+01:00')));
foreach ($result->invoices as $ksefNumber => $xml) {
    file_put_contents($ksefNumber.'.xml', $xml);
}
if ($result->isTruncated) {
    // Ten rounds were not enough: continue from $result->hwm with another export().
}
```

- When KSeF truncates the package, the export continues from the date of the last exported
  invoice, at most 10 rounds. **The extracted XML of all rounds is held in the result**, in memory;
  a very large export takes several calls. `ExportResult::$isTruncated` is true when the last round
  was still truncated; continue from `$result->hwm`.
- A truncated round that makes no progress throws `UnexpectedResponse` instead of looping.
- `$result->metadata` holds the `_metadata.json` content when the package has one.
- **Part size.** KSeF parts reach 50 MB (52,428,816 bytes encrypted,
  `KsefExport::MAX_PART_BYTES`), while `HttpClient` reads at most 20,000,000 bytes by default. For
  exports, build the client with a higher cap, or a larger part is refused with a `LogicException`
  naming the setting before anything is downloaded:

  ```php
  $http = new HttpClient($psr18Client, $psr17Factory, $psr17Factory, maxResponseBytes: KsefExport::MAX_PART_BYTES);
  ```

## Offline mode

An invoice may be issued outside KSeF and sent to it later. The KSeF 2.0 documentation of the
Ministry of Finance (`tryby-offline.md`) names the modes (`OfflineMode`):

| Mode | When | Sending deadline |
|---|---|---|
| `Offline24` | The taxpayer's own choice (art. 106nda of the VAT Act) | The first working day after the issue date (`P_1`) |
| `Unavailability` | A KSeF unavailability the Ministry of Finance announced, such as a maintenance window (art. 106nh) | The first working day after the day the unavailability ended |
| `Failure` | An announced KSeF failure (art. 106nf) | The 7th working day after the day the failure ended |
| `TotalFailure` | An announced total failure | None: the invoice need not be sent to KSeF |

An offline invoice carries two QR codes on its visualisation: KOD I, captioned "OFFLINE" until KSeF
assigns a number, and KOD II, signed with a KSeF certificate of type Offline (see
[KSeF certificates](#ksef-certificates) and [QR codes](#qr-codes)). An offline invoice KSeF
rejects is replaced by a [technical correction](#technical-correction).

### Send an offline invoice

`send()` and `sendBatch()` take an `$offline` flag. It applies to the whole batch.

Write an offline invoice once, and use the same string for its QR codes and for the send. The QR
codes printed on it hash its exact bytes. `Fa3Writer` stamps `DataWytworzeniaFa` from the clock
unless `PolishInvoice::$createdAt` is set, so an `Invoice` passed to `send()` is written again with
other bytes, and the printed codes would never match the invoice in KSeF. If you pass `Invoice`
objects, set `PolishInvoice::$createdAt` so every write gives the same bytes.

```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\KsefQrCodes;

$xml = (new Fa3Writer)->write($invoice); // write once
[$kod1, $kod2] = KsefQrCodes::offline(KsefEnvironment::Production, ContextIdentifier::nip('5265877635'), $xml, $offlineCertificate);
// ... print the invoice with both codes, then send the same bytes:

$sent = $ksef->send($xml, offline: true);
$status = $ksef->awaitFinal($sent->documentId);
echo $status->invoicingMode?->name, "\n"; // Offline

$session = $ksef->sendBatch(['FV-2026-001.xml' => $xml1, 'FV-2026-002.xml' => $xml2], offline: true);
```

KSeF classifies every invoice itself and reports the result as `invoicingMode` (`InvoicingMode::Online`
or `Offline`, null when absent) on `KsefInvoiceStatus` and on the inbox `InvoiceMetadata`. Its
classification does not depend on the flag alone: on TE, an invoice sent online without the flag
but dated the day before the send (`P_1`) came back as Offline. The library makes no local
prediction of KSeF's classification. Neither the flag nor the reported mode computes a deadline;
`OfflineDeadlines` does that, for any invoice KSeF reports as Offline.

### Deadlines

`OfflineDeadlines::for($issueDate, $mode, $outages, $cause)` returns an `OfflineDeadline`: the
`deadline` (the last instant of the deadline day, `23:59:59.999999` Europe/Warsaw) and the
`reason` (`DeadlineReason`) that produced it.

> **The deadline calculator is a calculation aid, not legal advice.** It implements the rules of
> the Ministry of Finance documentation as we read them. Check the deadlines of your own invoices
> against the law and the official announcements.

| Situation | `deadline` | `reason` |
|---|---|---|
| `Offline24` | The first working day after the issue date | `NextWorkingDay` |
| `Unavailability`, with `$cause` the unavailability (a `Maintenance` outage) that has ended | The first working day after its end day | `AfterUnavailability` |
| `Unavailability` whose cause has not ended | null | `UnavailabilityOngoing` |
| `Failure`, with `$cause` the failure that has ended | The 7th working day after its end day | `AfterFailure` |
| A failure announced while the invoice is pending, in any mode | Replaced by the failure rule, counted from the end of the last such failure | `AfterFailure` |
| A failure that has not ended, as the cause or announced while the invoice is pending | null | `FailureOngoing` |
| `TotalFailure`, or a total failure announced while the invoice is pending | null | `TotalFailure` |

- **Pending window.** An invoice is pending from the start of its issue day to its current
  deadline. A failure that starts inside that window replaces the deadline with the failure rule,
  even when this gives an earlier day (the documentation says the deadline "is moved and counted
  from the end of the last announced failure"). The count runs from the latest end among the
  failures that overlap the window, and starts again whenever a further failure starts inside the
  extended window. "Announced" is read as the outage's `start`, not the time Latarnia published
  the message: a failure starting inside the window counts even if it was published later, and one
  that started before the issue day does not, even if it was published inside the window.
- **Issue date.** `$issueDate` is converted to Europe/Warsaw before its day is taken. Pass `P_1` as
  midnight Europe/Warsaw (`new DateTimeImmutable('2026-03-02', new DateTimeZone('Europe/Warsaw'))`):
  an instant such as `2026-03-02 23:30 UTC` is already 3 March in Warsaw, while the QR links use
  the calendar date of the value as given.
- **Working days** (`PolishWorkingDays`) are Monday to Friday, except the statutory holidays of the
  Act of 18 January 1951 on public holidays: 1 and 6 January, Easter Sunday and Monday, 1 and
  3 May, Pentecost Sunday, Corpus Christi, 15 August, 1 and 11 November, and 24, 25 and 26
  December. 24 December is a holiday from 2025 on (Act of 6 December 2024, Dz.U. 2024 poz. 1965).
  Easter is computed in the class (Gregorian computus). Days are taken in Europe/Warsaw.
- **Supported years** are 2025 to 2099; any other year throws `InvalidArgumentException`, because
  a later change of the holiday law cannot be known here. A change of the law needs a new release.
- `$cause` is the outage the invoice was issued under. It is required for `Unavailability` (a
  `Maintenance` outage) and `Failure` (a `Failure` outage); a missing or mismatched cause throws
  `InvalidArgumentException`. In `Offline24` mode a `Maintenance` cause is ignored, and a `Failure`
  cause never moves the deadline by itself (it is not "announced while pending"), but it joins the
  failures the count runs from once another failure starts inside the pending window. With
  `TotalFailure` the cause is not read.
- `$outages` is the list of announced outages, for example folded from Latarnia messages (see
  below); maintenance entries in it are ignored.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\Offline\OfflineDeadlines;
use IntrAgile\EInvoicing\Poland\Offline\OfflineMode;
use IntrAgile\EInvoicing\Poland\Offline\Outage;
use IntrAgile\EInvoicing\Poland\Offline\OutageCategory;
use IntrAgile\EInvoicing\Poland\Offline\PolishWorkingDays;

$warsaw = new DateTimeZone('Europe/Warsaw');

// Offline24, issued on Wednesday 23 December 2026: 24 to 26 December are holidays, the 27th is a Sunday.
$deadline = OfflineDeadlines::for(new DateTimeImmutable('2026-12-23', $warsaw), OfflineMode::Offline24);
echo $deadline->reason->name, ' ', $deadline->deadline?->format('Y-m-d H:i:s'), "\n"; // NextWorkingDay 2026-12-28 23:59:59

// The example of the Ministry of Finance documentation: an offline24 invoice of Tuesday 8 July 2025,
// a failure announced on Wednesday 9 July and removed on Saturday 12 July.
$failure = new Outage(OutageCategory::Failure, new DateTimeImmutable('2025-07-09 10:00', $warsaw), new DateTimeImmutable('2025-07-12 14:00', $warsaw));
$deadline = OfflineDeadlines::for(new DateTimeImmutable('2025-07-08', $warsaw), OfflineMode::Offline24, [$failure]);
echo $deadline->reason->name, ' ', $deadline->deadline?->format('Y-m-d'), "\n"; // AfterFailure 2025-07-22

// Issued during a failure that has not ended: the deadline is not known yet.
$ongoing = new Outage(OutageCategory::Failure, new DateTimeImmutable('2026-11-20 09:00', $warsaw), null);
$deadline = OfflineDeadlines::for(new DateTimeImmutable('2026-11-20', $warsaw), OfflineMode::Failure, cause: $ongoing);
echo $deadline->reason->name, ' ', var_export($deadline->deadline, true), "\n"; // FailureOngoing NULL

var_dump(PolishWorkingDays::isWorkingDay(new DateTimeImmutable('2026-12-24', $warsaw))); // bool(false)
```

### Latarnia

Latarnia is the Ministry of Finance's public KSeF availability service. It needs no
authentication. `Latarnia::forEnvironment()` gives the client for TE
(`https://api-latarnia-test.ksef.mf.gov.pl/`) or PRODUCTION (`https://api-latarnia.ksef.mf.gov.pl/`).
The Ministry publishes no Latarnia for DEMO, so DEMO throws `LogicException`; pass a base URL to
the constructor instead.

```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\Latarnia;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\OutageEvents;

$latarnia = Latarnia::forEnvironment(KsefEnvironment::Production, $http);

$status = $latarnia->status();          // LatarniaStatus
echo $status->availability->name, "\n"; // Available, Maintenance, Failure or TotalFailure

$outages = OutageEvents::fold($latarnia->messages()); // list<Outage>, for OfflineDeadlines
```

- `status()` returns the current `KsefAvailability` and the messages it follows from, as Latarnia
  reports them. When several events are active, Latarnia reports the most severe: total failure,
  then failure, then maintenance.
- `messages()` returns every current `LatarniaMessage`: announced and running maintenance windows
  and failures, and those that ended in the last 30 days. Each has an id, an event id, a category,
  a type (`LatarniaMessageType`: `MaintenanceAnnouncement`, `FailureStart`, `FailureEnd`), a title
  and text, a start, an end (null while a failure runs), a version and a publication time.
- `OutageEvents::fold()` groups the messages by event id and keeps the highest version of each
  message (same id). A maintenance announcement carries its whole window. A failure starts at its
  `FAILURE_START` message and ends at the end its `FAILURE_END` message gives, or else at the end
  Latarnia copied onto the start message; with neither, it has not ended. An event whose start
  message has already dropped out of the list is built from its end message alone, which repeats
  the start. The outages are ordered by start, then by event id.
- A failed request, a server error (5xx) or a rate limit (429) throws `TransportUnavailable`;
  anything else unexpected, including a body of the wrong shape, throws `UnexpectedResponse`.
  Nothing is repeated, and request timeouts belong to your PSR-18 client.
- **TE serves stale sample events.** On 2026-11-22 the TE Latarnia still reported a total failure
  of January 2026 as active. Do not base TE tests on its content.

Folding messages into outages and computing a deadline needs no network:

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\Offline\OfflineDeadlines;
use IntrAgile\EInvoicing\Poland\Offline\OfflineMode;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\LatarniaMessage;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\OutageEvents;

// The start and end messages of one failure, in the shape GET /messages returns them.
$messages = array_map(LatarniaMessage::fromArray(...), [
    ['id' => 'K/2026/11/01', 'eventId' => 101, 'category' => 'FAILURE', 'type' => 'FAILURE_START',
        'title' => 'Awaria KSeF', 'text' => '…', 'start' => '2026-11-24T09:00:00+01:00', 'end' => null,
        'version' => 1, 'published' => '2026-11-24T09:05:00+01:00'],
    ['id' => 'K/2026/11/02', 'eventId' => 101, 'category' => 'FAILURE', 'type' => 'FAILURE_END',
        'title' => 'Koniec awarii KSeF', 'text' => '…', 'start' => '2026-11-24T09:00:00+01:00',
        'end' => '2026-11-24T15:30:00+01:00', 'version' => 1, 'published' => '2026-11-24T15:35:00+01:00'],
]);

$outages = OutageEvents::fold($messages);
echo count($outages), ' ', $outages[0]->category->name, ' ', $outages[0]->end?->format('Y-m-d H:i'), "\n"; // 1 Failure 2026-11-24 15:30

// An offline24 invoice of Monday 23 November 2026: the failure starts while it is pending.
$deadline = OfflineDeadlines::for(new DateTimeImmutable('2026-11-23', new DateTimeZone('Europe/Warsaw')), OfflineMode::Offline24, $outages);
echo $deadline->reason->name, ' ', $deadline->deadline?->format('Y-m-d'), "\n"; // AfterFailure 2026-12-03
```

## KSeF certificates

`certificates()` returns `KsefCertificates` for the authenticated subject. KSeF issues two types
(`KsefCertificateType`): **Offline**, which signs KOD II, and **Authentication**, which logs in to
KSeF with XAdES like any other certificate.

| Method | What it does |
|---|---|
| `limits()` | `CertificateLimits`: whether a request is allowed now, and the enrolments and active certificates left |
| `enrollmentData()` | The subject a request must carry, as KSeF reports it for the certificate you authenticated with |
| `enroll($type, $algorithm, $name, $validFrom)` | Generates the key, sends the request, polls for the result and returns an `EnrolledCertificate` |
| `retrieve($serial)` | One `KsefCertificate` by its serial number |
| `query($type, $status, $name, $serial, $expiresAfter)` | Every certificate matching the filters, with its status (Active, Blocked, Revoked, Expired) |
| `revoke($serial, $reason)` | Revokes a certificate (`RevocationReason`: Unspecified, Superseded, KeyCompromise) |

```php
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KeyAlgorithm;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificateType;

$enrolled = $ksef->certificates()->enroll(KsefCertificateType::Offline); // EC P-256 by default

// The private key exists only in this object: store it before doing anything else.
file_put_contents('/run/secrets/ksef-offline.key.pem', $enrolled->privateKeyPem());
chmod('/run/secrets/ksef-offline.key.pem', 0600);
file_put_contents('/run/secrets/ksef-offline.crt.pem', $enrolled->certificate->certificatePem);
echo $enrolled->certificate->serialNumber, "\n"; // 16 upper-case hex digits

$offlineCertificate = $enrolled->credentials(); // CertificateCredentials, for KOD II

$rsa = $ksef->certificates()->enroll(KsefCertificateType::Authentication, KeyAlgorithm::Rsa, 'ERP login');
```

- **Certificate login only.** KSeF accepts an enrolment only after an authentication with a
  certificate (XAdES). A configuration with `TokenCredentials` is refused with `LogicException`
  before any request.
- **The key is generated locally**, EC P-256 by default (the Ministry of Finance's recommendation,
  and the shorter KOD II signature) or RSA-2048 (`KeyAlgorithm::Rsa`). KSeF accepts no other key
  types or sizes. The private key is never sent.
- **The request carries exactly the subject of `enrollmentData()`**, in KSeF's order. The PKCS#10
  request is DER-encoded in PHP, because `openssl_csr_new()` cannot repeat an attribute (several
  given names).
- **`EnrolledCertificate` returns the private key once**, as unencrypted PKCS#8 PEM. KSeF cannot
  issue it again, and a lost key means a new enrolment. The key stays out of `var_dump()`,
  `print_r()`, `var_export()`, `json_encode()` and array casts, and the object refuses to be
  serialized, so it cannot end up in a log, a cache, a queued job or a session by accident.
- **Polling.** `enroll()` reads the request status within the `statusPollAttempts` and
  `statusPollWaitMs` bounds of the `KsefConfig`. A request still processing after them, or
  cancelled by KSeF (550), throws `TransportUnavailable` naming the enrolment reference; a rejected
  one (400) throws `DocumentRejected`. When a status read fails (a network error, a rate limit,
  an unreadable answer or an unknown status), the exception keeps its type, names the enrolment
  reference and says to revoke the certificate if it appears in `query()`, since KSeF may still
  issue it. If reading back an issued certificate fails, the exception names its serial number and
  says to revoke it.
- **Name.** 5 to 100 letters (Polish ones included), digits, spaces, hyphens and underscores;
  `"<type> <UTC date and time>"` when none is given.
- **Observed on TE** (2026-11-22): EC and RSA certificates issued in 3 to 4 seconds by
  `CN=TEST CCK KSeF`, valid for two years. An Offline certificate's common name ends in
  " (offline)" and has the key usage Non Repudiation; an Authentication certificate's ends in
  " (uwierzytelnienie)" and has Digital Signature. Logging in with an Authentication certificate
  (EC) worked, and KSeF reported the method `xades.ksef-certificate`. The limits were 300 enrolments
  and 100 active certificates per subject.

## QR codes

The visualisation of an FA(3) invoice carries KOD I, the invoice verification link, and, for an
invoice issued offline, KOD II, the issuer's certificate verification link. The library builds the
links and their captions (`QrCode`); rendering an image is optional.

| Code | Link | Caption (`QrCode::$label`) |
|---|---|---|
| KOD I | `<qr host>/invoice/<seller NIP>/<issue date DD-MM-YYYY>/<invoice hash>` | The KSeF number once KSeF has assigned one, `OFFLINE` until then |
| KOD II | `<qr host>/certificate/<context type>/<context value>/<seller NIP>/<certificate serial>/<invoice hash>/<signature>` | `CERTYFIKAT` |

- `KsefQrCodes::online($env, $invoiceXml, $ksefNumber)` returns KOD I, reading the seller NIP and
  the issue date from the invoice. `KsefQrCodes::offline($env, $context, $invoiceXml,
  $offlineCertificate, $ksefNumber)` returns KOD I and KOD II. The URL of KOD I stays the same when
  the KSeF number arrives; only the caption changes.
- `CertificateVerificationLink::build()` signs KOD II for an FA(3) file; `fromHash()` takes an
  invoice hash and an issue date, for documents other than FA(3). The invoice hash is the SHA-256 of
  the exact invoice bytes, Base64URL without padding, the same as in KOD I.
- **Signature.** It covers the link without `https://` and without the signature segment or a
  trailing slash. An EC P-256 key signs with ECDSA-SHA256 in IEEE P1363 form (r‖s, 64 bytes, 86
  characters encoded); an RSA key of 2048 bits or more signs with RSASSA-PSS (SHA-256, MGF1-SHA-256,
  salt 32) through phpseclib, because OpenSSL's PHP binding offers only PKCS#1 v1.5, which KSeF
  rejects.
- **Checks before signing.** The private key must match the certificate; a certificate marked as
  an Authentication certificate (key usage Digital Signature only) is refused; the certificate must
  be valid at some moment of the issue date (Europe/Warsaw); the serial number is taken from the
  certificate, in upper case, padded with leading zeros to 16 hex digits (OpenSSL drops leading zero
  bytes; KSeF always writes 16 digits). Each failed check throws
  `InvalidArgumentException`.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\CertificateVerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\KsefQrCodes;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;

$xml = (string) file_get_contents('faktura.xml'); // the exact bytes sent to KSeF

$kod1 = KsefQrCodes::online(KsefEnvironment::Test, $xml, null);
echo $kod1->label, "\n"; // OFFLINE
echo $kod1->url, "\n";   // https://qr-test.ksef.mf.gov.pl/invoice/2222222222/18-11-2026/<hash>
echo KsefQrCodes::online(KsefEnvironment::Test, $xml, '5265877635-20250826-0100001AF629-AF')->label, "\n";

// For the sample only: a self-signed RSA certificate made in memory stands in for a KSeF Offline
// certificate. It is valid from now, so the link is signed for today's issue date. The helper
// uses the library's own OpenSSL configuration, so it also works where PHP finds no openssl.cnf.
$certificate = SelfSignedCertificate::seal('2222222222');

$kod2 = CertificateVerificationLink::fromHash(
    KsefEnvironment::Test,
    ContextIdentifier::nip('2222222222'),
    '2222222222',
    VerificationLink::invoiceHash($xml),
    new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')),
    $certificate,
);
echo $kod2, "\n"; // https://qr-test.ksef.mf.gov.pl/certificate/Nip/2222222222/2222222222/<serial>/<hash>/<signature>
```

With a real Offline certificate, `KsefQrCodes::offline()` builds both codes from the invoice, and
a renderer draws them:

```php
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\ChillerlanQrRenderer;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\KsefQrCodes;

$offlineCertificate = new CertificateCredentials(
    (string) file_get_contents('/run/secrets/ksef-offline.crt.pem'),
    (string) file_get_contents('/run/secrets/ksef-offline.key.pem'),
);

[$kod1, $kod2] = KsefQrCodes::offline(KsefEnvironment::Production, ContextIdentifier::nip('5265877635'), $xml, $offlineCertificate);

$renderer = new ChillerlanQrRenderer;                 // needs chillerlan/php-qrcode ^5
file_put_contents('kod1.svg', $renderer->svg($kod1)); // caption "OFFLINE" under the code
file_put_contents('kod2.png', $renderer->png($kod2)); // 5 pixels per module; PNG needs ext-gd
```

- `QrRenderer` is the interface: `svg(QrCode)` returns a scalable SVG document on a white
  background, and `png(QrCode, $pixelsPerModule = 5)` returns PNG bytes (the Ministry of Finance
  samples use 5 pixels per module). Both draw the caption under the code.
- `ChillerlanQrRenderer` uses `chillerlan/php-qrcode` ^5, an optional dependency
  (`composer require chillerlan/php-qrcode`). Its constructor throws `LogicException` when the
  package is not installed, and `png()` needs `ext-gd`. chillerlan encodes the link (byte mode,
  error correction level M, a 4-module quiet zone); the image and the caption are drawn here, so
  both formats look the same.
- **Observed on TE** (`qr-test.ksef.mf.gov.pl`, 2026-11-22): EC and RSA-PSS links built by the
  library verified ("Weryfikacja prawidłowa"); the same links with a tampered signature, and a link
  signed with a certificate that was then revoked, did not. KOD I of an offline invoice changed
  from "not found" to "found in KSeF" after the send.

## Technical correction

When KSeF rejects an offline invoice (for example status 450, a schema error), the seller sends a
technical correction: the corrected file, naming the rejected one by the SHA-256 of its bytes.
`sendTechnicalCorrection()` computes that hash from the rejected file itself:

```php
$rejected = (string) file_get_contents('FV-2026-001.rejected.xml'); // byte for byte as sent
$corrected = (string) file_get_contents('FV-2026-001.xml');

$sent = $ksef->sendTechnicalCorrection($corrected, $rejected);
$status = $ksef->awaitFinal($sent->documentId);
```

- **Why the file and not a hash.** KSeF accepts a correction naming a hash it has never seen
  (observed on TE), so a mistyped hash would not be refused. The rejected file must be well-formed
  XML; its schema is not checked, since KSeF rejected it. A rejected file identical to the
  corrected one is refused with `InvalidArgumentException` before anything is sent.
- The corrected file is validated (or an `Invoice` written) as in `send()`, and sent in the online
  session in offline mode. KSeF lists it with `hashOfCorrectedInvoice` (Base64 SHA-256 of the
  rejected file) and `invoicingMode` Offline in `InvoiceMetadata`.
- **One correction per invoice.** KSeF refuses a second technical correction of the same invoice
  with HTTP 400 and code 21166, thrown as `DocumentRejected` ("a technical correction was already
  accepted for the rejected invoice"). A correction naming an invoice KSeF accepted is refused
  with HTTP 400 and code 21167, also thrown as `DocumentRejected` ("the invoice it names was
  accepted and needs no technical correction").
- If you sent an `Invoice` object, keep the FA(3) bytes written for it: `send()` does not return
  them. A caller who kept only the hash can use the low-level `OnlineSession::send($xml, true,
  $hashOfCorrectedInvoice)`. The same fallback applies when the rejected file is not well-formed
  XML or carries a DOCTYPE (for example one KSeF rejected with status 430):
  `sendTechnicalCorrection()` refuses such a file with `InvalidArgumentException`.

## Errors and retries

Every failure is a `TransportException` subclass ([TRANSPORT.md](../TRANSPORT.md#errors)):

| Situation | Exception |
|---|---|
| HTTP 401, or KSeF code 21301 or 21304 in a 4xx answer (including a refused token refresh); HTTP 403 | `AuthenticationFailed` |
| HTTP 429 | `RateLimited` (`retryAfterSeconds` from `Retry-After`, null when the header is missing or unreadable) |
| HTTP 400 with a refused-body code (21401, 21402, 21403, 21405, 21217, 9101, 9102, 9103, 9105) or with 21166 (a second technical correction of the same invoice) or 21167 (a technical correction of an accepted invoice); an FA(3) file that fails local validation | `DocumentRejected` |
| Network failure or HTTP 5xx on a GET | `TransportUnavailable` (safe to retry; not repeated automatically) |
| Network failure or HTTP 5xx on a POST | `OutcomeUnknown` |
| HTTP 404, a not-found code, a redirect, or anything else | `UnexpectedResponse` |

Retries:

| Request | Automatic retry |
|---|---|
| GET answered with 429 | Repeated after `Retry-After` (5 seconds when the header is missing or unreadable), at most 3 times and at most 60 seconds of waiting per HTTP request (not per operation). A 429 whose delay does not fit the time left is thrown at once, without waiting. |
| GET failing otherwise | Never; `TransportUnavailable` is safe to retry yourself. |
| Any request, POST included, refused with HTTP 401 (or 21301/21304 in a 4xx answer) | Repeated once with a renewed access token (a refresh, or a new authentication when the refresh is refused). |
| Opening an online or batch session, starting an export, authenticating with a KSeF token, refused with 21470 (unknown public key id) | Repeated once after the Ministry of Finance key list is fetched again. |
| Any POST refused with 429, failing without an answer, or answered with 5xx | Never. |

Both repeats follow a refusal, so KSeF did not process the first request; nothing else is
repeated. An error code counts in any position of the error list.

**A resend is safe.** After an `OutcomeUnknown` on `send()`, send the same invoice again: KSeF
reports the second copy as a duplicate (status 440, Failed / Duplicate) naming the KSeF number of
the first. A 440 proves only that an invoice with that number exists, so confirm with
`isSameDocument()` that it is your file (see **Duplicates (440)** above). The library never repeats an invoice send that failed without an answer. An
`OutcomeUnknown` whose message names `/auth/token/refresh` (or another `/auth/` request) comes
from renewing the access token: the invoice was not posted (or KSeF refused it with 401), so
sending it again is safe as well.

**Other exceptions.** Besides `TransportException`, a method that talks to KSeF may throw
`InvalidArgumentException` when an authentication on the way cannot use the certificate or private
key (unreadable, or not RSA of at least 2048 bits or EC on P-256) or a Ministry of Finance key
cannot be loaded, and `RuntimeException` when OpenSSL fails while signing or an export finds no
usable temporary file. Both point to the configuration or the PHP environment. Arguments are
checked before any request and refused with `InvalidArgumentException`.

## Testing on KSeF TE

KSeF TE (`KsefEnvironment::Test`) accepts self-signed certificates and synthetic NIPs, so the whole
flow can be tried without a real certificate:

- `SelfSignedCertificate::seal($nip)` (organizationIdentifier `VATPL-<NIP>`, KSeF reports the
  method `xades.qualified-seal`) and `SelfSignedCertificate::person($nipOrPesel)` (serialNumber
  `TINPL-<NIP>` or `PNOPL-<PESEL>`, method `xades.qualified-certificate`) generate a fresh RSA-2048
  key in memory and a certificate valid for 30 days. `randomNip()` gives a NIP with a valid check
  digit. According to the KSeF documentation, KSeF refuses self-signed certificates outside TE
  (not verified: DEMO and PRODUCTION were not tried).
- `KsefTestData` calls the `/testdata` endpoints (subjects, attachments, permissions, rate limits).
  It refuses every environment but TEST. The rate-limit calls authenticate with the
  configuration's credentials themselves.

```php
use IntrAgile\EInvoicing\Transport\Ksef\Testing\KsefTestData;

$testData = new KsefTestData($config, $http); // TEST only
$testData->enableAttachments($nip);
$testData->setRateLimits(['invoiceSend' => ['perMinute' => 5]]);
$testData->resetRateLimits();
```

- **TE data is shared and public.** Every TE user can read what is sent there. Use synthetic data
  only: no real names, NIPs, addresses or amounts.
- **Maintenance window.** TE is unavailable every day from 16:00 to 18:00 Europe/Warsaw.
- The live test group `ksef-live` runs the flows on this page against TE. It needs no secrets: each
  run generates fresh NIPs and certificates. It is excluded by default, refuses plain http and every
  KSeF host but the TE API, `qr-test.ksef.mf.gov.pl` and the TE Latarnia, skips from 15:55 to 18:00 Europe/Warsaw and
  never prints a token or key. Every KSeF certificate it enrols is revoked at the end of its test,
  pass or fail: `vendor/bin/phpunit --group ksef-live`.

## What is verified for the KSeF transport

**Live-verified on KSeF TE (API 2.10 RC), 2026-11-21 and 2026-11-22; DEMO and PRODUCTION not
verified.**

- On 2026-11-21 the live group `ksef-live`, then 14 tests, passed in full in one run each, on
  PHP 8.5 (13:52–13:56 Europe/Warsaw) and on PHP 8.3 (13:56–14:00). In both runs the buyer inbox
  test listed the invoice after 6 `pending()` calls (130 s) and spent 7 metadata queries in total.
- On 2026-11-22 the group, extended by 9 tests for offline mode, KSeF certificates, KOD II, the
  technical correction and Latarnia, passed in full on PHP 8.5: 23 of 23 tests in one run
  (11:20–11:25 Europe/Warsaw), 8 metadata queries in total. With the duplicate proof added it passed
  in full again, one run each, on PHP 8.5 (13:10–13:15) and on PHP 8.3 (13:15–13:20): 23 of 23
  tests, 178 assertions, 8 metadata queries per run.
- RSA-OAEP runs through phpseclib on every PHP version.

| Flow | Status |
|---|---|
| XAdES authentication with a self-signed seal and a TINPL personal certificate (request namespace `auth/token/2.1`) | Live-verified on TE |
| KSeF token: generate (rev2), authenticate, approval of a token generated after token login, revoke | Live-verified on TE; rev2 is missing on PRODUCTION (API 2.9) |
| Token refresh; a refused refresh leads to a full authentication | Live-verified on TE |
| Online session: send, status, KSeF number, UPO (also before close), duplicate (440), schema-invalid invoice, a send into a session KSeF closed (21180) | Live-verified on TE |
| Batch: three invoices in two parts | Live-verified on TE |
| Inbox: pending, fetch, acknowledge | Live-verified on TE |
| Inbound ids: `pending()` returns `<KSeF number>@<date>`; `fetch()` on a fresh transport makes 0 metadata queries and returns byte-equal content; a plain KSeF number fetches at one metadata query. After `acknowledge()` the stored cursor moved to the window's high water mark, and a fresh transport on the same cursor store no longer listed the invoice | Live-verified on TE (2026-11-22; cursor effect 2026-11-23) |
| Duplicate proof: `isSameDocument()` is true for the same bytes, false for a different file with the same `P_2`; the original's UPO is fetchable while its session is open. A duplicate of an original from another context (403/21178) or a closed-session receipt was not exercised | Live-verified on TE (2026-11-22) |
| Export | Live-verified on TE |
| Correction referencing the KSeF number of the corrected invoice | Live-verified on TE |
| Unknown public key id (21470) recovery, rate limit (429) | Live-verified on TE |
| KSeF certificates: limits, enrolment data, EC and RSA Offline enrolment, query, revoke | Live-verified on TE |
| An EC Authentication certificate: enrolment and XAdES login (ECDSA, method `xades.ksef-certificate`) | Live-verified on TE |
| KOD II: EC (P1363) and RSA-PSS links verify on `qr-test`; a tampered signature and a revoked certificate do not | Live-verified on TE, with the `Nip` context only |
| KOD II with the `InternalId` and `NipVatUe` context segments | Offline tests only |
| KOD II for a certificate whose serial starts with a zero byte (padded to 16 digits) | Offline tests only; no such certificate could be issued on TE on demand |
| KOD I of an offline invoice: "not found" before the send, found after it | Live-verified on TE |
| Offline send: `invoicingMode` Offline in the status; an online send with yesterday's `P_1` reported as Offline | Live-verified on TE |
| Technical correction of a rejected offline invoice (450), `hashOfCorrectedInvoice` in the metadata, a second correction refused (21166) | Live-verified on TE |
| Latarnia TE: `status()` and `messages()` parse (shape only; TE serves stale sample events) | Live-verified on TE |
| Latarnia PRODUCTION | Offline tests with canned answers only; not called live |
| A revoked Authentication certificate no longer logs in | Not verified live |
| Offline deadlines and Polish working days | Offline tests only (including the documented example); a calculation aid, not legal advice |
| Rendering QR images (`ChillerlanQrRenderer`) | Offline tests only: the rendered PNG is decoded again and compared with the link |
| A second 9105, export status 420 | Not observed live; offline tests only |
| The form code `fetch()` derives from a UBL (FA_PEF) download | Offline tests only (no PEF invoice can be produced on TE) |
| A technical correction of an accepted invoice (21167) | Offline tests only |
| UPO signature of the Ministry of Finance | **Not verified** (by design) |
| Qualified certificates with multi-valued RDNs in the issuer name | Not verifiable without a real certificate (DEMO/PRODUCTION) |

The published authentication schema has a defect: the `TNipVatUE` and `TPeppolId` patterns end in
a literal `$`. The bundled copy is kept byte-identical and the defect is documented in its
`SOURCE.md`.

## Not covered yet

- **Reading FA_PEF(3) and FA_KOR_PEF(3) downloads** (the Peppol-based invoices KSeF serves, form
  codes `PEF (3)` and `PEF_KOR (3)`, UBL 2.1 with Polish extensions): they download like any other
  invoice, but `Fa3Reader` does not map them. Only Peppol providers can issue them, so a PEF
  invoice cannot be produced on TE without a Peppol provider identity, and a reader could only be
  verified offline. Until then, read them as UBL 2.1 yourself; the generic `UblReader` was not
  tested on PEF files.
- **Typed builders** for the transport blocks, the new means of transport, the authorised party and
  the attachment, which are verbatim XML fragments for now.
- **`Podmiot1K/PrefiksPodatnika` and `Podmiot2K/IDNabywcy`** in correction data.
- The Laravel integration (configuration, queued sending, scheduled inbound polling, a persistent
  cursor store) belongs to `intragile/laravel-einvoicing`, not to this package.
- **Not planned:** sending FA_PEF (Peppol providers only); permission management beyond the TE
  test data; the Trusted Profile; verifying the Ministry of Finance UPO signature;
  FA_RR(1) farmers' invoices and FA(2), which KSeF accepts only on TEST and FA(3) replaced.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

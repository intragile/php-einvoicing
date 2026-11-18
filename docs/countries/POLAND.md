# Poland

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

How this package builds, reads and validates Polish structured invoices in the FA(3) format of
KSeF, how the shared EN 16931 model maps onto FA(3), and what is not covered yet. This page is a
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
rules, no official executable reference; live transmission not yet verified.**

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
- Nothing is sent to KSeF yet (see [Not covered yet](#not-covered-yet)).

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
number below 1.

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

Rows are read as written, with nothing guessed:

- **A row without `P_12`** (allowed on simplified invoices and in the cases of p. 92) that is not a
  margin row is marked `PolishLine::$rateCodeOmitted` and written back without `P_12`. When the
  invoice has exactly one summary bucket, the line takes the category and rate of that bucket's
  default code (for example S 23 for `P_13_1`); with several buckets its bucket is unknown, so it
  is category O without a rate, and writing it back is refused unless `P_13_8` is present.
- **A row with `P_12_XII` and without `P_12`** is a one-stop-shop line (category S at that rate).
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

The guard refuses an OSS line with a rate code override, a margin flag, or another category or
rate than S at its OSS rate. On read, such rows come back as S at the OSS rate with the OSS rate
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
BT-46), the seller legal registration (BT-30) and the buyer's next to a VAT identifier (BT-47),
the seller tax registration and additional legal information (BT-32, BT-33), electronic addresses
(BT-34, BT-49), a VAT accounting currency other than PLN (BT-6), the tax point date code (BT-8),
the paid amount (BT-113, except on ROZ) and the rounding amount (BT-114) when not zero, payment card
and direct debit (BG-18, BG-19), note subject codes (BT-21), country subdivisions (BT-39, BT-54),
line periods (BG-26), item price discounts and gross prices (BT-147, BT-148), item classifications
(BT-158), item attributes (BG-32), scheme identifiers FA(3) has no field for (BT-128-1, BT-47-1),
a price base quantity unit (BT-150) other than the line unit, the payment means text (BT-82)
when a payment form is written, a third party with an internal identifier (`IDWew`) next to a VAT
identifier or a legal registration, a GLN (`PolishParty::$gln`) of a party without an address, a
rate code override outside the line's category or rate bucket, and data on a bucket line beyond
its amounts (see [Invoices without rows](#invoices-without-rows)). VAT exemption reasons (BT-120, BT-121) are accepted only with the
exemption annotation (`Annotations::$exemption`, `P_19` with its legal basis), which supersedes
them; the legal basis is never guessed.

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
- **Omitted `P_8A`, `P_8B`, `P_9A`, `P_12`.** A row read without `P_12` is marked
  `rateCodeOmitted` and written back without it (see [Read](#read)). A row read without the others
  is marked (`unitOmitted`, `quantityOmitted`, `unitPriceOmitted`) and written back without them while the line still holds
  the values read from their absence. For `P_9A` the check is by value: a net price changed to an
  equal value at another scale (for example `45.9` and `45.90`) keeps `P_9A` omitted, so the price
  comes back at the reader's scale.
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
another link. `KsefEnvironment` gives the base URL of TEST, DEMO and PRODUCTION. This package
outputs the URL only; rendering the QR image is up to you.

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

## Not covered yet

- **Sending to KSeF.** The KSeF API 2.0 transport (authentication, XAdES signing, invoice
  encryption, online and batch sessions, status and UPO, download) is planned next. Until then this
  package builds, reads and validates FA(3) files; you send them.
- **Reading FA_PEF(3) and FA_KOR_PEF(3) downloads** (the Peppol-based invoices KSeF serves).
- **Offline mode:** the offline certificate, the signed KOD II link, the offline and outage deadline
  calculator, and the technical correction.
- **QR images.** Only the KOD I URL is produced.
- **Laravel bridge** (configuration per environment, queued sending, inbound polling, events).
- **Typed builders** for the transport blocks, the new means of transport, the authorised party and
  the attachment, which are verbatim XML fragments for now.
- **`Podmiot1K/PrefiksPodatnika` and `Podmiot2K/IDNabywcy`** in correction data.
- **Not planned:** FA_RR(1) farmers' invoices and FA(2), which KSeF accepts only on TEST and FA(3)
  replaced.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

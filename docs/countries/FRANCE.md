# France

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

What a French e-invoice needs, which formats this package builds, reads and validates, and what is
not covered yet. This page is a technical guide, not legal or tax advice.

## What this package is in the reform

Every in-scope French invoice must pass through a *plateforme agréée* (PA, approved platform). This
package is not a PA and never talks to the DGFiP. In the reform's terms it is a *solution
compatible*: software that builds, reads and validates the French documents and hands them to a
PA. The transport to a PA is not part of this version (see [Not covered yet](#not-covered-yet)).

The dates below come from our research notes, which cite the DGFiP and the BOFiP; check them
against the BOFiP before you rely on them in a product or a contract.

| What | Date | Source in our research |
|---|---|---|
| Every taxpayer in scope must be able to **receive** e-invoices; large and mid-size companies must **issue** them | 1 September 2026 | BOI-TVA-DECLA-20-30-60 and the DGFiP landing page |
| Small and micro companies must **issue** | 1 September 2027 (may move by decree to 1 December 2027 at the latest) | BOI-TVA-DECLA-20-30-60; the deferral option from a secondary source |
| FNFE-MPE French rules (Flux 2) "applicable en émission" | 1 October 2026 | header of `BR-FR-Flux2-Schematron-CII.sch` 1.4.0.04 |

The Finance Bill for 2027 was still in Parliament when this page was written and may change who
must issue in 2027. It does not change the formats.

## Formats

The French base set is the same UBL 2.1, CII and Factur-X stack the rest of the package handles,
with French rules on top. Two profiles exist, told apart by the specification identifier (BT-24):

- **EN 16931**: `urn:cen.eu:en16931:2017`.
- **EXTENDED-CTC-FR**: `urn:cen.eu:en16931:2017#conformant#urn.cpro.gouv.fr:1p0:extended-ctc-fr`,
  for multi-vendor invoices, sub-lines and the other EXT-FR-FE data.

| Format | Writing | Reading | Validation (`FranceValidator`) |
|---|---|---|---|
| UBL Invoice, EN 16931 and EXTENDED-CTC-FR | yes | yes | `validate()` |
| UBL CreditNote, EN 16931 and EXTENDED-CTC-FR | yes | yes | `validate()` |
| CII D22B, EN 16931 and EXTENDED-CTC-FR | yes (D22B is the default) | yes | `validate()` |
| Factur-X EN 16931, BASIC | yes (XML and PDF/A-3 embedding) | yes | `validateFacturX()` |
| Factur-X EXTENDED with French data | yes | yes | `validateFacturX()` |
| Factur-X BASIC WL | yes (XML) | no: it has no invoice lines, which the model requires | `validateFacturX()` |
| Factur-X MINIMUM and XRECHNUNG | yes (generic writer) | MINIMUM no, XRECHNUNG yes | refused: not invoices under the French rules |
| CDAR lifecycle messages, Flux 10 e-reporting | no | no | refused by `FranceValidator`; their rule sets are installed but have no validator yet |

`CiiWriter` writes UN/CEFACT D22B, which the reform requires, and allows several preceding invoice
references (BG-3). XRechnung CII stays D16B.

## Install the French rules

The executable French rules are published by FNFE-MPE in the
[France_RFE](https://github.com/fnfempe/France_RFE) repository. Its licence statements contradict
each other (the repository says Apache-2.0, every Schematron header names a EUPL version that does
not exist), so this package ships none of its files. One command downloads them at the pinned
commit `97ba0f3d4ef2abed5780e9490d5c3a2be832ab1d` (release 1.4.0.04), checks every file against the
SHA-256 sum in `resources/france/france-rfe-1.4.0.04.sha256`, compiles the seven rule sets on your
machine and copies the FNFE XML Schemas next to them. Every compiled file says it was compiled
locally and must not be redistributed.

```bash
vendor/bin/einvoicing-rules install france --dir=var/france-rules
vendor/bin/einvoicing-rules check france --dir=var/france-rules
```

On a server without network access, pass `--from=DIR`, the root of a France_RFE checkout at the
pinned commit; the same checksums apply. `check` exits 0 when every rule set loads with the
installed engine and every schema file matches its checksum. Reinstall after an upgrade of this
package when `check` fails. Factur-X documents also need the Apache-2.0 rules package:

```bash
composer require intragile/einvoicing-rules-facturx
```

The seven rule sets are Flux 2 for CII and UBL, EXTENDED-CTC-FR for CII and UBL, FNFE's Factur-X
EXTENDED rules, CDAR and Flux 10. Where an upstream file leaves out what the engine needs, the
installer compiles it with documented options, never by editing it
([VALIDATION.md](../VALIDATION.md#french-rules)).

## Validate

```php
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;

$validator = FranceValidator::create(__DIR__.'/var/france-rules');

// Standalone UBL or CII: BT-24 must be EN 16931 or EXTENDED-CTC-FR.
$report = $validator->validate($xml);

// The CII of a Factur-X invoice; the profile is optional and checked against the document.
$report = $validator->validateFacturX($facturXXml, FacturXProfile::Extended);

if (! $report->isValid()) {
    foreach ($report->fatal() as $finding) {
        echo $finding->ruleId, ': ', $finding->message, "\n";
    }
}
```

The specification identifier selects a chain. Every chain checks the XML Schema first and then runs
its rule sets in order; the rule sets run even when the schema check fails, so one report lists
every problem.

| Document | Schema | Rule sets |
|---|---|---|
| UBL or CII, EN 16931 | FNFE UBL 2.1 or CII D22B | CEN EN 16931 1.3.16, Flux 2 of the syntax |
| UBL or CII, EXTENDED-CTC-FR | FNFE UBL 2.1 or CII D22B | EXTENDED-CTC-FR of the syntax, Flux 2 of the syntax |
| Factur-X BASIC WL, BASIC, EN 16931 | Factur-X profile schema | Factur-X 1.09.2 profile rules, Flux 2 CII |
| Factur-X EXTENDED | Factur-X EXTENDED schema | FNFE's Factur-X EXTENDED rules, Flux 2 CII |

French Factur-X EXTENDED runs FNFE's patched copy of the Factur-X 1.09.2 EXTENDED rules, not the
upstream rules of `intragile/einvoicing-rules-facturx`. The upstream EXTENDED rules filter lines
by sub-type on a path that never exists (`ram:AssociatedDocumentLineDocument` as a child of
`ram:SpecifiedLineTradeSettlement`), so the nine VAT category rules `S-01` to `AG-01` also count
GROUP and INFORMATION lines; FNFE's fork filters on the right path and sums amounts as decimals.
Generic ZUGFeRD and Factur-X EXTENDED keep the upstream rules.

## Build a French invoice

`France::mark()` sets the process control (BG-2): the billing framework (*cadre de facturation*)
as the business process type (BT-23) and the profile as BT-24. It also attaches the
`FrenchInvoice` extension. The helpers in `IntrAgile\EInvoicing\France` cover the French data:

- `Siren::identifier()` (scheme 0002) and `Siret::identifier()` (scheme 0009); only the format is
  checked, no checksum. `Siret::siren()` returns the first nine digits.
- The four mandatory mentions as notes with their subject codes: `recoveryFeeNote()` (PMT),
  `latePaymentPenaltyNote()` (PMD), `earlyPaymentDiscountNote()` (AAB), and `treatmentNote()`
  (BAR, for example `Treatment::B2B`). `singleVatGroupNote()` (TXD) is for a single VAT group. You
  supply the wording.
- `BillingFramework` holds the framework codes (B1, S1, M1 … with the 8 and 9 variants).

<!-- sample: run -->
```php
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
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;

$seller = new Party(
    name: 'LE VENDEUR',
    identifiers: [Siret::identifier('10000000900017')],
    legalRegistration: Siren::identifier('100000009'),
    vatIdentifier: 'FR88100000009',
    electronicAddress: new ElectronicAddress('100000009', ElectronicAddressScheme::C_0225),
    address: new PostalAddress(country: CountryCode::FR, line1: "35 rue d'ici", city: 'PARIS', postCode: '75018'),
);
$buyer = new Party(
    name: 'LE CLIENT',
    legalRegistration: Siren::identifier('200000008'),
    vatIdentifier: 'FR37200000008',
    electronicAddress: new ElectronicAddress('200000008', ElectronicAddressScheme::C_0225),
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
    notes: [
        France::recoveryFeeNote('Indemnité forfaitaire pour frais de recouvrement : 40 €.'),
        France::latePaymentPenaltyNote("Pénalités de retard : trois fois le taux d'intérêt légal."),
        France::earlyPaymentDiscountNote('Pas d’escompte pour paiement anticipé.'),
        France::treatmentNote(Treatment::B2B),
    ],
    lines: [
        new InvoiceLine('1', '200', UnitCode::HUR, new Price('40.00'), new Item('SERVICE_FOURNI1'),
            new LineVat(VatCategory::StandardRate, '20')),
    ],
);

$invoice = TotalsCalculator::complete(France::mark($invoice, BillingFramework::S1));

$cii = (new CiiWriter)->write($invoice); // CII D22B with BT-23 S1 and BT-24 EN 16931
```

Before writing a French invoice, `UblWriter`, `CiiWriter` and the Factur-X writer run
`FrenchGuard::check()`, which throws an `InvalidArgumentException` when a value has more decimals
than the French rules allow: amounts 2, quantities 4, prices 6 and VAT rates 2. Decimals are
counted as the model holds them, so `'40.000'` as an amount fails even though it equals `40`. The
guard checks only these limits and the line structure below; the business rules are left to
`FranceValidator`, which needs the installed rules.

## Read a French invoice

`UblReader` and `CiiReader` attach the `FrenchInvoice` extension when BT-23 is a French billing
framework or BT-24 is EXTENDED-CTC-FR. BT-24 alone being EN 16931 is not enough, because every CEN
invoice carries it; a French EN 16931 invoice without BT-23 therefore reads as an ordinary
invoice, which the French rules would reject anyway (BT-23 is mandatory).

```php
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;

$invoice = (new CiiReader)->read($xml)->invoice;
$french = $invoice->extensions()->get(FrenchInvoice::class); // null for a non-French invoice
```

**Converting a French Factur-X EXTENDED invoice to UBL or CII.** A French invoice read from
Factur-X EXTENDED gets the profile EXTENDED-CTC-FR, but its BT-24 stays the Factur-X EXTENDED
guideline identifier the document carried. The UBL and CII writers write the model as given, so
the standalone document keeps that BT-24, and `FranceValidator::validate()` refuses it. Set BT-24
first, without replacing the extension (which holds the sub-lines):

```php
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;

$framework = BillingFramework::from((string) $invoice->processControl?->businessProcessType);
$invoice = $invoice->withProcessControl(France::processControl($framework, FrenchProfile::ExtendedCtcFr));
$cii = (new CiiWriter)->write($invoice);
```

`France::mark()` would set the same BT-24 but also replace the `FrenchInvoice` extension with an
empty one, dropping the GROUP and INFORMATION lines and the line order; the writer then refuses a
DETAIL line whose parent was dropped.

## EXTENDED-CTC-FR lines

EXTENDED-CTC-FR adds data per line (EXT-FR-FE-xxx in the DGFiP annex 1) and sub-lines. A line has
a sub-type (EXT-FR-FE-163): DETAIL lines and lines without a sub-type are invoice lines and are
summed into the totals; GROUP lines head the DETAIL lines that name them as parent
(EXT-FR-FE-162), and INFORMATION lines carry information only. The French rules sum only DETAIL
lines and lines without a sub-type, so the model keeps GROUP and INFORMATION lines apart:

- `FrenchLine`, attached to an `InvoiceLine` through its extension bag, holds the French line data:
  parent line, DETAIL sub-type, line seller (`LineSeller`, for multi-vendor invoices), line
  preceding invoice, line delivery (`LineDelivery`), line VAT exemption reason and VAT point date
  code, line VAT amount (EXT-FR-FE-181), line amount with VAT (EXT-FR-FE-184), line note subject
  code, and further invoiced object identifiers.
- `InformationalLine` is a GROUP or INFORMATION line. It is held by `FrenchInvoice`, outside
  `Invoice::$lines` and the totals; its amounts are informative and written as given.
- `FrenchInvoice::$lineOrder` lists every line identifier (BT-126), regular and informational, once,
  in document order. It is required when there are informational lines.
  `FrenchInvoice::linesInDocumentOrder()` returns the merged list.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineSubType;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;

$invoice = (new UblReader)->read((string) file_get_contents('invoice.xml'))->invoice;

// Every invoice line becomes a DETAIL line of one GROUP line.
$lines = array_map(
    static fn (InvoiceLine $line): InvoiceLine => $line->withExtension(new FrenchLine(parentLineId: 'G1', subType: LineSubType::Detail)),
    $invoice->lines,
);
$group = new InformationalLine(id: 'G1', subType: LineSubType::Group, item: new Item('Bundle'), quantity: '1', unit: UnitCode::C62);
$order = ['G1', ...array_map(static fn (InvoiceLine $line): string => $line->id, $lines)];

$invoice = France::mark($invoice->withLines($lines), BillingFramework::B1, FrenchProfile::ExtendedCtcFr)
    ->withExtension(new FrenchInvoice(FrenchProfile::ExtendedCtcFr, [$group], $order));

$cii = (new CiiWriter)->write($invoice); // GROUP line first, then the DETAIL lines
```

`FrenchGuard` checks the structure before writing: French line data needs the EXTENDED-CTC-FR
profile, regular and informational lines have distinct identifiers, a line order is given only for
EXTENDED-CTC-FR and then lists every line once, and every parent line identifier names another line
of the invoice. `FrenchInvoice` refuses informational lines for another profile.

Per syntax:

- **CII D22B and Factur-X EXTENDED** carry every field.
- **UBL** has no element for the line amount with VAT (EXT-FR-FE-184): `UblWriter` refuses a line
  that carries it rather than drop it. It also refuses a line VAT point date code without a line
  period (BG-26), because UBL writes the code inside the line period.
- **Factur-X**: `FacturXXmlWriter` refuses an EXTENDED-CTC-FR invoice for any profile but EXTENDED,
  because only EXTENDED carries the French line data. The Factur-X EXTENDED document carries the
  Factur-X EXTENDED guideline as BT-24, as FNFE's French Factur-X EXTENDED examples do.
- **CII D16B** (`new CiiWriter(CiiVersion::D16B)`) is not a French syntax; use the D22B default.

## How it is verified

All of this is verified offline. Nothing has been sent to a PA or the DGFiP from this version.

- **FNFE examples.** In its own chain, each of the 41 FNFE invoice examples (13 UBL, 13 CII, 15
  Factur-X) passes with no finding of any severity. The corpus has no UBL CreditNote and no
  Factur-X BASIC example, so those chains are tested with documents we generate. The 13 Factur-X
  examples with lines read into the model and write back; the two BASIC WL examples have no lines
  and are validated only.
- **Our own output.** Invoices written by this package (UBL, CII D22B, Factur-X EN 16931, BASIC and
  EXTENDED, with and without sub-lines) pass the FNFE schemas and every rule set of their chain.
- **Negative corpus.** Of the 692 French rules in scope (the French-only rule ids and the 12 asserts
  of FNFE's Factur-X EXTENDED fork), 667 have a recipe that breaks exactly that rule while the
  document stays schema-valid. The other 25 cannot fire on a schema-valid document (for example a
  test that is always true); each has its reason recorded next to the recipes.
- **Verdict equality with FNFE's XSLT.** 4,889 documents (the FNFE examples, our output, the
  negative recipes and about 4,000 mutations) were validated both by this package and by FNFE's own
  compiled XSLT in SaxonC-HE 13; the verdicts are equal for every document. The only accepted
  difference is the one documented in [VALIDATION.md](../VALIDATION.md#lets-and-xslt-functions):
  where a mutation makes a date or an amount unreadable, Saxon may evaluate an XSLT local variable
  or a rule-level let eagerly and stop with `FORG0001`, while this package evaluates it lazily and
  reports the date or amount format rule (BR-FR-03 or the Flux 10 amount rule) for that field. The
  test allows exactly that case (46 documents) and pins how often it is used.

## Not covered yet

- **CDAR lifecycle messages and Flux 10 e-reporting.** Their FNFE rule sets and schemas are
  installed and compiled, but there is no builder, reader or validator API for them yet.
- **Transport to a PA**, directory (*annuaire*) lookup and the routing between e-invoicing and
  e-reporting. Of the existing transport drivers, B2Brouter is a registered PA; e-invoice.be and
  Recommand are Peppol access points, not PAs, and do not satisfy a French sender's obligation.
- **Chorus Pro** (B2G) is out of scope.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

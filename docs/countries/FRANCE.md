# France

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

What a French e-invoice needs, which formats this package builds, reads and validates, how it
covers the lifecycle statuses, e-reporting and routing, and what is not covered yet. This page is
a technical guide, not legal or tax advice.

## What this package is in the reform

Every in-scope French invoice must pass through a *plateforme agréée* (PA, approved platform). This
package is not a PA and never talks to the DGFiP. In the reform's terms it is a *solution
compatible*: software that builds, reads and validates the French documents and hands them to a
PA. It builds the invoice, the lifecycle message (*compte rendu de vie*, CDV) and the Flux 10
e-reporting file; the PA transmits them. The transport to a PA is not part of this version (see
[Not covered yet](#not-covered-yet)).

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
| CDAR D22B lifecycle message (CDV) | yes (`CdarWriter`) | yes (`CdarReader`) | `CdarValidator` |
| Flux 10 e-reporting transmission | yes (`Flux10Writer`) | no: reading a transmission is the PA's job | `Flux10Validator` |

`FranceValidator` is for invoices only; it refuses a CDAR or Flux 10 document and names the
validator to use.

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

Lifecycle messages and e-reporting files have their own validators, which run the FNFE schema and
then the CDAR or Flux 10 rule set (again even when the schema check fails) and return a
`MessageValidationReport` with the same `isValid()`, `fatal()`, `warnings()` and `verdict()` as an
invoice report:

```php
use IntrAgile\EInvoicing\Validation\France\CdarValidator;
use IntrAgile\EInvoicing\Validation\France\Flux10Validator;

$report = CdarValidator::create(__DIR__.'/var/france-rules')->validate($cdvXml);
$report = Flux10Validator::create(__DIR__.'/var/france-rules')->validate($flux10Xml);
```

For invoices, the specification identifier selects a chain. Every chain checks the XML Schema first and then runs
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
first with `France::mark()`, which keeps the GROUP and INFORMATION lines and the line order of the
attached extension and changes only the profile:

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;

// The Factur-X EXTENDED XML of a French invoice; usually (new FacturXPdfReader)->read($pdf).
$source = (new UblReader)->read((string) file_get_contents('invoice.xml'))->invoice;
$facturX = (new FacturXXmlWriter)->write(France::mark($source, BillingFramework::B1), FacturXProfile::Extended)->xml;

$invoice = (new CiiReader)->read($facturX)->invoice; // EXTENDED-CTC-FR, BT-24 still Factur-X EXTENDED
$framework = BillingFramework::from((string) $invoice->processControl?->businessProcessType);
$invoice = France::mark($invoice, $framework, FrenchProfile::ExtendedCtcFr);
$cii = (new CiiWriter)->write($invoice);
```

Marking an invoice that has GROUP or INFORMATION lines with another profile than EXTENDED-CTC-FR
throws an `InvalidArgumentException`, because only that profile carries them.

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

## Lifecycle statuses

Every status of an invoice after it is issued travels as a lifecycle message, a CDV (*compte rendu
de vie*) in the UN/CEFACT CrossDomainAcknowledgementAndResponse D22B syntax (CDAR, flux 6). The
platforms exchange them; four statuses must also reach the public portal (PPF):

| Code | Label | `LifecycleStatus` | Sent to the PPF | Reason required |
|---|---|---|---|---|
| 200 | Déposée | `Deposited` | yes | |
| 201 | Emise par la plateforme | `Issued` | | |
| 202 | Reçue par la plateforme | `Received` | | |
| 203 | Mise à disposition | `MadeAvailable` | | |
| 204 | Prise en charge | `InProcess` | | |
| 205 | Approuvée | `Approved` | | |
| 206 | Approuvée partiellement | `PartiallyApproved` | | yes |
| 207 | En litige | `Disputed` | | yes |
| 208 | Suspendue | `Suspended` | | yes |
| 209 | Complétée | `Completed` | | |
| 210 | Refusée | `Refused` | yes | yes |
| 211 | Paiement transmis | `PaymentSent` | | |
| 212 | Encaissée | `Collected` | yes | |
| 213 | Rejetée | `Rejected` | yes | yes |
| 214 | Visée | `Endorsed` | | |
| 220 | Annulée | `Cancelled` | | |
| 221 | Erreur de routage | `RoutingError` | | |
| 224 | Demande de paiement direct | `DirectPaymentRequested` | | |
| 225 | Affacturée | `Factored` | | |
| 226 | Affacturée confidentiel | `FactoredConfidential` | | |
| 227 | Changement de compte à payer | `PaymentAccountChanged` | | |
| 228 | Non affacturée | `NotFactored` | | |
| 501 | Irrecevable | `Inadmissible` | | yes |

The labels of 214 and 224 to 228 are unverified: the AFNOR XP Z12-012 standard that defines them
was not available. The codes the public portal emits for its other flows (250, 251, 300, 301,
400, 401, 500, 601) are not invoice statuses; `CdarReader` refuses them with an
`UnsupportedDocument` that names the flow. `LifecycleStatus::isMandatory()`, `requiresReason()`
and `label()` return the columns above; `acknowledgementType()` gives the message type (MDT-77).
`StatusReason` holds the closed list of reason codes (rule BR-FR-CDV-CL-09) and
`RequestedAction` the requested actions.

`Lifecycle` builds the messages:

- `deposited()` (200, from the seller's platform), `rejected()` (213, from a platform, with a
  reason and an optional comment), `refused()` (210, from the buyer, with a reason and a required
  comment) and `collected()` (212, from the seller, one collected amount per VAT rate);
- `status()` for any other status, with the sender, the issuer, the recipients and the status
  details you give.

The referenced invoice is an `InvoiceReference`; `InvoiceReference::of($invoice, $receivedAt)`
takes the number, type and issue date from the invoice and the seller's SIREN from BT-30 or from
its first SIRET. Parties are `LifecycleParty` values: `platform()` (a PA by its matricule, scheme
0238), `ppf()`, `seller()` and `buyer()` (by SIREN, with an optional electronic address).

The message identifier defaults to the pattern of the FNFE examples,
`{invoice number}_{status}_{receipt time}#{invoice type}_{issue date}`, for example
`F202600010_212_20261102101500#380_20261102`. When that would exceed the 50 characters allowed
(an invoice number of more than 18 characters), the identifier becomes
`{status}_{receipt time}_{hash}`, 49 characters, where the hash is the first 30 hexadecimal
characters of the SHA-256 of the full pattern. Pass `id:` to set your own. Every other field has a
wither on `LifecycleMessage`.

CDAR timestamps carry no time zone. `CdarWriter` converts them to French local time
(`Europe/Paris`) before writing, and `CdarReader` returns them in that zone. Local times in the
autumn fall-back hour (02:00 to 03:00 on the last Sunday of October) occur twice and are therefore
ambiguous in format 204. A message reports on
exactly one invoice, as in every FNFE example; the reader refuses a document with more than one
acknowledgement or referenced document.

**Encaissée (212).** Services whose VAT is due on collection report each payment with status 212,
the collected amount split by VAT rate. `Collection::allocate()` splits a paid amount over the
invoice's VAT breakdown in proportion to each rate's gross amount (taxable amount plus VAT),
rounded to two decimals, with the rounding residual on the rate with the largest gross amount (the
higher rate on a tie). A negative amount is a refund.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\Collection;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\Lifecycle;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarWriter;
use IntrAgile\EInvoicing\Validation\France\CdarValidator;

$invoice = TotalsCalculator::complete(France::mark(new Invoice(
    number: 'F202600010',
    issueDate: new DateTimeImmutable('2026-11-02'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: new Party(name: 'LE VENDEUR', legalRegistration: Siren::identifier('100000009'),
        address: new PostalAddress(country: CountryCode::FR, city: 'PARIS')),
    buyer: new Party(name: 'LE CLIENT', legalRegistration: Siren::identifier('200000008'),
        address: new PostalAddress(country: CountryCode::FR, city: 'NICE')),
    lines: [
        new InvoiceLine('1', '1', UnitCode::C62, new Price('750.00'), new Item('Maintenance'),
            new LineVat(VatCategory::StandardRate, '20')),
        new InvoiceLine('2', '1', UnitCode::C62, new Price('100.00'), new Item('Formation'),
            new LineVat(VatCategory::StandardRate, '5.5')),
    ],
), BillingFramework::S1));

$paris = new DateTimeZone('Europe/Paris');

// The invoice as the platform received it, and the 1,005.50 EUR paid, split per VAT rate.
$reference = InvoiceReference::of($invoice, new DateTimeImmutable('2026-11-02 10:15:00', $paris));
$amounts = Collection::allocate($invoice, '1005.50'); // 900.00 at 20 %, 105.50 at 5.5 %

$message = Lifecycle::collected(
    $reference,
    LifecycleParty::seller('100000009', name: 'LE VENDEUR'),
    LifecycleParty::buyer('200000008', new Identifier('200000008', '0225'), 'LE CLIENT'),
    $amounts,
    CurrencyCode::EUR,
    new DateTimeImmutable('2026-11-20 09:00:00', $paris),
);
$xml = (new CdarWriter)->write($message); // id F202600010_212_20261102101500#380_20261102

$report = CdarValidator::create('var/france-rules')->validate($xml);
if (! $report->isValid()) {
    throw new RuntimeException('The lifecycle message is not valid: '.implode(', ', $report->verdict()));
}
```

`CdarReader` reads a CDV back into a `LifecycleMessage`. Elements it does not map are listed in
`LifecycleReadResult::$ignored`, as in the invoice readers:

```php
use IntrAgile\EInvoicing\Syntax\Cdar\CdarReader;

$result = (new CdarReader)->read($xml);
$status = $result->message->status;           // LifecycleStatus::Collected
$invoiceNumber = $result->message->invoice->number;
```

## E-reporting

Transactions that are not e-invoiced, and the payments of services whose VAT is due on
collection, are e-reported: the PA sends the DGFiP a Flux 10 file. There are four flows:

| Flow | What | Model |
|---|---|---|
| 10.1 | Transactions invoice by invoice, such as international B2B sales | `TransactionsReport::$invoices` (`Invoice` values) |
| 10.2 | Payments invoice by invoice | `PaymentsReport::$invoices` (`InvoicePayment`) |
| 10.3 | Transactions aggregated per day, such as B2C sales | `TransactionsReport::$days` (`DailyTransactions`) |
| 10.4 | Payments aggregated per day | `PaymentsReport::$days` (`DailyPayments`) |

An `EReport` is one transmission: `EReport::transactions()` or `EReport::payments()` with the
identifier, the creation time, `ReportType::Initial` or `Corrective`, the sending PA
(`LifecycleParty::platform()` with its four-character matricule and its name), the `Declarant`
(SIREN, name and `DeclarantRole::Seller` or `Buyer`) and the report with its `ReportPeriod`
(`decade()`, `month()` or `bimester()`, following the declarant's VAT regime). `Flux10Writer`
writes it in the DGFiP schema; there is no Flux 10 reader, since nothing but the PA consumes a
transmission.

Invoices reported one by one (10.1) are written straight from the `Invoice` model. The DGFiP
annex gives no BT column for most Flux 10 fields, so the mapping is our reading and is listed in
the `Flux10Writer` class documentation. The VAT due date code is written in the UNTDID 2475 form
the CII writer uses (3 → 5, 35 → 29, 432 → 72). A seller is identified by its legal registration
(BT-30) when its scheme is 0002, 0223, 0227, 0228 or 0229, else by its VAT identifier (another EU
member state, scheme 0223), else by country code and name (outside the EU, scheme 0227); the
same applies to the buyer. EU membership here is not the routing territory (FR, GP, MQ, RE):
Guadeloupe, Martinique and Réunion are outside the EU VAT area, so a party there without a SIREN
gets scheme 0227. A buyer without any of these is written without an identifier; a
seller without one is refused.

**B2C days (10.3).** `B2cAggregator` groups sales to consumers by operation date (the actual
delivery date BT-72, else the issue date BT-2), currency and category. The category follows the
billing framework: B… frameworks are goods (`TransactionCategory::Goods`, TLB1), S… frameworks
are services (TPS1). Mixed frameworks (M…), supplies not taxed in France (TNT1) and the margin
scheme (TMA1) need a classifier, a closure that returns the category of each line; an invoice
whose lines fall in several categories is split by line and cannot carry document-level
allowances or charges. Credit notes (381, 261, 396, 502, 503) count negative. Only invoices in
euros are accepted, because the report carries no conversion rate.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\EReporting\B2cAggregator;
use IntrAgile\EInvoicing\France\EReporting\Declarant;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\ReportPeriod;
use IntrAgile\EInvoicing\France\EReporting\ReportType;
use IntrAgile\EInvoicing\France\EReporting\TransactionsReport;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Syntax\Flux10\Flux10Writer;
use IntrAgile\EInvoicing\Validation\France\Flux10Validator;

$seller = new Party(name: 'LE VENDEUR', legalRegistration: Siren::identifier('100000009'),
    address: new PostalAddress(country: CountryCode::FR, city: 'PARIS'));
$consumer = new Party(name: 'PARTICULIER', address: new PostalAddress(country: CountryCode::FR, city: 'LYON'));

// Two sales of goods (billing framework B1) to consumers on 10 November 2026.
$sale = static fn (string $number, string $net, string $rate): Invoice => TotalsCalculator::complete(France::mark(new Invoice(
    number: $number,
    issueDate: new DateTimeImmutable('2026-11-10'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: $seller,
    buyer: $consumer,
    lines: [new InvoiceLine('1', '1', UnitCode::C62, new Price($net), new Item('Article'),
        new LineVat(VatCategory::StandardRate, $rate))],
), BillingFramework::B1));

// One day: TLB1, 150.00 without VAT, 22.75 VAT, subtotals at 20 % and 5.5 %, 2 transactions.
$days = (new B2cAggregator)->aggregate([$sale('T-1', '100.00', '20'), $sale('T-2', '50.00', '5.5')]);

$report = EReport::transactions(
    id: 'TR-2026-11',
    issuedAt: new DateTimeImmutable('2026-12-01 09:30:00', new DateTimeZone('Europe/Paris')),
    type: ReportType::Initial,
    platform: LifecycleParty::platform('PA01', 'PLATEFORME EXEMPLE'),
    declarant: new Declarant('100000009', 'LE VENDEUR', DeclarantRole::Seller),
    report: new TransactionsReport(ReportPeriod::month(2026, 11), days: $days),
);
$xml = (new Flux10Writer)->write($report);

$validation = Flux10Validator::create('var/france-rules')->validate($xml);
if (! $validation->isValid()) {
    throw new RuntimeException('The e-report is not valid: '.implode(', ', $validation->verdict()));
}
```

**Payments (10.2, 10.4).** `PaymentAggregator::forInvoice($invoice, $paid, $paidOn)` splits one
invoice's payment by VAT rate with `Collection::allocate()`, the same split as status 212.
`PaymentAggregator::daily($payments)` sums payments per day and VAT rate. Payments are reported in
euros; an invoice in another currency is refused. A payment date is a calendar date taken in its
own time zone, not converted to Paris time as CDAR status times are: pass the French local date.

The PA matricule comes from your PA account. The file goes to the DGFiP through the PA; this
package builds and validates it but does not send it.

## Routing

`Router` decides, without network access, whether an invoice is e-invoiced or e-reported and how
its collection is reported. `route()` takes the issuer's (seller's) side; the first matching rule
wins:

| Situation | `RoutingRule` | `Channel` |
|---|---|---|
| Treatment note ARCHIVEONLY | `ArchiveOnly` | `None` |
| Treatment note OUTOFSCOPE, or seller outside France | `OutOfScope` | `None` |
| Buyer without SIREN and VAT identifier, or treatment B2C or B2CINT | `B2c` | `AggregatedTransactionReport` (10.3) |
| Buyer outside France, treatment B2BINT, or a non-French buyer VAT identifier | `InternationalB2b` | `TransactionReport` (10.1) |
| Domestic B2B, buyer listed and active in the directory | `DomesticB2b` | `EInvoice` |
| Domestic B2B, buyer not listed or inactive | `AbsentRecipient` | `AggregatedTransactionReport` (10.3) |
| Domestic B2B, directory status unknown | `DomesticB2b`, `needsDirectoryCheck` | `EInvoice` |

`routePurchase()` takes the buyer's side: a purchase by a buyer in France from a seller outside it
is reported (10.1, `PurchaseFromForeignSupplier`, declared as buyer); anything else needs no
action. The `RoutingDecision` also carries the payment reporting: for services (BT-23 S… or M…)
whose VAT is due on collection (no VAT-on-debits option in BT-8, no reverse charge) it is status
212 on an e-invoice, 10.2 next to a 10.1 report and 10.4 next to a 10.3 report. The declarant role
is set for e-reports, and `RoutingRule::legalBasis()` names the article of the CGI or the BOFiP
paragraph behind each rule.

The directory (*annuaire*) is behind the `Annuaire` interface. `StaticAnnuaire` maps SIRENs to an
`AnnuaireStatus`; a live lookup through a PA is not part of this version. An unknown status (the
lookup was not possible) keeps the e-invoice and sets `needsDirectoryCheck`, because the
fallback to e-reporting applies to a recipient that is absent, not to a failed lookup.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;
use IntrAgile\EInvoicing\France\Routing\Channel;
use IntrAgile\EInvoicing\France\Routing\PaymentReporting;
use IntrAgile\EInvoicing\France\Routing\Router;
use IntrAgile\EInvoicing\France\Routing\RoutingRule;
use IntrAgile\EInvoicing\France\Routing\StaticAnnuaire;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;

$invoice = TotalsCalculator::complete(France::mark(new Invoice(
    number: 'F202600011',
    issueDate: new DateTimeImmutable('2026-11-02'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: new Party(name: 'LE VENDEUR', legalRegistration: Siren::identifier('100000009'),
        vatIdentifier: 'FR88100000009', address: new PostalAddress(country: CountryCode::FR, city: 'PARIS')),
    buyer: new Party(name: 'LE CLIENT', legalRegistration: Siren::identifier('200000008'),
        vatIdentifier: 'FR37200000008', address: new PostalAddress(country: CountryCode::FR, city: 'NICE')),
    notes: [France::treatmentNote(Treatment::B2B)],
    lines: [new InvoiceLine('1', '10', UnitCode::HUR, new Price('80.00'), new Item('Conseil'),
        new LineVat(VatCategory::StandardRate, '20'))],
), BillingFramework::S1));

// In production the directory status comes from your PA; a fixed list stands in for it here.
$decision = (new Router(new StaticAnnuaire(['200000008' => AnnuaireStatus::Active])))->route($invoice);

$decision->channel;              // Channel::EInvoice
$decision->payment;              // PaymentReporting::CollectedStatus: report payments with status 212
$decision->rule->legalBasis();   // "CGI art. 289 bis"

// The same invoice to a buyer missing from the directory is e-reported as a B2C sale.
$absent = (new Router(new StaticAnnuaire(['200000008' => AnnuaireStatus::NotListed])))->route($invoice);

if ($decision->channel !== Channel::EInvoice || $absent->rule !== RoutingRule::AbsentRecipient
    || $absent->payment !== PaymentReporting::AggregatedPaymentReport) {
    throw new RuntimeException('Unexpected routing decision.');
}
```

France for routing is metropolitan France, Guadeloupe, Martinique and Réunion (`FR`, `GP`, `MQ`,
`RE`); supplies between them are e-invoiced. French Guiana, Mayotte, Monaco and the overseas
collectivities are treated as foreign, which is a known limitation. A party's location is its
address country, else the prefix of its VAT identifier; a party whose location cannot be
determined counts as French. The treatment is read from the first BAR note with a known value.

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
- **Lifecycle messages.** Each of the 59 FNFE CDAR examples is valid, reads into the model with
  nothing ignored and writes back to the same canonical XML. A message built with `Lifecycle` for
  each of the 23 statuses passes the CDAR schema and rules with no finding.
- **E-reports.** Flux 10 files written by this package (invoices one by one, B2C days with and
  without a classifier, credit notes, payments per invoice and per day, an initial and a
  corrective transmission) pass the DGFiP schema and the Flux 10 rules with no finding.
- **Verdict equality with FNFE's XSLT.** 5,494 documents (the FNFE invoice and CDAR examples, our
  invoices, lifecycle messages and e-reports, their round trips, the negative recipes and about
  4,500 mutations) were validated both by this package and by FNFE's own compiled XSLT in
  SaxonC-HE 13; the verdicts are equal for every document. The only accepted difference is the one
  documented in [VALIDATION.md](../VALIDATION.md#lets-and-xslt-functions): where a mutation makes
  a date or an amount unreadable, Saxon may evaluate an XSLT local variable or a rule-level let
  eagerly and stop with `FORG0001`, while this package evaluates it lazily and reports the date or
  amount format rule (BR-FR-03 or the Flux 10 amount rule) for that field. The test allows exactly
  that case (52 documents) and pins how often it is used.

## Not covered yet

- **Transport to a PA**: sending invoices, lifecycle messages and Flux 10 files. Of the existing
  transport drivers, B2Brouter is a registered PA; e-invoice.be and Recommand are Peppol access
  points, not PAs, and do not satisfy a French sender's obligation.
- **Live directory (*annuaire*) lookup.** The router takes an `Annuaire` implementation; only the
  fixed `StaticAnnuaire` ships.
- **Chorus Pro** (B2G) is out of scope.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

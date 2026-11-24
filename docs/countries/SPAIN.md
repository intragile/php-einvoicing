# Spain

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Spanish VERI\*FACTU billing records: the record model, the mapping from the EN 16931 invoice,
the SHA-256 `Huella` and its chain, the QR link, the record XML with its validation, and two
transports, a direct AEAT client and B2Brouter. The Laravel side (a database chain store, queued
submission under the AEAT flow control, events and commands) is in `intragile/laravel-einvoicing`.

> **Release label:** Verifactu live-verified on the B2Brouter sandbox (registered; hash and QR cross-checked); direct AEAT submission verified offline only — it needs a Spanish NIF and a qualified certificate

This library is a component, not a certified billing system (SIF). IntrAgile does not act as the
producer of your billing software; see [Declaración responsable](#declaración-responsable).

## VERI\*FACTU in brief

VERI\*FACTU is not an invoice format. It is the billing-record and anti-tamper regime for invoicing
software of the *Reglamento de los Sistemas Informáticos de Facturación* (RRSIF, Real Decreto
1007/2023) and its ministerial order (Orden HAC/1177/2024). For every invoice and every
cancellation, the invoicing system:

- generates a small XML record, a `RegistroAlta` or a `RegistroAnulacion`;
- chains it to the previous record with a SHA-256 `Huella`;
- prints a QR code on the invoice that points to the AEAT verification service;
- sends the record to the AEAT web service over SOAP with mutual TLS.

**Dates.** Under current law (Real Decreto-ley 15/2025) VERI\*FACTU applies from 1 January 2027 to
corporate-tax payers and from 1 July 2027 to everyone else. On 5 October 2026 the Ministry
announced a move of both dates to October 2028 (**announced, not enacted**: nothing had been
published in the BOE as of 23 November 2026). The technical specification has been in production
since 23 April 2025 (XSD 1.0, hash specification 0.1.2, QR specification 0.5.0). Nothing in this
library depends on which date applies.

## What this release verifies

> Verifactu live-verified on the B2Brouter sandbox (registered; hash and QR cross-checked); direct AEAT submission verified offline only — it needs a Spanish NIF and a qualified certificate

- **Hash and chain, offline.** `Fingerprint` reproduces the four official AEAT test vectors: the
  three of the hash specification v0.1.2 §6 (first registration, chained registration,
  cancellation) and the R3 registration of `ejemploRegistro.xml`. The four records B2Brouter
  generated in its sandbox (a first record, a chained one, an exempt invoice and a credit note)
  are committed as fixtures; their `Huella` is recomputed from their literal text and their links
  are checked.
- **Schemas and rules, offline.** Every record the writers produce is checked against the
  official XML Schemas in the test suite, and `VerifactuValidator` runs the 24 published AEAT
  business rules.
- **B2Brouter, live.** Invoices issued through the B2Brouter Spanish sandbox reached `registered`
  at the AEAT through B2Brouter, and each record passed the five-step cross-check (schema,
  `Huella`, chain, content, QR): the core runs of 2026-11-23, and the Laravel bridge run of
  2026-11-24 through `EInvoicing::verifactu()->register()` and its poll job (four records
  registered, cross-checked and chained). The runs are in [VERIFICATION.md](../VERIFICATION.md#d2e-verifactu-on-the-b2brouter-sandbox).
- **Direct AEAT, offline only.** `AeatClient` is tested against responses and faults built from the
  AEAT schemas, WSDL and examples. No AEAT endpoint has been called: a live run needs a Spanish NIF
  and a qualified certificate.

## Not covered

- **Non-VERI\*FACTU mode**: XAdES-signed local records, the event log and its chain, the 6-hour
  summary event, the chain self-check and the *requerimiento* endpoint.
- **TicketBAI** (the Basque foral territories) and **Navarra**, which run their own regimes.
- **Facturae 3.2.x and FACe** (invoices to the public sector).
- **SII**: SII taxpayers are outside VERI\*FACTU.
- **The Crea y Crece B2B e-invoicing mandate** (Real Decreto 238/2026, Orden HAC/1028/2026).
- **The paginated `ConsultaFactuSistemaFacturacion` export**: `AeatClient::lookup()` reads one
  invoice at a time.
- **Invoices not in euro without EUR amounts**: records are written in euro, so a foreign-currency
  invoice needs `EuroAmounts` (see [Overrides](#overrides)).

## Install the official files

The AEAT XML Schemas, the WSDL, the error catalogue (`errores.properties`) and the W3C xmldsig
schema they import are **downloaded, never bundled**: AEAT publishes no licence that allows
redistribution. The installer downloads the pinned files, checks each against a committed SHA-256
sum and installs them below `<dir>/verifactu-tikeV1.0/`; nothing is replaced unless every file
matches.

```bash
vendor/bin/einvoicing-rules install spain --dir=var/verifactu
vendor/bin/einvoicing-rules check spain --dir=var/verifactu

# Air-gapped machine: download the eight files elsewhere and install them from that folder.
vendor/bin/einvoicing-rules install spain --dir=var/verifactu --from=/path/to/downloaded/files
```

The same from PHP:

```php
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;

VerifactuSchemas::install(__DIR__.'/var/verifactu');

$schemas = new VerifactuSchemas(__DIR__.'/var/verifactu');   // the --dir folder, the parent of verifactu-tikeV1.0
$schemas->check();   // throws RulesNotInstalled naming the first missing or changed file
```

Validation stays offline: the absolute W3C location the AEAT schemas import is mapped onto the
installed copy.

## Build a record

`RecordMapper::map()` turns an EN 16931 `Invoice` into a `RegistrationDraft`: every field except
the chain link, the generation timestamp and the `Huella`, which the `Chainer` adds. Without an
extension the invoice is described as a domestic F1 under the general VAT regime.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Chainer;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainKey;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\InMemoryChainStore;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordMapper;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordWriter;

$invoice = TotalsCalculator::complete(new Invoice(
    number: 'F-2026-0001',
    issueDate: new DateTimeImmutable('2026-11-24'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: new Party(
        name: 'Ejemplo Software S.L.',
        vatIdentifier: 'ESB12345674',   // IDEmisorFactura B12345674
        address: new PostalAddress(country: CountryCode::ES, line1: 'Calle Mayor 1', city: 'Madrid', postCode: '28013'),
    ),
    buyer: new Party(
        name: 'Cliente Prueba S.A.',
        vatIdentifier: 'ESA58818501',
        address: new PostalAddress(country: CountryCode::ES, line1: 'Avenida Diagonal 100', city: 'Barcelona', postCode: '08019'),
    ),
    lines: [
        new InvoiceLine(
            id: '1',
            quantity: '1',
            unit: UnitCode::C62,
            price: new Price('100.00'),
            item: new Item('Servicio de consultoría'),
            vat: new LineVat(VatCategory::StandardRate, '21'),
        ),
    ],
));

$draft = (new RecordMapper)->map($invoice);   // MappingRefused lists every problem at once

// Your billing software as it is declared to the AEAT (SistemaInformatico); there is no default.
$system = new ComputerSystem(
    'Your Company S.L.',     // NombreRazon of the producer
    Nif::parse('A39200019'), // its NIF (or null and a ForeignId)
    null,
    'YourInvoicing',         // NombreSistemaInformatico, at most 30 characters
    'YI',                    // IdSistemaInformatico, 2 characters
    '2.4.1',                 // Version
    'INST-0001',             // NumeroInstalacion
    true,                    // TipoUsoPosibleSoloVerifactu
    false,                   // TipoUsoPosibleMultiOT
    false,                   // IndicadorMultiplesOT
);

$store = new InMemoryChainStore;   // single process; use a persistent ChainStore in production
$key = ChainKey::for($draft->id->issuer, $system, VerifactuEnvironment::Test);
$record = (new Chainer($system))->appendTo($store, $key, $draft);
assert($record instanceof RegistrationRecord);

$xml = (new RecordWriter)->write($record);   // pass VerifactuSchemas to check it against the XSD
echo $record->fingerprint(), "\n";
echo VerifactuQr::url($record, VerifactuEnvironment::Test), "\n";
```

### Mapping

| AEAT field | Source | Rule |
|---|---|---|
| `IDEmisorFactura` | Seller VAT identifier BT-31 without the `ES` prefix, else the seller tax registration | Must be a valid Spanish NIF (DNI, NIE or CIF check character), otherwise refused |
| `NumSerieFactura`, `FechaExpedicionFactura` | BT-1, BT-2 | Character set and length checked, never transliterated |
| `NombreRazonEmisor` | BT-27 | At most 120 characters, trimmed |
| `TipoFactura` | BT-3 and the override | 380 → `F1`. `F2` (simplified) and `F3` (substitution) come only from the override. A 381 or 384 needs an explicit `R1`–`R5` **and** a `TipoRectificativa`; the legal basis of a correction is never guessed |
| `FacturasRectificadas`, `FacturasSustituidas` | BG-3 (BT-25, BT-26) with the issuer NIF; substituted invoices from the override | One `IDFactura` per preceding invoice; a missing date is refused |
| `ImporteRectificacion` | Override (`CorrectionAmounts`) | Required with `TipoRectificativa` S |
| `FechaOperacion` | BT-72, else the BG-14 end date | Written only when it differs from the issue date |
| `DescripcionOperacion` | Override, else the first note BT-22 when it has at most 500 characters after trimming, else `Factura <BT-1>` | An override longer than 500 characters is refused |
| `Destinatarios` | BT-48, else the override's recipients | A BT-48 starting with `ES` becomes `NIF`; any other BT-48 becomes `IDOtro` with `IDType` 02, the identifier as given and `CodigoPais` from its prefix (`EL` → `GR`). Without BT-48, the override's `recipients`, or none for `F2`/`R5` or `withoutRecipientArt61d`; otherwise refused |
| `Desglose` | BG-23, one `DetalleDesglose` per VAT breakdown group (at most 12), with the per-group overrides | Defaults below |
| `CuotaTotal`, `ImporteTotal` | BT-110, BT-112 | Written with the one canonical text of `Amount` (below) |
| `Macrodato` | Computed | `S` when the absolute `ImporteTotal` is at least 100,000,000.00 |

Default `DetalleDesglose` per EN 16931 VAT category (`Impuesto` / `ClaveRegimen` / qualification
or exemption):

| VAT category | Default | Rate and quota |
|---|---|---|
| `S` standard rate | `01` / `01` / `S1` | rate BT-119, quota BT-117 |
| `Z` zero rate | `01` / `01` / `S1` | `0.00`, `0.00` |
| `AE` reverse charge | `01` / `01` / `S2` | `0.00`, `0.00` (AEAT error 1198 otherwise) |
| `G` export | `01` / **`02`** / `E2` (art. 21 LIVA) | none; AEAT error 1199 forbids E2 with regime 01 |
| `K` intra-Community supply | `01` / `01` / `E5` (art. 25 LIVA) | none |
| `L` Canary Islands | `03` (IGIC) / `01` / `S1` | rate BT-119 |
| `M` Ceuta and Melilla | `02` (IPSI) / `01` / `S1` | rate BT-119 |
| `E` exempt | the article (`E1`, `E3`, `E4`, `E6`) **from the override only** | VATEX codes do not map one to one onto LIVA articles |
| `O` not subject | `N1` or `N2` **from the override only** | |
| `B` split payment | refused | |

When an exempt group's override sets `E2` or `E3` under IVA or IGIC without a regime, the regime
defaults to `02` as well. The equivalence surcharge (*recargo de equivalencia*) and
`BaseImponibleACoste` have no EN 16931 term and come only from the override.

**Sign convention.** A credit note corrected by difference (BT-3 381 with `TipoRectificativa` I)
carries the signed difference: base, quota, surcharge, `CuotaTotal`, `ImporteTotal` and the EUR
amounts are negated. A 384 is taken as already signed; a substitution (S) carries the full
corrected amounts.

**The mapper refuses rather than guesses**, listing every problem in one `MappingRefused`
(`$exception->problems`), for example: a corrective type without `TipoRectificativa`, BT-48
together with override recipients, a non-EUR invoice without `EuroAmounts`, `EuroAmounts` on a
EUR invoice, more than 12 breakdown groups, a recipient NIF equal to the issuer's, an override key
that matches no breakdown group.

### Overrides

Spanish data the EN 16931 model does not carry goes into a `SpanishInvoice` extension. Breakdown
overrides are keyed by VAT category and rate as `SpanishInvoice::breakdownKey()` produces them
(`S|21`, `S|10.5`, `E|-` for a category without a rate); every unset field keeps its default.

```php
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\EuroAmounts;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;

// An exempt line under art. 20 LIVA (E1).
$exempt = $invoice->withExtension(new SpanishInvoice(
    breakdown: ['E|-' => new BreakdownOverride(exemption: ExemptOperation::E1)],
));

// A credit note (381) by difference for an earlier invoice (BG-3 carries its number and date).
$credit = $creditNote->withExtension(new SpanishInvoice(
    type: InvoiceType::R1,
    correctionType: CorrectionType::Difference,
));

// An invoice in US dollars: the record amounts in euro, per breakdown group.
$usd = $dollarInvoice->withExtension(new SpanishInvoice(
    euroAmounts: new EuroAmounts(
        totalTax: Amount::of('19.38'),
        total: Amount::of('111.68'),
        breakdown: ['S|21' => ['base' => Amount::of('92.30'), 'quota' => Amount::of('19.38')]],
    ),
));
```

The other fields cover simplified invoices (`simplifiedArt7273`, `withoutRecipientArt61d`),
invoices issued by a third party or the recipient (`issuedBy`, `thirdParty`), foreign recipients
without a VAT identifier (`recipients` with a `ForeignId`), coupons, the external reference and the
agreement numbers, and the operation of the record (`AltaOperation`: initial, correction of an
accepted record, resubmission after a rejection).

### Amounts and text

AEAT hashes the literal text of the record, so every value has exactly one text, fixed when the
value object is built, and the writer and the fingerprint both read that text. `Amount` is scale
2, half-up, `-` for negatives, `.` as separator (`123.45`, `-12.30`); strings are trimmed; dates are
`dd-mm-yyyy`; `FechaHoraHusoGenRegistro` is ISO 8601 with offset and no fraction, written in
`Europe/Madrid` by default. `RecordReader` reads a foreign record keeping the literal text of every
hashed field (B2Brouter writes `100.0` where we write `100.00`), so its `Huella` can be recomputed.

## Chain

A chain is keyed by **issuer NIF, `NumeroInstalacion` and environment** (`ChainKey`): a chain
belongs to one installation and one issuer, and test and production records never share one.
`Chainer::append()` is pure: with no head it writes `PrimerRegistro` S, otherwise
`RegistroAnterior` with the head's `IDFactura` and `Huella`; it sets the generation timestamp and
computes the `Huella` (`strtoupper(hash('sha256', $input))` over the specified fields, not
URL-encoded). It refuses a timestamp earlier than the head's. `Chainer::appendTo()` runs it under
the store's lock and refuses a draft whose issuer or installation is not the key's
(`ChainConflict`).

A `ChainStore` implementation guarantees that the build runs under an exclusive lock on the chain,
that the record and the new head are stored atomically, that a record which does not link to the
head is refused with nothing stored, and that **stored records are never rewritten, deleted or
reordered**. The library only appends. `InMemoryChainStore` is single-process; the Laravel bridge
ships a database store.

**Seed.** To continue a chain another implementation started, seed it with that software's last
record; its `Huella` is trusted and the next record links to it:

```php
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;

$head = $store->seed($key, new InvoiceId(Nif::parse('B12345674'), 'F-2026-0999', new DateTimeImmutable('2026-12-31')), $huellaOfThatRecord);
```

**Literal text.** The `Huella` input is built from the text written into the XML, never from a
re-formatted value. Keep the written XML as it is: re-serialising it, or rebuilding a timestamp from
a parsed date, can change the text and break the next link.

**Generation and submission are separate.** A record is generated, hashed and stored first; that is
when the QR can be printed. It is sent afterwards, and a retry resends **the same bytes**. A
regenerated record would carry a new timestamp and a new `Huella`: a second record for the same
invoice.

A cancellation is a `RegistroAnulacion` in the same chain:

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;

// The first official vector of the AEAT hash specification v0.1.2 (§6.1).
$input = 'IDEmisorFactura=89890001K&NumSerieFactura=12345678/G33&FechaExpedicionFactura=01-01-2024'
    .'&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella=&FechaHoraHusoGenRegistro=2024-01-01T19:20:30+01:00';
echo Fingerprint::ofInput($input), "\n";   // 3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60

$cancellation = CancellationDraft::of(new InvoiceId(Nif::parse('89890001K'), '12345679/G34', new DateTimeImmutable('2024-01-01')));
echo $cancellation->annulled->number, "\n";
```

`(new Chainer($system))->appendTo($store, $key, $cancellation)` appends it like a registration.

## QR

`VerifactuQr::url($record, $environment)` builds the AEAT verification link with exactly four
parameters in this order, each percent-encoded (`12345678&G33` becomes `12345678%26G33`):

```text
https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345674&numserie=F-2026-0001&fecha=24-11-2026&importe=121.00
```

The test (pre-production) host is `https://prewww2.aeat.es`. `importe` is the record's
`ImporteTotal` text, so the QR and the record never disagree.

`VerifactuQr::code()` returns a `QrCode` for the shared renderer (`ChillerlanQrRenderer`, with
`chillerlan/php-qrcode` ^5; error correction level M, as the specification requires). The image
carries no caption, because the labels belong to the invoice layout: print
`VerifactuQr::LABEL_ABOVE` (`QR tributario:`) above the code and `VerifactuQr::LABEL_BELOW`
(`VERI*FACTU`) below it, at the top of the first page, the code **30 to 40 mm** wide with a margin
of at least 2 mm, and the labels no smaller than the invoice data.

```php
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\ChillerlanQrRenderer;

$svg = (new ChillerlanQrRenderer)->svg(VerifactuQr::code($record, VerifactuEnvironment::Production));
```

Orden HAC/1177/2024 art. 20.2 also asks an electronic invoice to carry the QR URL in its
structured data. The library does not inject it into the UBL or CII; place the URL in your invoice
data yourself.

## Validate

`VerifactuValidator` validates a single record or a whole `RegFactuSistemaFacturacion` submission
against the official XML Schemas, then runs the published AEAT business rules (validation document
v1.2.2) as rule set `es-verifactu`: 24 rules, `ES-VF-01` to `ES-VF-24`, each finding carrying the
AEAT error codes it stands for. `ES-VF-01` to `ES-VF-20` are fatal (AEAT rejects the record);
`ES-VF-21` to `ES-VF-24` are warnings for the errors AEAT accepts (*AceptadoConErrores*): the
±10.00 EUR tolerance of `CuotaTotal` and `ImporteTotal` against the breakdown lines, the record's
own `Huella`, and the previous record's `Huella`. The rules are implemented from
the published document; AEAT provides no executable reference.

```php
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuValidator;

$report = VerifactuValidator::create(new VerifactuSchemas(__DIR__.'/var/verifactu'))->validate($xml);

if (! $report->isValid()) {
    foreach ($report->fatal() as $finding) {
        echo $finding->ruleId, ': ', $finding->message, "\n";
    }
}
```

`RecordWriter` and `SubmissionWriter` take the same `VerifactuSchemas` to check every written
record against the schema and throw `InvalidRecord` on an error; they do not run the business
rules.

## Send to AEAT

`AeatClient` submits up to 1,000 records of one issuer per request (`submit()`) and looks one
record up (`lookup()`). SOAP 1.1 is built and read with ext-dom; ext-soap is not needed. **Verified
offline only.**

**Mutual TLS.** PSR-18 has no client-certificate API, so the certificate is configured on the
PSR-18 client you build. `ClientCertificate` loads PKCS#12 or PEM with ext-openssl and never shows
the key or the password in dumps or messages; `materialize()` writes the two PEM files (mode 0600)
an HTTP client needs. **Keep the `MaterializedCertificate` referenced** as long as the client is
used: its destructor deletes the files, so a chained `->materialize($dir)->guzzleOptions()` leaves
the client pointing at files that no longer exist.

**Ports.** A personal or representative certificate uses `www1.agenciatributaria.gob.es`
(test: `prewww1.aeat.es`), a seal certificate `www10.agenciatributaria.gob.es` (test:
`prewww10.aeat.es`); `AeatConfig` picks the port from `CertificateKind`.

**CA trust.** The AEAT servers chain to `AC RAIZ FNMT-RCM SERVIDORES SEGUROS`, which is in the
Mozilla bundle but not in the macOS system store. `CaBundle::containsFnmtRoot($path)` tells whether
a bundle has it. TLS verification is never disabled.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Transport\Aeat\AeatClient;
use IntrAgile\EInvoicing\Transport\Aeat\AeatConfig;
use IntrAgile\EInvoicing\Transport\Aeat\CaBundle;
use IntrAgile\EInvoicing\Transport\Aeat\ClientCertificate;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;

$certificate = ClientCertificate::fromPkcs12((string) file_get_contents('/run/secrets/aeat.p12'), $password);
$materialized = $certificate->materialize('/var/lib/app/aeat');   // keep it referenced

$bundle = '/etc/ssl/aeat-ca.pem';
if (! CaBundle::containsFnmtRoot($bundle)) {
    throw new RuntimeException('The CA bundle lacks the FNMT root.');
}

$factory = new HttpFactory;
$http = new HttpClient(new Client($materialized->guzzleOptions($bundle, 30.0)), $factory, $factory);
$aeat = new AeatClient(
    new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Personal, new VerifactuSchemas('/var/lib/app/verifactu')),
    $http,
);
```

`symfonyOptions()` and `streamContextOptions()` give the same for Symfony HttpClient and PHP
streams.

```php
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Transport\Aeat\LookupRequest;
use IntrAgile\EInvoicing\Transport\Aeat\RecordOutcome;

$submission = new Submission(new SubmissionHeader(Nif::parse('B12345674'), 'Ejemplo Software S.L.'), [$record]);
$response = $aeat->submit($submission);

$response->csv;           // keep it now: AEAT cannot return it later
$response->waitSeconds;   // TiempoEsperaEnvio
foreach ($response->outcomes($submission) as $index => $outcome) {
    // Accepted, AcceptedWithErrors, Rejected, AlreadyRegistered, AlreadyCancelled, Unconfirmed
}

$status = $aeat->lookup(new LookupRequest(Nif::parse('B12345674'), 'Ejemplo Software S.L.', $record->invoiceId()));
$status?->matches($record);   // AEAT holds this very record: same IDFactura and same Huella
```

**Flow control.** `FlowControl` applies Orden HAC/1177/2024 art. 16.2: the first send of a
throttle key goes at once; afterwards the next send waits `TiempoEsperaEnvio` seconds (60 until a
response states one), or goes at once when 1,000 records are pending. It is pure: `decide()` returns
a `SendDecision` (send now, `nextSendAt`, batch size), `afterResponse()` and `afterFailure()` return
the next `ThrottleState`, and `batches()` groups records per issuer into submissions of at most
1,000. The client never sleeps. A `ThrottleKey` is per installation (`NumeroInstalacion` and
environment) across all issuers by default, because the order binds the wait to *el sistema
informático*; `ThrottleScope::Issuer` is an opt-in.

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Transport\Aeat\FlowControl;
use IntrAgile\EInvoicing\Transport\Aeat\ThrottleState;

$flow = new FlowControl(static fn (): DateTimeImmutable => new DateTimeImmutable('2026-11-24T10:00:30Z'));

$first = $flow->decide(new ThrottleState, 5);
var_dump($first->sendNow);   // true: the first send of a key is not delayed

$after = $flow->decide(new ThrottleState(new DateTimeImmutable('2026-11-24T10:00:00Z'), 60), 5);
var_dump($after->sendNow);   // false: 60 seconds have not passed and fewer than 1,000 records wait
echo $after->nextSendAt?->format(DATE_ATOM), "\n";
```

**Errors.** Each failure is one of the transport exceptions:

| Exception | When | What to do |
|---|---|---|
| `InvalidRecord` | the submission fails the XML Schema | nothing was sent; fix the data |
| `AuthenticationFailed` | AEAT refuses the certificate or the authorisation (codes 4108, 4112, 4132; HTTP 401 and 403) | fix the certificate or the representation |
| `DocumentRejected` | a Client fault that refuses the whole submission | read the code, fix and send again |
| `OutcomeUnknown` | a Server fault, no answer, an unreadable answer, or a Client fault reporting a temporary AEAT failure (4110, 4111, 4118, 4128, 4134, 3500, 3501) | resend **the same bytes** |
| `RateLimited` | HTTP 429 | wait (`retryAfterSeconds` when given) |

`AeatErrors::codeOf($exception)` returns the AEAT code an `AuthenticationFailed` or
`DocumentRejected` carries, and `AeatErrors::classify($code)` tells its scope, category and
`ErrorAction` (retry the same bytes, fix the configuration, regenerate after a rejection, correct
an accepted record, resolve a duplicate, investigate). A lookup only reads: its failures are
`TransportUnavailable`, except `AuthenticationFailed`, `RateLimited` and an `InvalidRecord` query.

**The CSV is kept once.** The CSV of a submission comes only in its response; AEAT cannot provide
it later. Store it in the same transaction as the record states. After an unknown outcome, the
resend of the same bytes is safe: AEAT answers a record it holds with `RegistroDuplicado`, which
maps to `AlreadyRegistered` (without a new CSV) or `AlreadyCancelled`. **`AlreadyRegistered` means
AEAT holds a record for this `IDFactura`**, not necessarily this record, because AEAT matches the
invoice id, not the `Huella`. Unless the submission was a byte-identical resend, confirm it with
`lookup()` and `RecordStatus::matches()`.

## Send through B2Brouter

`B2BrouterVerifactu` issues Spanish invoices through B2Brouter's VERI\*FACTU service with the
existing `B2BrouterConfig` and `HttpClient`. In this mode **B2Brouter is the billing system (SIF)**:
its record names B2Brouter in `SistemaInformatico` and chains on B2Brouter's own installation, and
B2Brouter also delivers the invoice to the customer (by e-mail, as a PDF). Your chain store does not
advance a chain of its own for these invoices; it stores B2Brouter's records. Switching an issuer
between B2Brouter and direct AEAT submission starts a new chain on a different system, so do it as
an explicit cut-over.

**Asynchronous.** `issue()` imports and sends the invoice in one request and returns the
`IssuedInvoice` while it is pending. B2Brouter then generates, chains and submits the record; the
outcome arrives in the invoice's tax report. `awaitRegistered()` polls within the bound of
`VerifactuPolling`, by default **60 reads 3,000 ms apart** (about three minutes; sandbox
registrations have taken up to about 120 seconds), and returns the last report read, final or not.
`registered` and `registered_with_errors` are delivered states.

**No idempotency key.** The import refuses a taken invoice number. After an import that ended with
`OutcomeUnknown`, call `findByNumber()` before sending again: a found invoice was imported.

```php
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\B2BrouterVerifactu;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\ChillerlanQrDecoder;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckMode;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\VerifactuCrossCheck;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\VerifactuPolling;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;

$b2brouter = new B2BrouterVerifactu(new B2BrouterConfig($apiKey, 999999), $http, new VerifactuPolling(60, 3000));

$issued = $b2brouter->issue($invoice);
$report = $b2brouter->awaitRegistered($issued->invoiceId);

if ($report !== null && $report->isRegistered()) {
    $check = new VerifactuCrossCheck(
        new VerifactuSchemas(__DIR__.'/var/verifactu'),
        qrDecoder: ChillerlanQrDecoder::isAvailable() ? new ChillerlanQrDecoder : null,
    );
    $result = $check->verify($report, $previousReportFromYourStore, $invoice);
    if ($result->failsIn(CrossCheckMode::Strict)) {
        echo $result->summary(), "\n";
    }
    $report->fingerprint;   // the Huella
    $report->recordXml;     // the record as B2Brouter wrote it; store it unchanged
}
```

**Cross-check.** `VerifactuCrossCheck::verify($report, $previous, $invoice)` checks B2Brouter's
record in five steps:

1. **Schema:** the record XML loads safely and is valid against the XSD.
2. **Fingerprint:** the `Huella` recomputed from the literal text equals the record's `Huella` and
   the report's fingerprint.
3. **Chain:** `RegistroAnterior` names the previous record and carries its `Huella`. The previous
   report comes **from your own store**: B2Brouter's `previous_id` is opaque, from another id
   space, and is never read as a tax report id. Without a previous report, a first record passes
   and a chained one is skipped.
4. **Content:** the record carries what `RecordMapper` maps the invoice to (`IDFactura`,
   `TipoFactura`, `TipoRectificativa`, every breakdown line, `CuotaTotal`, `ImporteTotal`),
   compared by value.
5. **QR:** the URL decoded from B2Brouter's QR image equals `VerifactuQr::url()` of its record.
   Decoding needs `chillerlan/php-qrcode` with ext-gd or ext-imagick; without a decoder the URL
   parameters are compared, with a note.

`CrossCheckResult` lists every difference; `failsIn(CrossCheckMode::Strict)` fails on any of them,
`CrossCheckMode::Report` only reports. **B2Brouter prints the production ValidarQR host
(`www2.agenciatributaria.gob.es`) in its QR codes, in its sandbox too**, so the cross-check defaults
to `VerifactuEnvironment::Production`; pass `VerifactuEnvironment::Test` only for QR codes printed
with the AEAT pre-production host.

**Known divergences.** Two differences, both in step 4, are reported as `KnownDivergence`, never
as failures. They follow the B2Brouter account's settings, not the invoice:

- **Exemption article:** an exempt line gets the account's default `OperacionExenta` (E1 in the
  sandbox), whatever article the invoice states.
- **Credit-note sign:** a credit note by difference (R1, I) carries positive amounts, where our
  engine writes the signed difference.

No cancellation or correction through B2Brouter is offered.

## Declaración responsable

The producer of a billing system signs a *declaración responsable* that the system meets the
RRSIF (Real Decreto 1007/2023, Orden HAC/1177/2024). **IntrAgile does not act as producer, signs no
declaration and ships no template for one**, and the library carries no default
`SistemaInformatico`. The library implements record generation, the `Huella` and the chain, the
record XML, the QR URL and the AEAT SOAP client. Your application, as the billing system, must
still guarantee:

- the indefectible coupling of every invoice and its record (no invoice without a record);
- that records are never deleted or altered;
- that records are sent immediately;
- the correction and cancellation flows;
- its own `SistemaInformatico` identity (`ComputerSystem`);
- the declaration itself, naming this library and its version as a component.

## Testing

Two test groups cover Spain beyond the default unit tests:

- **`verifactu-official`** reads the AEAT schemas and the official example record. It is offline
  and part of the default run, but skipped until the files are downloaded into the git-ignored
  `tests/fixtures/verifactu/`; CI runs it again with `--fail-on-skipped`.
- **`b2brouter-spain-live`** issues a chain of invoices, an exempt invoice and a credit note
  through the B2Brouter Spanish sandbox and cross-checks each registered record in all five steps.
  It is excluded by default, needs `B2BROUTER_ES_API_KEY` and `B2BROUTER_ES_ACCOUNT_ID`, and
  refuses any key that does not start with `test_` and any host other than
  `https://api.b2brouter.net` before the first request. A run creates five sandbox invoices.

```bash
composer fetch-official -- --spain
vendor/bin/phpunit --group verifactu-official --fail-on-skipped

B2BROUTER_ES_API_KEY=test_… B2BROUTER_ES_ACCOUNT_ID=… vendor/bin/phpunit --group b2brouter-spain-live
```

No test calls an AEAT endpoint.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

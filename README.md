# PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

**A framework-agnostic EN 16931 e-invoicing core for PHP.** An invoice model built on exact
decimals, EN 16931 totals calculation, reading and writing of the two syntaxes the standard binds
to (UBL 2.1 and UN/CEFACT CII), validation against the official CEN EN 16931 rules and the
Peppol BIS Billing 3 rules, and sending and receiving over Peppol through an access point. No Java
is needed at runtime.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

> **Status:** unreleased, under development. The API may still change.

## What is in, and what is not

Included today:

- The complete EN 16931-1:2017+A1 semantic model as immutable PHP classes
  ([term to class map](docs/MODEL.md)).
- Code lists as backed enums, generated from the CEN validation artefacts 1.3.17.
- `TotalsCalculator`: line net amounts, VAT breakdown and document totals with exact decimals.
- Readers and writers for UBL 2.1 (Invoice, CreditNote) and UN/CEFACT CII (CrossIndustryInvoice).
- A pure-PHP validator that runs the official CEN EN 16931 Schematron rules (1.3.17) for UBL and
  CII, with verdicts checked equal to the official XSLT on a corpus of 1,892 documents
  ([how it works](docs/VALIDATION.md)).
- Validation against the Peppol BIS Billing 3.0.22 rules, compiled on your machine from the
  official OpenPeppol files, with verdicts checked equal to Saxon on a corpus of 1,611 documents
  ([Peppol BIS 3](docs/PEPPOL.md)). The Peppol envelope (SBDH) rules are compiled and checked
  the same way.
- XRechnung 3.0.2 validation that follows the KoSIT validator 1.6.3 with its configuration
  2026-08-31 (scenarios, XML Schema, custom levels), with the optional Apache-2.0 rules package
  `intragile/einvoicing-rules-xrechnung` ([XRechnung](#xrechnung)), and writing XRechnung from the
  model.
- Peppol transport: sending and receiving through an access point (Recommand driver), SML and
  SMP participant lookup, Peppol Directory search, webhook verification, and an SBDH envelope
  writer and reader ([Peppol transport](docs/TRANSPORT.md)). Live transmission through Recommand
  is not verified yet.

- ZUGFeRD / Factur-X 1.0 (profiles MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED and XRECHNUNG):
  writing the profile XML, embedding it into a PDF/A you supply, reading it back out of a hybrid
  PDF, and validating it, with the optional Apache-2.0 rules package
  `intragile/einvoicing-rules-facturx` ([ZUGFeRD / Factur-X](#zugferd--factur-x)). The PDF layer is
  pure PHP ([PDF layer](docs/PDF.md)).

Not included yet, planned for later releases: other access point drivers, CII and self-billing
over Peppol, and a Laravel package. Rendering the invoice PDF is out of scope: you supply the PDF.

## Install

Requires PHP 8.3 or later, `ext-dom`, `ext-libxml`, `ext-mbstring` and `ext-zlib`.

The package is not published yet. Once it is, installation will be:

```bash
composer require intragile/php-einvoicing
```

## Create an invoice

The common case is short. Amounts, quantities, prices and rates are given as strings or
integers and held as `Brick\Math\BigDecimal`. A `float` is rejected at runtime with an
`InvalidArgumentException` that names the business term, with or without
`declare(strict_types=1)` in your file, because a float cannot hold 0.1 exactly. One known limit:
in a file without `strict_types`, PHP still turns a `bool` into an integer (`true` becomes 1)
before the library sees it. PHPStan reports a `bool` argument from the PHPDoc types.

```php
<?php

declare(strict_types=1);

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\{CountryCode, CurrencyCode, InvoiceTypeCode, UnitCode, VatCategory};
use IntrAgile\EInvoicing\Model\{Invoice, InvoiceLine, Item, LineVat, Party, PostalAddress, Price};
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;

$seller = new Party('Eladó Kft.', vatIdentifier: 'HU12345678', address: new PostalAddress(CountryCode::HU, city: 'Budapest', postCode: '1011', line1: 'Fő utca 1.'));
$buyer = new Party('Vevő Zrt.', vatIdentifier: 'HU87654321', address: new PostalAddress(CountryCode::HU, city: 'Szeged', postCode: '6720'));
$vat = new LineVat(VatCategory::StandardRate, '27');

$invoice = new Invoice(
    number: 'INV-2026-0001',
    issueDate: new DateTimeImmutable('2026-11-11'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::HUF,
    seller: $seller,
    buyer: $buyer,
    lines: [
        new InvoiceLine('1', '2', UnitCode::C62, new Price('10000'), new Item('Consulting'), $vat),
        new InvoiceLine('2', '1.5', UnitCode::HUR, new Price('8000'), new Item('Support'), $vat),
    ],
);

$invoice = TotalsCalculator::complete($invoice); // line net amounts, VAT breakdown, totals

$ubl = (new UblWriter)->write($invoice); // UBL 2.1 XML string
$cii = (new CiiWriter)->write($invoice); // UN/CEFACT CII XML string
```

`TotalsCalculator::complete()` fills line net amounts and VAT breakdown entries you left empty
and keeps the ones you set. It always recomputes the document totals, so they agree with the lines.
A prepaid amount (BT-113) and a rounding amount (BT-114) go in as arguments, for example
`TotalsCalculator::complete($invoice, paidAmount: '500.00')`. VAT exemption reasons (BT-120/BT-121)
for categories such as E, AE, K or O, and the VAT total in accounting currency (BT-111), are yours
to supply. `TotalsCalculator::check()` returns the ids of the BR-CO calculation rules an invoice
violates. Writers refuse an invoice without totals.

## Read a received invoice

```php
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;

try {
    $result = (new UblReader)->read($xml); // or (new CiiReader)->read($xml)
} catch (UnsafeXml | UnsupportedDocument $e) {
    // refuse the document
}

$invoice = $result->invoice;  // IntrAgile\EInvoicing\Model\Invoice
$result->ignored;             // list<string>: paths of elements that were not mapped
```

Both readers return a `ReadResult`. Elements the model does not know, such as extensions or
content from a newer syntax version, are skipped and listed in `ReadResult::$ignored` instead
of failing the read. Only data the model cannot do without makes a document unsupported.

## Validate an invoice

```php
use IntrAgile\EInvoicing\Validation\Validator;

$report = Validator::en16931()->validate($xml); // UBL Invoice, UBL CreditNote or CII

if (! $report->isValid()) {
    foreach ($report->fatal() as $finding) {
        echo "{$finding->ruleId} at {$finding->location}: {$finding->message}\n";
    }
    foreach ($report->dynamicErrors as $error) {   // values the rules could not evaluate, such as "12,50"
        echo "{$error->errorCode} in {$error->ruleId} at {$error->location}\n";
    }
}
```

An invoice built with the model is written and validated in one call:
`Validator::en16931()->validateInvoice($invoice)` (UBL) or
`->validateInvoice($invoice, Syntax::Cii)` (`IntrAgile\EInvoicing\Syntax\Syntax`).

The validator runs the official CEN EN 16931 rules (validation artefacts 1.3.17: 982 asserts for
UBL, 810 for CII), compiled from the official Schematron into PHP; no Java, no XSLT processor.
`isValid()` is true when no fatal rule failed; warnings (`warnings()`) and information findings
(`information()`) do not make an invoice invalid, and `$report->ruleSets` names the rule sets
that ran (`en16931-ubl 1.3.17`). A value the rules cannot evaluate, such as `abc` as an amount,
does not stop the run: it is reported in `$report->dynamicErrors` with its XPath error code
(`FORG0001`), the other rules are still checked, and the invoice is not valid. A document that is
not an invoice of a supported syntax throws `UnsupportedDocument`, and a DOCTYPE throws
`UnsafeXml`; neither is ever reported as valid.

Verdicts are checked equal to the official CEN XSLT run by Saxon on a corpus of 1,892 documents
(the 34 CEN example invoices, 1,169 CEN unit-test documents, 680 seeded mutations, 6 model
invoices and 3 crafted documents). This is a test corpus, not a proof for every input. On the
1,757 documents Saxon reports on, the rule ids and flags are the same; on the 135 mutations where
Saxon aborts with a dynamic error, only the XPath error code is compared, not the findings.

A typical invoice validates in 7–50 ms and a 1,000-line invoice in about 1.7 s (single dev-box
measurement: Apple M1, PHP 8.5). Some rules grow faster than linearly with repeated elements
(UBL-SR-47 is quadratic in the number of payment means codes), so set input-size and time limits
for untrusted inbound files. [docs/VALIDATION.md](docs/VALIDATION.md) describes the supported
XPath, the cases where the engine mirrors Saxon, what the oracle checks, resource use and the
measurements.

The compiled rules are a derivative of the CEN artefacts and are licensed under the **EUPL-1.2**.
They are in a separate package, `intragile/einvoicing-rules-en16931` (`packages/rules-en16931`
in this repository), which this package requires; the engine itself stays MIT. Neither package is
published yet.

## XRechnung

`XRechnungValidator` validates XRechnung 3.0.2 invoices the way the KoSIT validator 1.6.3 does
with its XRechnung configuration 2026-08-31. It needs the separate rules package, which is a
suggestion of this package, not a requirement:

```bash
composer require intragile/einvoicing-rules-xrechnung
```

```php
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

$report = XRechnungValidator::create()->validate($xml);
$report->scenario;   // "EN16931 XRechnung (UBL Invoice)"
$report->isValid();  // false on an XML Schema violation or any fatal finding
```

The first KoSIT scenario whose match holds is applied: XRechnung CIUS, Extension or CVD for a UBL
Invoice, a UBL CreditNote (CIUS and CVD) or CII, or plain EN 16931. The document is checked
against that scenario's XML Schema and, only when it is schema valid, against CEN 1.3.16 and the
XRechnung 2.6.0 Schematron; the scenario's custom levels then replace rule flags (`CII-SR-465` is
fatal in XRechnung CII). A document whose specification identifier (BT-24) matches no scenario
exactly is refused with an exception that lists the accepted identifiers. To write XRechnung, put
`XRechnung::processControl()` on the invoice. The design goal is the same verdict as KoSIT:
scenario, XML Schema validity, the set of rule ids with their levels, and acceptance; the KoSIT
validator itself runs as the test oracle. Against KoSIT validator 1.6.3 with configuration
2026-08-31, scenario, XML Schema validity, verdict and acceptance are equal on 579 documents (the
KoSIT test suite, the XRechnung Schematron unit tests, the configuration tests, the CEN 1.3.16
examples and mutations); on 2 documents where a rule set hits an XPath dynamic error they are
equal once that rule set's findings are left out, as KoSIT reports none for it; and 3 files that
are not well-formed or carry a DOCTYPE are refused by both
([docs/VALIDATION.md](docs/VALIDATION.md#the-kosit-conformance-run)). The rules package is
Apache-2.0, like the KoSIT artefacts it is compiled from.

## ZUGFeRD / Factur-X

A hybrid invoice is a PDF/A-3 that carries the invoice as embedded CII XML. The library writes the
XML for a profile, embeds it into a PDF/A you supply by an incremental update (your PDF's bytes are
not changed), reads it back, and validates it against the Factur-X 1.09.2 rules. Writing a profile
and validating need the separate rules package, a suggestion of this package:

```bash
composer require intragile/einvoicing-rules-facturx
```

```php
use IntrAgile\EInvoicing\Syntax\FacturX\{FacturXPdfReader, FacturXPdfWriter, FacturXProfile, FacturXXmlWriter};
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;

$xml = (new FacturXXmlWriter)->write($invoice, FacturXProfile::En16931)->xml;
$result = (new FacturXPdfWriter)->embed($pdfA3Bytes, $xml);   // $result->pdf, $result->warnings

$document = (new FacturXPdfReader)->read($result->pdf);       // ->xml, ->profile, ->invoice()
$report = FacturXValidator::create()->validate($document->xml);
```

We do not convert to PDF/A: give us a PDF/A input. The writer adds the trailer `/ID`, the XMP
packet and an sRGB output intent, and warns (or throws `NotPdfA` with `strict: true`) when the
input declares no PDF/A part; it does not embed fonts or fix transparency. MINIMUM and BASIC WL
carry no lines, so reading them into the `Invoice` model throws `UnsupportedDocument`; the XML is
still available. Validation runs the XML Schema first, and the Schematron still runs after an XSD
failure. The format is verified offline: Schematron verdicts equal the official Factur-X 1.09.2
XSLT (SaxonC) on 699 documents with 0 differences, PDF/A was checked with veraPDF 1.30.3, all 102
PDFs of the Mustang corpus parse and all 61 FeRD example invoices are read. Nothing is verified in
live exchanges yet. Details: [docs/FACTURX.md](docs/FACTURX.md).

## Peppol BIS 3

`Validator::peppol($dir)` validates against Peppol BIS Billing 3.0.22: OpenPeppol's copy of the
CEN rules (CEN 1.3.16) and the Peppol rules, for UBL and CII. OpenPeppol publishes its rule files
without a licence, so this package ships no Peppol-derived file. One command downloads the
official files at a pinned tag, verifies their SHA-256 checksums and compiles them on your
machine; `--from=DIR` compiles from a local copy on an offline server.

```bash
vendor/bin/einvoicing-rules install peppol --dir=var/peppol-rules
```

```php
$report = Validator::peppol(__DIR__.'/var/peppol-rules')->validate($xml);
```

Verdicts are checked equal to the OpenPeppol Schematron run by Saxon on a corpus of 1,611
documents, with no mismatch; where Saxon aborts (101 documents), only the error code is compared.
`Validator::peppolEnvelope($dir)` checks the envelope (SBDH) of a Standard Business Document
against the official envelope rules. Install, deployment, verification details and timings:
[docs/PEPPOL.md](docs/PEPPOL.md).

## Sending over Peppol

```php
$transport = new ValidatingTransport(
    new RecommandTransport(new RecommandConfig($apiKeyId, $secret, $companyId), $http),
    Validator::peppol(__DIR__.'/var/peppol-rules'),
);

$result = $transport->send(OutboundDocument::fromXml($ublXml));
```

`$http` wraps any PSR-18 client (Guzzle, Symfony) and PSR-17 factories; the package itself
depends only on the PSR interfaces. The sender, receiver, document type and process are read from
the invoice. Always wrap the driver in `ValidatingTransport`: it refuses an invalid document
before it leaves. Every error says through `isRetryable()` whether retrying is safe; an
`OutcomeUnknown` means the access point may have accepted the document, so check before
resending. Participant lookup (SML and SMP), the Peppol Directory, receiving by polling or
webhooks, the SBDH envelope, what is verified and the known limitations:
[docs/TRANSPORT.md](docs/TRANSPORT.md). Live transmission through Recommand is not verified yet;
the driver is tested against fixtures written from Recommand's published API description.

## Security: hostile XML

Received invoices are untrusted input. The readers load XML through `SafeXml`: any DOCTYPE
declaration is refused with `UnsafeXml` before entities can be defined or expanded, the parser
runs with `LIBXML_NONET`, and `LIBXML_NOENT` and `LIBXML_DTDLOAD` are never used. External
entities, network access and entity-expansion attacks (billion laughs) are therefore not
possible. To report a vulnerability, see [SECURITY.md](SECURITY.md).

## Verification

- The official CEN example invoices (release
  [1.3.17](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17))
  are read, written and round-tripped by the test suite, and read across syntaxes.
- An optional test group runs the official CEN validation stylesheets (XSLT 2.0) through
  SaxonC-HE in Docker as an oracle: documents produced by the writers must pass the official
  rules, the oracle itself is checked to fire a rule on a deliberately broken total (BR-CO-16), and
  the validator's verdict must equal the oracle's on every document of the conformance corpus
  ([details](docs/VALIDATION.md#how-verdict-equality-is-checked)).
  The same group compiles the OpenPeppol Schematron with the ISO Schematron skeleton in Saxon and
  checks the Peppol verdicts ([details](docs/PEPPOL.md#how-the-verdicts-are-verified)).
  `docker/oracle/run.sh 8.5` builds the image and runs the group; it needs Docker and `vendor/`
  installed. This is development tooling only and is not part of the installed package.
- A second optional group, `kosit-oracle`, runs the KoSIT validator 1.6.3 (Java) with the
  XRechnung configuration 2026-08-31 in Docker and compares the XRechnung verdicts with it
  ([details](docs/VALIDATION.md#the-kosit-validator-as-the-xrechnung-oracle)).
  `docker/kosit/run.sh` builds the image, starts the KoSIT daemon and runs the group; it is not
  part of CI.
- The same oracle group also runs the official Factur-X 1.09.2 XSLT through SaxonC and compares
  the Factur-X verdicts, and the optional `verapdf` group (`docker/verapdf/run.sh`) runs veraPDF
  1.30.3 on the PDFs the writer produces ([details](docs/FACTURX.md#verification)).
- CI runs PHPUnit and PHPStan (level max) on PHP 8.3, 8.4 and 8.5, plus Pint, the oracle and a
  check that the compiled rules match the official Schematron.

## Developing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
php bin/generate-model-doc.php   # regenerates docs/MODEL.md
composer compile-rules           # recompiles the rules from the official Schematron
```

## Licensing and attribution

The library is MIT licensed ([LICENSE](LICENSE)). The CEN validation artefacts used in tests
are licensed under the EUPL v1.2, are not part of the installed package, and are described in
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md).
The code-list values in `src/CodeList/` are derived from those artefacts; see
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md) for the attribution. The compiled validation
rules in `packages/rules-en16931` are licensed under the EUPL-1.2; see
[packages/rules-en16931/SOURCE.md](packages/rules-en16931/SOURCE.md). The optional XRechnung rules,
scenario table and schemas in `packages/rules-xrechnung` are licensed under the Apache-2.0, like
the KoSIT artefacts they come from; see
[packages/rules-xrechnung/SOURCE.md](packages/rules-xrechnung/SOURCE.md). The Peppol BIS rules are
not distributed: OpenPeppol AISBL holds their copyright, and they are downloaded and compiled on
your machine ([why](docs/PEPPOL.md#why-the-rules-are-compiled-on-your-machine)). The optional
Factur-X rules and schemas in `packages/rules-facturx` are licensed under the Apache-2.0, like the
FeRD artefacts they come from; see [packages/rules-facturx/SOURCE.md](packages/rules-facturx/SOURCE.md).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

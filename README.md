# PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

**A framework-agnostic EN 16931 e-invoicing core for PHP.** An invoice model built on exact
decimals, EN 16931 totals calculation, and reading and writing of the two syntaxes the standard
binds to: UBL 2.1 and UN/CEFACT CII, and validation against the official CEN EN 16931 rules.
No Java is needed at runtime.

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
  CII, with verdicts proven equal to the official XSLT ([how it works](docs/VALIDATION.md)).

Not included yet, planned for later releases: the Peppol BIS 3 rules and Peppol transport, the
XRechnung and ZUGFeRD/Factur-X profiles, and a Laravel package.

## Install

Requires PHP 8.3 or later, `ext-dom`, `ext-libxml` and `ext-mbstring`.

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
and keeps the ones you set. It always recomputes the document totals, so they agree with the
lines. A prepaid amount (BT-113) and a rounding amount (BT-114) go in as arguments, for example
`TotalsCalculator::complete($invoice, paidAmount: '500.00')`. VAT
exemption reasons (BT-120/BT-121) for categories such as E, AE, K or O, and the VAT total in
accounting currency (BT-111), are yours to supply. `TotalsCalculator::check()` returns the ids
of the BR-CO calculation rules an invoice violates. Writers refuse an invoice without totals.

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
    foreach ($report->errors as $error) {   // values the rules could not evaluate, such as "12,50"
        echo "{$error->errorCode} in {$error->ruleId} at {$error->location}\n";
    }
}
```

An invoice built with the model is written and validated in one call:
`Validator::en16931()->validateInvoice($invoice)` (UBL) or
`->validateInvoice($invoice, Syntax::Cii)` (`IntrAgile\EInvoicing\Syntax\Syntax`).

The validator runs the official CEN EN 16931 rules (validation artefacts 1.3.17: 982 asserts for
UBL, 810 for CII), compiled from the official Schematron into PHP; no Java, no XSLT processor.
`isValid()` is true when no fatal rule failed; warnings are listed by `warnings()` and do not make
an invoice invalid. A value the rules cannot evaluate, such as `abc` as an amount, does not stop
the run: it is reported in `$report->errors` with its XPath error code (`FORG0001`), the other
rules are still checked, and the invoice is not valid. A document that is not an invoice of a
supported syntax throws `UnsupportedDocument`, and a DOCTYPE throws `UnsafeXml`; neither is ever
reported as valid.

Every verdict is checked against the official CEN XSLT run by Saxon: the 34 CEN example invoices,
1,169 CEN unit-test documents and 680 seeded mutations give exactly the same rule ids and flags.
A typical invoice validates in 7–50 ms, a 1,000-line invoice in about 1.7 s (measured on an
Apple M1 with PHP 8.5). [docs/VALIDATION.md](docs/VALIDATION.md) describes the supported XPath,
the cases where the engine mirrors Saxon, the proof and the measurements.

The compiled rules are a derivative of the CEN artefacts and are licensed under the **EUPL-1.2**.
They are in a separate package, `intragile/einvoicing-rules-en16931` (`packages/rules-en16931`
in this repository), which this package requires; the engine itself stays MIT. Neither package is
published yet.

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
  ([details](docs/VALIDATION.md#how-verdict-equality-is-proven)).
  `docker/oracle/run.sh 8.5` builds the image and runs the group; it needs Docker and `vendor/`
  installed. This is development tooling only and is not part of the installed package.
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
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md). The code-list values in
`src/CodeList/` are derived from those artefacts; see
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md) for the attribution. The compiled validation
rules in `packages/rules-en16931` are licensed under the EUPL-1.2; see
[packages/rules-en16931/SOURCE.md](packages/rules-en16931/SOURCE.md).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

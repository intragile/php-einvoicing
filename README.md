# PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

**A framework-agnostic EN 16931 e-invoicing core for PHP.** An invoice model built on exact
decimals, EN 16931 totals calculation, and reading and writing of the two syntaxes the standard
binds to: UBL 2.1 and UN/CEFACT CII. No Java is needed at runtime.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

> **Status:** unreleased, under development. The API may still change.

## What is in, and what is not

Included today:

- The complete EN 16931-1:2017+A1 semantic model as immutable PHP classes
  ([term to class map](docs/MODEL.md)).
- Code lists as backed enums, generated from the CEN validation artefacts 1.3.17.
- `TotalsCalculator`: line net amounts, VAT breakdown and document totals with exact decimals.
- Readers and writers for UBL 2.1 (Invoice, CreditNote) and UN/CEFACT CII (CrossIndustryInvoice).

Not included yet, planned for later releases: a pure-PHP validator for the EN 16931 business
rules, Peppol transport, the XRechnung and ZUGFeRD/Factur-X profiles, and a Laravel package.
Until the validator exists, the library does not tell you whether an invoice is valid; see
[Verification](#verification) for what has been checked.

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
  rules, and the oracle itself is checked to fire a rule on a deliberately broken total (BR-CO-16).
  `docker/oracle/run.sh 8.5` builds the image and runs the group; it needs Docker and `vendor/`
  installed. This is development tooling only and is not part of the installed package.
- CI runs PHPUnit and PHPStan (level max) on PHP 8.3, 8.4 and 8.5, plus Pint and the oracle.

## Developing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
php bin/generate-model-doc.php   # regenerates docs/MODEL.md
```

## Licensing and attribution

The library is MIT licensed ([LICENSE](LICENSE)). The CEN validation artefacts used in tests
are licensed under the EUPL v1.2, are not part of the installed package, and are described in
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md). The code-list values in
`src/CodeList/` are derived from those artefacts; see
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md) for the attribution.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

# PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

**A framework-agnostic EN 16931 e-invoicing core for PHP.** It will provide an invoice model
built on exact decimals, EN 16931 totals calculation, and reading and writing of the two
syntaxes the standard binds to: UBL 2.1 and UN/CEFACT CII.

[Magyar nyelvű leírás → README.hu.md](README.hu.md)

> **Status:** under development, not yet released. There is no public API yet.

## Goals

- EN 16931-1:2017+A1, checked against the official CEN validation artefacts
  ([release 1.3.17](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17)).
- No floating point for amounts, quantities, prices or rates; all arithmetic uses
  [brick/math](https://github.com/brick/math).
- Safe parsing of received invoices: no DOCTYPE, no external entities, no network access.
- Runtime dependencies kept to `ext-dom`, `ext-libxml`, `ext-mbstring` and `brick/math`.

## Requirements

PHP 8.3 or later.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The official CEN example invoices and validation stylesheets used by the tests live in
`tests/fixtures/official/`. They are licensed under EUPL v1.2, not MIT, and are not part of the
installed package; see [tests/fixtures/official/SOURCE.md](tests/fixtures/official/SOURCE.md).

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

# Source of the compiled rules

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The files in `rules/` are derived from the CEN/TC 434 EN 16931 validation artefacts. They are
third-party material licensed under the European Union Public Licence v1.2 (EUPL-1.2).

## Origin

| | |
|---|---|
| Project | [ConnectingEurope/eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931) |
| Release | [`validation-1.3.17`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17) (2026-10-09) |
| UBL source | `en16931-ubl-1.3.17.zip` → `schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch`, SHA-256 `eb27995ba7bace330475a0afd54a21bb0ebb050d55774949d5e10f65ecef7b95` |
| CII source | `en16931-cii-1.3.17.zip` → `schematron/preprocessed/EN16931-CII-validation-preprocessed.sch`, SHA-256 `74fa94df0513881b9c8e369650bb2282f9c8a30bb52ce4ce43d1219e88ccedc2` |
| Licence text | `LICENSE.txt` at the tag, SHA-256 `fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5` |

Each generated file records the name and SHA-256 of the `.sch` it was compiled from, in its header
comment and in its `source` and `sha256` keys.

## What was changed

The preprocessed Schematron is the official rule set with all includes and abstract patterns
resolved. The compiler keeps every pattern, rule and assert in document order, with its id, flag,
original `context` and `test` text and message (whitespace collapsed), and adds the XPath syntax
tree of each context and test. Phases are dropped: the official XSLT runs all patterns when no phase
is selected, and so does the engine. Nothing else is added, removed or reworded.

## Licence

The upstream repository's `LICENSE.txt` and `README.md` state:

> Licensed under European Union Public Licence (EUPL) version 1.2.

[`LICENSE`](LICENSE) in this package is the EUPL v1.2 text from the upstream `LICENSE.txt`, without
the banner that precedes it there (the banner is the notice quoted above).

EUPL v1.2 Article 2 grants the right to "modify the Work, and make Derivative Works based upon the
Work" and to "distribute the Work or copies thereof". Article 5 attaches these conditions, which
this package meets:

- **Attribution:** the licence text travels with the files in [`LICENSE`](LICENSE), the origin is
  named above, and every generated file states in its header that it is generated from the CEN
  EN 16931 validation artefacts under EUPL-1.2.
- **Notice of modification:** the header of every generated file says that it is generated (that
  is, modified) from the upstream Schematron, and "What was changed" above describes the change.
  The files were first compiled on 2026-11-11; the header carries no date so that the output stays
  byte-stable, and the Git history records the date of every later regeneration.
- **Copyleft:** the derivative files are distributed under EUPL v1.2 only, in this separate
  package with its own `composer.json` (`"license": "EUPL-1.2"`). The MIT-licensed engine loads
  them as data and contains none of them.
- **Source code:** the source repository and tag are named above; the compiler that produced the
  files is `bin/compile-schematron.php` in
  [`intragile/php-einvoicing`](https://github.com/intragile/php-einvoicing).

## Regenerating

```bash
composer compile-rules                     # writes rules/*.php
php bin/compile-schematron.php --check     # exits 1 when a file is stale
```

Both run from the root of the `php-einvoicing` repository, after `php bin/fetch-official.php`.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

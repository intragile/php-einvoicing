# Source of the compiled rules

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The files in `rules/` are derived from the CEN/TC 434 EN 16931 validation artefacts. They are
third-party material licensed under the European Union Public Licence v1.2 (EUPL-1.2).

## Origin

| | |
|---|---|
| Project | [ConnectingEurope/eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931) |
| Licence text | `LICENSE.txt` at both tags (identical), SHA-256 `fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5` |

Two releases are compiled. 1.3.17 is the default; 1.3.16 is kept because the KoSIT XRechnung
validator configuration is built on it.

| Release | Archive | Preprocessed `.sch` | SHA-256 of the `.sch` |
|---|---|---|---|
| [`validation-1.3.17`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17) (2026-10-09) | `en16931-ubl-1.3.17.zip` | `schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch` | `eb27995ba7bace330475a0afd54a21bb0ebb050d55774949d5e10f65ecef7b95` |
| | `en16931-cii-1.3.17.zip` | `schematron/preprocessed/EN16931-CII-validation-preprocessed.sch` | `74fa94df0513881b9c8e369650bb2282f9c8a30bb52ce4ce43d1219e88ccedc2` |
| [`validation-1.3.16`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.16) (2026-04-13) | `en16931-ubl-1.3.16.zip` (SHA-256 `bafada015efbc5248bf5e05ad2191e1d9833ef96e9dd5f4bce420a747342da85`) | `schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch` | `268d4f7a2688676695e6c69cba6fba69a6802604fee12cb544a6b30ff09555a3` |
| | `en16931-cii-1.3.16.zip` (SHA-256 `1cd53cb8a84d38aedc82c0caede217da983a7934dd663f793a092fd66443c561`) | `schematron/preprocessed/EN16931-CII-validation-preprocessed.sch` | `54e0dc6d06cd7f17d268bb9696ff56f58d386ee28961c9bbef0a56718c400c89` |

Each generated file records the name and SHA-256 of the `.sch` it was compiled from, in its header
comment and in its `source` and `sha256` keys.

## What was changed

The preprocessed Schematron is the official rule set with all includes and abstract patterns
resolved. The compiler keeps every pattern, rule and assert in document order, with its id, flag,
original `context` and `test` text and message (whitespace collapsed), and adds the XPath syntax
tree of each context and test. Phases are dropped: the official XSLT runs all patterns when no phase
is selected, and so does the engine. The schema `title` element and the `flag` attributes on 45
`rule` elements per release (22 in UBL, 23 in CII) are dropped as well, because the official XSLT
never emits them in its report; the flag of each assert is kept. Nothing else is added, removed or reworded.

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
- **Source code:** the source repository and tags are named above; the compiler that produced the
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

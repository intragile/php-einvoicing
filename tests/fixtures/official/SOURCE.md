# Official CEN test fixtures

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The files under `cen-1.3.17/` are unmodified copies of the CEN/TC 434 EN 16931 validation
artefacts. They are third-party material and are **not** covered by this package's MIT licence.

## Origin

| | |
|---|---|
| Project | [ConnectingEurope/eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931) |
| Release | [`validation-1.3.17`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17) (2026-10-09) |
| UBL archive | `en16931-ubl-1.3.17.zip`, SHA-256 `bf02a0e3f6cbe692a4a28e70be7bdcaded5612b4169b543e13d6cb4f33c29e92` |
| CII archive | `en16931-cii-1.3.17.zip`, SHA-256 `e5f3235824b6b38454b1d54cf3f14e01efb5b33971fbefc4e64997d0ef619e89` |
| Licence text | `LICENSE.txt` at the tag, SHA-256 `fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5` |

Only the `examples/`, `xslt/` and `schematron/codelist/` directories of each archive are kept.
The code-list Schematron (`EN16931-UBL-codes.sch`, `EN16931-CII-codes.sch`) is the input of
`bin/generate-codelists.php`, which derives the enums in `src/CodeList/` from it (see
[`src/CodeList/SOURCE.md`](../../../src/CodeList/SOURCE.md)). The examples in the
archives are byte-identical to `ubl/examples/` and `cii/examples/` in the repository at the same
tag (their Git blob hashes match).

## Licence

The upstream repository's `LICENSE.txt` and `README.md` state:

> Licensed under European Union Public Licence (EUPL) version 1.2.

GitHub's licence detection reports `NOASSERTION` for the repository, probably because the file
puts that notice in a banner before the licence text. The text that follows is the EUPL v1.2
("EUROPEAN UNION PUBLIC LICENCE v. 1.2 / EUPL © the European Union 2007, 2016"); see
[upstream `LICENSE.txt`](https://github.com/ConnectingEurope/eInvoicing-EN16931/blob/validation-1.3.17/LICENSE.txt).

Redistribution is allowed. EUPL v1.2 Article 2 grants the right to "reproduce the Work" and to
"distribute the Work or copies thereof". Article 5 attaches these conditions, which this folder
meets:

- **Attribution:** the files are unmodified, and a copy of the licence travels with them in
  [`cen-1.3.17/LICENSE.txt`](cen-1.3.17/LICENSE.txt).
- **Copyleft:** the copies are redistributed under EUPL v1.2, not under MIT. No code in this
  package links against them. The examples and the XSLT are read as test input only; the only
  derived output is the set of code values in `src/CodeList/`, documented in
  [`src/CodeList/SOURCE.md`](../../../src/CodeList/SOURCE.md).
- **Source code:** the source repository is named above.

The `tests/` directory is excluded from the Composer dist archive (`.gitattributes`
`export-ignore`), so these files are present in the Git repository only, never in an installed
package.

## OASIS UBL 2.1 schemas

The files under `ubl-2.1/xsd/` are unmodified copies of the OASIS UBL 2.1 schemas. They are used
only by the test suite, to validate written documents with `DOMDocument::schemaValidate()`.

| | |
|---|---|
| Project | [OASIS Universal Business Language (UBL) 2.1 OS](https://docs.oasis-open.org/ubl/os-UBL-2.1/) (4 November 2013) |
| Archive | `https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.zip`, SHA-256 `60b80d76394a8a2add90723ecb8e0e2e9d826775de9749df37a72d60703f86ed` |

Only `xsd/maindoc/UBL-Invoice-2.1.xsd`, `xsd/maindoc/UBL-CreditNote-2.1.xsd` and the
`xsd/common/` modules they import are kept. Every file carries its OASIS copyright notice
("Copyright (c) OASIS Open 2013. All Rights Reserved."), which the
[OASIS IPR Policy](https://www.oasis-open.org/policies-guidelines/ipr/) requires to stay with
copies of OASIS specifications; the files are not covered by this package's MIT licence.

## Refreshing

```bash
php bin/fetch-official.php
```

The script downloads the two release archives, the licence text and the UBL 2.1 package,
verifies each against the SHA-256 sums above, and rebuilds `cen-1.3.17/` and `ubl-2.1/`.
Verified downloads are cached in the git-ignored `.cache/official/`, so repeated runs work
offline and produce the same files. It
needs the `zip` extension.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

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
`xsd/common/` modules they import are kept, unmodified. The files are not covered by this
package's MIT licence.

### Licence

Each schema file carries "Copyright (c) OASIS Open 2013. All Rights Reserved." The package itself
has no separate licence file; its terms are the "Notices" section of the specification
(`UBL-2.1.html` in the archive, also at
<https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html>), which says:

> This document and translations of it may be copied and furnished to others, and derivative works
> that comment on or otherwise explain it or assist in its implementation may be prepared, copied,
> published, and distributed, in whole or in part, without restriction of any kind, provided that
> the above copyright notice and this section are included on all such copies and derivative
> works. However, this document itself may not be modified in any way, including by removing the
> copyright notice or references to OASIS, [...]

Redistribution is therefore allowed on two conditions, which this folder meets:

- **Unmodified:** the schema files are byte-for-byte copies from the archive, notices included.
- **Notice included:** `bin/fetch-official.php` copies the complete "Notices" section to
  [`ubl-2.1/NOTICE.txt`](ubl-2.1/NOTICE.txt) next to the schemas. It is converted from HTML to
  plain text and reflowed: the wording is unchanged, link targets are omitted.

Full policy: [OASIS IPR Policy](https://www.oasis-open.org/policies-guidelines/ipr/).

## UN/CEFACT Cross Industry Invoice D16B schemas

The files under `cii-d16b/` are unmodified copies of the UN/CEFACT Cross Industry Invoice D16B
schemas (SCRDM subset, uncoupled code list modules), schema version 100.D16B of 10 October 2016.
They are used only by the test suite, to validate written CII documents with
`DOMDocument::schemaValidate()`. The CEN repository ships them at the same tag as the artefacts,
in `cii/schema/D16B SCRDM (Subset)/uncoupled clm/CII/uncefact/data/standard/`; the four files
form a closed set (they import only each other).

| File | SHA-256 |
|---|---|
| `CrossIndustryInvoice_100pD16B.xsd` | `b9798aafcba039d0630f0015ffae5092a1758bf924d19d97b6bf0bf9d95f22a0` |
| `CrossIndustryInvoice_QualifiedDataType_100pD16B.xsd` | `a39e0662c31fbdca22237118d6b6bbfa16f69efbd1ee5b23787366349c5b924d` |
| `CrossIndustryInvoice_ReusableAggregateBusinessInformationEntity_100pD16B.xsd` | `cc682d67791ffe16c45320619ff089a1c8f3ad22691fc426e4056f2e67909fe0` |
| `CrossIndustryInvoice_UnqualifiedDataType_100pD16B.xsd` | `5d4ce8a6445caa4b7691f6fba6ad08e312e6a853cd3a422666d121ee2d13ef8c` |

Source: `https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/validation-1.3.17/cii/schema/D16B%20SCRDM%20(Subset)/uncoupled%20clm/CII/uncefact/data/standard/<file>`.
The files are not covered by this package's MIT licence.

### Licence

Each schema file carries "Copyright (C) UN/CEFACT (2016). All Rights Reserved." followed by this
permission:

> This document and translations of it may be copied and furnished to others, and derivative works
> that comment on or otherwise explain it or assist in its implementation may be prepared, copied,
> published and distributed, in whole or in part, without restriction of any kind, provided that
> the above copyright notice and this paragraph are included on all such copies and derivative
> works. However, this document itself may not be modified in any way, such as by removing the
> copyright notice or references to UN/CEFACT, [...]
>
> The limited permissions granted above are perpetual and will not be revoked by UN/CEFACT or its
> successors or assigns.

Redistribution is therefore allowed on two conditions, which this folder meets:

- **Unmodified:** the files are byte-for-byte copies, verified against the sums above.
- **Notice included:** the copyright notice and the permission paragraph are part of every file,
  so no separate notice file is needed.

## Refreshing

```bash
php bin/fetch-official.php
```

The script downloads the two release archives, the licence text, the UBL 2.1 package and the CII
D16B schemas, verifies each against the SHA-256 sums above, and rebuilds `cen-1.3.17/`,
`ubl-2.1/` and `cii-d16b/`.
Verified downloads are cached in the git-ignored `.cache/official/`, so repeated runs work
offline and produce the same files. It
needs the `zip` extension.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

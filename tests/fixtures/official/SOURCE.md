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
| Unit tests | `test/` of the repository archive at the tag (`https://github.com/ConnectingEurope/eInvoicing-EN16931/archive/refs/tags/validation-1.3.17.zip`), per-file SHA-256 in `cen-1.3.17/unit-tests/MANIFEST.sha256` |

Only the `examples/`, `xslt/`, `schematron/preprocessed/` and `schematron/codelist/` directories
of each archive are kept.
The preprocessed Schematron (`EN16931-UBL-validation-preprocessed.sch`,
`EN16931-CII-validation-preprocessed.sch`) is the official rule set with all includes and
abstract patterns resolved; it is the input of the Schematron compiler.
The code-list Schematron (`EN16931-UBL-codes.sch`, `EN16931-CII-codes.sch`) is the input of
`bin/generate-codelists.php`, which derives the enums in `src/CodeList/` from it (see
[`src/CodeList/SOURCE.md`](../../../src/CodeList/SOURCE.md)). The examples in the
archives are byte-identical to `ubl/examples/` and `cii/examples/` in the repository at the same
tag (their Git blob hashes match).

### Unit tests

`cen-1.3.17/unit-tests/` is an unmodified copy of the repository's `test/` directory at the tag:

| Directory | Files | Content |
|---|---|---|
| `Invoice-unit-UBL/` | 206 | VEFA `testSet` files, one UBL Invoice per `<test>` |
| `CreditNote-unit-UBL/` | 71 | VEFA `testSet` files, one UBL CreditNote per `<test>` |
| `cii/` | 2 | VEFA `testSet` files, one CII invoice per `<test>` |
| `testfiles/` | 29 | plain UBL invoices and credit notes |

Together they hold 1,169 documents (1,160 UBL, 9 CII); `Fixtures::unitTests()` extracts each as
a standalone document. GitHub does not guarantee that a source archive is byte-stable over time,
so the archive is not pinned by checksum; instead, every unpacked file is verified against
`MANIFEST.sha256` (the only file in that folder that is not from upstream).

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
  package links against them. The examples, the XSLT, the preprocessed Schematron and the unit
  tests are read as test input only; the only
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

## Peppol BIS Billing 3.0.22 (downloaded, not committed)

Unlike everything above, the Peppol material is **not committed and not redistributed**.
OpenPeppol publishes no licence for it: the
[peppol-bis-invoice-3](https://github.com/OpenPEPPOL/peppol-bis-invoice-3) repository has no
licence file, and the specification only states that "OpenPeppol AISBL holds the copyright of
this Peppol BIS" (`guide/bis/introduction.adoc`, "Statement of copyright"). Without a licence
there is no permission to redistribute, so `bin/fetch-official.php` downloads the files from the
official repository onto the developer's or CI machine, into the git-ignored
`tests/fixtures/peppol-3.0.22/` (`.gitignore`: `/tests/fixtures/peppol-*/`). The test helpers in
`tests/Fixtures.php` skip their tests when that folder is absent.

| | |
|---|---|
| Project | [OpenPEPPOL/peppol-bis-invoice-3](https://github.com/OpenPEPPOL/peppol-bis-invoice-3) |
| Tag | [`3.0.22`](https://github.com/OpenPEPPOL/peppol-bis-invoice-3/tree/3.0.22) (commit `38d693d71d28d813781323a6ca97324edcc4d239`, 2026-11-04) |
| Rule files | `https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/3.0.22/rules/sch/<file>`, into `peppol-3.0.22/sch/` |
| Test corpus | `rules/` of the repository archive at the tag (`https://github.com/OpenPEPPOL/peppol-bis-invoice-3/archive/refs/tags/3.0.22.zip`) |

The five rule files are pinned by SHA-256 in
[`src/Validation/Peppol/PeppolSources.php`](../../../src/Validation/Peppol/PeppolSources.php),
the single place these pins are kept:

| File | SHA-256 |
|---|---|
| `CEN-EN16931-UBL.sch` | `268d4f7a2688676695e6c69cba6fba69a6802604fee12cb544a6b30ff09555a3` |
| `CEN-EN16931-CII.sch` | `54e0dc6d06cd7f17d268bb9696ff56f58d386ee28961c9bbef0a56718c400c89` |
| `PEPPOL-EN16931-UBL.sch` | `f637444fab75a6da7e5004f94a6de8e71383ef5658dafe6c68925103b17ea148` |
| `PEPPOL-EN16931-CII.sch` | `7678c3cd4a219095ef5d4f2ece17e5d447eb92655e57a7d52290b35acfdc9dd8` |
| `PeppolBIS-Envelope-Rules.sch` | `96648791fb824b6b58ddae9cc5575e8298e0061fc43dd0934a0b1459396d76f9` |

The `CEN-*` files are OpenPeppol's own copy of the CEN rules (CEN 1.3.16), not the 1.3.17 files
above. `PeppolBIS-Envelope-Rules.sch` holds the rules for the Peppol Business Message Envelope
(SBDH) around an invoice; the copy published at
`https://docs.peppol.eu/poacc/billing/3.0/files/PeppolBIS-Envelope-Rules.sch` has the same
SHA-256 (checked 2026-11-12).

From the archive only `rules/unit-UBL-*`, `rules/unit-CII-*`, `rules/unit-SBDH/` (envelope
rules), `rules/examples/` and `rules/snippets/` are unpacked (269 files);
`rules/national-examples/` is skipped. Every unpacked file is verified against
[`../peppol-3.0.22.sha256`](../peppol-3.0.22.sha256), a manifest of our own that holds only the
relative paths and SHA-256 sums, no OpenPeppol content. Two upstream files are misnamed
`PEPPOL-COMMON-R048.xm`; they are kept, because `Fixtures::peppolUnitTests()` reads every regular
file. `rules/unit-UBL-PEPPOL/PEPPOL-COMMON-R041.xml` tests the syntax-independent rules on UBL
Order documents rather than invoices.

| Fixture helper | Content |
|---|---|
| `Fixtures::peppolSchematron($key)` | path of a rule file, keyed `cen-ubl`, `cen-cii`, `peppol-ubl`, `peppol-cii`, `envelope` |
| `Fixtures::peppolUnitTests()` | 1,029 documents (678 UBL, 351 CII) from 242 VEFA `testSet` files, named `peppol/<dir>/<file>#<index>` |
| `Fixtures::peppolEnvelopeTests()` | the 14 Standard Business Documents of `unit-SBDH/BR-SBDH.xml` (VEFA test set expanded), named `peppol/unit-SBDH/BR-SBDH.xml#<index>`; the syntax is that of the enveloped invoice |
| `Fixtures::peppolExamples()` | the 26 files of `examples/` and `snippets/`, keyed `examples/<file>` or `snippets/<file>` |

## CEN 1.3.16

The files under `cen-1.3.16/` are unmodified copies of the CEN/TC 434 validation artefacts of
release `validation-1.3.16`, the rule version the KoSIT XRechnung configuration is built on. The
licence is the same as for 1.3.17 (EUPL-1.2, the `LICENSE.txt` at the tag has SHA-256
`fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5`, identical to the 1.3.17 one) and
travels with the files in [`cen-1.3.16/LICENSE.txt`](cen-1.3.16/LICENSE.txt); the conditions listed
above apply unchanged.

| | |
|---|---|
| Release | [`validation-1.3.16`](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.16) |
| UBL archive | `en16931-ubl-1.3.16.zip`, SHA-256 `bafada015efbc5248bf5e05ad2191e1d9833ef96e9dd5f4bce420a747342da85` |
| CII archive | `en16931-cii-1.3.16.zip`, SHA-256 `1cd53cb8a84d38aedc82c0caede217da983a7934dd663f793a092fd66443c561` |
| UBL rules | `EN16931-UBL-validation-preprocessed.sch`, SHA-256 `268d4f7a2688676695e6c69cba6fba69a6802604fee12cb544a6b30ff09555a3` |
| CII rules | `EN16931-CII-validation-preprocessed.sch`, SHA-256 `54e0dc6d06cd7f17d268bb9696ff56f58d386ee28961c9bbef0a56718c400c89` |

Only `schematron/preprocessed/` and `examples/` of each archive are kept. The two `.sch` files
have the same SHA-256 as OpenPeppol's `CEN-EN16931-UBL.sch` and `CEN-EN16931-CII.sch` at
`peppol-bis-invoice-3` 3.0.22. Helpers: `Fixtures::cen1316($syntax)` (the preprocessed `.sch`) and
`Fixtures::cen1316Examples()` (both syntaxes, keyed `<syntax>/<file>`).

## XRechnung (KoSIT)

The files under `xrechnung/` are unmodified copies of the artefacts of the Coordination Office for
IT Standards (KoSIT), XRechnung 3.0.2. They are third-party material and are **not** covered by this
package's MIT licence. All repositories are licensed under **Apache-2.0**; the licence text sits
beside each part (`LICENSE`, and `NOTICE` where the repository has one), so the redistribution
conditions of Apache-2.0 section 4 are met: the licence travels with the files, nothing is modified,
and the KoSIT copyright notices stay in place. The validator jar (`itplr-kosit/validator` v1.6.3,
SHA-256 `799e64befca97d4080e03608c80b85dd5a5ecc5f4ae4f35d1116ec2855b9a7c9`) is listed in
`XRechnungSources` as the reference implementation of the conformance run but is never downloaded by
`bin/fetch-official.php` and never committed.

| Folder | Origin | Archive SHA-256 | Kept |
|---|---|---|---|
| `schematron-2.6.0/` | [`xrechnung-schematron`](https://github.com/itplr-kosit/xrechnung-schematron) release `v2.6.0`, `xrechnung-3.0.2-schematron-2.6.0.zip` | `ca5e07afd04e72cd283d581590ffff3a15f4aac9d2f40c993345d78e67ea22b4` | the zip's `schematron/` (`ubl/`, `cii/`, `common.sch`) and `LICENSE` |
| `configuration-2026-08-31/` | [`validator-configuration-xrechnung`](https://github.com/itplr-kosit/validator-configuration-xrechnung) release `v2026-08-31`, `xrechnung-3.0.2-validator-configuration-2026-08-31.zip` | `2530cd107c414511c5d0462ec10f886910395abfca820db82e83d70bf01221a8` | the whole zip (`scenarios.xml`, `resources/`, docs); `LICENSE` and `NOTICE` from the source archive below, because the zip has none |
| `testsuite-2026-08-31/` | [`xrechnung-testsuite`](https://github.com/itplr-kosit/xrechnung-testsuite) release `v2026-08-31`, `xrechnung-3.0.2-testsuite-2026-08-31.zip` | `1e81ef7563e0fa04c0e0b0631318a7e0843a3c0beb57d83ef95ac0c4a095e3d7` | `instances/` (86 files); `LICENSE` from the repository at the tag (SHA-256 `c71d239df91726fc519c6eb72d318ec65820627232b2f796219e87dcf35d0ab4`), because the zip has none |
| `unit-2.6.0/` | `xrechnung-schematron` source archive at `v2.6.0` (`https://codeload.github.com/itplr-kosit/xrechnung-schematron/tar.gz/refs/tags/v2.6.0`) | `895872383bd380c9edcbe4c4ff973645df68102e3a797b84cd6b80280a3dd7f9` | `test/instances/{ubl-inv,ubl-cn,cii}` (354 files) and `LICENSE` |
| `configuration-tests-2026-08-31/` | `validator-configuration-xrechnung` source archive at `v2026-08-31` (`https://codeload.github.com/itplr-kosit/validator-configuration-xrechnung/tar.gz/refs/tags/v2026-08-31`) | `e4a8f51f2c691a612fcf76f3ccadc5ea933da958e196dc732b35aa97623be9e1` | every `.xml` below `src/test/` (64 files: `instances/`, `integration/`, `cen-unit-test/`, `unexpected/`, `svrl/`, assertions) and `LICENSE` |

The two codeload archives hashed the same on two downloads (2026-11-13), so they are pinned by
archive checksum like the release assets. If GitHub ever changes their bytes, the fetch script
stops with a checksum mismatch.

Every file of the tree is listed with its SHA-256 in [`xrechnung.sha256`](xrechnung.sha256), which the
fetch script verifies and `FixturesTest` re-checks. The 504 XML instances (86 test suite, 354
Schematron unit tests, 64 configuration tests) are exposed, minus the two flagged ones (502), by `Fixtures::xrechnungCorpus()`,
names `<folder>/<relative path>`. Two files are flagged in the manifest and excluded: they
are the processing-error samples `instances/processing-error/bin001.xml` and `ubl007.xml` of the
configuration tests, which are not well-formed XML (`unparseable`; a file with a DOCTYPE would be
flagged `doctype`). They are listed by `Fixtures::xrechnungRejectedCorpus()`.

Other helpers: `Fixtures::xrechnungSchematron($syntax)` (the KoSIT `.sch` for `ubl` or `cii`) and
`Fixtures::xrechnungConfiguration()` (the folder with `scenarios.xml`). The test suite folder is
about 20 MB in total.

## Refreshing

```bash
php bin/fetch-official.php
```

The script downloads the two release archives, the repository archive, the licence text, the
UBL 2.1 package, the CII D16B schemas, the CEN 1.3.16 release archives, the KoSIT archives and the
Peppol rule files and repository archive, verifies each against the SHA-256 sums above (the CEN unit
tests file by file against `MANIFEST.sha256`, the XRechnung tree against `xrechnung.sha256`, the
Peppol corpus against `peppol-3.0.22.sha256`), and rebuilds `cen-1.3.17/`, `cen-1.3.16/`,
`xrechnung/`, `ubl-2.1/`, `cii-d16b/` and `../peppol-3.0.22/`. `php bin/fetch-official.php --write-manifest` recreates the
manifests from the archives instead of verifying against them; use it only when moving to a new
tag. CI runs `composer fetch-official` before the test suite.
Verified downloads are cached in the git-ignored `.cache/official/`, so repeated runs work
offline and produce the same files. It
needs the `zip` extension.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

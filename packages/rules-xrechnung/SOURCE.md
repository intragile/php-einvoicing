# Source of the compiled rules, scenario table and schemas

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Everything in this package is derived from, or copied from, two Apache-2.0 releases of the
Koordinierungsstelle für IT-Standards (KoSIT). The compiled files are distributed under the
[Apache License 2.0](LICENSE) with the attributions in [NOTICE](NOTICE). The XML Schemas in
`schemas/` are unmodified third-party files under their own OASIS and UN/CEFACT notices.

## Origin

| | Release | Archive | SHA-256 of the archive |
|---|---|---|---|
| XRechnung Schematron | [`v2.6.0`](https://github.com/itplr-kosit/xrechnung-schematron/releases/tag/v2.6.0) (XRechnung 3.0.2) | `xrechnung-3.0.2-schematron-2.6.0.zip` | `ca5e07afd04e72cd283d581590ffff3a15f4aac9d2f40c993345d78e67ea22b4` |
| KoSIT validator configuration for XRechnung | [`v2026-08-31`](https://github.com/itplr-kosit/validator-configuration-xrechnung/releases/tag/v2026-08-31) (XRechnung 3.0.2) | `xrechnung-3.0.2-validator-configuration-2026-08-31.zip` | `2530cd107c414511c5d0462ec10f886910395abfca820db82e83d70bf01221a8` |

[`LICENSE`](LICENSE) is the `LICENSE` file of the Schematron archive unchanged (SHA-256
`57e93bb611b8aeb93ff0f23b271a23291217b0016f6e66297dfd5e9467538a5f`): the Apache License 2.0
text, followed by KoSIT's note on the CEN and DIN licence of the XRechnung standard. The
configuration archive ships its own copy of the Apache License 2.0 and the `NOTICE` quoted in
[`NOTICE`](NOTICE).

## Compiled rule sets

| File | Source in the Schematron archive | SHA-256 of the source |
|---|---|---|
| `rules/xrechnung-ubl-2.6.0.php` | `schematron/ubl/XRechnung-UBL-validation.sch` | `e143facbe9e6a39cc89d68de0576e81806f21a64830b102250c706a524f35ee6` |
| `rules/xrechnung-cii-2.6.0.php` | `schematron/cii/XRechnung-CII-validation.sch` | `53bb5c0664fc603bbaaddeed26eb59525a05d3359d1a63f148876b2f2eedde1d` |
| both, through `<include href="../common.sch"/>` | `schematron/common.sch` | `7443d2f4c12d3e1ffa6b38f7f83cea89a9b03343f31f726083018d8985492a12` |

The UBL rule set has 6 patterns, 39 rules and 77 asserts; the CII rule set 7 patterns, 43 rules
and 74 asserts (both counts include the `variable-pattern` of `common.sch`). Each generated file
records the name and SHA-256 of its top-level `.sch` in its header comment and in its `source`
and `sha256` keys; the included `common.sch` is pinned by the table above.

### What was changed

- **Includes inlined:** the `include` of `common.sch` is replaced by the pattern it holds. The
  `let`s of that pattern become global variables, as in the XSLT KoSIT compiles from the same
  files, because the `xsl:function`s of the schemas use them.
- **Messages compiled:** every assert keeps its id, flag, original `context` and `test` text and
  its message with whitespace collapsed. The `name` and `value-of` elements inside a message are
  kept as message parts, evaluated when the assert fails, exactly where the upstream message has
  them; the XPath syntax tree of every context, test and `value-of` is added.
- **Dropped:** the schema `title` and the single `phase` (`xrechnung-model`), which activates every
  pattern; the engine runs all patterns, as the official XSLT does without a selected phase.

Nothing is reworded, and no rule is added, removed or changed.

## Scenario table

`rules/scenarios-2026-08-31.php` is compiled by
`IntrAgile\EInvoicing\Validation\XRechnung\ScenarioTableCompiler` from `scenarios.xml` of the
configuration archive (SHA-256 `3e8161a39dfaa6aea4d990b279aab848be4f4c6169e820f354a159bda7693fb0`).
It holds the 11 scenarios in configuration order, each with its name, namespace bindings and
`match` expression verbatim, its schema, its Schematron steps and its custom levels (56 in all).

### What was changed

- The stylesheets the configuration runs (`resources/…/EN16931-UBL-validation.xsl`,
  `EN16931-CII-validation.xsl`, `XRechnung-UBL-validation.xsl`, `XRechnung-CII-validation.xsl`)
  are replaced by the compiled rule sets built from the same releases: `en16931-ubl` and
  `en16931-cii` 1.3.16 from `intragile/einvoicing-rules-en16931`, and `xrechnung-ubl` and
  `xrechnung-cii` 2.6.0 from this package. Any other stylesheet stops the compiler.
- Schema locations lose their `resources/` prefix: they are relative to `schemas/`.
- Descriptions, the report stylesheets and `noScenarioReport` are left out; they do not decide a
  verdict. Every scenario must use the acceptance expression
  `/rep:report/rep:assessment[1]/rep:accept[1]`, which is therefore not stored. Any other element
  or acceptance expression stops the compiler, so a configuration update cannot drop a step
  silently.

## Schemas

The files in `schemas/` are byte-for-byte copies of the configuration archive's
`resources/ubl/2.1/xsd/` and `resources/cii/16b/xsd/` files, at the same paths relative to
`resources/`. Only the schemas the scenario table names and the files they import or include,
transitively, are copied (19 files): the UBL Invoice and CreditNote main documents with 13 common
modules, and the four CII D16B modules. They are KoSIT's copies (the UBL `xsdrt` run-time
schemas), which differ from the copies in this repository's test fixtures, so the two are not
shared.

| File | SHA-256 |
|---|---|
| `ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd` | `3a5aacd823f0e5b8f25ae7b5191c2002d5333ba351d87de9371ed62dca2b2b0c` |
| `ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd` | `1e117b6c1ab713604b29b0d685442a81a0c78a82575445ee8c43e6050cda7454` |
| `ubl/2.1/xsd/common/CCTS_CCT_SchemaModule-2.1.xsd` | `dd546e4809df86b6445589f69f0d6c9df162840ae386574ddfc1da7638103e15` |
| `ubl/2.1/xsd/common/UBL-CommonAggregateComponents-2.1.xsd` | `580b5af6f68f7f556bd15945ba0e819cbf561442c81694f9b9468036ebedec4d` |
| `ubl/2.1/xsd/common/UBL-CommonBasicComponents-2.1.xsd` | `a3b349bf92e5cff26e303d2f05b7e00c884dcafcfd9e12755fb80289abf5e22e` |
| `ubl/2.1/xsd/common/UBL-CommonExtensionComponents-2.1.xsd` | `ad7a4e490978adfbcfc5ec0bb20941cf11ac960ccf0c4de8791a7c731a8dbe87` |
| `ubl/2.1/xsd/common/UBL-CommonSignatureComponents-2.1.xsd` | `4fa9e2370100040fe14c43e135ef77e2eb66b21cb8dbfc2ffb8d82ae991fe92e` |
| `ubl/2.1/xsd/common/UBL-ExtensionContentDataType-2.1.xsd` | `fcee77a11870208e6377ea6311b9f2a050bca24bdad8606ea02d71e9f9e72f8d` |
| `ubl/2.1/xsd/common/UBL-QualifiedDataTypes-2.1.xsd` | `7dcb156e610239c97ae70940cf4653b88e48c3595bf5f56a2204a32e2893e6cf` |
| `ubl/2.1/xsd/common/UBL-SignatureAggregateComponents-2.1.xsd` | `9234c2ca48dbfa9a22a786112bb075c5922a305170920eaab1e3c04fa0b7344b` |
| `ubl/2.1/xsd/common/UBL-SignatureBasicComponents-2.1.xsd` | `0fbe2d7afff0c1e11164b8ec83e13f18801021c3c87e390a9d76f9cf862f6a64` |
| `ubl/2.1/xsd/common/UBL-UnqualifiedDataTypes-2.1.xsd` | `09052d406b4293e2a5f9c2bfee6df10ad4d8d5f0b36e24a6349d7f7936d89eb6` |
| `ubl/2.1/xsd/common/UBL-XAdESv132-2.1.xsd` | `a4f726bcf8cc3f7d9ffa4dab99e005535a8e8b60dced1e5d94578d2e05afa96e` |
| `ubl/2.1/xsd/common/UBL-XAdESv141-2.1.xsd` | `1fa4625e9cefcb7a9abb5ac1b64315547450031eece8a55bd584e4ba4b79dbc1` |
| `ubl/2.1/xsd/common/UBL-xmldsig-core-schema-2.1.xsd` | `101909c9f06456d61ddcc4fb982f1d40dc357b439f393b1a2eb46e42acd60809` |
| `cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd` | `3baf143b90f289d62c5dfb086aa51fa183490a2d0b38a40c6b31dcae0760f2ba` |
| `cii/16b/xsd/CrossIndustryInvoice_QualifiedDataType_100pD16B.xsd` | `6649215914728652e883662bec1d3eab008c215e86a59f3bb417db9723ee8c5c` |
| `cii/16b/xsd/CrossIndustryInvoice_ReusableAggregateBusinessInformationEntity_100pD16B.xsd` | `1c42244298a863bcc0b5196983ff0a5d084fcabe58aea77ef82b242e7cd0ddfd` |
| `cii/16b/xsd/CrossIndustryInvoice_UnqualifiedDataType_100pD16B.xsd` | `04fce6f6f28521886731294be18a4e6bb5cb3003456457d99ad5c4d3e652f497` |

### Licence of the schemas

The OASIS UBL 2.1 files carry "Copyright (c) OASIS Open 2013. All Rights Reserved."; the terms are
the "Notices" section of the UBL 2.1 specification. The UN/CEFACT files carry their copyright
notice and permission paragraph in their header. Both allow copying and distribution of the
documents provided that they are not modified and that the notice is included. This package
meets both conditions:

- **Unmodified:** the files are byte-for-byte copies, checked by `bin/compile-xrechnung.php
  --check` and by the test suite, and kept out of line-ending conversion by `.gitattributes`.
- **Notice included:** each file keeps its header, and [`NOTICE`](NOTICE) repeats the OASIS
  "Notices" section and the UN/CEFACT notice, and names the W3C XML Signature and ETSI XAdES
  schemas that OASIS copied into UBL.

## Regenerating

```bash
composer compile-rules                     # writes rules/*.php and copies schemas/
composer compile-rules -- --check          # exits 1 when a file is stale, missing or superfluous
```

Both run from the root of the `php-einvoicing` repository; `composer compile-rules` runs
`bin/compile-schematron.php` and `bin/compile-xrechnung.php`, and passes `--check` to both. The
sources are the committed KoSIT fixtures in `tests/fixtures/official/xrechnung/`.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

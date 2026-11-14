# Source of the compiled rules and schemas

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Everything in this package is derived from, or copied from, the Factur-X 1.09.2 (ZF_250)
technical artefacts of FeRD and FNFE-MPE, as shipped by the Mustang project. The files are
distributed under the [Apache License 2.0](LICENSE) with the attributions in [NOTICE](NOTICE).

## Origin

| | |
|---|---|
| Project | [ZUGFeRD/mustangproject](https://github.com/ZUGFeRD/mustangproject) |
| Commit | [`c766bdd2f1b978c329157affc020eba4bcf51688`](https://github.com/ZUGFeRD/mustangproject/tree/c766bdd2f1b978c329157affc020eba4bcf51688) |
| Release metadata in the files | Factur-X 1.09.2, released 2026-08-04, effective 2026-09-01 |
| Schemas | `validator/src/main/resources/schema/ZF_250`, 20 files, aggregate SHA-256 `a66ac39854336a759c27a9289319146c4889c1e4acfb5d3e33ea1cc0bcfeba55` |
| Schematron | `validator/src/main/resources/schematron/ZF_250`, 10 files, aggregate SHA-256 `84b15df7dbddde67134d073dcc0bfc69ed96b11c97997da5f542f44039899184` |
| `LICENSE` | the Apache License 2.0 text of the repository `LICENSE` (SHA-256 `ba98dd76bda1e82034791438bb931145f85e5db3975852a015344ad5990fa21b`), without its "EXTERNAL COMPONENTS" section |
| `NOTICE` | the repository `NOTICE` (SHA-256 `9225d07c21aa4a404ac53372f04c9945b8e1e0d47f466036d27a09527373c6cf`) with the FeRD and FNFE-MPE attribution added, followed by the "EXTERNAL COMPONENTS" section of the repository `LICENSE`, unchanged |

The aggregate is `find . -type f -print0 | LC_ALL=C sort -z | xargs -0 shasum -a 256 | shasum -a 256`,
run inside the repository folder. The sources are also committed in the `php-einvoicing`
repository under `tests/fixtures/official/facturx-1.09.2/`.

## Compiled rule sets

Each profile has one Schematron, `FACTUR-X_<profile>.sch`, and a code database
`FACTUR-X_<profile>_codedb.xml` that the Schematron reads through `document()`.

| File | Source `.sch` | SHA-256 of the `.sch` | SHA-256 of the code database | Asserts | Reports |
|---|---|---|---|---|---|
| `rules/facturx-minimum-1.09.2.php` | `FACTUR-X_MINIMUM.sch` | `c570a628498e0b633a3dd66dd745653a8a2bc66fc6ee346b1971b9dc2d5f6778` | `5c575cf37533a12a758f08b6b9691841c1691f2af214f21e040a07ec892e1f20` | 55 | 11 |
| `rules/facturx-basicwl-1.09.2.php` | `FACTUR-X_BASIC-WL.sch` | `c32dd6eb4ef9c3e88772726ff86aca796476b719152edd6d95aa96ddb17a63f0` | `a34cd66dda00a9260368fa02dad97e966ded1c1ad100787a5318074328d20abb` | 278 | 59 |
| `rules/facturx-basic-1.09.2.php` | `FACTUR-X_BASIC.sch` | `b2a84cee0e497733bf595c435db18ba720a9f2e16f6741a972667a2f277f6bed` | `a150b48359a72e1c1629edddb147d5133aabd03adb6ce8b3d287d0c2a6936d21` | 385 | 87 |
| `rules/facturx-en16931-1.09.2.php` | `FACTUR-X_EN16931.sch` | `ef8c8380b499d1267b2dcf1fa709b45631a77d6d5f220c6cc2ade2fdade59473` | `70339a41d71b02bb97683f8eea04acbd764e077a88a446b32860d89c3351b62f` | 433 | 194 |
| `rules/facturx-extended-1.09.2.php` | `FACTUR-X_EXTENDED.sch` | `5b9039e31f86e90f1a34925e84d3fad206efa3ca5639bb98a0cefd3661d3e36a` | `6e23aedbba0e3b32b3b1211d145186193215f56f755201555306c42327e75fbc` | 943 | 521 |

Each generated file records the name and SHA-256 of its `.sch` in its header comment and in its
`source` and `sha256` keys, and the SHA-256 of the embedded code database in `documentSha256`.

### What was changed

The `.sch` files are compiled, not shipped.

- **Messages compiled:** every assert and report keeps its id, flag, original `context` and `test`
  text and its message with whitespace collapsed; the XPath syntax tree of every context, test and
  `value-of` is added.
- **Reports:** a `report` compiles like an assert that fires when its test is true.
- **Missing flags:** an assert or report without a `flag` is compiled as `fatal`, which is what the
  official XSLT reports for it.
- **Missing ids:** an assert or report without an `id` gets the synthesized id
  `FX-REPORT-<first 10 hex digits of SHA-256(rule context, newline, test)>`; the ids are unique
  within each profile.
- **Code database embedded:** the code database each Schematron opens with `document()` is stored
  in the compiled file under `documents`, as written, so no file is read at validation time.

Nothing is reworded, and no rule is added, removed or changed.

## Schemas

`schemas/<profile>/` holds the profile's `FACTUR-X_<profile>.xsd` (the BASIC WL files are named
`FACTUR-X_BASICWL*`) and the three modules it imports, 20 files in all. They are byte-for-byte
copies of `schema/ZF_250/<profile>/` and carry no licence header of their own; the Apache 2.0
licence and the notices in [NOTICE](NOTICE) travel with them. The aggregate SHA-256 of
`schemas/` equals the aggregate of the source folder given above.

## Licence

FeRD (Forum elektronische Rechnung Deutschland, the publisher of ZUGFeRD) states on its
"Disclaimer and rights of use ZUGFeRD" page
(<https://www.ferd-net.de/en/ueber-uns/ressourcen-1/veroeffentlichungen/disclaimer-and-rights-of-use-zugferd>,
retrieved 2026-11-13):

> For the use of the ZUGFeRD Technical Artifacts (Schemata and Schematron) the Apache 2.0 license
> conditions apply. These can be viewed at https://www.apache.org/licenses/LICENSE-2.0.

The German page "Haftungsausschluss und Nutzungsrechte"
(<https://www.ferd-net.de/ueber-uns/ressourcen-1/veroeffentlichungen/haftungsausschluss-und-nutzungsrechte>,
retrieved 2026-11-13) says the same:

> Für die Nutzung der Technischen Artefakte (Schemata und Schematron) des ZUGFeRD-Standards gelten
> die Apache 2.0 Lizenzbedingungen. Diese können unter dem Link
> https://www.apache.org/licenses/LICENSE-2.0 eingesehen werden.

The XSD files are the "Schemata"; the `.sch` files and their code databases are the "Schematron".
Redistribution follows Apache-2.0 section 4: the licence and notice travel with the package, the
XSDs are unmodified, and the changes to the Schematron are listed above.

## Regenerating

```bash
composer compile-rules                     # writes rules/*.php and copies schemas/
composer compile-rules -- --check          # exits 1 when a file is stale, missing or superfluous
```

Both run from the root of the `php-einvoicing` repository; `composer compile-rules` runs
`bin/compile-facturx.php` after the other compile scripts and passes `--check` to all of them.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

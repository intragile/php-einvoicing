# Factur-X Rules for PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The Factur-X 1.09.2 (ZUGFeRD) Schematron of the five profiles MINIMUM, BASIC WL, BASIC, EN 16931
and EXTENDED compiled to PHP arrays, and the profile XML Schemas, for the Factur-X validator of
[`intragile/php-einvoicing`](https://github.com/intragile/php-einvoicing). Install it with
`composer require intragile/einvoicing-rules-facturx` to validate Factur-X and ZUGFeRD the way
the official Schematron does.

`IntrAgile\EInvoicing\Rules\FacturX\RuleFiles` returns the paths: `RuleFiles::rules($profile)`
for `rules/facturx-<profile>-1.09.2.php` and `RuleFiles::schema($profile)` for
`schemas/<profile>/FACTUR-X_<profile>.xsd`, with `$profile` one of the names in
`RuleFiles::profiles()`.

| Profile | Asserts | Reports |
|---|---|---|
| MINIMUM | 55 | 11 |
| BASIC-WL | 278 | 59 |
| BASIC | 385 | 87 |
| EN16931 | 433 | 194 |
| EXTENDED | 943 | 521 |

Each compiled file embeds the code database its Schematron reads through `document()`, so the
files are large (EXTENDED is about 12 MB) and are loaded only for the profile in use.

## Licence

The package is licensed under the [Apache License 2.0](LICENSE). FeRD, the publisher of ZUGFeRD,
states that the Apache 2.0 conditions apply to the ZUGFeRD technical artefacts (Schemata and
Schematron); the attributions are in [NOTICE](NOTICE). Origin, checksums and what was changed are
in [SOURCE.md](SOURCE.md): the XSDs are unmodified, the Schematron is compiled, not shipped.

## Regenerating

Every file is generated or copied by `bin/compile-facturx.php` in the `php-einvoicing`
repository and must not be edited by hand:

```bash
composer compile-rules                     # writes rules/*.php and copies schemas/
composer compile-rules -- --check          # exits 1 when a file is stale, missing or superfluous
```

## Versioning

The five packages, `intragile/php-einvoicing`, the rule packages
`intragile/einvoicing-rules-en16931`, `intragile/einvoicing-rules-xrechnung` and
`intragile/einvoicing-rules-facturx`, and `intragile/laravel-einvoicing`, are released together
with the same version number and follow Semantic Versioning. Use the same minor version of each.
A rule package declares a Composer `conflict` with cores older than its own minor version (and
with the next major), so new rules are never installed next to an engine that cannot run them.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

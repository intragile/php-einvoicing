# XRechnung Rules for PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The KoSIT XRechnung Schematron 2.6.0 (UBL and CII) compiled to PHP arrays, the scenario table of
the KoSIT validator configuration for XRechnung 2026-08-31, and the UBL 2.1 and CII D16B XML
Schemas that table names, for the XRechnung validator of
[`intragile/php-einvoicing`](https://github.com/intragile/php-einvoicing): install it with
`composer require intragile/einvoicing-rules-xrechnung` to validate XRechnung the way the KoSIT
validator does. `IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles` returns the paths of
`rules/xrechnung-ubl-2.6.0.php` (77 asserts), `rules/xrechnung-cii-2.6.0.php` (74 asserts),
`rules/scenarios-2026-08-31.php` (11 scenarios) and `schemas/`. The package is licensed under
the [Apache License 2.0](LICENSE), with the attributions in [NOTICE](NOTICE); the schemas keep
their OASIS and UN/CEFACT notices. Every file is generated or copied by
`bin/compile-xrechnung.php` in the `php-einvoicing` repository (`composer compile-rules`) and must
not be edited by hand; origin, checksums and what was changed are in [SOURCE.md](SOURCE.md).

## Versioning

The five packages, `intragile/php-einvoicing`, the rule packages
`intragile/einvoicing-rules-en16931`, `intragile/einvoicing-rules-xrechnung` and
`intragile/einvoicing-rules-facturx`, and `intragile/laravel-einvoicing`, are released together
with the same version number and follow Semantic Versioning. Use the same minor version of each.
A rule package declares a Composer `conflict` with cores older than its own minor version (and
with the next major), so new rules are never installed next to an engine that cannot run them.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

# EN 16931 Rules for PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The official CEN EN 16931 validation rules for UBL 2.1 and UN/CEFACT CII (validation artefacts
1.3.17 and 1.3.16), compiled to PHP arrays. The rule engine that runs them is
[`intragile/php-einvoicing`](https://github.com/intragile/php-einvoicing), which requires this
package; you do not need to install it yourself.

## Licence: EUPL-1.2

The rules are a derivative of the CEN/TC 434 validation artefacts, which are licensed under the
[European Union Public Licence v1.2](LICENSE). This package is therefore distributed under
**EUPL-1.2**, not under the MIT licence of the engine. Origin, checksums and the licence
conditions are documented in [SOURCE.md](SOURCE.md).

## Contents

| File | Rule set |
|---|---|
| `rules/en16931-ubl-1.3.17.php` | EN 16931 bound to UBL 2.1: 3 patterns, 104 rules, 982 asserts |
| `rules/en16931-cii-1.3.17.php` | EN 16931 bound to CII D16B: 3 patterns, 115 rules, 810 asserts |
| `rules/en16931-ubl-1.3.16.php` | EN 16931 bound to UBL 2.1: 3 patterns, 104 rules, 979 asserts |
| `rules/en16931-cii-1.3.16.php` | EN 16931 bound to CII D16B: 3 patterns, 113 rules, 806 asserts |

Each file returns one array: the rule set's id and version, the source file and its SHA-256, the
namespace bindings, and every pattern, rule and assert with its original XPath text and its parsed
syntax tree. `IntrAgile\EInvoicing\Rules\En16931\RuleFiles::ubl()` and `::cii()` return their
absolute paths; both take an optional version (`RuleFiles::VERSIONS`) and default to
`RuleFiles::VERSION`, 1.3.17. An unknown version throws an `InvalidArgumentException`.

## Why two releases

1.3.17 is the current CEN release and the default: `Validator::en16931()` uses it. The KoSIT
XRechnung validator configuration, the reference for German XRechnung validation, pins 1.3.16, so
that build is shipped as well. Validating against KoSIT's exact rule version keeps the verdicts
identical to KoSIT's. The two releases differ in the rules of VAT categories "IGIC" and "IPSI"
(1.3.16 identifies them as `BR-AF-*` and `BR-AG-*`, 1.3.17 as `BR-IG-*` and `BR-IP-*`) and in a few
syntax rules (`UBL-SR-57` to `UBL-SR-59`, `CII-SR-495` to `CII-SR-497`, `CII-DT-105`, `CII-DT-106`
are new in 1.3.17; `CII-SR-471` was removed).

## Generated, do not edit

The files in `rules/` are generated from the official preprocessed Schematron by
`bin/compile-schematron.php` in the `php-einvoicing` repository (`composer compile-rules`). Edit
nothing by hand: a change belongs upstream or in the compiler, and CI fails when a generated file
differs from what the compiler writes.

## Versioning

The five packages, `intragile/php-einvoicing`, the rule packages
`intragile/einvoicing-rules-en16931`, `intragile/einvoicing-rules-xrechnung` and
`intragile/einvoicing-rules-facturx`, and `intragile/laravel-einvoicing`, are released together
with the same version number and follow Semantic Versioning. Use the same minor version of each.
A rule package declares a Composer `conflict` with cores older than its own minor version (and
with the next major), so new rules are never installed next to an engine that cannot run them.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

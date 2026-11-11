# EN 16931 Rules for PHP E-Invoicing

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The official CEN EN 16931 validation rules for UBL 2.1 and UN/CEFACT CII (validation artefacts
1.3.17), compiled to PHP arrays. The rule engine that runs them is
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

Each file returns one array: the rule set's id and version, the source file and its SHA-256, the
namespace bindings, and every pattern, rule and assert with its original XPath text and its parsed
syntax tree. `IntrAgile\EInvoicing\Rules\En16931\RuleFiles::ubl()` and `::cii()` return their
absolute paths.

## Generated, do not edit

The files in `rules/` are generated from the official preprocessed Schematron by
`bin/compile-schematron.php` in the `php-einvoicing` repository (`composer compile-rules`). Edit
nothing by hand: a change belongs upstream or in the compiler, and CI fails when a generated file
differs from what the compiler writes.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

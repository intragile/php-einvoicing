# Validation

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This page explains how `IntrAgile\EInvoicing\Validation\Validator` checks an invoice against the
official CEN EN 16931 business rules: what it runs, which XPath it understands, where it follows
Saxon's behaviour, how its verdicts are checked against the official validator, and how fast it
is.

## How it works

CEN/TC 434 publishes the EN 16931 rules as Schematron (validation artefacts
[1.3.17](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17)),
plus XSLT 2.0 stylesheets compiled from it. Running those stylesheets needs an XSLT 2.0 processor,
which in practice means Java (Saxon). This library runs the same rules in plain PHP instead:

1. **Build time.** `bin/compile-schematron.php` reads the official *preprocessed* Schematron (all
   includes and abstract patterns already resolved), parses every rule context and assert test
   into a syntax tree with namespace prefixes resolved, and writes one PHP file per syntax that
   returns those arrays. The files ship in the separate package `intragile/einvoicing-rules-en16931`
   (see [Licences](#licences)). At runtime nothing is parsed, and with opcache the arrays are
   shared memory. Each file records the format of its array; a file written by an engine with
   another format is refused with `UnsupportedSchematron` asking to recompile it.
2. **Run time.** The runner applies the semantics of the ISO Schematron XSLT skeleton: every
   pattern visits the document node and every element and attribute in document order, a node is
   handled by the first rule of the pattern whose context matches it, and every assert of that rule
   is evaluated with the node as context item. All patterns run (no phase is selected, as in the
   official XSLT).
3. **Report.** `Validator` loads the XML safely (a DOCTYPE is refused with `UnsafeXml`), detects
   UBL Invoice, UBL CreditNote or CII from the document element, runs the matching rule set and
   returns a `ValidationReport`: the rule sets that ran (`ruleSets`, as `"<id> <version>"`), the
   failed asserts (`findings`, each an `IntrAgile\EInvoicing\Validation\Finding`: rule id,
   `Severity`, location, message, test; filtered by `fatal()`, `warnings()` and `information()`)
   and the asserts that could not be evaluated (`dynamicErrors`, each a `DynamicError`). The
   classes under `IntrAgile\EInvoicing\Schematron` and `IntrAgile\EInvoicing\XPath` are
   internal; only their exceptions (`UnsupportedSchematron`, `UnsupportedXPath`,
   `XPathSyntaxError`) are meant to be caught.

| Rule set | Patterns | Rules | Asserts | Fatal | Warning |
|---|---|---|---|---|---|
| `en16931-ubl` 1.3.17 | 3 | 104 | 982 | 284 | 698 |
| `en16931-cii` 1.3.17 | 3 | 115 | 810 | 339 | 471 |

`isValid()` is true only when no fatal assert failed and no assert raised a dynamic error.
Warnings do not make an invoice invalid.

A document the rule set does not cover is refused, never reported as valid: an unknown document
element (`<Order>`, or anything other than the three invoice roots) or a document element that no
rule of the selected rule set handles throws `UnsupportedDocument`; so does input that is empty or
not well-formed XML.

## Supported XPath

The engine implements the XPath 2.0 subset the CEN rule sets use (measured on 1.3.17), and fails
loudly on anything else: the compiler throws `UnsupportedSchematron` naming the assert, so a new
rule set that needs more cannot be compiled into silently wrong rules.

| Area | Supported |
|---|---|
| Functions | `not`, `normalize-space`, `exists`, `empty`, `xs:decimal`, `xs:date`, `upper-case`, `count`, `sum`, `true`, `false`, `boolean`, `string`, `contains`, `string-length`, `substring`, `substring-after`, `substring-before`, `ends-with`, `round`, `concat`, `abs`, `name`, `local-name`, `distinct-values`, `matches` (XPath 2.0 arities, no collation arguments) |
| Operators | `or`, `and`, general comparisons `= != < <= > >=`, value comparisons `eq ne lt le gt ge`, `+ - * div idiv mod`, unary `-`, `\|` / `union`, `every … in … satisfies`, `some … in … satisfies`, `for … in … return` (several bindings nest left to right), `if (…) then … else …`, ranges `a to b`, `cast as` and `castable as` with `xs:boolean` / `xs:date` / `xs:decimal` / `xs:integer` / `xs:string` (with optional `?`) |
| Paths | axes `child`, `self`, `attribute`, `parent`, `ancestor`, `ancestor-or-self`, `descendant`, `descendant-or-self`, `preceding`, `following-sibling`, `preceding-sibling`; abbreviations `@`, `..`, `.`, `//`, `*`, `prefix:*`; kind tests `node()` and `text()`; expressions as path steps (`…/xs:decimal(cbc:Amount)`, `…/normalize-space(cbc:ID)`) |
| Predicates | boolean and numeric (positional) predicates on steps and on parenthesised expressions, applied in axis order (`ancestor::*[1]` is the nearest ancestor, `preceding-sibling::*[1]` the nearest preceding sibling) |
| Variables and user functions | `$name` only when declared by the caller or bound by an enclosing `for`, `some` or `every` (otherwise `UnsupportedXPath` naming `XPST0008` at parse time); calls to functions in a namespace other than `fn` and `xs` only when declared with their arity (the engine does not yet evaluate them: such a call raises `UnsupportedXPath` until `xsl:function` is supported) |
| Rule contexts | XSLT match patterns: `N`, `N/N`, `//N`, `/N/N/N`, `N[@attr]`, predicates with function calls, unions |
| Regular expressions | XML Schema syntax with anchors, back-references and reluctant quantifiers; flags `i s m x`; `\w`, `\s`, `\d` and categories with XML Schema meaning |

Rejected with `UnsupportedXPath` (in the compiler: `UnsupportedSchematron`): `let`,
`typeswitch`, `instance of`, `treat as`, `intersect`, `except`, `is`, `<<`, `>>`, unary `+`,
casts to other types, other axes, `*:local`, other kind tests, unknown functions or arities, unknown
prefixes, and in regular expressions `\i`, `\c`, `\I`, `\C`, `\p{IsBlock}` and character class
subtraction. Schematron `report`, `let`, `value-of`, processing instructions, abstract rules and
abstract patterns are rejected by the compiler as well, and so is any attribute the CEN and Peppol
schemas do not use (such as `pattern/@documents`, `rule/@role` or `assert/@diagnostics`): the error
names the element, the attribute and the line. Peppol BIS 3 needs `let` and `xsl:function`; the engine will be
extended for it.

## Where the engine follows Saxon

XPath 2.0 leaves some behaviour to the processor, and Saxon-HE is what the official stylesheets
run on. Where the two could differ, the engine mirrors SaxonC-HE 13.1.1, and each case is pinned by
probe expressions evaluated by both (`tests/XPath/ProbeCases.php`):

- **Untyped values.** Text from the document compared with a number is promoted to `xs:double`;
  untyped `'9' < '10'` compares as strings (false); untyped plus an integer gives a double.
- **Decimal casts.** `xs:decimal('25')` of a short whole number (no `.`, at most 15 characters) is
  typed `xs:integer`, as in Saxon. Only the type label differs; it matters for division scale.
- **Decimal division.** `a div b` on decimals keeps `max(18, s(a) − s(b) + 18)` fraction digits and
  rounds half-down, where `s()` is the scale with trailing zeros stripped (it can be negative).
  `1 div 3` = `0.333333333333333333`.
- **Doubles** print with at least two significant digits (`4.9E-324`). `round()` rounds half
  towards positive infinity and keeps the type.
- **String functions.** `normalize-space` strips only space, tab, CR and LF (a no-break space
  stays); `upper-case` uses full Unicode case mapping (`ß` becomes `SS`); `string-length` and
  `substring` count code points. Names match by namespace URI, never by prefix.
- **Type errors.** `'abc' + 1` is `XPTY0004` (a string is never promoted in arithmetic), while an
  untyped `abc` from the document plus 1 is `FORG0001`. A function or cast that expects one item
  and gets two (a repeated `cbc:Percent`) is `XPTY0004`, not the first item.
- **General comparisons** are evaluated pair by pair in order: `('abc','1') = 1` raises
  `FORG0001`, `('1','abc') = 1` is true.
- **`and` / `or` with an error in one operand.** Saxon raises an operand's error only when the other
  operand does not decide the result (false for `and`, true for `or`), in either operand order.
  When both operands fail, the right operand's error wins. The engine does the same.
- **Errors while matching a rule context** are recoverable, as in XSLT 2.0: when a rule-context
  predicate raises an error for a node, that node does not match the rule (a later rule of the same
  pattern may take it), other nodes are unaffected, and no error is reported. This applies only to
  data-driven errors (`FORG0001`, `XPTY0004`, `FOAR0001`, `XPTY0019`, … — `XPathError::RECOVERABLE`); an unbound
  variable, an engine guard or an unsupported construct still propagates.
- **`idiv` and `mod`.** `idiv` truncates towards zero and returns an `xs:integer` (`-7 idiv 2` =
  `-3`); `mod` keeps the sign of the dividend (`-7 mod 2` = `-1`), is exact on decimals and uses
  IEEE `fmod` on doubles. A zero integer or decimal divisor is `FOAR0001`, as is `idiv` by a double
  zero; `5e0 mod 0` is `NaN`.
- **`castable as`** is false, not a type error, when the operand atomizes to more than one item;
  `'1.0' castable as xs:integer` is false while `1.5 castable as xs:integer` is true (a decimal
  value is truncated, a decimal lexical form is not an integer).
- **Ranges.** `a to b` takes at most one `xs:integer` per operand (an untyped value is cast,
  `1.5 to 3` is `XPTY0004`); an empty operand or `a > b` gives `()`. A range of more than
  100,000 items raises the engine error `INTRAGILE_RANGE` before any item is built, instead of
  exhausting memory (Saxon evaluates ranges lazily; the engine builds them as a list).
- **`if` and `for`.** Only the branch chosen by the condition is evaluated, so an error in the
  other branch never surfaces; `for` returns its results in iteration order, without sorting nodes.
- **Dates.** A date without timezone is compared as UTC (the implicit timezone of the oracle).

## Dynamic errors

A received file can contain values that make an assert impossible to evaluate: `12,50` or `abc` as
an amount, an empty amount, `2026-13-01` as a date, a singleton element given twice. Saxon then
aborts the whole run with an error code and produces no report. The engine does not abort:

- the assert is recorded in `dynamicErrors` as a `DynamicError` with the error code (`FORG0001`, `XPTY0004`, …), the
  rule id and the location of the context node;
- every other assert and rule is still evaluated, so the report also lists the other findings;
- `isValid()` is false.

So the engine never crashes on such input and never calls it valid. On a document that makes Saxon
abort, the report carries the same error code as Saxon, plus the findings of the rules that could
still be evaluated.

## How verdict equality is checked

The test group `oracle` runs the official CEN XSLT through SaxonC-HE in Docker as the judge
(`docker/oracle/run.sh 8.3|8.4|8.5`). A verdict is the sorted set of distinct `"<rule id> <flag>"`
strings of a document; locations and message text are not compared. Per document:

- if Saxon returns a report, our verdict must be identical and we must report no dynamic error;
- if Saxon aborts with a dynamic error, we must report a dynamic error with the same code.

The corpus (CEN 1.3.17):

| Documents | Count |
|---|---|
| Official CEN example invoices (UBL and CII) | 34 |
| CEN unit-test documents (`test/` at the release tag, VEFA test sets expanded) | 1,169 |
| Seeded mutations: 20 per example (remove, duplicate, empty, `abc` amount, amount + 0.01, `ZZZ` code, currency swap, start/end date swap, space and comment before a value) | 680 |
| Model invoices written by this library in UBL and CII | 6 |
| Documents whose rule-context predicates fail (recoverable context errors) | 3 |

That is 1,892 documents on which the verdicts are checked equal; this is a test corpus, not a
proof for every possible input. Of the 680 mutations, Saxon aborts on 135 (102 `FORG0001`, 32
`XPTY0004`, 1 `FORG0006`); on those only the XPath error code is compared, not the findings. On
PHP 8.3, 8.4 and 8.5 the whole oracle group, including 245 single expression probes and the
wrapper guard below, is 4,071 tests; any mismatch fails the build.

The SaxonC PHP extension gives no error code for a dynamic error and cannot run another
transformation after one. The oracle therefore wraps the official stylesheet in a test-only XSLT
3.0 stylesheet that imports it unchanged and catches the error code with `xsl:try` around
`xsl:apply-imports`. A guard test checks that the wrapped and the plain official stylesheet give
the same verdict on every document of the corpus Saxon does not abort on (1,757 documents).

## Regenerating the rules for a new CEN release

1. Update the release tag and the pinned SHA-256 sums in `bin/fetch-official.php`, then run
   `php bin/fetch-official.php --write-manifest` once. It downloads the artefacts, verifies them,
   unpacks them into `tests/fixtures/official/` and records the new unit-test files; later runs
   (`composer fetch-official`) verify against that manifest.
2. Update the version and source directory in `bin/compile-schematron.php`, `RuleFiles::VERSION`
   in the rules package and the fixture paths in `tests/Fixtures.php`, then run
   `composer compile-rules`. If the new rules use XPath or Schematron the engine does not
   support, this step fails and names the assert.
3. Run the oracle group: `docker/oracle/run.sh 8.5` (Docker needed). Every difference is an engine
   defect to fix in the engine, never in the generated files or by special-casing a rule id.
4. Update `packages/rules-en16931/SOURCE.md` (origin, checksums, modification notice). The code
   lists in `src/CodeList/` come from the same release and are regenerated separately by
   `bin/generate-codelists.php`.

The generated files are never edited by hand; CI runs `php bin/compile-schematron.php --check` and
fails when a generated file differs from what the compiler writes.

## Licences

- The engine (`src/`, package `intragile/php-einvoicing`) is MIT licensed.
- The compiled rules are a derivative of the CEN validation artefacts, which are licensed under the
  EUPL-1.2. They live in `packages/rules-en16931/` (package `intragile/einvoicing-rules-en16931`)
  under the EUPL-1.2; origin, checksums and the licence conditions are in its
  [SOURCE.md](../packages/rules-en16931/SOURCE.md). The engine requires that package, so an
  application using the validator ships EUPL-1.2 material in its vendor directory.

Neither package is published yet. Before the first release the rules package moves to its own
repository.

## Performance

Measured on the development machine (Apple M1, PHP 8.5.0 CLI, opcache off), one `Validator`
reused, 20 runs per document after one warm-up run:

| Documents | Median | p95 |
|---|---|---|
| All 34 CEN examples (680 runs) | 15.1 ms | 46.8 ms |
| Slowest example (`ubl-tc434-example10.xml`, 21 KB) | 47.3 ms | 48.4 ms |
| Fastest example (`CII_example9.xml`, 8 KB) | 7.1 ms | 7.2 ms |

Time depends on the number of nodes the rules visit, not on file size: the 3.3 MB Italian example,
which is mostly an embedded attachment, takes 15 ms.

Large invoices (UBL, generated lines, same machine):

| Lines | Median |
|---|---|
| 1,000 (590 KB, 7 runs) | 1.71 s (1.69–1.73 s) |
| 10,000 (5.9 MB, 3 runs) | 20.0 s (18.7–20.3 s), process peak memory 130 MB |

For these invoices time grows roughly linearly with the number of lines (ten times the lines took
11.7 times as long; see [Resource use](#resource-use) for a rule that does not scale this way); the
test suite asserts that 1,000 lines take less than 15 times as long as 100 lines and less than
5 seconds. These are single measurements on one
machine, not a guarantee.

## Resource use

Most rules cost time in proportion to the size of the invoice, but not all. UBL-SR-47,
`count(//cbc:PaymentMeansCode[not(preceding::cbc:PaymentMeansCode/. = .)])`, compares every payment
means code with all codes before it, so its time is quadratic in their number: 1,000 codes took
0.83 s and 4,000 codes 7.7 s (single measurement on the development machine). Saxon evaluates the
official XSLT with the same shape. An attacker can therefore make a small file expensive. For
untrusted inbound files, limit the input size before validating (an invoice of a few megabytes is
large) and run validation with a time limit, for example in a queue worker with a timeout.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

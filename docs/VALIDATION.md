# Validation

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This page explains how `IntrAgile\EInvoicing\Validation\Validator` checks an invoice against the
official CEN EN 16931 business rules: what it runs, which XPath it understands, where it follows
Saxon's behaviour, how its verdicts are checked against the official validator, and how fast it
is. The same engine runs the Peppol BIS Billing 3 rules; installing and using them is described
in [PEPPOL.md](PEPPOL.md).

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
   shared memory. Each file records the format of its array. The compiler writes format 3; the
   engine also reads format 2, which format 3 only extends (with the message parts of
   [Messages](#schematron-subset)), so rule sets installed by an earlier release keep working. A
   file of any other format is refused with `UnsupportedSchematron` asking to recompile it.
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
   and the asserts that could not be evaluated (`dynamicErrors`, each a `DynamicError`).
   `verdict()` gives the sorted distinct `"<rule id> <flag>"` strings the oracle compares. The
   classes under `IntrAgile\EInvoicing\Schematron` and `IntrAgile\EInvoicing\XPath` are
   internal, apart from `RuleSet` (it is passed to the `Validator` constructor) and the
   exceptions (`UnsupportedSchematron`, `UnsupportedXPath`, `XPathSyntaxError`), which are meant
   to be caught. The Peppol installer adds `RulesNotInstalled` and `RuleSourceMismatch` in
   `IntrAgile\EInvoicing\Validation\Exception`.

| Rule set | Patterns | Rules | Asserts | Fatal | Warning |
|---|---|---|---|---|---|
| `en16931-ubl` 1.3.17 | 3 | 104 | 982 | 284 | 698 |
| `en16931-cii` 1.3.17 | 3 | 115 | 810 | 339 | 471 |
| `peppol-cen-ubl` 3.0.22 (OpenPeppol's copy of CEN 1.3.16) | 3 | 104 | 979 | 281 | 698 |
| `peppol-ubl` 3.0.22 | 13 | 99 | 165 | 139 | 26 |
| `peppol-cen-cii` 3.0.22 (OpenPeppol's copy of CEN 1.3.16) | 3 | 113 | 806 | 335 | 471 |
| `peppol-cii` 3.0.22 | 7 | 63 | 91 | 74 | 17 |

`Validator::en16931()` runs one rule set per syntax. `Validator::peppol($rulesDir)` runs two per
syntax, the CEN layer first, and the report concatenates their `ruleSets`, findings and dynamic
errors in that order; `UnsupportedDocument` depends only on the first rule set. The constructor
takes, per syntax, one `IntrAgile\EInvoicing\Schematron\RuleSet` or a list of them; a compiled rule
file (as `composer compile-rules` or `einvoicing-rules install` writes it) is loaded with
`RuleSet::fromFile($path)`, which caches each file per path for the life of the process. `isValid()`
is true only when no fatal assert failed, no assert raised a dynamic error and no XML Schema error was reported. Warnings do not make
an invoice invalid.

A document the rule set does not cover is refused, never reported as valid: an unknown document
element (`<Order>`, or anything other than the three invoice roots) or a document element that no
rule of the selected rule set handles throws `UnsupportedDocument`; so does input that is empty or
not well-formed XML.

## Supported XPath

The engine implements the XPath 2.0 subset the CEN rule sets (measured on 1.3.17) and the Peppol BIS
Billing 3.0.22 rule sets use, and fails loudly on anything else: the compiler throws
`UnsupportedSchematron` naming the assert, so a new rule set that needs more cannot be compiled into
silently wrong rules.

| Area | Supported |
|---|---|
| Functions | `not`, `normalize-space`, `exists`, `empty`, `xs:decimal`, `xs:date`, `upper-case`, `count`, `sum`, `true`, `false`, `boolean`, `string`, `contains`, `string-length`, `substring`, `substring-after`, `substring-before`, `ends-with`, `round`, `concat`, `abs`, `name`, `local-name`, `distinct-values`, `matches`, `number`, `string-to-codepoints`, `codepoints-to-string`, `reverse`, `translate`, `tokenize`, `replace`, `string-join`, `starts-with`, `last`, `xs:integer`, `xs:string` (XPath 2.0 arities, no collation arguments; `string-join` joins any atomic values, as Saxon does; `last()` only inside a predicate or a path step) |
| Operators | `or`, `and`, general comparisons `= != < <= > >=`, value comparisons `eq ne lt le gt ge`, `+ - * div idiv mod`, unary `-`, `\|` / `union`, `every … in … satisfies`, `some … in … satisfies`, `for … in … return` (several bindings nest left to right), `if (…) then … else …`, ranges `a to b`, `cast as` and `castable as` with `xs:boolean` / `xs:date` / `xs:decimal` / `xs:integer` / `xs:string` (with optional `?`) |
| Paths | axes `child`, `self`, `attribute`, `parent`, `ancestor`, `ancestor-or-self`, `descendant`, `descendant-or-self`, `preceding`, `following-sibling`, `preceding-sibling`; abbreviations `@`, `..`, `.`, `//`, `*`, `prefix:*`; kind tests `node()` and `text()`; expressions as path steps (`…/xs:decimal(cbc:Amount)`, `…/normalize-space(cbc:ID)`) |
| Predicates | boolean and numeric (positional) predicates on steps and on parenthesised expressions, applied in axis order (`ancestor::*[1]` is the nearest ancestor, `preceding-sibling::*[1]` the nearest preceding sibling); `last()` is the number of items the predicate filters |
| Variables and user functions | `$name` only when declared by the caller, by a `let` in scope or by an enclosing `for`, `some` or `every` (otherwise `UnsupportedXPath` naming `XPST0008` at parse time); calls to functions in a namespace other than `fn` and `xs` only when declared as an `xsl:function` of the schema with that arity (see [Lets and XSLT functions](#lets-and-xslt-functions)) |
| Rule contexts | XSLT match patterns: `N`, `N/N`, `//N`, `/N/N/N`, `N[@attr]`, predicates with function calls, unions; a parenthesised union with predicates, `(/A \| /B)[$v and …]`, selects the nodes of the union for which the predicates hold (a numeric-literal predicate is rejected); a parenthesised union followed by steps, `(/A[$v]/B \| /C/D)/E`, matches like the distributed union `/A[$v]/B/E \| /C/D/E` (every alternative must be a path pattern; a filtered union followed by steps is rejected) |
| Regular expressions | XML Schema syntax with anchors, back-references and reluctant quantifiers; flags `i s m x` (the XPath 3.0 flag `q` is reported as unsupported); `\w`, `\s`, `\d` and categories with XML Schema meaning |

Rejected with `UnsupportedXPath` (in the compiler: `UnsupportedSchematron`): the XPath 3.0 `let`
expression, `typeswitch`, `instance of`, `treat as`, `intersect`, `except`, `is`, `<<`, `>>`, unary
`+`, casts to other types, other axes, `*:local`, other kind tests, unknown functions or arities,
unknown prefixes, and in regular expressions `\i`, `\c`, `\I`, `\C`, `\p{IsBlock}` and character
class subtraction. Schematron `report`, message elements other than `name` and `value-of`,
processing instructions, abstract rules and abstract patterns are rejected by the compiler as well,
and so is any attribute the CEN, Peppol and XRechnung schemas do not use (such as
`pattern/@documents`, `rule/@role`, `assert/@diagnostics` or `name/@path`): the error names the
element, the attribute and the line.

## Schematron subset

Besides the elements every rule set needs (`schema`, `title`, `ns`, `let`, `phase`, `active`,
`pattern`, `rule`, `assert`) and the XSLT functions described below, the compiler supports what the
XRechnung 2.6.0 schemas use, so they compile as published:

- **`include`.** An `include` is replaced by the document element of the file it names, recursively,
  before anything is compiled. The `href` must be a relative path to a local file without a
  fragment; it resolves against the including file and must stay inside the include root (the
  directory of the top-level schema unless the caller passes a wider one). Cycles are refused.
- **Global pattern lets.** A `let` of a pattern is a global variable, as in the ISO skeleton and
  SchXslt: every rule, let and XSLT function of every pattern sees it, and its name must be unique
  among the schema and pattern lets (`XTSE0630`).
- **Messages.** An assert message may contain empty `name` elements (without `path`) and
  `value-of select="…"` elements. When the assert fails, `name` is the qualified name of the failing
  node as the document writes it (XPath `name()`), and `value-of` is the string value of the
  selected items, joined by a single space as `xsl:value-of` does; the `select` sees the variables
  the test sees. The whole message is then whitespace-collapsed. If a `value-of` raises an XPath
  error, the finding carries the static message (the text with every element left out) and no
  `DynamicError` is reported: the official stylesheets evaluate messages only for the report, and
  the verdict never depends on them.

## Lets and XSLT functions

The Peppol BIS Billing 3 schemas declare Schematron `let` variables and XSLT 2.0 functions
(`xsl:function`). The engine runs them as the ISO Schematron XSLT skeleton turns them into XSLT,
with the behaviour Saxon shows on probe cases (`ProbeCases::functions()`):

- **Lets are lazy.** A let is computed the first time an assert, a rule context or another let
  reads it, and then kept. A let no assert reads is never evaluated, so a value that cannot be
  converted (`xs:decimal('abc')`) in an unused let reports nothing. A let that fails is reported as
  a `DynamicError` by every assert that reads it, each with its own rule id.
- **Scope and context.** Schema-level and pattern-level lets are global variables: they are
  evaluated with the document node as context, at most once per document, and rule contexts may
  read them. Rule-level lets are evaluated with the node the rule handles as context. A variable
  that depends on itself is the error `XTDE0640`.
- **Function calls.** Arguments are evaluated before the call (Saxon does the same for a function
  it does not inline, such as a recursive one; a small function it inlines may skip an argument it
  never reads). Local `xsl:variable`s are lazy, like lets: one that is never read, or read only in
  a branch not taken, raises no error, as in Saxon. `xsl:choose` runs the first `xsl:when` whose
  test is true, else `xsl:otherwise`. A function body may not use the focus (`.`, `/`, a relative
  path, `last()`, `string()` without argument): XSLT leaves it absent there, and the compiler
  rejects such a body.
- **Conversion rules.** The `as` types `xs:boolean`, `xs:integer`, `xs:decimal`, `xs:string` and
  `xs:string?` convert a parameter, a variable and a function result: the value is atomised and an
  untyped value (text from the document) is cast to the type, so untyped `abc` for an `xs:decimal`
  is `FORG0001`. An `xs:integer` is accepted for an `xs:decimal` and stays an integer; a string is
  never converted (`'1.00'` for an `xs:decimal` is a type error). Saxon 13 follows the XPath 4.0
  coercion rules here and converts an `xs:double` for an `xs:decimal` to the shortest decimal that
  reads back as the same double (`0.1e0` becomes `0.1`; NaN cannot be converted); the engine does
  the same. An empty sequence without `?` and more than one item are type errors; the items are
  converted one at a time, so a failing cast of the first or second item is reported before the
  cardinality error. Type errors are `XPTY0004` for a parameter, `XTTE0570` for a variable and
  `XTTE0780` for a function result. A variable whose content is text and has no `as` is a
  temporary tree in XSLT; the engine holds its text as an untyped value, which is what every use in
  an expression sees.
- **Recursion limit.** More than 2,000 nested function calls raise the engine error
  `INTRAGILE_RECURSION` instead of exhausting memory or the PHP stack. The error is not
  recoverable: in an assert it is a `DynamicError` of that assert; in a rule context it is one
  `DynamicError` for the rule (no rule id, location `/`) and the rule handles no node. The Peppol
  functions recurse at most a dozen levels (`u:addPIVA` over an 11-digit VAT number). 2,000 nested
  calls take about 9 MB of memory with opcache and about 16 MB without it (PHP reserves more per
  call frame without the opcode optimizer); a function that keeps a long string per level needs
  more. A global let is always computed from call depth zero, so its value does not depend on
  where it is first read.

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
  data-driven errors (`FORG0001`, `XPTY0004`, `FOAR0001`, `XPTY0019`, `XTTE0780`, … —
  `XPathError::RECOVERABLE`); an unbound variable, a circular variable (`XTDE0640`), an engine
  guard (`INTRAGILE_RANGE`, `INTRAGILE_RECURSION`) or an unsupported construct still propagates,
  and is reported once for the rule.
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

- the assert is recorded in `dynamicErrors` as a `DynamicError` with the error code
  (`FORG0001`, `XPTY0004`, …), the rule id and the location of the context node;
- every other assert and rule is still evaluated, so the report also lists the other findings;
- `isValid()` is false.

So the engine never crashes on such input and never calls it valid. On a document that makes Saxon
abort, the report carries the same error code as Saxon, plus the findings of the rules that could
still be evaluated.

## Engine limits

Three guards stop input or a corrupt rule file from exhausting memory or recursing forever. Each
is a non-recoverable error: in an assert it becomes a `DynamicError` of that assert, in a rule
context one `DynamicError` for the rule (no rule id, location `/`), and the invoice is not valid.

| Limit | Error code |
|---|---|
| A range `a to b` of more than 100,000 items | `INTRAGILE_RANGE` |
| More than 2,000 nested user-function calls | `INTRAGILE_RECURSION` |
| A variable (let or local `xsl:variable`) that depends on itself | `XTDE0640` |

`INTRAGILE_*` codes are the engine's own; Saxon has no such limit (it evaluates ranges lazily),
so on input that reaches one the engine reports an error where Saxon would answer. No measured
CEN or Peppol rule comes near them: the deepest Peppol recursion is about a dozen calls, and the
test suite validates a Peppol invoice with a 50,000-digit identifier in under 2 seconds.

## XRechnung validation

`IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator` reproduces the KoSIT validator
1.6.3 with the XRechnung validator configuration 2026-08-31. It needs the package
`intragile/einvoicing-rules-xrechnung` (Apache-2.0), a `suggest` of the core;
`XRechnungValidator::create()` throws `RulesNotInstalled` naming
`composer require intragile/einvoicing-rules-xrechnung` when it is missing.

```php
$report = XRechnungValidator::create()->validate($xml);
```

`validate()` follows KoSIT step by step:

1. The document is parsed like every other input (a DOCTYPE raises `UnsafeXml`).
2. The scenarios of the configuration are tried in order and the first whose match expression
   holds is applied. The match expressions are compiled from `scenarios.xml` by
   `composer compile-rules`, evaluated by the engine's own XPath evaluator with the scenario's
   namespace bindings, as an effective boolean value with the document node as context. They
   compare the specification identifier (BT-24) exactly, without whitespace normalisation, as
   KoSIT does. When no scenario matches, `UnsupportedDocument` lists the accepted identifiers
   (`XRechnung::CIUS`, `XRechnung::EXTENSION`, `XRechnung::CVD` and the EN 16931 one); there is
   no fallback to a weaker scenario.
3. The document is validated against the scenario's XML Schema, KoSIT's copy of UBL 2.1 or
   CII D16B, shipped in the rules package (`SchemaValidator`, no network access, instance schema
   hints ignored).
4. Only when the document is schema valid, the scenario's Schematron rule sets run in order: CEN
   1.3.16 (the release KoSIT pins, not the default 1.3.17), then XRechnung 2.6.0 for the
   XRechnung scenarios. After an XML Schema failure KoSIT runs no Schematron, so the report then
   has the scenario name, the schema errors, no findings and no rule sets, and is not valid.
5. The scenario's custom levels replace the flag of each matching finding (`error` → `fatal`,
   `warning`, `information`); id, location and message are kept. They differ per scenario: for
   example `UBL-CR-646` is an error in the XRechnung CIUS and CVD invoice scenarios and
   information in the Extension scenario.

The report names the scenario (`$report->scenario`) and the rule sets that ran. It is valid when
the document is schema valid and no finding is fatal after custom levels, which is KoSIT's
acceptance. Information findings (such as `BR-DE-TMP-32`) are reported, as KoSIT reports them.

The scenario table, the compiled XRechnung rule sets and the schemas live in
`packages/rules-xrechnung` and are generated from the pinned KoSIT releases, never edited by
hand. "Validated like KoSIT" means that, per document, the scenario name, the XML Schema
validity, the sorted set of `"<rule id> <level>"` strings and the acceptance equal what the KoSIT
validator reports. The conformance test against the KoSIT validator itself is described in the
next section ([The KoSIT conformance run](#the-kosit-conformance-run)): 579 documents give
exactly KoSIT's verdict, 2 more give it after the documented dynamic-error rule, and 3 files that
are not well-formed or carry a DOCTYPE are refused by both.

To write XRechnung, give the invoice `XRechnung::processControl()` (or
`XRechnung::processControl(XRechnung::EXTENSION)`), which sets BT-24 and the Peppol billing
profile as BT-23.

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
PHP 8.3, 8.4 and 8.5 the whole oracle group, including the single expression probes, the
wrapper guard below and the Peppol corpus ([PEPPOL.md](PEPPOL.md#how-the-verdicts-are-verified):
1,611 documents compared with the OpenPeppol Schematron compiled by the ISO skeleton in Saxon,
plus a guard that the skeleton-compiled CEN 1.3.17 Schematron equals the official CEN 1.3.17
XSLT on 34 examples and 200 mutations), is 6,202 tests; any mismatch fails the build.

The SaxonC PHP extension gives no error code for a dynamic error and cannot run another
transformation after one. The oracle therefore wraps the official stylesheet in a test-only XSLT
3.0 stylesheet that imports it unchanged and catches the error code with `xsl:try` around
`xsl:apply-imports`. A guard test checks that the wrapped and the plain official stylesheet give
the same verdict on every document of the corpus Saxon does not abort on (1,757 documents).

### The KoSIT validator as the XRechnung oracle

For XRechnung the judge is the KoSIT validator 1.6.3 itself (Java), run with the XRechnung
validator configuration 2026-08-31 in the test group `kosit-oracle`. It is excluded from the
default run; `docker/kosit/run.sh [PHPUnit arguments]` (Docker, `curl` and `vendor/` needed):

1. builds the image `php-einvoicing-kosit:1.6.3` from `docker/kosit/Dockerfile`: an
   `eclipse-temurin` 21 JRE pinned by tag and digest, the validator jar and the configuration
   zip downloaded from their release URLs and checked against the SHA-256 sums of
   `XRechnungSources` (a unit test keeps the two in sync), running as a non-root user;
2. starts the KoSIT daemon (`-s scenarios.xml -r <configuration> -D -G -H 0.0.0.0 -P 8080`) on
   `127.0.0.1:${KOSIT_PORT:-8081}` and waits up to 120 seconds for it to answer;
3. runs the host PHPUnit with `--group kosit-oracle --fail-on-skipped` and
   `KOSIT_ORACLE_URL` pointing at the daemon, then removes the container on any exit.

`KOSIT_SKIP_BUILD=1` reuses the image; `KOSIT_BUILD_TIMEOUT` and `KOSIT_RUN_TIMEOUT` (seconds,
900 each) bound the build and the run; `PHP` selects the PHP binary. Without `KOSIT_ORACLE_URL`
the tests of the group are skipped.

A document is POSTed to the daemon, which answers with its VARL report: HTTP 200 when it accepts
the document, 406 when it rejects it (Schematron error, XML Schema failure or no matching
scenario) and 422 on a processing error: a body that is not well-formed XML, or a Schematron step
aborted by an XPath dynamic error. The comparison reads from the report the matched scenario
name, the validity of the XML Schema step, the sorted distinct `"<rule id> <level>"` strings of
the Schematron steps (KoSIT `error` read as `fatal`) and the acceptance. KoSIT writes the
assert's flag into each message and applies the scenario's custom levels only to the acceptance,
so the custom levels embedded in the report's copy of the scenario are applied when the verdict
is read. KoSIT runs no Schematron once the XML Schema step has
failed, and a document that matches no scenario has no scenario name. `KositProbeTest` pins this
behaviour, so a KoSIT upgrade that changes it fails there first.

### The KoSIT conformance run

`KositConformanceTest` sends every document below to the daemon and validates it with
`XRechnungValidator::create()`. Per document, the scenario name, the XML Schema validity, the
sorted set of `"<rule id> <level>"` strings after custom levels and the acceptance must equal
KoSIT's; when KoSIT matches no scenario, the validator must throw `UnsupportedDocument`.

| Documents | Count |
|---|---|
| KoSIT XRechnung test suite 2026-08-31 (standard, extension, technical cases) | 86 |
| XRechnung Schematron 2.6.0 unit-test instances (UBL Invoice 133, UBL CreditNote 93, CII 128) | 354 |
| Validator configuration 2026-08-31 test files, including non-invoice XML such as SVRL reports | 62 |
| CEN 1.3.16 example invoices (UBL and CII) | 32 |
| Mutations of five instances (UBL Invoice, UBL CreditNote, CII, Extension, CVD): BT-24 with a trailing space, `xrechnung_2.3` or the Peppol BIS id; buyer reference removed; seller contact removed; IBAN check digit broken; due date and payment terms removed; an element out of schema order; `abc` as an amount | 45 |
| Schema-valid documents that raise an XPath dynamic error, one per Schematron step | 2 |
| Files that are not well-formed (KoSIT must not accept them, the validator must throw) | 2 |
| A test suite instance with a DOCTYPE added (KoSIT must not accept it, the validator must throw `UnsafeXml`) | 1 |

Measured on KoSIT validator 1.6.3 with configuration 2026-08-31 (2026-11-13), in three groups:

- The 579 corpus documents, CEN examples and mutations are compared on all four points (scenario,
  XML Schema validity, verdict, acceptance) and all are equal: 318 accepted, 215 rejected by a
  Schematron error, 11 rejected by the XML Schema, and 35 without a scenario, where the validator
  throws `UnsupportedDocument`.
- The 2 dynamic-error documents are processing errors on KoSIT's side (HTTP 422). Scenario, XML
  Schema validity and rejection are equal; the verdicts are equal after the findings of the rule
  set that hit the dynamic error are left out of ours, as KoSIT reports none for that step (rule
  below).
- The 3 refused files are not compared on a verdict: KoSIT does not accept them (HTTP 422, its
  XML parser refuses them; the DOCTYPE with "DOCTYPE is disallowed") and the validator throws
  (`UnsupportedDocument`, or `UnsafeXml` for the DOCTYPE).

The run takes about 70 seconds. The `abc` amount fails the XML Schema in both syntaxes, so no
Schematron runs on it.

A dynamic error is compared by KoSIT's own rule: an assert that cannot be evaluated aborts the
XSLT transformation of its Schematron step, which then reports no message at all while the later
steps still run, and the daemon answers HTTP 422 with a rejection. The engine reports every assert
it could evaluate plus the dynamic errors, so on such a document the test requires a processing
error on KoSIT's side exactly when the engine reports dynamic errors, and compares KoSIT's messages
with the engine's verdict minus the entries of the rule sets that raised a dynamic error. None
of the 579 corpus documents raises one; only the 2 dynamic-error documents do.

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
- The XRechnung rule sets, the scenario table and the KoSIT copies of the UBL and CII schemas are
  in `packages/rules-xrechnung/` (package `intragile/einvoicing-rules-xrechnung`) under the
  Apache-2.0 licence of the KoSIT artefacts, with their `NOTICE`; origin and checksums are in its
  [SOURCE.md](../packages/rules-xrechnung/SOURCE.md). It is optional: only
  `XRechnungValidator` needs it.

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
5 seconds. These are single measurements on one machine, not a guarantee. Peppol timings are in
[PEPPOL.md](PEPPOL.md#performance).

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

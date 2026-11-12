# Peppol BIS Billing 3

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This page explains how to validate invoices, and the Peppol envelope around them, against the
Peppol BIS Billing 3.0 rules (release 3.0.22): what is checked, why the rules are compiled on your machine instead of shipped
with the package, how to install them, how the verdicts are verified, and what is not covered
yet. The engine itself is described in [VALIDATION.md](VALIDATION.md).

## Quick start

```bash
vendor/bin/einvoicing-rules install peppol --dir=var/peppol-rules
```

```php
use IntrAgile\EInvoicing\Validation\Validator;

$report = Validator::peppol(__DIR__.'/var/peppol-rules')->validate($xml);

if (! $report->isValid()) {
    foreach ($report->fatal() as $finding) {
        echo "{$finding->ruleId} at {$finding->location}: {$finding->message}\n";
    }
}
```

The report is the same `ValidationReport` that `Validator::en16931()` returns (`findings`,
`dynamicErrors`, `fatal()`, `warnings()`, `information()`, `verdict()`, `isValid()`), and
`validateInvoice($invoice, Syntax::Cii)` works the same way. `$report->ruleSets` names both
layers that ran, for example `['peppol-cen-ubl 3.0.22', 'peppol-ubl 3.0.22']`.

## What is checked

A Peppol BIS Billing 3.0 invoice must pass two Schematron layers, and the validator runs both for
every document, in this order:

1. **OpenPeppol's copy of the CEN EN 16931 rules.** OpenPeppol bundles its own copy of the CEN
   rules with each BIS release, and release 3.0.22 bundles CEN 1.3.16, not the 1.3.17 that
   `Validator::en16931()` runs. The copies differ (1.3.16 has 979 UBL asserts against 982 in
   1.3.17, contains the BR-AF and BR-AG rules and has other BR-CL-03/04/05 tests), so the Peppol
   verdict comes from OpenPeppol's copy, compiled from the files of the BIS release.
2. **The Peppol rules** (`PEPPOL-EN16931-UBL.sch`, `PEPPOL-EN16931-CII.sch`): the `PEPPOL-*`
   rules and the national rules (DE, DK, GR, IS, IT, NL, NO, SE) that OpenPeppol publishes in them.

| Rule set (id in `ruleSets`) | Source file | Patterns | Rules | Asserts | Fatal | Warning |
|---|---|---|---|---|---|---|
| `peppol-cen-ubl` | `CEN-EN16931-UBL.sch` (CEN 1.3.16) | 3 | 104 | 979 | 281 | 698 |
| `peppol-ubl` | `PEPPOL-EN16931-UBL.sch` | 13 | 99 | 165 | 139 | 26 |
| `peppol-cen-cii` | `CEN-EN16931-CII.sch` (CEN 1.3.16) | 3 | 113 | 806 | 335 | 471 |
| `peppol-cii` | `PEPPOL-EN16931-CII.sch` | 7 | 63 | 91 | 74 | 17 |

The counts are those of the compiled rule sets; elements commented out in the source files are
not counted. The Peppol layer also declares Schematron `let` variables (UBL 10 schema-level,
22 pattern-level and 17 rule-level; CII 4, 13 and 6) and XSLT functions (13 for UBL, 12 for
CII, for example check-digit and VAT-number checks). The engine runs both natively; see
[Lets and XSLT functions](VALIDATION.md#lets-and-xslt-functions).

`isValid()` is true when neither layer reports a fatal finding and no assert raised a dynamic
error. A document that is not a UBL Invoice, UBL CreditNote or CII invoice (for example a UBL
Order) throws `UnsupportedDocument`; a DOCTYPE throws `UnsafeXml`.

## Envelope (SBDH) rules

An invoice sent over Peppol travels inside a Standard Business Document: the Peppol Business
Message Envelope (SBDH) followed by the invoice. OpenPeppol publishes rules for that envelope in
`PeppolBIS-Envelope-Rules.sch`, and `install` compiles them together with the invoice rules.
`Validator::peppolEnvelope($dir)` takes the whole Standard Business Document:

```php
$report = Validator::peppolEnvelope(__DIR__.'/var/peppol-rules')->validate($sbdXml);
// $report->ruleSets === ['peppol-envelope 3.0.22']
```

| Rule | Applies to | Checks |
|---|---|---|
| `BR-SBDH-1` | BIS Billing 3.0 (`billing:01:1.0` or `billing_with_response`) | the envelope Receiver equals the buyer's `EndpointID` as `schemeID:value` |
| `BR-SBDH-2` | same | the envelope Sender equals the seller's `EndpointID` |
| `BR-SBDH-3` | self-billing 3.0 (`selfbilling:01:1.0`) | the envelope Receiver equals the seller's `EndpointID` |
| `BR-SBDH-4` | same | the envelope Sender equals the buyer's `EndpointID` |

- All four are fatal. Documents with another customization or profile pass without a check.
- **The comparison is case-sensitive.** The official rule compares strings with XPath `=`, so
  `0002:FR23342` in the envelope does not match `fr23342` in the invoice, although Peppol
  participant identifiers are case-insensitive. The validator mirrors the official rule; write
  the envelope identifiers exactly as they appear in the invoice. The invoice's `EndpointID` is
  compared after `normalize-space()`, the envelope identifier and the `schemeID` as written.
- The rules check only these identifiers: not `COUNTRY_C1`, the `DOCUMENTID` and `PROCESSID`
  scopes or the header structure, and not the invoice itself. Run `Validator::peppol()` on the
  invoice for that.
- A document whose element is not an SBDH `StandardBusinessDocument` (for example a bare
  invoice), or that envelopes no invoice, throws `UnsupportedDocument`. `$report->syntax` is the
  syntax of the enveloped invoice.
- A rules folder written by an earlier version of `install` holds only the four invoice rule
  files: `Validator::peppol()` still works with it, `Validator::peppolEnvelope()` throws
  `RulesNotInstalled` naming the `install` command, and `check` exits 1 until `install` is run
  again.

## Why the rules are compiled on your machine

OpenPeppol publishes its Schematron files without a licence (the repository has no LICENSE file,
and OpenPeppol AISBL holds the copyright). This package therefore distributes no file derived
from them: no compiled rules and no Peppol test documents, neither in the package nor in its git
repository. Instead, `vendor/bin/einvoicing-rules install peppol` downloads the five official
`.sch` files from the OpenPeppol repository at the pinned tag `3.0.22`, verifies each one against
a pinned SHA-256 checksum, and compiles them into PHP rule files on your machine. The compiled
files carry the note "OpenPeppol AISBL holds the copyright — do not redistribute" in their header;
keep them with your application and do not publish them.

Sources (`https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/3.0.22/rules/sch/`):

| File | SHA-256 |
|---|---|
| `CEN-EN16931-UBL.sch` | `268d4f7a2688676695e6c69cba6fba69a6802604fee12cb544a6b30ff09555a3` |
| `CEN-EN16931-CII.sch` | `54e0dc6d06cd7f17d268bb9696ff56f58d386ee28961c9bbef0a56718c400c89` |
| `PEPPOL-EN16931-UBL.sch` | `f637444fab75a6da7e5004f94a6de8e71383ef5658dafe6c68925103b17ea148` |
| `PEPPOL-EN16931-CII.sch` | `7678c3cd4a219095ef5d4f2ece17e5d447eb92655e57a7d52290b35acfdc9dd8` |
| `PeppolBIS-Envelope-Rules.sch` | `96648791fb824b6b58ddae9cc5575e8298e0061fc43dd0934a0b1459396d76f9` |

The rules are compiled from the upstream files as they are: never edited by hand, and no rule is
special-cased by its id.

## Install and check

```bash
# Download over HTTPS, verify the checksums, compile into DIR
vendor/bin/einvoicing-rules install peppol --dir=DIR

# Offline server: compile from a folder that holds the five .sch files
vendor/bin/einvoicing-rules install peppol --dir=DIR --from=/path/to/sch

# Exit code 0 when all five compiled rule sets in DIR load with the installed engine, 1 otherwise
vendor/bin/einvoicing-rules check peppol --dir=DIR
```

- `install` verifies all five checksums before it writes anything. On a mismatch it throws
  `RuleSourceMismatch`, names the file and both hashes, and leaves `DIR` unchanged. It writes five
  files, `peppol-cen-ubl-3.0.22.php`, `peppol-ubl-3.0.22.php`, `peppol-cen-cii-3.0.22.php`,
  `peppol-cii-3.0.22.php` and `peppol-envelope-3.0.22.php`, and replaces them only after all five
  have compiled.
- The download uses PHP streams only (no HTTP client dependency): HTTPS with certificate
  verification, at most three HTTPS redirects, a time limit and a size limit.
- For `--from`, get the five files from the
  [OpenPeppol repository](https://github.com/OpenPEPPOL/peppol-bis-invoice-3/tree/3.0.22/rules/sch)
  at tag `3.0.22` on a machine with network access; the same checksums are verified.
- `Validator::peppol($dir)` throws `RulesNotInstalled` when a file is missing, with the
  `install` command to run in the message, and `UnsupportedSchematron` asking to recompile when
  the files were compiled by an engine with another rule-file format.

## Deployment

- Run `install` in your build or deploy step (for example right after `composer install`) and
  keep `DIR` with the application, outside `vendor/` (Composer may replace that folder) and out
  of any public repository.
- Re-run `install` after upgrading this package: a new engine version may change the rule-file
  format, and `check` tells you whether the current files still load.
- With `--from`, the build server needs no network access; a copy of the five `.sch` files
  verified once is enough.
- `RuleSet::fromFile()` caches each compiled file per path for the life of the PHP process. A
  long-running worker that reinstalls into the same folder keeps the old rules until it restarts;
  restart workers after `install`.
- The four invoice rule sets take about 16 MB of memory once loaded. A process that also loads the
  CEN 1.3.17 rules (`Validator::en16931()`) holds both.

## How the verdicts are verified

No compiled XSLT for the Peppol layer is published; as far as we could find, OpenPeppol builds its
own validator by compiling the Schematron with the ISO Schematron XSLT skeleton in Saxon. The oracle
test group does the same: it compiles the four invoice `.sch` files with the ISO skeleton in SaxonC-HE
(Docker), runs the resulting stylesheets, and compares the verdict of every document with ours (the
sorted set of distinct `"<rule id> <flag>"` strings over both layers; locations and messages are not
compared). If Saxon aborts with a dynamic error, our report must carry a dynamic error with the same
code.

| Documents | Count |
|---|---|
| CEN 1.3.17 example invoices (UBL and CII) | 34 |
| Peppol examples and snippets (Invoice and CreditNote documents) | 26 |
| Peppol unit-test documents (Invoice and CreditNote documents only; all `rules/unit-*` folders except SBDH, VEFA test sets expanded) | 1,025 |
| Seeded mutations: 20 per Peppol example (the CEN mutation kinds plus a changed check digit and a changed profile id) | 520 |
| Model invoices written by this library in UBL and CII with the Peppol profile | 6 |

On these 1,611 documents there was no verdict mismatch. Saxon aborts on 99 of the mutations and on 2
unit-test documents; on those only the error code is compared, not the findings. The 4 UBL Order
documents among the unit tests are not counted above; they are not invoices: the validator throws
`UnsupportedDocument` for them, and a test asserts it. This is a test corpus, not a proof for every
possible input.

The envelope rules are verified the same way, in `PeppolEnvelopeConformanceTest`: the
`PeppolBIS-Envelope-Rules.sch` compiled with the ISO skeleton and run by SaxonC-HE gives the same
verdict as `Validator::peppolEnvelope()` on the 14 documents of the official envelope tests
(`rules/unit-SBDH`) and on 10 envelopes written by `EnvelopeWriter` for Peppol examples, some
altered afterwards (another sender or receiver, letter case, padding, self-billing and swapped
parties). That class is not counted in the totals below.

The skeleton pipeline is itself guarded: the CEN 1.3.17 Schematron compiled with the ISO
skeleton gives the same verdicts as the official CEN 1.3.17 XSLT on the 34 CEN examples and 200
seeded mutations. Together with the CEN 1.3.17 corpus of `Validator::en16931()`, the whole oracle
group is 6,202 tests and passes on PHP 8.3, 8.4 and 8.5. The Peppol documents are downloaded by
`composer fetch-official` into a git-ignored folder; their per-file checksums are in
`tests/fixtures/peppol-3.0.22.sha256`.

## Performance

Single dev-box measurement (Apple M1, PHP 8.5.0 CLI, opcache off, a shared machine at load
average about 4), one `Validator` reused, 20 runs per document after one warm-up run, over the
26 Peppol examples and snippets (all UBL, 5–22 KB):

| Documents | Median | p95 |
|---|---|---|
| All 26 (520 runs) | 52.0 ms | 87.1 ms |
| Slowest (`snippets/price.xml`, 21 KB) | 88.9 ms | 101.5 ms |
| Fastest (`examples/vat-category-Z.xml`, 5 KB) | 23.3 ms | 24.5 ms |

Both layers run on every document, so a Peppol validation costs more than a CEN-only one. These
are single measurements on one machine, not a guarantee. The limits and the advice for untrusted
input in [Resource use](VALIDATION.md#resource-use) apply here too.

## Not covered yet

- **Peppol transport**: sending and receiving through an access point, SMP and Peppol Directory
  lookup. Both are planned for a later release.
- **Live network**: the format is verified offline against the official rules; transmission
  through the Peppol network is not verified yet.
- Other Peppol BIS documents (orders, despatch advice and the like) are refused, not validated.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

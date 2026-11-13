# ZUGFeRD / Factur-X

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This package writes, reads and validates ZUGFeRD 2.x and Factur-X 1.0 hybrid invoices: a PDF/A-3
document that carries the invoice as an embedded UN/CEFACT CII XML file. It does so in pure PHP,
without a PDF library, and without Java. The format is verified offline against the official
files and tools ([Verification](#verification)); it has not been tried in live exchanges, and
nothing is released yet.

Rendering the human-readable PDF is not part of this package. You supply the PDF; the library
embeds the XML into it. A renderer may follow as a separate, optional package.

## Profiles

| Profile | `FacturXProfile` | Guideline identifier (BT-24) | Lines |
|---|---|---|---|
| MINIMUM | `Minimum` | `urn:factur-x.eu:1p0:minimum` | no |
| BASIC WL | `BasicWl` | `urn:factur-x.eu:1p0:basicwl` | no |
| BASIC | `Basic` | `urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic` | yes |
| EN 16931 | `En16931` | `urn:cen.eu:en16931:2017` | yes |
| EXTENDED | `Extended` | `urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended` | yes |
| XRECHNUNG | `XRechnung` | `urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0` | yes |

`FacturXProfile::fromGuidelineId()` maps an identifier to its profile (the XRechnung 3.0 extension
identifier gives `XRechnung` too) and returns `null` for anything else. The XRECHNUNG profile is
the XRechnung 3.0 CIUS embedded in a PDF: its XML is written and validated with the XRechnung
support of this package, and the attachment is named `xrechnung.xml`.

## Install

The XML schemas and Schematron rules of the five Factur-X profiles live in a separate Apache-2.0
package, a suggestion of the core package:

```bash
composer require intragile/einvoicing-rules-facturx
```

Writing a profile XML and validating need it; embedding into a PDF and reading do not. Without it,
`FacturXXmlWriter` and `FacturXValidator::create()` throw `RulesNotInstalled` with this command in
the message. The core package needs `ext-zlib` (PDF streams are deflated); there is no other new
dependency.

## Write

```php
use IntrAgile\EInvoicing\Syntax\FacturX\{FacturXPdfWriter, FacturXProfile, FacturXXmlWriter};

$xml = (new FacturXXmlWriter)->write($invoice, FacturXProfile::En16931)->xml;
$result = (new FacturXPdfWriter)->embed($pdfA3Bytes, $xml);
file_put_contents('invoice.pdf', $result->pdf);
```

`$invoice` is an `Invoice` with totals (see `TotalsCalculator::complete()` in the
[README](../README.md#create-an-invoice)). `FacturXXmlWriter::write()` sets the guideline
identifier of the profile, writes the invoice as CII and reduces it to what the profile allows
([What the reduction removes](#what-the-reduction-removes)).

`FacturXPdfWriter::embed(string $pdf, string $xml, ?FacturXProfile $profile = null,
?AfRelationship $relationship = null, ?DateTimeImmutable $modified = null, bool $strict = false)`
returns an `EmbedResult`: `pdf` (the new bytes), `profile`, `relationship` and `warnings`.

- **Profile.** It is read from the XML's guideline identifier. Pass `$profile` to assert it: a
  different one throws `ProfileMismatch`.
- **Relationship.** The default `/AFRelationship` is `Data` for MINIMUM, BASIC WL, BASIC, EN 16931
  and EXTENDED, and `Alternative` for XRECHNUNG. The official FeRD example invoices use `Data` in
  61 of 61 non-XRechnung invoices. Pass `AfRelationship::Data`, `Alternative` or `Source` to
  choose another.
- **Date.** `$modified` is written as the modification date; fixing it makes the output
  reproducible, byte for byte.
- **Incremental update.** The input is never rewritten. The invoice is appended as an incremental
  update, so the input's bytes are an unchanged prefix of the result ([PDF layer](PDF.md)).

What the writer adds: the XML as an embedded file (named `factur-x.xml`, or `xrechnung.xml`),
registered in the catalog's `/AF` array and in the `/EmbeddedFiles` name tree; an XMP packet with
the PDF/A-3 identification and the Factur-X extension schema (`fx:DocumentType`,
`fx:DocumentFileName`, `fx:Version`, `fx:ConformanceLevel`), merged into the XMP the PDF already
has; a trailer `/ID` when there is none; an sRGB `OutputIntent` when the PDF has none; and a copy
of the Info dictionary with a synchronised `/ModDate`.

What it refuses, each with an exception that extends `UnsupportedDocument`, except `UnsafeXml`,
which is a `RuntimeException`; catch it too (`catch (UnsafeXml | UnsupportedDocument $e)`):

| Case | Exception |
|---|---|
| The PDF is encrypted | `EncryptedPdf` |
| The PDF cannot be read, or its index had to be reconstructed | `UnreadablePdf` |
| The PDF already carries an invoice (`factur-x.xml`, `xrechnung.xml`, `zugferd-invoice.xml`) | `InvoiceAlreadyEmbedded` |
| The PDF declares PDF/A-4 or later | `UnsupportedPdfAPart` |
| The input declares no PDF/A, or is PDF 2.0, and `$strict` is true | `NotPdfA` |
| The XML is not CII, or declares no known profile | `UnsupportedDocument` |
| The XML declares another profile than `$profile` | `ProfileMismatch` |
| The PDF's XMP metadata is not well-formed XML, or has no `rdf:RDF` element | `UnsupportedDocument` |
| The XML or the XMP carries a DOCTYPE | `UnsafeXml` |

### PDF/A input

**We do not convert to PDF/A. Give us a PDF/A input.** Factur-X requires PDF/A-3. The writer embeds
the XML and adds the pieces listed above, but it does not embed fonts, flatten transparency or
change colours. Producing a conforming PDF/A is the job of whatever renders your PDF.

- An input that declares a PDF/A part in its XMP (`pdfaid:part`) is embedded into; the declaration
  is set to part 3, which embedded files require, with a warning when it was part 1 or 2.
- An input that declares no PDF/A part is embedded into with a warning in
  `EmbedResult::$warnings`, or refused with `NotPdfA` when `$strict` is true. Use `strict: true`
  in production code that must never emit a non-PDF/A file.
- An input that is PDF 2.0 (its header, or its catalog `/Version`) is embedded into with the
  warning that PDF/A-3 is based on PDF 1.7, so the result is not a valid PDF/A-3; it is refused
  with `NotPdfA` when `$strict` is true.
- Whether such an output passes a PDF/A validator depends on the input. We do not claim that it
  fails, and we do not claim that it passes: in one test, a plain headless-Chrome print passed
  PDF/A-3b after embedding because the missing trailer `/ID`, XMP and output intent were exactly
  what the writer adds, but that is not a guarantee for any other file. Check your output with
  veraPDF.
- `pdfaid:conformance` of the input is carried over unchanged, not normalised. An input whose
  value is invalid (for example a lowercase `a`) stays invalid in the output.

## Read

```php
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;

$document = (new FacturXPdfReader)->read($pdfBytes);

$document->xml;                 // the embedded XML, byte for byte
$document->profile;             // FacturXProfile, from the XML's guideline identifier
$document->relationship;        // AfRelationship, or null
$document->xmpConformanceLevel; // fx:ConformanceLevel as the XMP says
$document->pdfaPart;            // pdfaid:part
$document->warnings;            // list<string>
$invoice = $document->invoice()->invoice; // Invoice, as for a CII file
```

The reader finds the invoice by file name (`factur-x.xml`, then `xrechnung.xml`, then
`zugferd-invoice.xml`), in the `/AF` array before the name tree, matching `/UF` before `/F`; of
two matches in the same place, the earlier one wins. The profile comes from the XML;
a disagreeing XMP level, a missing `/AFRelationship` or a missing guideline identifier is a
warning. A PDF with no invoice, and ZUGFeRD 1.0 documents (another CII version), throw
`UnsupportedDocument`.

**MINIMUM and BASIC WL cannot be read into the model.** They carry no invoice lines, so they are
not a complete EN 16931 invoice. `FacturXDocument::invoice()` throws `UnsupportedDocument` for
them; use `$document->xml` directly, or validate it. BASIC, EN 16931, EXTENDED and XRECHNUNG read
into an `Invoice`.

## Validate

```php
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;

$report = FacturXValidator::create()->validate($xml);
$report->scenario;  // "EN 16931"
$report->isValid();
```

`validate(string $xml, ?FacturXProfile $profile = null)` picks the profile from the document's
guideline identifier; passing `$profile` asserts it and throws `ProfileMismatch` otherwise. A
document that is not CII, or whose identifier is not a Factur-X one, throws `UnsupportedDocument`
(the message lists the accepted identifiers). A DOCTYPE throws `UnsafeXml`. The XRECHNUNG profile
is handed to `XRechnungValidator`.

The document is checked against the profile's XML Schema first, then against its Schematron
rules. **Schematron still runs after an XSD failure**, so one report lists every problem at once.
The result is a `ValidationReport` like the other validators', with the rule set
`facturx-<profile> 1.09.2`. The rules are compiled from the official Factur-X 1.09.2 Schematron
(including its code lists, read at compile time through `document()`), and a report element that
always fires is a fatal "not used in this profile" finding. Assertions without a flag count as
fatal; reports without an id get a stable synthesized id of the form `FX-REPORT-<hash>`.

To validate a PDF, read it first and validate `$document->xml`. Validation checks the XML, not the
PDF/A conformance of the file.

## What the reduction removes

A profile's XML Schema is narrower than the full CII schema. `FacturXXmlWriter` removes what the
profile does not allow, so the output is valid for it:

- elements and attributes the profile schema does not know;
- everything the profile's Schematron prohibits unconditionally (a report that always fires);
- for MINIMUM and BASIC WL, the invoice lines, since neither profile carries them.

`write()` returns a `ReducedXml`: `xml`, and `removed`, the sorted paths of what was removed. A
non-empty `removed` means the invoice carried data the profile cannot hold; check it before
sending, or choose a richer profile. Use EN 16931 when you need the full semantic model.

The reduced document is validated against the profile schema once more before it is returned. A
failure there would be a defect of the writer, not of the invoice, and throws a `LogicException`
naming the profile. The reduction reads the profile schema and the files it imports only through
relative paths inside the schema's directory; any other `schemaLocation` is refused with
`UnusableSchema`.

## Verification

Format verified offline; nothing is verified in live exchanges.

- Schematron verdicts equal the official Factur-X 1.09.2 XSLT (run by SaxonC) on 699 documents,
  with 0 differences. The corpus is the 57 FeRD example invoices, the XML read from their 57
  PDFs, the writer's output for three test invoices in each of the five profiles, and 570
  mutations of the examples. Per document, the sorted set of rule ids with their levels is equal.
- PDF/A was checked with veraPDF 1.30.3 on 10 input and output pairs: the 9 Mustang corpus PDFs
  that carry no invoice and declare a PDF/A part, plus one headless Chrome print. The other corpus
  PDFs already carry an invoice or have an index that had to be reconstructed, so the writer
  refuses them, and one declares no PDF/A. Every input that passed veraPDF passed after embedding,
  and the update broke no rule. One input fails before and after: its `pdfaid:conformance` is a
  lowercase `a`, which the writer carries over unchanged. The Chrome print, which declares no
  PDF/A, passes PDF/A-3b after embedding.
- All 102 PDFs of the Mustang corpus parse, and the invoices of all 61 FeRD example invoice PDFs
  are read.

The test groups, `docker/oracle/run.sh` and `docker/verapdf/run.sh` are development tooling and
are not part of the installed package.

## Licences

The profile schemas and rules are in `intragile/einvoicing-rules-facturx` under the Apache-2.0
licence of FeRD's technical artefacts, with their origin and checksums in its
[SOURCE.md](../packages/rules-facturx/SOURCE.md).

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

# The PDF layer

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The [ZUGFeRD / Factur-X](FACTURX.md) support reads and extends PDF files with a small PDF parser
and writer of its own, in `IntrAgile\EInvoicing\Pdf`. It is dependency-free (it needs `ext-zlib`
and nothing else) and it is built for one job: find the attached invoice in a PDF, and attach one.
It is not a general PDF library. It cannot render, edit pages or extract text.

A PDF from a customer or supplier is untrusted input. The parser never uses `eval`, never follows
an external reference and never touches a file or the network. Damaged or hostile input raises
`UnreadablePdf`, never a PHP error or warning, provided PHP's `memory_limit` leaves room for what
a document needs: about the size of the PDF, plus up to about 30 MiB while the cross-reference
index is built or rebuilt, plus the decoded streams being read (one at most 40 MiB, all of
them at most 256 MiB, see [Limits](#limits)). Building the index holds at most one decoded
stream at a time.
The hostile-input tests run at PHP's default `memory_limit` of 128M.

## What the parser reads

- Classic cross-reference tables, cross-reference streams and hybrid files.
- Object streams, and files with several incremental updates (`/Prev` chains).
- Damaged files: when the cross-reference index is wrong or missing, the parser rebuilds it by
  scanning the file. `PdfDocument::reconstructed()` says so. An object stream whose filter is
  given indirectly is decoded with the filter object the scan found. One object stream that
  cannot be decoded (damaged, an unresolvable or unsupported filter) is skipped, so the objects
  inside it are missing, but the rest of the file is still read. After reconstruction
  `PdfDocument::size()` is one more than the highest object number found; the damaged file's
  trailer `/Size` is ignored.
- The `FlateDecode` filter, including the PNG and TIFF predictors.

## What it refuses

| Input | Exception |
|---|---|
| An encrypted PDF (the trailer has `/Encrypt`) | `EncryptedPdf` |
| A stream filter other than `FlateDecode` on a stream the parser has to read | `UnsupportedPdfFeature` |
| Data that is not a PDF (no `%PDF-` header in the first 1024 bytes) or is too damaged to read | `UnreadablePdf` |
| A name tree (such as `/EmbeddedFiles`) with a key that is not a string | `UnreadablePdf` |
| A breach of a limit below | `UnreadablePdf` |
| Writing into a file whose index had to be reconstructed | `UnreadablePdf` |

A PHP warning or engine error (such as a `TypeError`) raised while a PDF is parsed is turned into
`UnreadablePdf`, with the engine error as its previous exception. Exceptions raised while a stream
is decoded do not keep the decoded data alive through their stack traces.

`EncryptedPdf` and `UnsupportedPdfFeature` extend `UnreadablePdf`, which extends
`UnsupportedDocument`, so one `catch (UnsupportedDocument $e)` covers every refusal of the PDF
layer. The Factur-X reader and writer also throw `UnsafeXml` for XML or XMP that carries a DOCTYPE;
it is a `RuntimeException`, not an `UnsupportedDocument`, so catch both
(`catch (UnsafeXml | UnsupportedDocument $e)`). Decryption is not supported; PDF/A forbids
encryption anyway, so a conforming input is never encrypted.

## Limits

The limits are class constants of `PdfParser`. They keep memory and time bounded on a hostile
file, such as a decompression bomb or a cross-reference table that claims millions of objects.

| Limit | Value |
|---|---|
| One decoded stream | 40 MiB |
| All decoded streams of a document together | 256 MiB |
| Nesting of arrays and dictionaries | 64 levels |
| `/Prev` links followed | 64 |
| Object count | 8 388 607 (the PDF limit) |
| Predictor-coded data of a document | 4 MiB |
| Cross-reference entries | one per 8 bytes of file, at least 4096, at most 524 288 |

Inflation runs in chunks and is stopped as soon as it passes the limit, so a bomb is never held in
memory. A cross-reference index larger than these bounds is not read; the file is reconstructed
instead. A reconstruction that finds more than 524 288 objects is refused with `UnreadablePdf`.

## Incremental update, and why

`FacturXPdfWriter` never rewrites the PDF. It appends new and replaced objects and a new
cross-reference section after the original bytes, and the new section's `/Prev` points at the
original's newest one (ISO 32000-1, 7.5.6). The original bytes are therefore an unchanged prefix
of the result.

- **Nothing of the input can be damaged.** Pages, fonts, images and colour spaces are not parsed
  or rewritten, so their PDF/A conformance cannot get worse by being re-serialised.
- **Signatures survive.** A signature covers a byte range; appending after it leaves that range
  untouched.
- **It is fast and predictable.** Nothing in the PDF is re-encoded: pages and their content are
  never parsed, and only the new and replaced objects are written.
- **It is reproducible.** The same input, XML and modification date give the same bytes.

The update is a cross-reference stream when the original's newest section is one, and a classic
table otherwise. When the original's index had to be reconstructed, the writer refuses with
`UnreadablePdf`: appending an update that points back at wrong offsets would corrupt the file.
Re-save such a PDF with a PDF tool (qpdf, Ghostscript, a PDF editor) first.

## Signatures

A signed PDF can be embedded into: the appended update leaves the signed bytes unchanged. The
writer detects a signed input (a catalog `/Perms` entry, or an AcroForm `/SigFlags` of 1 or more)
and adds a warning to `EmbedResult::$warnings`. A PDF signature validator may still report that
the document changed after signing, because an update was appended, and a certification
signature (DocMDP) may forbid the change; the writer does not check the DocMDP permissions. Where
you can, embed the invoice first and sign afterwards. The library neither creates nor verifies
signatures.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

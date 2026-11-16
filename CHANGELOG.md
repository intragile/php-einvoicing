# Changelog

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/).

## [1.0.0] - YYYY-MM-DD

### Added

- **Peppol transport:** the B2Brouter driver: `B2BrouterConfig`, `B2BrouterTransport` (import and
  send in one request, delivery status, unacknowledged received invoices, fetch with SBDH
  unwrapping, acknowledgement) and `B2BrouterWebhook` (`X-B2Brouter-Signature` over
  `"<t>.<raw body>"`, timestamp tolerance opt-in). BIS Billing 3.0 invoices and credit notes are
  sent by default; other document types need a configured B2Brouter document type code. The API
  version `2026-06-26` is pinned. Opt-in sandbox test group `b2brouter-live`. Observed in the
  sandbox: an unregistered-recipient failure is reported only in the `errors` list, without an
  error code, and maps to `recipient_not_found`; `status()` reports the `errors` list when
  `error_message` is empty.
- **Peppol transport:** the e-invoice.be driver: `EInvoiceBeConfig`, `EInvoiceBeTransport` (upload
  and send of BIS Billing 3.0 UBL only, delivery status with the timeline's failure note, inbox
  listing back to a configurable look-back, download from the signed URL without the API key,
  local-only acknowledgement) and `EInvoiceBeWebhook`, which verifies `X-Signature` over the
  canonical JSON string of the event (`CanonicalJson`, Python `json.dumps(sort_keys=True)`; a
  number with a fraction fails closed). Opt-in sandbox test group `einvoicebe-live`, which needs
  `EINVOICEBE_SANDBOX_CONFIRMED=1`. A signed download URL is fetched over https only, from
  `*.r2.cloudflarestorage.com` only (the host observed in the sandbox).
- **Peppol transport:** provider-neutral webhooks in `IntrAgile\EInvoicing\Transport\Webhook`:
  the `WebhookParser` interface (`parse()`, `verify()`), `WebhookEvent` and `WebhookKind`
  (`DocumentReceived`, `DeliveryChanged`, `Other`). `RecommandWebhook`, `B2BrouterWebhook` and
  `EInvoiceBeWebhook` implement it. Webhooks are verified offline against each provider's
  documented scheme (e-invoice.be also against its published test vector); webhooks were not
  live-tested.
- **Peppol transport:** the `InboundSource` contract states that consumers de-duplicate on the
  document id, because a provider that cannot record acknowledgements lists a document again.
- **Documentation:** a section per driver in `docs/TRANSPORT.md` and a driver table in the README.
  Recommand is live-tested on the Peppol test network; B2Brouter and e-invoice.be are live-tested
  against the provider sandbox (simulated network). Storecove is planned.
- **Documentation:** `docs/VERIFICATION.md` with the results of the offline release gate (KoSIT
  1.6.3, the official Factur-X 1.09.2 Schematron through SaxonC, veraPDF 1.30.3), of the live
  runs and of the import into Lexware Office; `docs/countries/DE.md`, the Germany country
  document; a `CHANGELOG.md` in each rule package.
- **Testing:** the opt-in groups `release-artefacts` and `release-gate` build and check the
  release invoices (XRechnung and ZUGFeRD) with the official validators; the live groups
  `recommand-live`, `b2brouter-live` and `einvoicebe-live` redact every configured secret and
  refuse a production account.
- **Peppol transport:** XRechnung over Peppol. `OutboundDocument::fromXml()` sends XRechnung 3.0
  (CIUS: UBL Invoice, UBL CreditNote and CII) and the XRechnung 3.0 Extension (UBL Invoice and
  CII) under their own Peppol document types (`XRechnungPeppol`, Peppol Code Lists v9.7), and
  reads the CII routing data from BT-34, BT-49, BT-40 and BT-23. The XRechnung CVD (no Peppol
  document type) and the deprecated UBL Credit Note Extension are refused.
  `OutboundDocument::fromInvoice()` takes an optional `Syntax`; CII is allowed only for an
  XRechnung invoice.
- **Validation:** the `DocumentValidator` interface (`validate(string $xml): ValidationReport`),
  implemented by `Validator`, `XRechnungValidator` and `FacturXValidator`, and
  `PeppolPayloadValidator`, which routes a document by its root element and specification
  identifier (BT-24) to the Peppol BIS rules or to `XRechnungValidator`. Without
  `intragile/einvoicing-rules-xrechnung`, an XRechnung document throws `RulesNotInstalled` naming
  the composer command.
- **Peppol:** `IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes` (`@internal`, outside the BC
  promise), the one document-type resolver that sending and validation share, and
  `PeppolBis3::CUSTOMIZATION`.
- **Transport:** `HttpClient` takes the secrets to redact as a fifth constructor argument and
  through `withSecrets()`, and `send()` takes a per-request `$maxBytes` that can only lower the
  cap; `RecommandConfig::MIN_CREDENTIAL_LENGTH` and `RecommandConfig::redactingClient()`;
  `SmpClient::DEFAULT_MAX_BYTES`; `EnvelopeReader::isEnvelopeStream()`.
- **Validation:** `DynamicError::$ruleSet` names the rule set that raised the error
  (`"<id> <version>"`).
- **PDF:** `PdfDocument::skippedObjectStreams()` counts the object streams skipped while an
  index is reconstructed.
- **Rule installer:** `PartialInstall::$backupPaths` lists the backups left in the rules folder.
- **Documentation:** a test compiles every PHP sample of the READMEs and `docs/*.md` and runs the
  main ones (`tests/Docs/CodeSamplesTest.php`); a versioning policy in the README of every package.
- ZUGFeRD / Factur-X 1.0 for the profiles MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED and
  XRECHNUNG: `FacturXXmlWriter` writes the profile XML reduced to what the profile allows,
  `FacturXPdfWriter::embed()` embeds it into a PDF/A you supply by an incremental update (embedded
  file, `/AF`, XMP, trailer `/ID`, sRGB output intent), `FacturXPdfReader` extracts it, and
  `FacturXValidator` validates it (XML Schema first, then Schematron, which still runs after an
  XSD failure). The PDF is not converted to PDF/A: an input that declares no PDF/A part gets a
  warning, or `NotPdfA` in strict mode. Reading MINIMUM and BASIC WL into the model throws
  `UnsupportedDocument`, as they carry no lines. The default `/AFRelationship` is Data, and
  Alternative for XRECHNUNG.
- A pure-PHP PDF parser in `IntrAgile\EInvoicing\Pdf`: classic and stream cross-reference
  sections, object streams, index reconstruction, FlateDecode with predictors, and hard safety
  limits (a breach throws `UnreadablePdf`); no PDF library.
- `intragile/einvoicing-rules-facturx` (Apache-2.0), a suggested package: the Factur-X 1.09.2
  Schematron of the five profiles compiled to rule sets, and the profile XML Schemas.
- Schematron compiler: `report` elements, default flags and synthesized ids for assertions
  without them (`CompileOptions`), and `document()` on files embedded at compile time.
- Factur-X conformance tests: the validator's verdicts equal the official Factur-X 1.09.2 XSLT
  (SaxonC) on 699 documents, PDF/A is checked with veraPDF 1.30.3 (optional `verapdf` group), all
  102 Mustang corpus PDFs parse and all 61 FeRD example invoices are read. Format verified
  offline and, in the release gate, with the official Factur-X Schematron and veraPDF; ZUGFeRD
  PDFs are not sent over Peppol.
- `docs/FACTURX.md` and `docs/PDF.md`.
- EN 16931 code lists as backed enums in `IntrAgile\EInvoicing\CodeList`, generated from the
  CEN validation artefacts 1.3.17.
- The EN 16931-1 semantic model as immutable classes in `IntrAgile\EInvoicing\Model`, with exact
  decimals (`brick/math`).
- `TotalsCalculator`: line net amounts, VAT breakdown, document totals (including prepaid and
  rounding amounts) and a BR-CO check.
- Amount, quantity and rate inputs reject `float` at runtime, in strict and non-strict callers.
- UBL 2.1 and UN/CEFACT CII readers and writers, with safe XML loading that refuses DOCTYPE
  declarations. Writers refuse output the official rules or XML parsers would reject (invalid
  characters, BT-111 without BT-6, more than one buyer or payee identifier), and readers report
  amounts in a currency other than BT-5.
- SaxonC oracle (Docker) running the official CEN validation stylesheets as a test group.
- `docs/MODEL.md`, generated by `bin/generate-model-doc.php` and checked by the test suite.
- `Validator::en16931()`: validation of UBL and CII invoices against the official CEN EN 16931
  rules (validation artefacts 1.3.17) in pure PHP, returning a `ValidationReport` with fatal and
  warning findings and the asserts that could not be evaluated (dynamic errors such as `FORG0001`);
  `validateInvoice()` writes and validates a model invoice.
- An XPath 2.0 engine for the subset the CEN rules use, with Saxon-checked semantics, and a
  Schematron compiler (`bin/compile-schematron.php`, `composer compile-rules`) that turns the
  official preprocessed Schematron into PHP rule files.
- The compiled rules as the EUPL-1.2 package `intragile/einvoicing-rules-en16931`
  (`packages/rules-en16931`).
- A conformance test group that checks the validator's verdicts equal to the official CEN XSLT
  run by SaxonC on a corpus of 1,892 documents (the CEN examples, the CEN unit tests, seeded
  mutations, model invoices and crafted documents); where Saxon aborts with a dynamic error (135
  mutations), only the XPath error code is compared.
- `docs/VALIDATION.md`: how validation works, the supported XPath, what the oracle checks,
  resource use and measured timings.
- `Validator::peppol($rulesDir)`: validation against Peppol BIS Billing 3.0.22, running
  OpenPeppol's copy of the CEN rules (CEN 1.3.16) and then the Peppol rules for UBL and CII. The
  `Validator` constructor accepts a list of rule sets per syntax, and `RuleSet::fromFile()` loads
  custom compiled rule sets.
- `vendor/bin/einvoicing-rules install peppol --dir=DIR [--from=DIR]` downloads the four official
  OpenPeppol Schematron files at the pinned tag 3.0.22 (or reads them from a local folder),
  verifies their SHA-256 checksums and compiles them on the user's machine; `check peppol
  --dir=DIR` tells whether the compiled rules load. No Peppol-derived file is distributed.
  New exceptions `RulesNotInstalled` and `RuleSourceMismatch`.
- XPath engine: `if`, `for`, `some`, ranges, `castable as`, `idiv`, `mod`, the
  `following-sibling` and `preceding-sibling` axes and the string, number and sequence functions
  the Peppol rules use; Schematron `let` variables (lazy) and XSLT 2.0 functions
  (`xsl:function` with `param`, `variable`, `sequence` and `choose`, recursion included); the
  engine limits `INTRAGILE_RANGE` (ranges over 100,000 items), `INTRAGILE_RECURSION` (over
  2,000 nested calls) and `XTDE0640` (circular variable).
- Peppol conformance tests: verdicts checked equal to the OpenPeppol Schematron compiled with the
  ISO Schematron skeleton and run by SaxonC on a corpus of 1,611 documents (CEN examples, Peppol
  examples and unit tests, seeded mutations and model invoices); where Saxon aborts (101
  documents), only the error code is compared. A guard checks that the skeleton-compiled CEN
  1.3.17 Schematron gives the official CEN XSLT's verdicts.
- `docs/PEPPOL.md`: what Peppol validation checks, why the rules are compiled locally, install
  and deployment, verification and timings, and what is not covered yet (SBDH envelope rules,
  Peppol transport).
- Peppol transport contracts in `IntrAgile\EInvoicing\Transport`: `Transport` (send, status),
  `InboundSource` (pending, fetch, acknowledge), `OutboundDocument` (routing metadata read from a
  UBL Invoice or CreditNote, or written from a model invoice), `SendResult`, `DeliveryStatus`,
  `InboundDocument`, `ValidatingTransport` (refuses a document that fails validation before it
  is sent) and `FakeTransport` for tests. Peppol identifiers `ParticipantId`, `DocumentTypeId`
  and `ProcessId`, and the BIS Billing 3.0 identifiers in `PeppolBis3`.
- `HttpClient` over any PSR-18 client and PSR-17 factories (new dependencies `psr/http-client`,
  `psr/http-factory`, `psr/http-message`), with a response size cap and credential redaction.
  Transport exceptions under `Transport\Exception` with `isRetryable()`; a state-changing
  request that got no answer throws `OutcomeUnknown` instead of inviting a duplicate resend.
- Participant lookup: `SmlLocator` (U-NAPTR in the production or test SML), `SmpClient` (SMP 1.x
  ServiceGroup and ServiceMetadata, one redirect, 2 MB cap, parsed with `SafeXml`) and
  `ParticipantLookup`, which reports `Registered`, `NotRegistered` or `Unknown` (never
  `NotRegistered` on a DNS or SMP failure). The resolver is pluggable through `NaptrResolver`.
- `DirectoryClient`: Peppol Directory search and lookup by participant, throttled to the
  Directory's two requests per second per instance.
- `RecommandTransport`: sending, delivery status, inbox polling, fetching (SBDH unwrapped) and
  acknowledging through the Recommand access point API; `RecommandWebhook` verifies the
  HMAC-SHA256 signature of webhook notifications and exposes a deduplication key. Tested offline
  against fixtures written from the published API description, and live on the Peppol test
  network (test group `recommand-live`; Peppol BIS 3 UBL, XRechnung UBL and CII).
- `EnvelopeWriter` and `EnvelopeReader`: the Peppol Business Message Envelope (SBDH), written in
  schema order with the participant ids exactly as in the invoice, and read by namespace.
- `Validator::peppolEnvelope($dir)`: the official Peppol envelope rules (`BR-SBDH-1` to
  `BR-SBDH-4`) on a whole Standard Business Document, compiled from
  `PeppolBIS-Envelope-Rules.sch`, with verdicts checked equal to SaxonC on 24 envelopes.
- `docs/TRANSPORT.md`: sending, HTTP client setup, errors, lookup, Directory, receiving and
  webhooks, the SBDH envelope, what is verified and the known limitations.
- XRechnung 3.0.2 validation: `XRechnungValidator` reproduces the KoSIT validator 1.6.3 with the
  XRechnung validator configuration 2026-08-31. The first scenario whose match holds is applied
  (XRechnung CIUS, Extension and CVD, plain EN 16931; UBL Invoice, UBL CreditNote and CII), the
  document is checked against the scenario's XML Schema and, only when schema valid, against CEN
  1.3.16 and XRechnung 2.6.0; the scenario's custom levels then replace rule flags. A
  specification identifier that matches no scenario throws `UnsupportedDocument` listing the
  accepted ones. `ValidationReport` gains `schemaErrors` and `scenario`.
- `XRechnung::processControl()` and the identifiers `XRechnung::CIUS`, `EXTENSION`, `CVD` and
  `PEPPOL_PROFILE` for writing XRechnung from the model.
- The compiled XRechnung rule sets, the scenario table (generated from KoSIT's `scenarios.xml`)
  and KoSIT's copies of the UBL 2.1 and CII D16B schemas as the Apache-2.0 package
  `intragile/einvoicing-rules-xrechnung` (`packages/rules-xrechnung`, with `NOTICE` and
  `SOURCE.md`). It is a `suggest` of the core; `XRechnungValidator::create()` throws
  `RulesNotInstalled` naming the package when it is missing. `composer compile-rules -- --check`
  covers both rule packages.
- A compiled CEN 1.3.16 build beside 1.3.17 in `intragile/einvoicing-rules-en16931`
  (`RuleFiles::ubl('1.3.16')`, `RuleFiles::cii('1.3.16')`, `RuleFiles::VERSIONS`), the release
  KoSIT pins.
- `SchemaValidator`: XML Schema validation against a pinned XSD only, offline (a non-local import
  or include is refused), returning `SchemaError`s in document order; an unusable schema throws
  `UnusableSchema`, never a finding.
- Schematron engine: `include` (confined to an include root, `Compiler::compile()` takes it as an
  optional argument), pattern `let`s as global variables visible to `xsl:function`s (a let that
  refers to a later one is a compile error), `name` and `value-of` in assert messages, and union
  rule contexts such as `(A | B)/C`.
- A KoSIT oracle (`docker/kosit/run.sh`, test group `kosit-oracle`): the KoSIT validator 1.6.3
  daemon in Docker; probe tests pin its behaviour, and the conformance test checks the scenario,
  XML Schema validity, verdict and acceptance equal to KoSIT on 584 documents (the KoSIT test
  suite, the XRechnung Schematron unit tests, the configuration tests, the CEN 1.3.16 examples,
  mutations, dynamic-error and refused files).
- `composer fetch-official` also downloads the pinned KoSIT releases (Schematron, validator
  configuration, test suite) and the CEN 1.3.16 release, verifies their SHA-256 checksums and
  checks every unpacked KoSIT file against a manifest. The KoSIT jar is never downloaded.

### Changed

- **Transport (security):** e-invoice.be refuses a signed download URL whose host is an IP literal,
  `localhost`, a name under `.localhost`, or a shorthand or numeric IPv4 form (`127.1`,
  `2130706433`, `0x7f.1`), and an e-invoice.be 404 on upload names the base URL and API version.
- **Transport:** e-invoice.be webhooks refuse a `data.document_id` that is not of the form
  `doc-<letters and digits>`; webhook account ids and deduplication keys of B2Brouter,
  e-invoice.be and Recommand are trimmed; a B2Brouter state-change webhook whose state is not a
  string gives a null delivery state.
- **PDF:** the stream cap is 48 MiB (previously 64 MiB), so an index plus one decoded stream fit
  PHP's default 128M `memory_limit`; `FacturXPdfReader` reports object streams skipped during an
  index reconstruction as a warning.
- **Participant lookup:** the SMP document type scheme and the `busdox-docid-qns::` prefix are
  compared case-insensitively.
- **Peppol transport (public API):** `Recommand\WebhookEvent` is removed in favour of
  `Transport\Webhook\WebhookEvent`, which `RecommandWebhook::parse()` now returns. Its fields:
  `kind` (a `WebhookKind`) is new; `deduplicationKey` (always set) replaces `idempotencyKey`;
  `accountId` replaces `companyId`; `type`, `documentId`, `deliveryState` and `failure` are kept.
- **Peppol transport (public API):** `RecommandWebhook::verify(string $rawBody, array $headers)`
  takes the request headers instead of the signature header value.
- **Recommand (behaviour change):** without an `X-Idempotency-Key` header, the webhook
  deduplication key is `"{type}:{documentId}:{state}"` (the delivery state value, empty without
  one) instead of `null`. A signature header that appears more than once, even with identical
  values, is refused with `AuthenticationFailed`. An event type other than `document.received`,
  `document.sent` and `document.delivery_status_changed` is `WebhookKind::Other`.
- **Peppol transport (behaviour change):** a UBL document sent over Peppol needs the exact Peppol
  BIS specification identifier (BT-24) or an XRechnung pair of root element and BT-24. The
  writers' default `urn:cen.eu:en16931:2017` (an invoice without process control) is refused with
  guidance to set `new ProcessControl(PeppolBis3::CUSTOMIZATION, PeppolBis3::PROCESS)`. An
  XRechnung document must use the Peppol billing process (BT-23).
- **Peppol transport (public API):** `ValidatingTransport::__construct()` takes a
  `DocumentValidator` instead of a `Validator`; the type is wider, so existing callers keep
  working.
- **Transport (behaviour change):** Peppol Directory answers 500, 502, 503 and 504 throw the
  retryable `TransportUnavailable` instead of `UnexpectedResponse`. `FakeTransport::fetch()` and
  `acknowledge()` of an unknown id throw `UnexpectedResponse` instead of
  `InvalidArgumentException`; a queued `SendResult` with an empty id gets `fake-q-1`, `fake-q-2`,
  …, and a generated id never repeats one already returned.
- **Recommand (behaviour change):** `RecommandConfig` refuses an API key id or secret shorter than
  eight characters, without quoting it, and trims the company id. An inbox `createdAt` that is not
  an ISO 8601 time with a time zone is an `UnexpectedResponse`. A webhook status event whose
  `deliveryStatus` is `null` gives `DeliveryState::Unknown`. A whitespace-only document id throws
  `InvalidArgumentException`.
- **Participant lookup:** the SMP answer cap is 2 MiB (2,097,152 bytes, previously 2,000,000) and
  applies while the answer is read; an endpoint answer whose `DocumentIdentifier` does not repeat
  the requested document type exactly gives `Unknown`.
- **SBDH:** `EnvelopeReader` refuses an envelope whose header is not the first element or appears
  more than once, a `COUNTRY_C1` that is not two upper-case letters, a `CreationDateAndTime` that
  does not exist, and a `Standard` or `Type` that does not name the payload root.
- **Schematron compiler:** a file reached through more than one include is inlined only once, and
  a repeated inclusion of a file that declares global lets is refused (Saxon's XTSE0630). Include
  paths are compared with `/` as the separator, so Windows paths work. A compiled rule file whose
  embedded documents do not match their recorded SHA-256 is refused when it is loaded.
- **XRechnung validation:** the scenario table compiler refuses attributes and repeated elements
  the KoSIT configuration does not use; `XRechnungValidator` refuses a rule file of another
  version than the scenario names (`RulesNotInstalled`, naming both).
- **XML Schema loading:** a `file://` URI with a host other than `localhost` (a remote share) is
  refused like a network URI, with `UnusableSchema`; that includes `file://C:/x`, whose
  well-formed form is `file:///C:/x`.
- **Factur-X:** a PDF 2.0 input is embedded into with a warning, or refused with `NotPdfA` in
  strict mode; `FacturXXmlWriter` checks the reduced XML against the profile schema once more and
  throws `LogicException` on a failure; a profile schema import with an absolute or escaping
  `schemaLocation` is refused with `UnusableSchema`.
- **PDF:** a name-tree key that is not a string makes the PDF unreadable; an indirect `/Filter` on
  a cross-reference stream leads to index reconstruction; one unreadable object stream is skipped
  and counted instead of aborting the reconstruction; after a reconstruction, `size()` is the
  highest object number plus one.
- **Rule installer:** backups are hard links and each file is replaced by an atomic rename;
  `PartialInstall` names the failed files and every backup left behind.
- **Packaging:** the eight packages (five engine packages and three entry packages) share one
  version. The core requires
  `intragile/einvoicing-rules-en16931` `^1.0` and `psr/http-client` `^1.0.1`, and conflicts with
  the optional rule packages outside `^1.0`; each rule package conflicts with cores outside
  `^1.0`; `brick/math` is allowed in `^0.14.8 || ^1.0`, since fresh Laravel 13 applications lock
  version 1. The `rules-facturx` `LICENSE` is the plain Apache-2.0 text, with Mustang's external
  components note moved to `NOTICE`. The calculation code uses the camel-case `RoundingMode` cases
  (`Rounding::MODE` is `RoundingMode::HalfUp`).
- **Validation:** `Validator::validateInvoice()` on the envelope validator throws
  `LogicException`, since an envelope validator cannot validate an invoice.
- Compiled rule files are written in format 4, which adds `report` entries and the embedded
  `documents` of `document()` to format 3. Formats 2, 3 and 4 are all read, so rule files compiled
  earlier need no recompile.
- The package requires `ext-zlib`, for the PDF streams.
- `UnsupportedDocument` is no longer `final`: `ProfileMismatch`, `NotPdfA`, `UnreadablePdf` and
  other Factur-X and PDF refusals extend it, so one `catch` covers them.
- `einvoicing-rules install peppol` downloads and compiles five official files instead of four,
  adding the envelope rules (`peppol-envelope-3.0.22.php`). `check` exits 1 on a folder written
  by an earlier version until `install` is run again; `Validator::peppol()` keeps working with it.
- `install` restores the previously installed rules when moving the new files into place fails;
  `PartialInstall` is thrown only when that restore fails as well.
- The rule `Downloader` opens HTTPS streams through an injectable `StreamOpener` (default
  `PhpStreamOpener`), so its redirect, size and time limits are tested offline.
- The validation result types are `IntrAgile\EInvoicing\Validation\{Finding, DynamicError,
  Severity}` (moved from `Schematron`). `ValidationReport` renames `errors` to `dynamicErrors`,
  adds `information()`, and replaces `ruleSet` and `version` with `ruleSets`, the list of rule
  sets that ran as `"<id> <version>"`. The classes under `IntrAgile\EInvoicing\Schematron` and
  `IntrAgile\EInvoicing\XPath` are marked `@internal`, apart from their exceptions and
  `RuleSet`, which is public.
- Compiled rule files carry a `format` key and are written in format 3, which adds the optional
  message parts of an assert (`name`, `value-of`) to format 2. Formats 2 and 3 are both read, so
  Peppol rules installed by an earlier version need no reinstall; a rule file of any other format
  is refused with `UnsupportedSchematron` asking to recompile it. `CompiledRuleSetWriter` takes
  the origin named in the file header.
- The Schematron compiler refuses attributes outside those the CEN and Peppol schemas use (for
  example `pattern/@documents` or `assert/@diagnostics`) and processing instructions inside an
  assert, instead of ignoring them.
- Requires `brick/math` ^0.14.8 (previously ^0.12 or ^0.13): the first release with every
  method the core uses, and within the range Laravel 13 installs.

### Fixed

- **PDF:** a PHP deprecation raised while a PDF or Factur-X file is read no longer turns into
  `UnreadablePdf`; it goes to PHP's own error handling. Warnings and notices still do, even when
  masked or suppressed.
- **Transport:** `HttpClient` redacts a response body before it cuts the 500-byte excerpt, so a
  secret across the cut is replaced whole.
- **Peppol identifiers:** a participant identifier that is not valid UTF-8 is refused with a clear
  message.
- **PDF:** decode errors no longer keep the decoded buffers alive through their exception traces.
- **XML Schema loading:** `SchemaValidator` no longer turns unrelated PHP warnings raised during
  validation into `UnusableSchema`.
- **Factur-X:** the Factur-X XMP extension schema is written once, whatever form the existing
  packet uses, and an `rdf:RDF` nested inside a property is no longer read as the PDF/A
  declaration; comments in elements the reduction empties are removed.
- **Schematron:** compiled rule files with integer-like `document()` URIs load, and an empty list
  of message parts is refused.
- The UBL writer refuses a purchase order reference (BT-13) of exactly `NA` beside a sales
  order reference (BT-14): `NA` is the placeholder it writes when only BT-14 exists, so the
  value would be read back as absent.
- The UBL reader recognises the card network placeholder `NA` with surrounding whitespace.
- A reported amount currency names the business term of the amount (for example BT-146) and,
  for BT-111, the VAT accounting currency (BT-6) instead of always BT-5.
- Both writers check every text of the model before writing and refuse invalid UTF-8 or
  characters outside XML 1.0 with the model path and the business term (for example
  `notes[0].text (BT-22)`), instead of only the element name.
- The UBL writer writes the payment means text (BT-82) only on the first `cac:PaymentMeans`,
  not again on the one of each further credit transfer, which broke UBL-SR-46.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

# Verification

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This file records how the release was verified against the official validators, with the
validator versions, the documents checked and the results. It holds no credentials and no
account identifiers.

## D1: offline release gate

The release gate checks our own output with the official validators, offline:

- the XRechnung documents (UBL invoice, UBL credit note, CII, and the XRechnung Extension in UBL
  and CII) and the XML embedded in the XRechnung ZUGFeRD PDF go through the KoSIT validator;
- the Factur-X XML of the release invoice in every Factur-X profile goes through the official
  Factur-X Schematron (XSLT), run by SaxonC;
- both ZUGFeRD PDFs go through veraPDF.

The KoSIT configuration 2026-08-31 contains only XRechnung scenarios and no Factur-X/ZUGFeRD scenario; Factur-X MINIMUM to EXTENDED output is therefore checked with the official Factur-X 1.09.2 Schematron (XSLT) through SaxonC: no failed assert and no fired report.

The release invoice is `RE-2026-0815` and the credit note `GS-2026-0042` (`tests/Support/ReleaseInvoices.php`).
The PDFs were printed by headless Chrome from an HTML rendering of the invoice, and the Factur-X
writer embedded the XML. The commit column names the commit whose `src/` and release invoices
produced the checked documents.

| Document | Validator and version | Result | Date | Commit |
| --- | --- | --- | --- | --- |
| `RE-2026-0815-xrechnung-ubl.xml` | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung (UBL Invoice)) | 2026-11-15 | `251932e` |
| `GS-2026-0042-xrechnung-ubl-creditnote.xml` | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung (UBL CreditNote)) | 2026-11-15 | `251932e` |
| `RE-2026-0815-xrechnung-cii.xml` | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung (CII)) | 2026-11-15 | `251932e` |
| XRechnung Extension, UBL (writer output) | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung Extension (UBL Invoice)) | 2026-11-15 | `251932e` |
| XRechnung Extension, CII (writer output) | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung Extension (CII)) | 2026-11-15 | `251932e` |
| `RE-2026-0815-zugferd-xrechnung.pdf`, embedded XML | KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | accepted (EN16931 XRechnung (CII)) | 2026-11-15 | `251932e` |
| Factur-X MINIMUM (writer output) | Factur-X 1.09.2 Schematron (XSLT), SaxonC-HE 13.0 | no failed assert, no fired report | 2026-11-15 | `251932e` |
| Factur-X BASIC WL (writer output) | Factur-X 1.09.2 Schematron (XSLT), SaxonC-HE 13.0 | no failed assert, no fired report | 2026-11-15 | `251932e` |
| Factur-X BASIC (writer output) | Factur-X 1.09.2 Schematron (XSLT), SaxonC-HE 13.0 | no failed assert, no fired report | 2026-11-15 | `251932e` |
| `RE-2026-0815-zugferd-en16931.pdf`, embedded XML (EN 16931) | Factur-X 1.09.2 Schematron (XSLT), SaxonC-HE 13.0 | no failed assert, no fired report | 2026-11-15 | `251932e` |
| Factur-X EXTENDED (writer output) | Factur-X 1.09.2 Schematron (XSLT), SaxonC-HE 13.0 | no failed assert, no fired report | 2026-11-15 | `251932e` |
| `RE-2026-0815-zugferd-en16931.pdf` | veraPDF 1.30.3, PDF/A-3b validation profile | compliant | 2026-11-15 | `251932e` |
| `RE-2026-0815-zugferd-xrechnung.pdf` | veraPDF 1.30.3, PDF/A-3b validation profile | compliant | 2026-11-15 | `251932e` |

The checked artefacts, as listed in `build/release-artefacts/SHA256SUMS` (the gate compares
every file it reads with this list, and the XML files with the writer output):

```text
0774dfa63a5c9a5a37d8d6267639ff66d0d46ae5b1c0e4ba652b72efe81dd943  RE-2026-0815-zugferd-en16931.pdf
01e0340643731fe8fecb7ef3e9ee2568b6bc0c4690a75d8578058ffd5e27149f  RE-2026-0815-zugferd-xrechnung.pdf
66f64425cfd8fa7c29176d1b7c537a9ca38d306586f41bb1aae5a389c0342233  RE-2026-0815-xrechnung-ubl.xml
ec31cff8895df5e6d129cd2ca4dbe72911406edd92382ed9c4e44f1f9ff4cf4b  RE-2026-0815-xrechnung-cii.xml
b18422a962f1a724219fbcbaaf5ade7e06f3a0cba467b1b33dddbe4162998b22  GS-2026-0042-xrechnung-ubl-creditnote.xml
```

The PDF hashes change with every Chrome print; the XML hashes do not.

Finding of the first gate run: the official Factur-X rules for BASIC, EN 16931 and EXTENDED reported
PEPPOL-EN16931-R008 (a warning: empty element) on `ram:ApplicableHeaderTradeDelivery`, which the
CII schema requires and which stays empty for an invoice without any delivery data. The release
invoice now states its date of supply (BT-72), which a German invoice must show anyway. The writer
is unchanged, so an invoice without delivery data still gets this warning from the official rules
(and from our validator, whose verdict matches them).

To repeat the gate (Docker with the KoSIT, SaxonC and veraPDF images, and Google Chrome; the KoSIT
and SaxonC runners already pass `--fail-on-skipped`, and the KoSIT runner also
`--fail-on-empty-test-suite`):

```sh
CHROME_BIN="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
    timeout 300 vendor/bin/phpunit --colors=never --group release-artefacts
KOSIT_SKIP_BUILD=1 timeout 900 docker/kosit/run-release-gate.sh
ORACLE_SKIP_BUILD=1 timeout 700 docker/oracle/run.sh 8.5 --colors=never --group release-gate \
    --filter test_official_facturx_xslt_passes_own_output --fail-on-empty-test-suite
timeout 2100 vendor/bin/phpunit --colors=never --group release-gate --filter test_verapdf \
    --fail-on-skipped --fail-on-empty-test-suite
```

Each run merges its verdicts into `build/release-gate/summary.json` (not committed), with the
SHA-256 of the artefact file wherever a document was read from one. The release-artefacts run
writes new PDFs (new hashes), so the table above holds only for the files listed here.

## D2: Recommand over the Peppol test network

Run on 2026-11-15 against a Recommand playground created with `useTestNetwork: true`, so the
documents travelled over the real Peppol test infrastructure (AS4). The sending and receiving
participant is one fictitious test company of the playground (a send to itself through the test
network); its identifier is not listed here. The company verification that Recommand requires was
the simulated verification a playground offers.

| Group | Commit | Document | AS4 message id | Delivery state | Received | Acknowledged |
|---|---|---|---|---|---|---|
| core `recommand-live` | `787676d` | Peppol BIS Billing 3.0 UBL | `29855931-42d0-4578-9602-b8a60a464a13@phase4` | Delivered | yes | yes |
| core `recommand-live` | `787676d` | XRechnung 3.0 UBL | `bdf22e78-749f-4dbf-88eb-cd9ef14ff44d@phase4` | Delivered | yes | yes |
| core `recommand-live` | `787676d` | XRechnung 3.0 CII | `feea0532-c69d-40de-bf2a-6558c5b32e3f@phase4` | Delivered | yes | yes |
| bridge `recommand-live` (laravel-einvoicing) | `4f5e86a` | Peppol BIS Billing 3.0 UBL | `e4b67831-8e1c-4409-af88-06dcb311bcd4@phase4` | delivered | n/a | n/a |

The core run also passed `test_playground_send_status_pending_and_acknowledge` (4 tests, 35
assertions). The XRechnung document types were registered on the receiving company by the test
(`RECOMMAND_SETUP_DOCTYPES=1`).

## D2b–D2c: provider sandboxes (simulated network)

Run on 2026-11-15. Neither sandbox puts a document on the Peppol network: these runs prove the
provider API contract against the provider's own simulation, not Peppol delivery.

### B2Brouter sandbox (`b2brouter-live`, commit `5317f3b`, 3 tests, 63 assertions)

| Scenario | Provider state | Our state | Inbox |
|---|---|---|---|
| send to test recipient `0088:9508397101047` | `registered` | Delivered | n/a |
| send to test recipient `0088:9506215594996` | `registered` → `refused` | Delivered (refusal is a business response) | n/a |
| send to test recipient `0088:9500047420799` | `error`, reason only in `errors` ("Recipient is not registered to receive this document (sandbox simulation)"), no `error_code` | Failed, `recipient_not_found` | n/a |
| received invoice seeded by import (`issued=false`) | received | n/a | pending → fetched (number matches) → acknowledged → no longer pending |

Observed live and fixed in the 04d fix commits: B2Brouter requires the account's own company as
seller (sent) or buyer (received), validates the buyer's tax identifiers, refuses invoices dated
in a closed fiscal year, and reports some failures only in the `errors` list. The document type
list offers XRechnung 3.0 codes for UBL and CII. `GET /invoices/{id}/as/legal` answered HTTP 422
in the sandbox, so the delivered document could not be compared with the sent one.

### e-invoice.be sandbox company (`einvoicebe-live`, commit `787676d`, 3 tests, 18 assertions)

| Scenario | Provider state | Our state | Inbox |
|---|---|---|---|
| send to self (sandbox: delivered by e-mail) | `SENT` | Delivered | n/a |
| inbound seeded with the app's "Simulate received document" (release BIS 3 invoice `SIM-202611151344`) | `RECEIVED` | n/a | pending → fetched through the signed URL (number matches) → acknowledged |

The signed download URL pointed to Cloudflare R2 storage (`*.eu.r2.cloudflarestorage.com`); the
driver now downloads only from `*.r2.cloudflarestorage.com` over https. `GET /api/me/` reports no
field that marks a sandbox company, so the live test keeps its explicit
`EINVOICEBE_SANDBOX_CONFIRMED=1` opt-in.

## D3: import into Lexware Office

Run on 2026-11-16 in a Lexware Office XL trial (web app; no version number is shown). The five
release artefacts listed under D1 were uploaded unchanged (`shasum -a 256 -c SHA256SUMS`: all OK
before the upload) through "Belege" as received documents. The documents were left in the state
"zu prüfen" and not booked.

| Artefact | SHA-256 (first 12) | Lexware document format | Fields checked | Result |
|---|---|---|---|---|
| `RE-2026-0815-xrechnung-ubl.xml` | `66f64425cfd8` | XML (XRechnung 3), E-Rechnung | seller, buyer with Leitweg-ID, number, type 380, issue/due/delivery date, line, VAT category S 19 %, net 950,00, VAT 180,50, gross and payable 1.130,50, SEPA IBAN, specification and process identifiers | all match `ReleaseInvoices` |
| `RE-2026-0815-xrechnung-cii.xml` | `ec31cff8895d` | XML (XRechnung 3), E-Rechnung | seller, buyer with Leitweg-ID, number, type 380, issue/due date, VAT category S 19 %, net 950,00, VAT 180,50, gross and payable 1.130,50, SEPA IBAN | all match |
| `GS-2026-0042-xrechnung-ubl-creditnote.xml` | `b18422a962f1` | XML (XRechnung 3), E-Rechnung, booked as "Ausgabenminderung" | seller, buyer, number, type 381, preceding invoice RE-2026-0815, VAT category S 19 %, net 950,00, VAT 180,50, gross 1.130,50 | all match |
| `RE-2026-0815-zugferd-en16931.pdf` | `0774dfa63a5c` | PDF (ZUGFeRD 2 Comfort), E-Rechnung | data read from the embedded XML: supplier, number, issue and due date, gross 1.130,50 | all match |
| `RE-2026-0815-zugferd-xrechnung.pdf` | `01e034064373` | PDF (ZUGFeRD 2 XRechnung), E-Rechnung | data read from the embedded XML: supplier, number, issue and due date, gross 1.130,50 | all match |

Lexware recognised every file as an e-invoice and filled the document data from the structured
data, not by text recognition. For the two XML invoices and the credit note it showed its own
rendering of the XML, which was compared field by field; for the PDFs it showed the PDF page and
filled the document data from the embedded XML.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

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

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

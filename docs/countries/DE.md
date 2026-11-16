# Germany

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

What a German e-invoice needs, which formats and channels this package covers, and what has been
verified live and what only offline. This page is a technical guide, not legal or tax advice.

## Legal basis and dates

The obligation for domestic B2B invoices comes from the Wachstumschancengesetz (an amendment of
section 14 of the Umsatzsteuergesetz, UStG), as explained by the letters of the Federal Ministry
of Finance (BMF) of 15 October 2024 and 15 October 2025.

| What | Date | Status of our source |
|---|---|---|
| Every business must be able to **receive** an e-invoice | 1 January 2025 | Marked primary ([P]) in our research, via the BMF letter of 15 October 2025 as summarised by IHK Köln |
| **Issuing** is mandatory for businesses whose prior-year turnover is above EUR 800,000 | 1 January 2027 | same |
| **Issuing** is mandatory for everyone | 1 January 2028 | same |
| Paper and other invoice formats remain allowed during the transition | until 31 December 2026 | same |
| EDI invoices remain allowed during the transition | until the end of 2027 | **UNVERIFIED**: some sources say end of 2028 |
| B2G: federal authorities require e-invoices (ZRE and OZG-RE, over Peppol) | since 2020 | long-standing and widely known; not re-checked by us |

The dates above come from the IHK Köln summary of the BMF letter, not from the letter itself,
which we have not opened. Before you rely on a date in a product or a
contract, check it against the BMF letter "Einführung der obligatorischen elektronischen
Rechnung" of 15 October 2025. Exceptions our research lists (small-amount invoices up to EUR 250,
small businesses (Kleinunternehmer), tickets) are **UNVERIFIED** here: check the letter.

There is no central platform for B2B and no reporting obligation to the tax authority in the
current law (our research found none planned yet; **UNVERIFIED** for later changes).

## What counts as an e-invoice

An e-invoice in the German sense is a structured invoice in the EN 16931 format. A plain PDF is
not one. Of the ZUGFeRD / Factur-X profiles, MINIMUM and BASIC WL are not compliant invoices
(they carry no invoice lines); the compliant ones start at EN 16931. This is a long-standing
reading of the rules that we did not re-check against the BMF letter.

A German invoice must also state the date of supply (section 14(4) no. 6 UStG), which in the
model is BT-72. The official Factur-X rules warn about an empty
`ram:ApplicableHeaderTradeDelivery` for an invoice without delivery data
([VERIFICATION.md](../VERIFICATION.md)).

## Formats this package handles

| Format | Writing | Reading | Validation |
|---|---|---|---|
| XRechnung 3.0 CIUS, UBL Invoice | yes | yes | `XRechnungValidator`, reproducing KoSIT 1.6.3 with the configuration 2026-08-31 |
| XRechnung 3.0 CIUS, UBL CreditNote | yes | yes | same |
| XRechnung 3.0 CIUS, CII | yes | yes | same |
| XRechnung 3.0 Extension, UBL Invoice and CII | yes | yes | same |
| ZUGFeRD / Factur-X EN 16931 | yes (PDF embedding) | yes | `FacturXValidator`, official Factur-X 1.09.2 Schematron |
| ZUGFeRD / Factur-X XRECHNUNG | yes | yes | same |
| ZUGFeRD / Factur-X EXTENDED | yes | yes | same |
| ZUGFeRD / Factur-X MINIMUM, BASIC WL, BASIC | yes (XML) | MINIMUM and BASIC WL throw `UnsupportedDocument` (no lines) | same |

Not covered: the XRechnung CVD (no Peppol document type) and the deprecated UBL Credit Note
Extension are refused for sending. The package does not render the invoice PDF: you supply the
PDF/A, and the writer embeds the XML ([docs/FACTURX.md](../FACTURX.md)). See
[docs/VALIDATION.md](../VALIDATION.md) and the `XRechnung` section of the
[README](../../README.md#xrechnung).

## Channels

- **Peppol.** XRechnung 3.0 (UBL and CII) and the XRechnung 3.0 Extension go over Peppol under
  their own document types, through the Recommand, B2Brouter and e-invoice.be drivers (see
  [TRANSPORT.md](../TRANSPORT.md) for what each driver sends: e-invoice.be sends Peppol BIS
  Billing 3.0 UBL only, and B2Brouter needs a configured document type code for XRechnung). The
  receiver must be registered in its SMP for the XRechnung document type. Federal B2G invoices are
  received through ZRE and OZG-RE over Peppol.
- **E-mail.** B2B invoices may be exchanged freely, for example as an XRechnung XML or a ZUGFeRD
  PDF attached to an e-mail. This package writes and validates the document; it has no e-mail
  transport, so sending it is your code.

## Verified live and verified offline

Full results, with validator versions and dates, are in [VERIFICATION.md](../VERIFICATION.md).

| Area | How | Where |
|---|---|---|
| XRechnung UBL invoice, UBL credit note, CII, Extension (UBL, CII) | Accepted by KoSIT validator 1.6.3, XRechnung configuration 2026-08-31 | Offline (D1) |
| Factur-X XML of every profile | Official Factur-X 1.09.2 Schematron, no failed assert and no fired report | Offline (D1) |
| ZUGFeRD PDFs (EN 16931, XRECHNUNG) | veraPDF 1.30.3, PDF/A-3b | Offline (D1) |
| Import into accounting software | The five release artefacts imported into Lexware Office, recognised as e-invoices, fields matched | Live, in the vendor's application (D3) |
| Sending over Peppol | Recommand: Peppol BIS Billing 3.0 UBL, XRechnung 3.0 UBL and CII delivered, received and acknowledged | Live, Peppol test network (D2) |
| Sending through B2Brouter and e-invoice.be | Provider sandboxes, which simulate the network | Live against the provider sandbox, not Peppol delivery (D2b-D2c) |
| Webhooks | Signatures against each provider's documented scheme | Offline only |

Not verified: sending to a real German recipient on the production Peppol network, delivery of a
ZUGFeRD PDF by any channel, acceptance by the ZRE or OZG-RE federal portals, and import into
accounting software other than Lexware Office.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

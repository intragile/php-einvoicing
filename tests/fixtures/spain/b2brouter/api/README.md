# B2Brouter Spanish sandbox API shapes

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Responses of the B2Brouter API captured once on 2026-11-23, around 20:15 Europe/Warsaw time. Only the sandbox was used: a `test_` key on `https://api.b2brouter.net`. The data comes from our own Spanish sandbox account. The issuer NIF `B64829179` and the recipient NIF `A58818501` are fictitious test values, and no real company or person appears.

The capture imported two invoices one after the other: A (`LIVE-c992baa1c618`) and then B (`LIVE-75058f2f4e70`). Both were built from `SpanishInvoices::domestic()`, dated the day of the capture, written with `UblWriter`, and imported with `POST /accounts/{account}/invoices/import?send_after_import=true&issued=true&transport_type_code_for_contact=email&document_type_code_for_contact=pdf.invoice`.

| File | Source |
| --- | --- |
| `import-201.json` | B's import response (HTTP 201) |
| `invoice.json` | `GET /invoices/{id}` of B, first read |
| `tax-report.json` | `GET /tax_reports/{id}` of B's report once `registered` |
| `tax-report-previous.json` | `GET /tax_reports/{id}` of A's report once `registered`: the record before B's in the chain |

## What the capture showed

- **Wrapping.** The invoice comes wrapped in `invoice`, and the tax report in `tax_report`.
- **The import response and the first invoice read are identical** after redaction. Both are in state `sending`, and both already list the report in `tax_report_ids`.
- **Report choice.** `tax_report_ids` held one id, the one of the report read here.
- **States**, in order, with seconds since the start of the run. Reads were 3 s apart.
  - A: `signed` (1.9 s), then `registered` (5.4 s).
  - B: `signed` from 7.0 s to 82.6 s (23 reads), then `registered` (86.0 s).
  - Two earlier test runs the same evening also showed `processing` before `signed` or `registered`.
  - `signed` can last more than 60 s.
  - No `refused` or `error` state was seen.
- **`previous_id` is not a tax report id.** It belongs to a separate id space: B's `previous_id` is 292891, while A's report id is 1064651. In an earlier run, `GET /tax_reports/{previous_id}` answered HTTP 404 `resource_missing`. The chain is proven by the record's `RegistroAnterior` (the `IDFactura` and the `Huella` of A's record) only.
- **`identifier`.** The report field `identifier` holds the QR verification URL. As received (redacted copies drop the query string), B's was `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=B64829179&numserie=LIVE-75058f2f4e70&fecha=23-11-2026&importe=121.0`.
- **QR.** The `qr` field is a Base64 PNG. Decoded with chillerlan/php-qrcode, the text equals `identifier` byte for byte in both reports. Its host is `www2.agenciatributaria.gob.es`, the production ValidarQR host, even in the sandbox.
  - It equals `VerifactuQr::url(record, VerifactuEnvironment::Production)` byte for byte, with the record read from `xml_base64`.
  - It does not equal `VerifactuQr::url(record, VerifactuEnvironment::Test)`, whose host is `prewww2.aeat.es`.
  - B's decoded text: `https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=B64829179&numserie=LIVE-75058f2f4e70&fecha=23-11-2026&importe=121.0`.

## Redaction

- **Account id.** Every `account_id`, and every integer equal to the account id (such as `invoice.account.id`), is `999999`.
- **E-mail addresses** are `redacted@example.invalid`.
- **URL query strings and fragments** are dropped from every string value.
- **The API key** never appears.
- **`xml_base64`.** The record names the installation as `NumeroInstalacion` `1-<account id>`, and this value is replaced by `1-999999`.
  - The Base64 is re-encoded in B2Brouter's own layout: 60-character lines separated by `\n`, with no trailing newline.
  - Before the replacement, re-encoding the unredacted XML in that layout reproduced the received text exactly.
  - `SistemaInformatico` is not part of the `Huella` input, so the fingerprint still recomputes.
  - Apart from that value, `xml_base64`, `qr` and `fingerprint` are kept byte for byte.

## Checksums

SHA-256 of each JSON file as committed:

```
b91a6c2d58b25f12d0bdeea19683642a91ac9d872508ee9db1689e47d5845976  import-201.json
b91a6c2d58b25f12d0bdeea19683642a91ac9d872508ee9db1689e47d5845976  invoice.json
3680493c32c1b2f829ec4a4086de80432d8d06383ae44992e7843524f819339e  tax-report.json
2b67e06f2f5b3ce8e9ccb768809090c359bd6ba989f58e254c350e73e2397549  tax-report-previous.json
```

Check them with `shasum -a 256 *.json` in this folder.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

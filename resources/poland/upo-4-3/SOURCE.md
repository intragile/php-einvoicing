# KSeF UPO XML Schema (version 4-3)

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

`upo-v4-3.xsd` is an unmodified copy of the XML Schema of the KSeF official receipt (UPO,
*Urzędowe Poświadczenie Odbioru*, namespace `http://upo.schematy.mf.gov.pl/KSeF/v4-3`), the
document KSeF API 2.0 returns from `GET /sessions/{referenceNumber}/invoices/ksef/{ksefNumber}/upo`.
It is published by the Ministry of Finance of Poland (Ministerstwo Finansów). `Upo::validate()`
validates receipts against it offline.

The schema is self-contained: it imports and includes nothing. It does not declare the enveloped
`ds:Signature` that KSeF appends to every receipt, so a receipt is validated with the signature
removed. It fixes `NazwaPodmiotuPrzyjmujacego` to "Ministerstwo Finansów", while the TEST
environment (TE) writes "Ministerstwo Finansów - środowisko testowe (TE)"; that name is accepted
for receipts validated for TE only. The signature itself is not verified.

## Origin

| | |
|---|---|
| Project | [CIRFMF/ksef-api](https://github.com/CIRFMF/ksef-api) (KSeF 2.0 API documentation) |
| Commit | [`d4460b6c77616c423924254d8be1cbc2858027c6`](https://github.com/CIRFMF/ksef-api/tree/d4460b6c77616c423924254d8be1cbc2858027c6) (2026-11-17) |
| Path | `faktury/upo/schemy/` |
| Fetched | 2026-11-18 |

| File | Repository path | SHA-256 |
|---|---|---|
| `upo-v4-3.xsd` | `faktury/upo/schemy/upo-v4-3.xsd` | `1e5ff386a29324021a9e0126319680aec0b1e0d4f4a18add30b2f5d12ce6fa86` |
| `LICENSE.txt` | `LICENSE.txt` | `f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b` |

Source: `https://github.com/CIRFMF/ksef-api/blob/d4460b6c77616c423924254d8be1cbc2858027c6/<repository path>`.

The files are kept byte for byte, and a test compares every hash above with the bundled file.

## Licence

The repository's `LICENSE.txt` is the MIT licence ("Copyright (c) 2025 Ministerstwo Finansów"),
copied here unchanged. The MIT licence permits redistribution provided the copyright notice and
permission notice are included, which `LICENSE.txt` does.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

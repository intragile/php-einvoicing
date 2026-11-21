# KSeF AuthTokenRequest XML Schema

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

`schemat_auth_v2-1.xsd` is an unmodified copy of the XML Schema of the KSeF API 2.0
`AuthTokenRequest` document (namespace `http://ksef.mf.gov.pl/auth/token/2.1`), the document that
is signed with XAdES and sent to `POST /auth/xades-signature`. It is published by the Ministry of
Finance of Poland (Ministerstwo Finansów). The tests validate the requests built by
`AuthTokenRequest` against it offline.

The schema is self-contained: it imports and includes nothing. It does not declare the enveloped
`ds:Signature`, so a signed request is validated with the signature removed.

Known defect of the published schema: the `TNipVatUE` (and `TPeppolId`) patterns end with `$`,
which XML Schema regular expressions treat as a literal character, not an anchor. No real
NIP-VAT UE value matches it offline; the tests pin that this facet is the only error of such a
request. The file is kept unmodified all the same.

## Origin

| | |
|---|---|
| Project | [CIRFMF/ksef-api](https://github.com/CIRFMF/ksef-api) (KSeF 2.0 API documentation) |
| Commit | [`d4460b6c77616c423924254d8be1cbc2858027c6`](https://github.com/CIRFMF/ksef-api/tree/d4460b6c77616c423924254d8be1cbc2858027c6) (2026-11-17) |
| Path | `auth/schemy/` |
| Fetched | 2026-11-18 |

| File | Repository path | SHA-256 |
|---|---|---|
| `schemat_auth_v2-1.xsd` | `auth/schemy/schemat_auth_v2-1.xsd` | `d48477808e342892f62ef8bc7f0b134812f0536b3bb9cc8433d4fff2a926c44c` |
| `LICENSE.txt` | `LICENSE.txt` | `f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b` |

Source: `https://github.com/CIRFMF/ksef-api/blob/d4460b6c77616c423924254d8be1cbc2858027c6/<repository path>`.

The files are kept byte for byte, and a test compares every hash above with the bundled file.

## Licence

The repository's `LICENSE.txt` is the MIT licence ("Copyright (c) 2025 Ministerstwo Finansów"),
copied here unchanged. The MIT licence permits redistribution provided the copyright notice and
permission notice are included, which `LICENSE.txt` does.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

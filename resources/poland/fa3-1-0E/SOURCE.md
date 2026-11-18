# FA(3) XML Schemas

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The four XSD files in this folder are unmodified copies of the XML Schema of the Polish structured
invoice FA(3) (KSeF, form code `FA`, variant `3`, `kodSystemowy="FA (3)"`, schema version `1-0E`,
namespace `http://crd.gov.pl/wzor/2025/06/25/13775/`), published by the Ministry of Finance of
Poland (Ministerstwo Finansów). `Fa3Validator` validates FA(3) documents against them offline.

## Origin

| | |
|---|---|
| Project | [CIRFMF/ksef-api](https://github.com/CIRFMF/ksef-api) (KSeF 2.0 API documentation) |
| Commit | [`d4460b6c77616c423924254d8be1cbc2858027c6`](https://github.com/CIRFMF/ksef-api/tree/d4460b6c77616c423924254d8be1cbc2858027c6) (2026-11-17) |
| Path | `faktury/schemy/FA/` and `faktury/schemy/FA/bazowe/` |
| Fetched | 2026-11-18 |

| File | Repository path | SHA-256 |
|---|---|---|
| `schemat_FA(3)_v1-0E.xsd` | `faktury/schemy/FA/schemat_FA(3)_v1-0E.xsd` | `b646b6b525f51adf1bb2545f111fc8ca6e7aa6dd2f98948f1667d3695c06d958` |
| `StrukturyDanych_v10-0E.xsd` | `faktury/schemy/FA/bazowe/StrukturyDanych_v10-0E.xsd` | `cb08348374598e1e716e086c40d740390fb9e1bfa3aba1f4ec4cba0e1ef6d60f` |
| `ElementarneTypyDanych_v10-0E.xsd` | `faktury/schemy/FA/bazowe/ElementarneTypyDanych_v10-0E.xsd` | `8daf4d3771de200b26b697294cc906a2add3de9acfbbd97f4b1bd4fc0e5ecb2f` |
| `KodyKrajow_v10-0E.xsd` | `faktury/schemy/FA/bazowe/KodyKrajow_v10-0E.xsd` | `48be2a9f181d7ff80f185c62491ba12604c5cacbbe21af8e2aaaf2c585bbd214` |
| `LICENSE.txt` | `LICENSE.txt` | `f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b` |

Source: `https://github.com/CIRFMF/ksef-api/blob/d4460b6c77616c423924254d8be1cbc2858027c6/<repository path>`.

The files are kept byte for byte (two of them use CR line endings), and a test compares every
hash above with the bundled file.

## Offline resolution

`schemat_FA(3)_v1-0E.xsd` imports the shared type definitions with an absolute location:

```
http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/StrukturyDanych_v10-0E.xsd
```

`StrukturyDanych` includes `ElementarneTypyDanych`, which includes `KodyKrajow`, both by relative
location. Instead of editing the main schema, the validator maps the URI prefix
`http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/` onto this folder
(`Fa3Schema::localPrefixes()`), so the four files form a closed set and no network access takes
place. Any other remote reference is refused.

## Licence

The repository's `LICENSE.txt` is the MIT licence ("Copyright (c) 2025 Ministerstwo Finansów"),
copied here unchanged. The MIT licence permits redistribution provided the copyright notice and
permission notice are included, which `LICENSE.txt` does.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

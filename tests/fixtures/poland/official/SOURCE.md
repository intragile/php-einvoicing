# Official FA(3) invoice templates

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The three XML files in this folder are unmodified copies of the FA(3) invoice templates of the
official KSeF client for C# by the Ministry of Finance of Poland (Ministerstwo Finansów). They
are used only by the test suite.

## Origin

| | |
|---|---|
| Project | [CIRFMF/ksef-client-csharp](https://github.com/CIRFMF/ksef-client-csharp) |
| Branch | `main`, not pinned to a commit (the files were fetched by raw URL) |
| Path | `KSeF.Client.Tests.Core/Templates/` |
| Fetched | 2026-11-18 |

| File | SHA-256 |
|---|---|
| `invoice-template-fa-3.xml` | `9057355fdf2143415a3f4a6639a0664c203eb5fa7505da8439800802316b876b` |
| `invoice-template-fa-3-with-attachment.xml` | `7d01e9ad011b33941a2b219c3e04d63e77f6a9eccdf0a5c0d7b62b31dee763c4` |
| `invoice-template-fa-3-with-custom-Subject3.xml` | `91fa7cea12035b93745559bbb18e803cdf2718758dba9ceca93f5a22f2109fad` |
| `LICENCE.txt` (repository root) | `6fdffadac60b13d29bcca88c1bd12601766d9ccd4df8b429e8390e58999cfad4` |

Source: `https://raw.githubusercontent.com/CIRFMF/ksef-client-csharp/main/<path>`. Because `main`
moves, an upstream change to a template is not noticed automatically; the hashes above record the
state that was fetched.

The same folder upstream holds two more templates, `invoice-template-fa-3-pef.xml` and
`invoice-template-fa-3-pef-correction.xml`. They are UBL (PEF) documents, not FA(3), and are not
copied.

## Placeholders

The templates carry placeholders that the C# client replaces before sending: `#nip#` (seller
NIP), `#nipOdbiorca#` (NIP of the third party in `Podmiot3`) and `#invoice_number#`. Published
as is, each template fails the schema on the NIP pattern. The tests replace them with the
synthetic, checksum-valid NIPs `2222222222` and `3333333333` and the number `1`
(`tests/Support/PolishSamples.php`).

## Licence

The repository's `LICENCE.txt` is the MIT licence ("Copyright (c) 2025 Ministerstwo Finansów"),
copied here unchanged.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

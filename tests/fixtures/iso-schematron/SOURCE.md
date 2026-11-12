# ISO Schematron skeleton

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The two XSLT files in this folder are unmodified copies of the ISO Schematron reference
implementation ("skeleton") by Rick Jelliffe. They are used only by the test suite: the oracle
compiles Schematron files with them through Saxon, which is how OpenPeppol builds its validation
artefacts (vefa-validator). They are third-party material, MIT-licensed by their authors; see
[`LICENSE`](LICENSE).

## Origin

| | |
|---|---|
| Project | [Schematron/schematron](https://github.com/Schematron/schematron) |
| Commit | [`77dcd36c53d12ed786c144ece3b2af7694abdc56`](https://github.com/Schematron/schematron/tree/77dcd36c53d12ed786c144ece3b2af7694abdc56) (2020-11-01, the repository's latest commit on 2026-11-12) |
| Path | `trunk/schematron/code/` |

| File | SHA-256 |
|---|---|
| `iso_svrl_for_xslt2.xsl` | `0588d617924a0686255f6d182633d434c7986d561be8fcc3b363907d3f671b26` |
| `iso_schematron_skeleton_for_saxon.xsl` | `95f3195d9f437ea8ff5f75d1a27f4e68ae20b236fe0d4a217bb4209f498a10a3` |
| `LICENSE` (repository root) | `aa4e5212cc969790feda10af34b87b649efcec96432e1b71571deb7d228c6832` |

Source: `https://raw.githubusercontent.com/Schematron/schematron/77dcd36c53d12ed786c144ece3b2af7694abdc56/<path>`.

`iso_svrl_for_xslt2.xsl` is the entry point: it produces SVRL and has a single active
`xsl:import`, of `iso_schematron_skeleton_for_saxon.xsl` in the same folder (the other skeleton
imports in it are commented out). The Saxon skeleton imports nothing else, so the two files
form a closed set.

## Licence

The repository's `LICENSE` is the MIT licence ("Copyright (c) 2004-2010 Rick Jellife and Academia
Sinica Computing Centre, Taiwan"), copied here unchanged. Each XSLT file also carries the MIT
licence text in its header comment (`iso_svrl_for_xslt2.xsl`: 2004-2010,
`iso_schematron_skeleton_for_saxon.xsl`: 2000-2010, same holders). The MIT licence permits
redistribution provided the copyright notice and permission notice are included, which both the
file headers and `LICENSE` do.

The `tests/` directory is excluded from the Composer dist archive (`.gitattributes`
`export-ignore`), so these files are present in the Git repository only, never in an installed
package.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

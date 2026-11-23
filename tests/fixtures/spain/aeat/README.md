# AEAT VERI*FACTU response fixtures

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

SOAP 1.1 responses of the AEAT VERI*FACTU web service, constructed from RespuestaSuministro.xsd, RespuestaConsultaLR.xsd and the SWeb v1.0.3 examples; not AEAT output. No AEAT endpoint was contacted to produce them. Every NIF, CSV, request id and timestamp is fictitious: the issuer `89890001K` and recipient `89890002E` are the test NIFs of the AEAT hash specification, and the error descriptions are short invented texts, not the AEAT catalogue wording.

Each payload validates against its schema (`ResponseFixturesTest`, group `verifactu-official`, after `composer fetch-official -- --spain`). The invoices are the vector invoices of `tests/Support/VerifactuSamples.php`: `12345678/G33` (vector 1) and `12345679/G34` (vector 2), both issued on 01-01-2024.

| File | Content |
| --- | --- |
| `correcto.xml` | `EstadoEnvio` Correcto, CSV `A1B2C3D4E5F6G7H8`, `TiempoEsperaEnvio` 60, two `Correcto` lines (G33, G34) |
| `parcialmente-correcto.xml` | `EstadoEnvio` ParcialmenteCorrecto, lines in reverse request order: G34 `AceptadoConErrores` 2005, G33 `Incorrecto` 1142; no line for the third record of the request |
| `incorrecto.xml` | `EstadoEnvio` Incorrecto, no CSV and no `DatosPresentacion`, one `Incorrecto` line 1100 |
| `duplicado-correcta.xml` | Alta of G33 rejected as a duplicate (3000), `EstadoRegistroDuplicado` Correcta |
| `duplicado-anulada.xml` | Anulacion of G34 rejected as a duplicate (3001), `EstadoRegistroDuplicado` Anulada |
| `fault-client-4104.xml` | SOAP fault `Client`, `Codigo[4104]` (header issuer not identified) |
| `fault-client-4112.xml` | SOAP fault `Client`, `Codigo[4112]` (certificate holder not authorised) |
| `fault-server.xml` | SOAP fault `Server` without an AEAT code |
| `consulta-con-datos.xml` | Query response `ConDatos`: G33 `Correcto` with the vector 1 `Huella`, G34 `Anulado` with the vector 2 `Huella` |
| `consulta-sin-datos.xml` | Query response `SinDatos` |
| `html-502.html` | A proxy error page, as returned instead of a SOAP envelope by a failing gateway |

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

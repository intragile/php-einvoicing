# B2Brouter VERI*FACTU sandbox records

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Four VERI*FACTU `RegistroAlta` records that the B2Brouter Verifactu API generated in its sandbox on 2026-11-23, for invoices we submitted from our own sandbox account "IntrAgile ES Sandbox". They are the record XML B2Brouter returned for each tax report, kept byte for byte (`.gitattributes` marks the folder `-text -eol`) except for one value: the installation number `NumeroInstalacion`, which B2Brouter derives from the sandbox account id, is replaced by `1-999999` so that no account id is committed. `SistemaInformatico` is not an input of the `Huella`, so every record still reproduces its own fingerprint. The issuer NIF `B64829179` and the recipient NIF `A58818501` are fictitious test values with a valid CIF control digit; no real company or person appears in them. The `SistemaInformatico` block names B2Brouter as the producing software, as B2Brouter wrote it.

The tests use them as records written by other software: each one reproduces its own `Huella` from the literal text of its hash fields, although B2Brouter writes amounts as `100.0`, `21.0` and `121.0` and rates as `21.00`, and the four form one chain in file-name order.

| File | Invoice | Content | Huella |
| --- | --- | --- | --- |
| `record-1064490.xml` | PROBE-ES-0001 | F1, 21 % VAT, first record of the chain (`PrimerRegistro` S) | `1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73` |
| `record-1064493.xml` | PROBE-ES-UBL-0002 | F1, 21 % VAT, from an imported UBL invoice | `D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812` |
| `record-1064500.xml` | PROBE-ES-UBL-E-0003 | F1, exempt line `OperacionExenta` E1 | `1FC6D6D59975C2CFC2E1FE74F3E77643D47B6CB352E291B83AFC825AD7FA9928` |
| `record-1064501.xml` | PROBE-ES-CN-0005 | R1 by difference (`TipoRectificativa` I) of PROBE-ES-UBL-0002 | `3AAB5BE8A33CDFF9DE5FD4BCF0601F169D5CFAE546611CC5EEA5CA9248D84C19` |

## Checksums

SHA-256 of each file as committed, after the installation number was replaced.

```
7da540d597d96d476bf632ecaf91f68358ba44d02081b9c3c0e93e27bf7ab5ef  record-1064490.xml
371338381cb71ec48e9f9f91681b4aa16219dd758773e217bb17f7223d625e60  record-1064493.xml
f5e6d02319703369d7c549dd8b75fc655cc5928651726e7cc39541e32ceda91f  record-1064500.xml
ef88a008675373823720bf681fb463180a1756f4a699dabf75852e98a9d07179  record-1064501.xml
```

Check them with `shasum -a 256 record-*.xml` in this folder.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

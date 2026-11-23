# B2Brouter VERI*FACTU sandbox records

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

Four VERI*FACTU `RegistroAlta` records that the B2Brouter Verifactu API generated in its sandbox on 2026-11-23, for invoices we submitted from our own sandbox account "IntrAgile ES Sandbox". They are the record XML B2Brouter returned for each tax report, kept byte for byte (`.gitattributes` marks the folder `-text -eol`). The issuer NIF `B64829179` and the recipient NIF `A58818501` are fictitious test values with a valid CIF control digit; no real company or person appears in them. The `SistemaInformatico` block names B2Brouter as the producing software, as B2Brouter wrote it.

The tests use them as records written by other software: each one reproduces its own `Huella` from the literal text of its hash fields, although B2Brouter writes amounts as `100.0`, `21.0` and `121.0` and rates as `21.00`, and the four form one chain in file-name order.

| File | Invoice | Content | Huella |
| --- | --- | --- | --- |
| `record-1064490.xml` | PROBE-ES-0001 | F1, 21 % VAT, first record of the chain (`PrimerRegistro` S) | `1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73` |
| `record-1064493.xml` | PROBE-ES-UBL-0002 | F1, 21 % VAT, from an imported UBL invoice | `D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812` |
| `record-1064500.xml` | PROBE-ES-UBL-E-0003 | F1, exempt line `OperacionExenta` E1 | `1FC6D6D59975C2CFC2E1FE74F3E77643D47B6CB352E291B83AFC825AD7FA9928` |
| `record-1064501.xml` | PROBE-ES-CN-0005 | R1 by difference (`TipoRectificativa` I) of PROBE-ES-UBL-0002 | `3AAB5BE8A33CDFF9DE5FD4BCF0601F169D5CFAE546611CC5EEA5CA9248D84C19` |

## Checksums

SHA-256 of each file, taken from the captured source before copying and from the copy here afterwards; both lists are identical.

```
38d82eb87e215d7d8fbf8947b956b801d9aa5562434e2cd20b5639e270202757  record-1064490.xml
36a20a23289cb27621dc25347f3e57bd95bb9757f39561ab7ba6f6b0b1fe8d60  record-1064493.xml
398bf42ef7954fd02916f3341007a9337c6fe120a9eed02fb72f65bde09569c0  record-1064500.xml
156881adbf2c54c3649857f0cef0127ef025fc34b4f06fc701e694572f5bff94  record-1064501.xml
```

Check them with `shasum -a 256 record-*.xml` in this folder.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

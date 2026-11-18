# FA(3) brochure examples

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

The XML files in this folder are FA(3) invoices written by hand for the test suite. Each one
re-creates the facts of an example in the FA(3) information brochure of the Ministry of Finance
of Poland ("Broszura informacyjna dotycząca struktury logicznej FA(3)"). They carry facts only:
the parties, addresses and bank account are synthetic (checksum-valid synthetic NIPs
`2222222222` and `1111111111`), and no brochure text is copied. Where the brochure gives no
amount, the amounts are ours. Every file validates against the bundled FA(3) schema.

| File | Brochure example | What it re-creates |
|---|---|---|
| `correction-before-after.xml` | 14 and 23 (second method) | Corrective invoice (KOR) of 2026-09-10 for invoice `FV/110/07/2026` of 2026-07-14 issued in KSeF (the KSeF number's checksum recomputed: `9999999999-20260714-D5FB0C9ED490-19`). Returned goods shown as state-before (`StanPrzed`) and state-after rows that reuse the row numbers 1, 1, 2, 2: blanket 3 × 90 → 2 × 90, bedding 5 × 120 → 3 × 120, all at 23 %. `P_13_1` −330.00, `P_14_1` −75.90, `P_15` −405.90. |
| `advance-eur.xml` | 16 | Advance invoice (ZAL) in EUR documenting three partial advances: 2026-09-10 500 at 4.4512, 2026-09-15 2500 at 4.4724, 2026-09-20 1000 at 4.5148. `P_13_1` 3252.03, `P_14_1` 747.97, `P_14_1W` 3351.17, which is our figure, not the brochure's (the brochure prints no PLN VAT): the EUR VAT of each advance rounded to 2 decimals, multiplied by that advance's exchange rate and rounded (416.19 + 2090.76 + 844.22); converting the unrounded EUR VAT gives 3351.16, `P_15` 4000.00; order value 10000.00 in one order line. |
| `settlement-roz.xml` | 21 | Settlement invoice (ROZ) of 2026-09-10 for the advance invoices `FZ/123/07/2025` and `FZ/133/08/2025` issued outside KSeF (`NrKSeFZN`). Full order value in the row and buckets, `P_15` 3690.00 still due. |
| `margin-used-car.xml` | 13 | Supply of a used car under the margin scheme: `P_PMarzy` 1, `P_PMarzy_3_1` 1, one row without `P_12`, `P_13_11` and `P_15` 50000.00. |
| `rozliczenie.xml` | 24 | Legal service of 200.00 net at 23 % (`P_15` 246.00) with a charge of 17.00 (stamp duty refund) and a deduction of 300.00 (customer balance) in `Rozliczenie`, leaving `DoRozliczenia` 37.00. |

`tests/Fixtures.php` lists the files (`Fixtures::polandBrochure()`), and
`tests/Syntax/Fa3/Fa3RoundTripTest.php` reads, re-writes and compares them.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

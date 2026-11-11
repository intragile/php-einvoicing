# PHP E-Invoicing

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

**Keretrendszer-független EN 16931 e-számlázási mag PHP-hoz.** Pontos tizedes számításra épülő
számlamodellt, EN 16931 szerinti összesítést, valamint a szabványhoz tartozó két szintaxis
(UBL 2.1 és UN/CEFACT CII) olvasását és írását fogja nyújtani.

[English description → README.md](README.md)

> **Állapot:** fejlesztés alatt, még nincs kiadva. Nyilvános API még nincs.

## Célok

- EN 16931-1:2017+A1, a hivatalos CEN validációs csomaggal ellenőrizve
  ([1.3.17-es kiadás](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17)).
- Összegekhez, mennyiségekhez, árakhoz és kulcsokhoz nincs lebegőpontos szám; minden számítás
  a [brick/math](https://github.com/brick/math) csomaggal történik.
- A beérkező számlák biztonságos feldolgozása: nincs DOCTYPE, nincs külső entitás, nincs
  hálózati hozzáférés.
- Futásidejű függőségek: csak `ext-dom`, `ext-libxml`, `ext-mbstring` és `brick/math`.

## Követelmények

PHP 8.3 vagy újabb.

## Tesztelés

```bash
composer install
vendor/bin/phpunit
```

A tesztekhez használt hivatalos CEN mintaszámlák és validációs stíluslapok a
`tests/fixtures/official/` mappában vannak. Ezekre az EUPL v1.2 licenc vonatkozik, nem az MIT,
és nem részei a telepített csomagnak; részletek:
[tests/fixtures/official/SOURCE.md](tests/fixtures/official/SOURCE.md).

## Biztonság

Lásd: [SECURITY.md](SECURITY.md).

## Licenc

MIT. Lásd: [LICENSE](LICENSE).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

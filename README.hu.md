# PHP E-Invoicing

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

**Keretrendszer-független EN 16931 e-számlázási mag PHP-hoz.** Pontos tizedes számításra épülő
számlamodell, EN 16931 szerinti összesítés, valamint a szabványhoz tartozó két szintaxis
(UBL 2.1 és UN/CEFACT CII) olvasása és írása. Futásidőben nincs szükség Javára.

[English description → README.md](README.md)

> **Állapot:** kiadatlan, fejlesztés alatt. Az API még változhat.

## Mi van benne, és mi nincs

Jelenleg tartalmazza:

- A teljes EN 16931-1:2017+A1 szemantikai modell változtathatatlan PHP osztályokként
  ([fogalom–osztály tábla](docs/MODEL.md)).
- Kódlisták típusos enumként, a CEN 1.3.17 validációs csomagból generálva.
- `TotalsCalculator`: sorok nettó összege, ÁFA-bontás és számlaösszesítők pontos tizedesekkel.
- UBL 2.1 (Invoice, CreditNote) és UN/CEFACT CII (CrossIndustryInvoice) olvasó és író.

Későbbi kiadásokra tervezett, jelenleg nincs benne: tisztán PHP-s validátor az EN 16931 üzleti
szabályaihoz, Peppol-átvitel, az XRechnung és a ZUGFeRD/Factur-X profilok, valamint Laravel
csomag. Amíg a validátor nincs meg, a könyvtár nem mondja meg, hogy egy számla érvényes-e; mi
lett ellenőrizve, azt az [Ellenőrzés](#ellenőrzés) szakasz írja le.

## Telepítés

PHP 8.3 vagy újabb, valamint `ext-dom`, `ext-libxml` és `ext-mbstring` szükséges.

A csomag még nincs publikálva. Publikálás után a telepítés:

```bash
composer require intragile/php-einvoicing
```

## Számla létrehozása

A gyakori eset rövid. Az összegeket, mennyiségeket, árakat és kulcsokat szövegként vagy
egész számként kell megadni, és `Brick\Math\BigDecimal` formában tárolódnak. Mindig kapcsolja be a
`declare(strict_types=1)`-et: azzal a `float` átadása `TypeError`; nélküle a PHP csendben
átalakítja az értéket.

```php
<?php

declare(strict_types=1);

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\{CountryCode, CurrencyCode, InvoiceTypeCode, UnitCode, VatCategory};
use IntrAgile\EInvoicing\Model\{Invoice, InvoiceLine, Item, LineVat, Party, PostalAddress, Price};
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;

$seller = new Party('Eladó Kft.', vatIdentifier: 'HU12345678', address: new PostalAddress(CountryCode::HU, city: 'Budapest', postCode: '1011', line1: 'Fő utca 1.'));
$buyer = new Party('Vevő Zrt.', vatIdentifier: 'HU87654321', address: new PostalAddress(CountryCode::HU, city: 'Szeged', postCode: '6720'));
$vat = new LineVat(VatCategory::StandardRate, '27');

$invoice = new Invoice(
    number: 'INV-2026-0001',
    issueDate: new DateTimeImmutable('2026-11-11'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::HUF,
    seller: $seller,
    buyer: $buyer,
    lines: [
        new InvoiceLine('1', '2', UnitCode::C62, new Price('10000'), new Item('Consulting'), $vat),
        new InvoiceLine('2', '1.5', UnitCode::HUR, new Price('8000'), new Item('Support'), $vat),
    ],
);

$invoice = TotalsCalculator::complete($invoice); // line net amounts, VAT breakdown, totals

$ubl = (new UblWriter)->write($invoice); // UBL 2.1 XML string
$cii = (new CiiWriter)->write($invoice); // UN/CEFACT CII XML string
```

A `TotalsCalculator::complete()` csak azt tölti ki, amit üresen hagyott, és megtartja a megadott
értékeket. Az ÁFA-mentességi okot (BT-120/BT-121) az olyan kategóriáknál, mint E, AE, K vagy O,
valamint az elszámolási pénznemben megadott ÁFA-összeget (BT-111) Önnek kell megadnia. A
`TotalsCalculator::check()` visszaadja azokat a BR-CO számítási szabályokat, amelyeket a számla
megsért. Az írók a végösszesítők nélküli számlát elutasítják.

## Beérkezett számla olvasása

```php
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;

try {
    $result = (new UblReader)->read($xml); // vagy (new CiiReader)->read($xml)
} catch (UnsafeXml | UnsupportedDocument $e) {
    // a dokumentum elutasítása
}

$invoice = $result->invoice;  // IntrAgile\EInvoicing\Model\Invoice
$result->ignored;             // list<string>: a nem leképezett elemek útvonalai
```

Mindkét olvasó `ReadResult`-ot ad vissza. A modell által nem ismert elemeket (kiterjesztéseket,
újabb szintaxisverzió elemeit) az olvasó kihagyja, és a `ReadResult::$ignored` listában
felsorolja, nem pedig hibával leáll. Csak az olyan adat hiánya tesz egy dokumentumot nem
támogatottá, amely nélkül a modell nem létezhet.

## Biztonság: rosszindulatú XML

A beérkező számlák megbízhatatlan bemenetek. Az olvasók az XML-t a `SafeXml`-en keresztül
töltik be: bármilyen DOCTYPE-deklarációt `UnsafeXml` hibával elutasítanak, még mielőtt entitás
definiálódhatna vagy kibontódhatna; az elemző `LIBXML_NONET` kapcsolóval fut, a `LIBXML_NOENT`
és a `LIBXML_DTDLOAD` pedig soha nincs használva. Külső entitás, hálózati hozzáférés és
entitás-kibontásos támadás (billion laughs) így nem lehetséges. Sérülékenység bejelentése:
[SECURITY.md](SECURITY.md).

## Ellenőrzés

- A hivatalos CEN mintaszámlák
  ([1.3.17-es kiadás](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/tag/validation-1.3.17))
  olvasását, írását és oda-vissza átalakítását a tesztcsomag ellenőrzi, a szintaxisok közötti
  olvasással együtt.
- Egy opcionális tesztcsoport a hivatalos CEN validációs stíluslapokat (XSLT 2.0) futtatja
  SaxonC-HE-vel Dockerben, referencia-ellenőrzőként (oracle): az írók által előállított
  dokumentumoknak át kell menniük a hivatalos szabályokon, magát az ellenőrzőt pedig az
  ellenőrzi, hogy szándékosan elrontott végösszegre szabályt vált ki (BR-CO-16). A
  `docker/oracle/run.sh 8.5` felépíti a képet és lefuttatja a csoportot; Docker és telepített
  `vendor/` kell hozzá. Ez csak fejlesztői eszköz, nem része a telepített csomagnak.
- A CI PHPUnitot és PHPStant (max szint) futtat PHP 8.3, 8.4 és 8.5 alatt, továbbá Pintet és
  az oracle-csoportot.

## Fejlesztés

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
php bin/generate-model-doc.php   # újragenerálja a docs/MODEL.md-t
```

## Licenc és forrásmegjelölés

A könyvtár MIT licencű ([LICENSE](LICENSE)). A tesztekben használt CEN validációs csomag
EUPL v1.2 licenc alatt áll, nem része a telepített csomagnak, leírása:
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md). A `src/CodeList/`
kódlistaértékei ebből származnak; a forrásmegjelölést lásd:
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

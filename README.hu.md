# PHP E-Invoicing

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

**Keretrendszer-független EN 16931 e-számlázási mag PHP-hoz.** Pontos tizedes számításra épülő
számlamodell, EN 16931 szerinti összesítés, valamint a szabványhoz tartozó két szintaxis
(UBL 2.1 és UN/CEFACT CII) olvasása és írása, valamint ellenőrzés a hivatalos CEN EN 16931
szabályok szerint. Futásidőben nincs szükség Javára.

[English description → README.md](README.md)

> **Állapot:** kiadatlan, fejlesztés alatt. Az API még változhat.

## Mi van benne, és mi nincs

Jelenleg tartalmazza:

- A teljes EN 16931-1:2017+A1 szemantikai modell változtathatatlan PHP osztályokként
  ([fogalom–osztály tábla](docs/MODEL.md)).
- Kódlisták típusos enumként, a CEN 1.3.17 validációs csomagból generálva.
- `TotalsCalculator`: sorok nettó összege, ÁFA-bontás és számlaösszesítők pontos tizedesekkel.
- UBL 2.1 (Invoice, CreditNote) és UN/CEFACT CII (CrossIndustryInvoice) olvasó és író.
- Tisztán PHP-s validátor, amely a hivatalos CEN EN 16931 Schematron-szabályokat (1.3.17)
  futtatja UBL-re és CII-re; az eredménye bizonyítottan azonos a hivatalos XSLT-ével
  ([hogyan működik](docs/VALIDATION.md), angolul).

Későbbi kiadásokra tervezett, jelenleg nincs benne: a Peppol BIS 3 szabályai és a Peppol-átvitel,
az XRechnung és a ZUGFeRD/Factur-X profilok, valamint Laravel csomag.

## Telepítés

PHP 8.3 vagy újabb, valamint `ext-dom`, `ext-libxml` és `ext-mbstring` szükséges.

A csomag még nincs publikálva. Publikálás után a telepítés:

```bash
composer require intragile/php-einvoicing
```

## Számla létrehozása

A gyakori eset rövid. Az összegeket, mennyiségeket, árakat és kulcsokat szövegként vagy
egész számként kell megadni, és `Brick\Math\BigDecimal` formában tárolódnak. A `float` értéket a
könyvtár futásidőben elutasítja egy `InvalidArgumentException` kivétellel, amely megnevezi az
üzleti fogalmat (BT). Ez a `declare(strict_types=1)` meglététől függetlenül így működik, mert a
float a 0,1-et sem tudja pontosan tárolni. Ismert korlát: `strict_types` nélküli fájlban a PHP a
`bool` értéket még a könyvtár előtt egész számmá alakítja (`true`-ból 1 lesz). A `bool`
argumentumot a PHPStan a PHPDoc típusok alapján jelzi.

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

A `TotalsCalculator::complete()` kitölti az üresen hagyott tételsori nettó összegeket és
ÁFA-összesítő sorokat, a megadottakat pedig megtartja. A számla végösszesítőit mindig újraszámolja,
így azok egyeznek a tételsorokkal. Az előre kifizetett összeget (BT-113) és a kerekítési összeget
(BT-114) argumentumként kell átadni, például
`TotalsCalculator::complete($invoice, paidAmount: '500.00')`. Az ÁFA-mentességi okot (BT-120/BT-121) az olyan kategóriáknál, mint E, AE, K vagy O,
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

## Számla ellenőrzése

```php
use IntrAgile\EInvoicing\Validation\Validator;

$report = Validator::en16931()->validate($xml); // UBL Invoice, UBL CreditNote vagy CII

if (! $report->isValid()) {
    foreach ($report->fatal() as $finding) {
        echo "{$finding->ruleId} itt: {$finding->location}: {$finding->message}\n";
    }
    foreach ($report->errors as $error) {   // ki nem értékelhető értékek, például "12,50"
        echo "{$error->errorCode} a(z) {$error->ruleId} szabályban itt: {$error->location}\n";
    }
}
```

A modellel összeállított számlát egy hívás megírja és ellenőrzi:
`Validator::en16931()->validateInvoice($invoice)` (UBL) vagy
`->validateInvoice($invoice, Syntax::Cii)` (`IntrAgile\EInvoicing\Syntax\Syntax`).

A validátor a hivatalos CEN EN 16931 szabályokat futtatja (1.3.17-es validációs csomag: UBL-re
982, CII-re 810 ellenőrzés), amelyeket a hivatalos Schematronból PHP-ra fordítottunk; nincs
szükség Javára vagy XSLT-feldolgozóra. Az `isValid()` akkor igaz, ha egyetlen fatális szabály sem
sérült; a figyelmeztetéseket a `warnings()` adja vissza, ezek nem teszik érvénytelenné a számlát.
Ha egy érték nem értékelhető ki (például összegként `abc` szerepel), az ellenőrzés nem áll le: a
hiba a `$report->errors` listába kerül az XPath-hibakóddal (`FORG0001`), a többi szabály
ellenőrzése folytatódik, a számla pedig érvénytelen. Ha a dokumentum nem támogatott szintaxisú
számla, `UnsupportedDocument`, ha DOCTYPE-ot tartalmaz, `UnsafeXml` kivétel keletkezik; egyiket
sem minősíti érvényesnek.

Minden eredményt a Saxonnal futtatott hivatalos CEN XSLT-vel vetünk össze: a 34 CEN mintaszámla,
1169 CEN egységteszt-dokumentum és 680 gépileg módosított (mutált) változat pontosan ugyanazokat a
szabályazonosítókat és szinteket adja. Egy átlagos számla ellenőrzése 7–50 ms, egy 1000 tételes
számláé körülbelül 1,7 s (Apple M1, PHP 8.5). A támogatott XPath-részhalmazt, azokat az eseteket,
ahol a motor a Saxon viselkedését követi, a bizonyítást és a méréseket a
[docs/VALIDATION.md](docs/VALIDATION.md) írja le (angolul).

A lefordított szabályok a CEN-csomag származékos művei, ezért **EUPL-1.2** licencűek. Külön
csomagban vannak (`intragile/einvoicing-rules-en16931`, ebben a tárolóban a
`packages/rules-en16931` mappa), amelyet ez a csomag függőségként behúz; maga a motor MIT licencű
marad. Egyik csomag sincs még publikálva.

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
  ellenőrzi, hogy szándékosan elrontott végösszegre szabályt vált ki (BR-CO-16), a validátor
  eredményének pedig a megfelelőségi korpusz minden dokumentumán meg kell egyeznie az oracle-éval
  ([részletek](docs/VALIDATION.md#how-verdict-equality-is-proven), angolul). A
  `docker/oracle/run.sh 8.5` felépíti a képet és lefuttatja a csoportot; Docker és telepített
  `vendor/` kell hozzá. Ez csak fejlesztői eszköz, nem része a telepített csomagnak.
- A CI PHPUnitot és PHPStant (max szint) futtat PHP 8.3, 8.4 és 8.5 alatt, továbbá Pintet, az
  oracle-csoportot, és ellenőrzi, hogy a lefordított szabályok egyeznek a hivatalos Schematronnal.

## Fejlesztés

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint --test
php bin/generate-model-doc.php   # újragenerálja a docs/MODEL.md-t
composer compile-rules           # újrafordítja a szabályokat a hivatalos Schematronból
```

## Licenc és forrásmegjelölés

A könyvtár MIT licencű ([LICENSE](LICENSE)). A tesztekben használt CEN validációs csomag
EUPL v1.2 licenc alatt áll, nem része a telepített csomagnak, leírása:
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md). A `src/CodeList/`
kódlistaértékei ebből származnak; a forrásmegjelölést lásd:
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md). A `packages/rules-en16931` mappában lévő
lefordított validációs szabályok EUPL-1.2 licencűek; lásd:
[packages/rules-en16931/SOURCE.md](packages/rules-en16931/SOURCE.md).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

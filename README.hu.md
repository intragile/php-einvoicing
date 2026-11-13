# PHP E-Invoicing

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

**Keretrendszer-független EN 16931 e-számlázási mag PHP-hoz.** Pontos tizedes számításra épülő
számlamodell, EN 16931 szerinti összesítés, a szabványhoz tartozó két szintaxis (UBL 2.1 és
UN/CEFACT CII) olvasása és írása, ellenőrzés a hivatalos CEN EN 16931 és a Peppol BIS Billing 3
szabályok szerint, valamint küldés és fogadás Peppolon egy hozzáférési ponton keresztül.
Futásidőben nincs szükség Javára.

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
  futtatja UBL-re és CII-re; az eredményének egyezését a hivatalos XSLT-ével 1892 dokumentumból
  álló korpuszon ellenőriztük ([hogyan működik](docs/VALIDATION.md), angolul).
- Ellenőrzés a Peppol BIS Billing 3.0.22 szabályai szerint, amelyeket a hivatalos OpenPeppol
  fájlokból a saját gépeden fordít le a csomag; az eredmény egyezését a Saxonéval 1611
  dokumentumból álló korpuszon ellenőriztük ([Peppol BIS 3](docs/PEPPOL.md), angolul). A
  Peppol-boríték (SBDH) szabályait ugyanígy lefordítja és ellenőrzi.
- XRechnung 3.0.2 ellenőrzés, amely a KoSIT validátor 1.6.3 működését követi a 2026-08-31-es
  konfigurációval (forgatókönyvek, XML Schema, egyedi szintek), az opcionális, Apache-2.0 licencű
  `intragile/einvoicing-rules-xrechnung` szabálycsomaggal ([XRechnung](#xrechnung)), valamint
  XRechnung írása a modellből.
- Peppol-átvitel: küldés és fogadás hozzáférési ponton keresztül (Recommand-meghajtó), résztvevő
  keresése az SML-ben és az SMP-ben, keresés a Peppol Directoryban, webhookok ellenőrzése, valamint
  SBDH-boríték írása és olvasása ([Peppol-átvitel](docs/TRANSPORT.md), angolul). Az éles küldést a
  Recommandon keresztül még nem ellenőriztük.

Későbbi kiadásokra tervezett, jelenleg nincs benne: további hozzáférésipont-meghajtók, CII- és
önszámlázó (self-billing) számlák küldése Peppolon, a ZUGFeRD/Factur-X profil,
valamint Laravel csomag.

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
`TotalsCalculator::complete($invoice, paidAmount: '500.00')`. Az ÁFA-mentességi okot
(BT-120/BT-121) az olyan kategóriáknál, mint E, AE, K vagy O, valamint az elszámolási pénznemben
megadott ÁFA-összeget (BT-111) neked kell megadnod. A `TotalsCalculator::check()` visszaadja
azokat a BR-CO számítási szabályokat, amelyeket a számla megsért. Az írók a végösszesítők nélküli
számlát elutasítják.

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
    foreach ($report->dynamicErrors as $error) {   // ki nem értékelhető értékek, például "12,50"
        echo "{$error->errorCode} a(z) {$error->ruleId} szabályban itt: {$error->location}\n";
    }
}
```

A modellel összeállított számlát egy hívás megírja és ellenőrzi:
`Validator::en16931()->validateInvoice($invoice)` (UBL) vagy
`->validateInvoice($invoice, Syntax::Cii)` (`IntrAgile\EInvoicing\Syntax\Syntax`).

A validátor a hivatalos CEN EN 16931 szabályokat futtatja (1.3.17-es validációs csomag: UBL-re
982, CII-re 810 ellenőrzés), amelyeket a hivatalos Schematronból PHP-ra fordítottunk; nincs
szükség Javára vagy XSLT-feldolgozóra. Az `isValid()` akkor igaz, ha egyetlen `fatal` súlyosságú
szabály sem sérült; a figyelmeztetések (`warnings()`) és a tájékoztató megállapítások
(`information()`) nem teszik érvénytelenné a számlát, a `$report->ruleSets` pedig a lefutott
szabálykészleteket nevezi meg (`en16931-ubl 1.3.17`). Ha egy érték nem értékelhető ki (például
összegként `abc` szerepel), az ellenőrzés nem áll le: a hiba a `$report->dynamicErrors` listába
kerül az XPath-hibakóddal (`FORG0001`), a többi szabály ellenőrzése folytatódik, a számla pedig
érvénytelen. Ha a dokumentum nem támogatott szintaxisú számla (vagy nem is számla),
`UnsupportedDocument` kivétel keletkezik, DOCTYPE esetén `UnsafeXml`; a validátor egyiket sem
minősíti érvényesnek.

Az eredmények egyezését a Saxonnal futtatott hivatalos CEN XSLT-vel 1892 dokumentumból álló
korpuszon ellenőriztük (34 CEN mintaszámla, 1169 CEN egységteszt-dokumentum, 680 gépileg
módosított (mutált) változat, 6 modellből írt számla és 3 célzottan összeállított dokumentum).
Ez tesztkorpusz, nem bizonyítás minden lehetséges bemenetre. Az 1757 dokumentumon, amelyről a
Saxon eredményt ad, ugyanazok a szabályazonosítók és súlyossági szintek jönnek ki; azon a 135
mutált változaton, amelyen a Saxon dinamikus hibával leáll, csak az XPath-hibakódot hasonlítjuk
össze, a megállapításokat nem.

Egy átlagos számla ellenőrzése 7–50 ms, egy 1000 tételes számláé körülbelül 1,7 s (egyszeri mérés
a fejlesztői gépen: Apple M1, PHP 8.5). Egyes szabályok ismétlődő elemeknél a lineárisnál
gyorsabban lassulnak (az UBL-SR-47 a fizetési mód kódok számában négyzetes), ezért kívülről
érkező, megbízhatatlan fájloknál korlátozd a bemenet méretét és az ellenőrzés idejét. A
támogatott XPath-részhalmazt, azokat az eseteket, ahol a motor a Saxon viselkedését követi, az
oracle által ellenőrzötteket, az erőforrás-igényt és a méréseket a
[docs/VALIDATION.md](docs/VALIDATION.md) írja le (angolul).

A lefordított szabályok a CEN-csomag származékos művei, ezért **EUPL-1.2** licencűek. Külön
csomagban vannak (`intragile/einvoicing-rules-en16931`, ebben a tárolóban a
`packages/rules-en16931` mappa), amelyet ez a csomag függőségként behúz; maga a motor MIT licencű
marad. Egyik csomag sincs még publikálva.

## XRechnung

Az `XRechnungValidator` úgy ellenőrzi az XRechnung 3.0.2 számlákat, ahogy a KoSIT validátor
1.6.3 a 2026-08-31-es XRechnung-konfigurációval. Ehhez a külön szabálycsomag kell, amelyet ez a
csomag csak javasol, nem húz be:

```bash
composer require intragile/einvoicing-rules-xrechnung
```

```php
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

$report = XRechnungValidator::create()->validate($xml);
$report->scenario;   // "EN16931 XRechnung (UBL Invoice)"
$report->isValid();  // false XML Schema-hibánál vagy bármely fatal eredménynél
```

Az első olyan KoSIT-forgatókönyv fut le, amelynek a feltétele teljesül: XRechnung CIUS,
Extension vagy CVD UBL számlára, UBL jóváírásra (CIUS és CVD) vagy CII-re, illetve a sima
EN 16931. A dokumentumot a forgatókönyv XML Schemája szerint ellenőrzi, és csak ha az rendben
van, a CEN 1.3.16 és az XRechnung 2.6.0 Schematron szerint; ezután a forgatókönyv egyedi szintjei
felülírják a szabályok besorolását (a `CII-SR-465` XRechnung CII-nél fatal). Ha a dokumentum
specifikáció-azonosítója (BT-24) egyik forgatókönyvvel sem egyezik pontosan, kivételt kapsz,
amely felsorolja az elfogadott azonosítókat. XRechnung írásához tedd a számlára az
`XRechnung::processControl()` értékét. A cél a KoSIT-tal azonos eredmény: forgatókönyv, XML
Schema-érvényesség, a szabályazonosítók szintjükkel, és az elfogadás; tesztoracle-ként maga a
KoSIT validátor fut ([docs/VALIDATION.md](docs/VALIDATION.md), angolul). A szabálycsomag
Apache-2.0 licencű, mint a KoSIT-anyagok, amelyekből fordul.
## Peppol BIS 3

A `Validator::peppol($dir)` a Peppol BIS Billing 3.0.22 szabályai szerint ellenőriz: az
OpenPeppol saját CEN-szabálymásolata (CEN 1.3.16) és a Peppol-szabályok futnak le, UBL-re és
CII-re. Az OpenPeppol licenc nélkül teszi közzé a szabályfájljait, ezért ez a csomag semmilyen
Peppolból származó fájlt nem terjeszt. Egyetlen parancs letölti a hivatalos fájlokat egy rögzített
kiadásból, ellenőrzi az SHA-256 ellenőrzőösszegüket, és a saját gépeden lefordítja őket;
hálózat nélküli szerveren a `--from=DIR` egy helyi másolatból fordít.

```bash
vendor/bin/einvoicing-rules install peppol --dir=var/peppol-rules
```

```php
$report = Validator::peppol(__DIR__.'/var/peppol-rules')->validate($xml);
```

Az eredmények egyezését a Saxonnal futtatott OpenPeppol Schematronnal 1611 dokumentumból álló
korpuszon ellenőriztük, eltérés nélkül; ahol a Saxon leáll (101 dokumentum), ott csak a
hibakódot hasonlítjuk össze. A `Validator::peppolEnvelope($dir)` egy Standard Business
Document borítékát (SBDH) ellenőrzi a hivatalos borítékszabályok szerint. Telepítés, üzemeltetés, az ellenőrzés
részletei és mérések: [docs/PEPPOL.md](docs/PEPPOL.md) (angolul).

## Küldés Peppolon

```php
$transport = new ValidatingTransport(
    new RecommandTransport(new RecommandConfig($apiKeyId, $secret, $companyId), $http),
    Validator::peppol(__DIR__.'/var/peppol-rules'),
);

$result = $transport->send(OutboundDocument::fromXml($ublXml));
```

A `$http` bármilyen PSR-18 klienst (Guzzle, Symfony) és PSR-17 gyárakat csomagol be; maga a csomag
csak a PSR-interfészektől függ. A küldőt, a címzettet, a dokumentumtípust és a folyamatot a
számlából olvassa ki. A meghajtót mindig csomagold `ValidatingTransport`-ba: az érvénytelen
dokumentumot még elküldés előtt visszautasítja. Minden hiba `isRetryable()` metódusa megmondja,
biztonságos-e az újrapróbálás; az `OutcomeUnknown` azt jelenti, hogy a hozzáférési pont
befogadhatta a dokumentumot, ezért újraküldés előtt ellenőrizd. Résztvevő keresése (SML és SMP),
a Peppol Directory, fogadás lekérdezéssel vagy webhookkal, az SBDH-boríték, mit és hogyan
ellenőriztünk, és az ismert korlátok: [docs/TRANSPORT.md](docs/TRANSPORT.md) (angolul). Az éles
küldést a Recommandon keresztül még nem ellenőriztük; a meghajtót a Recommand közzétett
API-leírásából kézzel írt mintaválaszokkal teszteljük.

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
  ([részletek](docs/VALIDATION.md#how-verdict-equality-is-checked), angolul). Ugyanez a
  csoport az OpenPeppol Schematront az ISO Schematron-vázzal (skeleton) a Saxonban lefordítja, és
  ellenőrzi a Peppol-eredményeket ([részletek](docs/PEPPOL.md#how-the-verdicts-are-verified),
  angolul). A `docker/oracle/run.sh 8.5` felépíti a képet és lefuttatja a csoportot; Docker és
  telepített `vendor/` kell hozzá. Ez csak fejlesztői eszköz, nem része a telepített csomagnak.
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
[tests/fixtures/official/SOURCE.md](https://github.com/intragile/php-einvoicing/blob/main/tests/fixtures/official/SOURCE.md).
A `src/CodeList/` kódlistaértékei ebből származnak; a forrásmegjelölést lásd:
[src/CodeList/SOURCE.md](src/CodeList/SOURCE.md). A `packages/rules-en16931` mappában lévő
lefordított validációs szabályok EUPL-1.2 licencűek; lásd:
[packages/rules-en16931/SOURCE.md](packages/rules-en16931/SOURCE.md). A `packages/rules-xrechnung`
mappa opcionális XRechnung-szabályai, forgatókönyv-táblája és sémái Apache-2.0 licencűek, mint a
KoSIT-anyagok, amelyekből származnak; lásd:
[packages/rules-xrechnung/SOURCE.md](packages/rules-xrechnung/SOURCE.md). A Peppol BIS szabályait a
csomag nem terjeszti: szerzői joguk az OpenPeppol AISBL-é, ezért a saját gépeden töltődnek le és
fordulnak le ([miért](docs/PEPPOL.md#why-the-rules-are-compiled-on-your-machine), angolul).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

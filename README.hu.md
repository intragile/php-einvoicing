# PHP E-Invoicing

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

**Keretrendszer-független EN 16931 e-számlázási mag PHP-hoz.** Pontos tizedes számításra épülő
számlamodell, EN 16931 szerinti összesítés, a szabványhoz tartozó két szintaxis (UBL 2.1 és
UN/CEFACT CII) olvasása és írása, ellenőrzés a hivatalos CEN EN 16931 és a Peppol BIS Billing 3
szabályok szerint, valamint küldés és fogadás Peppolon egy hozzáférési ponton keresztül.
Futásidőben nincs szükség Javára.

[English description → README.md](README.md)

> **Állapot:** 1.0.0-s verzió, előkészítés alatt, még nincs kiadva (a dátumot a címkézéskor töltjük ki). Mit és hogyan ellenőriztünk: [Ellenőrzés](#ellenőrzés) és [docs/VERIFICATION.md](docs/VERIFICATION.md) (angolul).

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
- Peppol-átvitel: küldés és fogadás hozzáférési ponton keresztül, résztvevő keresése az SML-ben
  és az SMP-ben, keresés a Peppol Directoryban, webhookok ellenőrzése, valamint SBDH-boríték írása
  és olvasása ([Peppol-átvitel](docs/TRANSPORT.md), angolul). Elérhető meghajtó a Recommand, a
  B2Brouter és az e-invoice.be; a Storecove tervezett. A Recommandot a Peppol teszthálózaton
  teszteltük élesben; a B2Brouter és az e-invoice.be a szolgáltatói sandbox ellen van élesben
  tesztelve (szimulált hálózat) ([meghajtók](#meghajtók)).
- ZUGFeRD / Factur-X 1.0 (MINIMUM, BASIC WL, BASIC, EN 16931, EXTENDED és XRECHNUNG profil): a
  profil XML-jének írása, beágyazása egy általad adott PDF/A-ba, kiolvasása a hibrid PDF-ből és
  ellenőrzése, az opcionális, Apache-2.0 licencű `intragile/einvoicing-rules-facturx`
  szabálycsomaggal ([ZUGFeRD / Factur-X](#zugferd--factur-x)). A PDF-réteg tisztán PHP-s
  ([PDF-réteg](docs/PDF.md), angolul).

Országjegyzetek: [Németország](docs/countries/DE.md) (jogalap, formátumok, csatornák és az ellenőrzés, angolul).

Nincs benne: önszámlázó (self-billing) számlák küldése Peppolon, valamint CII a Peppol BIS
Billing 3.0 szerint (CII Peppolon csak XRechnungként megy). A számla PDF-jének megjelenítése
(renderelése) nem feladata a csomagnak: a PDF-et te adod. A Laravel-integráció külön csomag:
`intragile/laravel-einvoicing`.

## Telepítés

PHP 8.3 vagy újabb, valamint `ext-dom`, `ext-libxml`, `ext-mbstring` és `ext-zlib` szükséges.

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

<!-- sample: run -->
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

<!-- sample: run -->
```php
use IntrAgile\EInvoicing\Validation\Validator;

$xml = file_get_contents('invoice.xml');         // egy beérkezett vagy megírt számla
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

<!-- sample: run -->
```php
<?php

declare(strict_types=1);

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\{CountryCode, CurrencyCode, ElectronicAddressScheme, InvoiceTypeCode, PaymentMeansCode, UnitCode, VatCategory};
use IntrAgile\EInvoicing\Model\{Contact, CreditTransfer, ElectronicAddress, Invoice, InvoiceLine, Item, LineVat, Party, PaymentInstructions, PostalAddress, Price};
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;

$invoice = TotalsCalculator::complete(new Invoice(
    number: 'RE-2026-0815',
    issueDate: new DateTimeImmutable('2026-11-13'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: new Party(
        'Muster Software GmbH',
        vatIdentifier: 'DE123456789',
        electronicAddress: new ElectronicAddress('rechnung@muster-software.example', ElectronicAddressScheme::EM),
        address: new PostalAddress(CountryCode::DE, line1: 'Lindenstraße 12', city: 'Berlin', postCode: '10115'),
        contact: new Contact(name: 'Erika Mustermann', phone: '+49 30 1234567', email: 'erika.mustermann@muster-software.example'),
    ),
    buyer: new Party(
        'Beispiel Handel AG',
        electronicAddress: new ElectronicAddress('eingang@beispiel-handel.example', ElectronicAddressScheme::EM),
        address: new PostalAddress(CountryCode::DE, line1: 'Marktplatz 3', city: 'Köln', postCode: '50667'),
    ),
    lines: [
        new InvoiceLine('1', '10', UnitCode::HUR, new Price('95.00'), new Item('Softwareentwicklung'), new LineVat(VatCategory::StandardRate, '19')),
    ],
    dueDate: new DateTimeImmutable('2026-12-13'),
    buyerReference: '04011000-12345-34',            // a Leitweg-ID (BT-10)
    paymentTerms: 'Zahlbar innerhalb von 30 Tagen ohne Abzug.',
    processControl: XRechnung::processControl(),    // XRechnung 3.0 CIUS (BT-24), Peppol billing (BT-23)
    payment: new PaymentInstructions(
        meansCode: PaymentMeansCode::C_58,
        remittanceInformation: 'RE-2026-0815',
        creditTransfers: [new CreditTransfer(accountIdentifier: 'DE75512108001245126199', accountName: 'Muster Software GmbH')],
    ),
));

$xml = (new UblWriter)->write($invoice);           // vagy (new CiiWriter)->write($invoice)

$report = XRechnungValidator::create()->validate($xml);
$report->scenario;   // "EN16931 XRechnung (UBL Invoice)"
if (! $report->isValid()) {   // false XML Schema-hibánál vagy bármely fatal eredménynél
    throw new RuntimeException(implode("\n", $report->verdict()));
}
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
KoSIT validátor fut. A KoSIT validátor 1.6.3-mal és a 2026-08-31-es konfigurációval összevetve
579 dokumentumon (a KoSIT tesztcsomag, az XRechnung Schematron egységtesztjei, a konfiguráció
tesztjei, a CEN 1.3.16 példák és mutációk) a forgatókönyv, az XML Schema-érvényesség, az
eredmény és az elfogadás is megegyezik; 2 dokumentumon, ahol egy szabálykészletben XPath dinamikus
hiba lép fel, akkor egyezik, ha az adott szabálykészlet találatait kihagyjuk, mert a KoSIT arra
nem jelez semmit; 3 nem jól formált vagy DOCTYPE-ot tartalmazó fájlt pedig mindkettő elutasít
([docs/VALIDATION.md](docs/VALIDATION.md#the-kosit-conformance-run), angolul). A szabálycsomag
Apache-2.0 licencű, mint a KoSIT-anyagok, amelyekből fordul.

## ZUGFeRD / Factur-X

A hibrid számla egy PDF/A-3, amely a számlát beágyazott CII XML-ként hordozza. A könyvtár megírja a
profil XML-jét, inkrementális frissítéssel beágyazza egy általad adott PDF/A-ba (a PDF eredeti
bájtjai nem változnak), visszaolvassa, és ellenőrzi a Factur-X 1.09.2 szabályai szerint. A profil
írásához és az ellenőrzéshez a külön szabálycsomag kell, amely ennek a csomagnak csak javaslata:

```bash
composer require intragile/einvoicing-rules-facturx
```

```php
use IntrAgile\EInvoicing\Syntax\FacturX\{FacturXPdfReader, FacturXPdfWriter, FacturXProfile, FacturXXmlWriter};
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;

$xml = (new FacturXXmlWriter)->write($invoice, FacturXProfile::En16931)->xml;
$result = (new FacturXPdfWriter)->embed($pdfA3Bytes, $xml);   // $result->pdf, $result->warnings

$document = (new FacturXPdfReader)->read($result->pdf);       // ->xml, ->profile, ->invoice()
$report = FacturXValidator::create()->validate($document->xml);
```

Nem alakítunk át PDF/A-vá: PDF/A bemenetet adj. Az író hozzáadja a trailer `/ID`-t, az XMP-csomagot
és egy sRGB kimeneti szándékot (output intent), és figyelmeztet (`strict: true` esetén `NotPdfA`
kivételt dob), ha a bemenet nem deklarál PDF/A-részt; betűtípusokat nem ágyaz be, az átlátszóságot
nem javítja. A MINIMUM és a BASIC WL profil nem tartalmaz tételsorokat, ezért az `Invoice`
modellbe olvasásuk `UnsupportedDocument` kivételt dob; az XML ettől még elérhető. Az ellenőrzés
előbb az XML Schemát futtatja, a Schematron pedig XSD-hiba után is lefut. A formátumot offline
ellenőriztük: a Schematron-eredmények 699 dokumentumon megegyeznek a hivatalos Factur-X 1.09.2
XSLT-ével (SaxonC), 0 eltéréssel; a PDF/A-t a veraPDF 1.30.3-mal ellenőriztük, a Mustang-korpusz
mind a 102 PDF-je beolvasható, és a 61 FeRD-mintaszámla mindegyike kiolvasható. A kiadási
számla minden Factur-X profilban átment a hivatalos Factur-X Schematronon is, mindkét ZUGFeRD PDF
átment a veraPDF-en, és be lettek importálva a Lexware Office-ba
([docs/VERIFICATION.md](docs/VERIFICATION.md), angolul). A ZUGFeRD PDF-eket nem küldjük Peppolon.
Részletek: [docs/FACTURX.md](docs/FACTURX.md), angolul.

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
Document borítékát (SBDH) ellenőrzi a hivatalos borítékszabályok szerint. Telepítés,
üzemeltetés, az ellenőrzés részletei és mérések: [docs/PEPPOL.md](docs/PEPPOL.md) (angolul).

## Küldés Peppolon

```php
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Recommand\{RecommandConfig, RecommandTransport};
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;

$transport = new ValidatingTransport(
    new RecommandTransport(new RecommandConfig($apiKeyId, $secret, $companyId), $http),
    PeppolPayloadValidator::create(__DIR__.'/var/peppol-rules'),
);

$result = $transport->send(OutboundDocument::fromXml($xml));   // vagy OutboundDocument::fromInvoice($invoice)
```

A `$http` bármilyen PSR-18 klienst (Guzzle, Symfony) és PSR-17 gyárakat csomagol be; maga a csomag
csak a PSR-interfészektől függ. A küldőt, a címzettet, a dokumentumtípust és a folyamatot a
számlából olvassa ki. A `B2BrouterTransport` és az `EInvoiceBeTransport` ugyanígy illeszthető be.

### Meghajtók

Elérhető meghajtó a Recommand, a B2Brouter és az e-invoice.be; a Storecove tervezett.

| Meghajtó | Peppol tesztháló | Szolgáltatói sandbox | Állapot |
|---|---|---|---|
| Recommand | igen (playground csapat `useTestNetwork` beállítással) | igen | élesben tesztelve a Peppol teszthálózaton |
| B2Brouter | nem (a sandbox szimulálja a hálózatot) | igen, ingyenes | élesben tesztelve a szolgáltatói sandbox ellen (szimulált hálózat) |
| e-invoice.be | nem (a sandbox szimulálja a hálózatot) | igen, ingyenes | élesben tesztelve a szolgáltatói sandbox ellen (szimulált hálózat) |
| Storecove | nem ellenőrzött | 30 napos, kérésre | tervezett |

A hálózatot csak szimuláló szolgáltatói sandbox elleni teszt a szolgáltató API-szerződését
igazolja, nem a Peppolon történő kézbesítést; a Peppol teszthálót csak a Recommand éri el. Az
e-invoice.be csak Peppol BIS Billing 3.0 UBL-t küld. A B2Brouter a továbbított dokumentumot a
saját számlamodelljéből állítja elő, így a kézbesített bájtok eltérhetnek a te XML-edtől. A
webhook-aláírásokat a szolgáltatók dokumentált sémája szerint offline ellenőrizzük (az e-invoice.be-ét a közzétett tesztvektorral is); a webhookokat nem teszteltük élesben. Mindhárom meghajtót offline is teszteljük a szolgáltatók közzétett API-leírásából írt mintaválaszokkal. A futtatások eredménye: [docs/VERIFICATION.md](docs/VERIFICATION.md) (angolul).
Meghajtónkénti részletek: [docs/TRANSPORT.md](docs/TRANSPORT.md) (angolul).

Mit lehet elküldeni: Peppol BIS Billing 3.0 számlát UBL Invoice vagy CreditNote formában, a
pontos BIS specifikáció-azonosítóval (BT-24); XRechnung 3.0-t UBL Invoice, UBL CreditNote vagy
CII formában; és XRechnung 3.0 Extensiont UBL Invoice vagy CII formában. Minden mást
`UnsupportedDocument` kivétellel elutasít, köztük a sima EN 16931 számlát
(`urn:cen.eu:en16931:2017`, ezt írják az írók folyamatvezérlés nélküli számlára: tedd rá a
`new ProcessControl(PeppolBis3::CUSTOMIZATION, PeppolBis3::PROCESS)` vagy az
`XRechnung::processControl()` értéket), az XRechnung CVD-t, az XRechnung UBL Credit Note
Extensiont és az önszámlázást. A címzettnek az SMP-jében regisztrálnia kell a dokumentumtípust:
az a címzett, aki csak a BIS Billing 3.0-t jelenti be, XRechnungot nem tud fogadni.

A meghajtót mindig csomagold `ValidatingTransport`-ba: az érvénytelen dokumentumot még elküldés
előtt visszautasítja. A `PeppolPayloadValidator` a BIS dokumentumra a Peppol BIS szabályait, az
XRechnung dokumentumra a KoSIT XRechnung-szabályait alkalmazza (ehhez az
`intragile/einvoicing-rules-xrechnung` kell). Minden hiba `isRetryable()` metódusa megmondja,
biztonságos-e az újrapróbálás; az `OutcomeUnknown` azt jelenti, hogy a hozzáférési pont
befogadhatta a dokumentumot, ezért újraküldés előtt ellenőrizd. Résztvevő keresése (SML és SMP),
a Peppol Directory, fogadás lekérdezéssel vagy webhookkal, az SBDH-boríték, mit és hogyan
ellenőriztünk, és az ismert korlátok: [docs/TRANSPORT.md](docs/TRANSPORT.md) (angolul). 

## Biztonság: rosszindulatú XML

A beérkező számlák megbízhatatlan bemenetek. Az olvasók az XML-t a `SafeXml`-en keresztül
töltik be: bármilyen DOCTYPE-deklarációt `UnsafeXml` hibával elutasítanak, még mielőtt entitás
definiálódhatna vagy kibontódhatna; az elemző `LIBXML_NONET` kapcsolóval fut, a `LIBXML_NOENT`
és a `LIBXML_DTDLOAD` pedig soha nincs használva. Külső entitás, hálózati hozzáférés és
entitás-kibontásos támadás (billion laughs) így nem lehetséges. Sérülékenység bejelentése:
[SECURITY.md](SECURITY.md).

## Ellenőrzés

A kiadás bizonyítékai a [docs/VERIFICATION.md](docs/VERIFICATION.md) fájlban vannak (angolul);
nem tartalmaz hitelesítő adatot és fiókazonosítót.

- **D1, offline kiadási kapu (2026-11-15).** Az XRechnung dokumentumokat (UBL számla, UBL
  jóváíró, CII, az Extension UBL-ben és CII-ben) és az XRechnung ZUGFeRD PDF XML-jét a KoSIT
  validator 1.6.3 (XRechnung konfiguráció 2026-08-31) elfogadta. A MINIMUM-tól az EXTENDED-ig
  minden profil Factur-X XML-je üres eredményt adott a hivatalos Factur-X 1.09.2 Schematronon
  (XSLT, SaxonC): nincs sikertelen assert és nincs kiváltott report. Mindkét ZUGFeRD PDF megfelel
  a veraPDF 1.30.3 PDF/A-3b profiljának.
- **D2, Recommand a Peppol teszthálózaton (2026-11-15).** A Peppol BIS Billing 3.0 UBL, az
  XRechnung 3.0 UBL és az XRechnung 3.0 CII elküldve, kézbesítve, fogadva és nyugtázva; a Laravel
  híd ugyanezzel a meghajtóval kézbesített egy számlát.
- **D2b-D2c, szolgáltatói sandboxok (2026-11-15).** A B2Brouter és az e-invoice.be a hálózatot
  szimuláló sandboxuk ellen: küldés, állapot és beérkező postafiók. Ez a szolgáltató
  API-szerződését igazolja, nem a Peppolon történő kézbesítést.
- **D3, import a Lexware Office-ba (2026-11-16).** Az öt kiadási fájl változtatás nélkül importálva;
  minden fájlt e-számlaként ismert fel, és minden ellenőrzött mező egyezett.
- **Offline egyezés.** A validátor eredményei megegyeznek a hivatalos XSLT-ével 1892 (CEN), 1611
  (Peppol), 584 (KoSIT, XRechnung) és 699 (Factur-X) dokumentumon.
- **Webhookok.** Az aláírásokat a szolgáltatók dokumentált sémája szerint offline ellenőrizzük
  (az e-invoice.be-ét a közzétett tesztvektorral is); a webhookokat nem teszteltük élesben.

### Ismert korlátok (1.x-re halasztva)

- Még nincs operátori parancs olyan `pending` kimenő sorra, amelynek az állapotlekérései
  elfogytak (a Laravel híd leírja, hogyan oldható fel a hozzáférési pontnál).
- e-invoice.be: a postafiókot nálunk vágjuk a visszatekintési időnél; a szolgáltató `date_from`
  szűrőjét még nem használjuk.
- Nincs védelem arra, hogy egy állapotmentes webhook második állapotlekérési láncot indítson (az
  extra lánc a dokumentum próbálkozási keretén osztozik, így az összes lekérés korlátos).

Egyik sem helyességi hiba. Az egyes területek korlátai a saját dokumentumukban vannak, például
[Peppol-átvitel](docs/TRANSPORT.md#known-limitations).

### Az offline eredmények ellenőrzése

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
- Egy második opcionális csoport, a `kosit-oracle`, a KoSIT validator 1.6.3-at (Java) futtatja
  az XRechnung 2026-08-31-es konfigurációjával Dockerben, és azzal veti össze az
  XRechnung-eredményeket ([részletek](docs/VALIDATION.md#the-kosit-validator-as-the-xrechnung-oracle),
  angolul). A `docker/kosit/run.sh` felépíti a képet, elindítja a KoSIT-démont és lefuttatja a
  csoportot; a CI nem futtatja.
- Ugyanez az oracle-csoport a hivatalos Factur-X 1.09.2 XSLT-t is futtatja SaxonC-vel, és azzal
  veti össze a Factur-X-eredményeket; az opcionális `verapdf` csoport (`docker/verapdf/run.sh`) a
  veraPDF 1.30.3-mal ellenőrzi az író által előállított PDF-eket
  ([részletek](docs/FACTURX.md#verification), angolul).
- A CI PHPUnitot és PHPStant (max szint) futtat PHP 8.3, 8.4 és 8.5 alatt, továbbá Pintet, az
  oracle-csoportot, és ellenőrzi, hogy a lefordított szabályok egyeznek a hivatalos Schematronnal.

## Verziókezelés

A nyolc csomag mindig együtt, azonos verziószámmal jelenik meg, a szemantikus verziózás (Semantic
Versioning) szerint: az öt motorcsomag (`intragile/php-einvoicing`, az
`intragile/einvoicing-rules-en16931`, `intragile/einvoicing-rules-xrechnung` és
`intragile/einvoicing-rules-facturx` szabálycsomag, valamint az `intragile/laravel-einvoicing`) és a
három belépő csomag (`intragile/laravel-xrechnung`, `intragile/laravel-zugferd` és
`intragile/laravel-peppol`). Mindegyikből ugyanazt a minor verziót használd. A szabálycsomagok
Composer `conflict` bejegyzéssel kizárják a saját minor verziójuknál régebbi (és a következő major)
magot, így új szabályok sosem kerülnek olyan motor mellé, amely nem tudja futtatni őket.

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
fordulnak le ([miért](docs/PEPPOL.md#why-the-rules-are-compiled-on-your-machine), angolul). A
`packages/rules-facturx` mappa opcionális Factur-X szabályai és sémái Apache-2.0 licencűek, mint a
FeRD-anyagok, amelyekből származnak; lásd: [packages/rules-facturx/SOURCE.md](packages/rules-facturx/SOURCE.md).

---

Fejlesztő és karbantartó: [IntrAgile Kft.](https://intragile.eu)

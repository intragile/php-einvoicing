<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use DateTimeImmutable;
use ErrorException;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;
use IntrAgile\EInvoicing\Pdf\PdfParser;
use IntrAgile\EInvoicing\Syntax\FacturX\AfRelationship;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class FacturXPdfReaderTest extends TestCase
{
    private const string XMP_PDFA3 = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        .'<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/"><pdfaid:part>3</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance></rdf:Description>'
        .'</rdf:RDF></x:xmpmeta>';

    /** XMP in the attribute (shorthand) form declaring the BASIC level. */
    private const string XMP_BASIC_ATTRIBUTES = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        .'<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" pdfaid:part="3" pdfaid:conformance="B"/>'
        .'<rdf:Description rdf:about="" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#" fx:DocumentType="INVOICE" fx:DocumentFileName="factur-x.xml" fx:Version="1.0" fx:ConformanceLevel="BASIC"/>'
        .'</rdf:RDF></x:xmpmeta>';

    /** FeRD folder => profile of the invoices in it. */
    private const array FOLDERS = [
        '0. MINIMUM' => FacturXProfile::Minimum,
        '1. BASIC WL' => FacturXProfile::BasicWl,
        '2. BASIC' => FacturXProfile::Basic,
        '3. EN16931' => FacturXProfile::En16931,
        '4. EXTENDED' => FacturXProfile::Extended,
        '5. XRECHNUNG' => FacturXProfile::XRechnung,
    ];

    /**
     * FeRD examples whose embedded XML does not carry the guideline identifier of its folder's
     * profile: example name => the identifier it does carry.
     */
    private const array OLDER_GUIDELINE = [
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Betriebskostenabrechnung/XRECHNUNG_Betriebskostenabrechnung_fx.pdf' => 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.1',
    ];

    /**
     * The (profile, /AFRelationship, XMP fx:ConformanceLevel) of every FeRD example invoice, as
     * read from the downloaded files and reviewed against the specification's defaults.
     *
     * @var array<string, array{?string, ?string, ?string}>
     */
    private const array FERD_TABLE = [
        'library/ZF252_Beispiele/0. MINIMUM/MINIMUM_Buchungshilfe/MINIMUM_Buchungshilfe_fx.pdf' => ['MINIMUM', 'Data', 'MINIMUM'],
        'library/ZF252_Beispiele/0. MINIMUM/MINIMUM_Rechnung/MINIMUM_Rechnung_fx.pdf' => ['MINIMUM', 'Data', 'MINIMUM'],
        'library/ZF252_Beispiele/1. BASIC WL/BASIC-WL_Buchungshilfe/BASIC-WL_Buchungshilfe_fx.pdf' => ['BASIC WL', 'Data', 'BASIC WL'],
        'library/ZF252_Beispiele/1. BASIC WL/BASIC-WL_Einfach/BASIC-WL_Einfach_fx.pdf' => ['BASIC WL', 'Data', 'BASIC WL'],
        'library/ZF252_Beispiele/2. BASIC/B01_Einfach/B01_01_Einfach_fx.pdf' => ['BASIC', 'Data', null],
        'library/ZF252_Beispiele/2. BASIC/B02_Rechnungskorrektur/B02_01_Rechnungskorrektur_fx.pdf' => ['BASIC', 'Data', null],
        'library/ZF252_Beispiele/2. BASIC/B03_Taxifahrt/B03_01_Taxifahrt_fx.pdf' => ['BASIC', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E01_1_Teilrechnung/E01_01_1_Teilrechnung_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E02_2_Teilrechnung/E02_01_2_Teilrechnung_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E03_AbweichenderZahlungsempf/E03_01_AbweichenderZahlungsempf_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E04_Betriebskostenabrechnung_NEU/E04_01_Betriebskostenabrechnung_NEU_fx.pdf' => ['EN 16931', 'Data', 'EN16931'],
        'library/ZF252_Beispiele/3. EN16931/E05_Einfach/E05_01_Einfach_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E06_Einfach_DueDate/E06_01_Einfach_DueDate_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E07_Einfach_negativePaymentDue/E07_01_Einfach_negativePaymentDue_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E08_Elektron_Anlage_XML/E08_01_Elektron_Anlage_XML_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E09_ElektronischeAdresse/E09_01_ElektronischeAdresse_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E10_Gutschrift/E10_01_Gutschrift_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E11_Haftpflichtversicherung_Versicherungssteuer/E11_01_Haftpflichtversicherung_Versicherungssteuer_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E12_Innergemeinschaftliche_Lieferungen/E12_01_Innergemeinschaftliche_Lieferungen_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E13_Kleinunternehmer_ohneUStId/E13_01_Kleinunternehmer_ohneUStId_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E14_Kraftfahrversicherung/E14_01_Kraftfahrversicherung_fx.pdf' => ['EN 16931', 'Data', 'EN16931'],
        'library/ZF252_Beispiele/3. EN16931/E15_Miete/E15_01_Miete_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E16_OEPNV/E16_01_OEPNV_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E17_Photovoltaik/E17_01_Photovoltaik_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E18_Physiotherapeut/E18_01_Physiotherapeut_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E19_Rabatte/E19_01_Rabatte_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E20_Rechnungskorrektur/E20_01_Rechnungskorrektur_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E21_RechnungsUebertragung/E22_01_RechnungsUebertragung_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E22_Reisekosten/E22_01_Reisekosten_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E23_Sachversicherung_berechneter_Steuersatz/E23_01_Sachversicherung_berechneter_Steuersatz_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E24_SEPA_Prenotification/E24_01_SEPA_Prenotification_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/3. EN16931/E25_steuerbefreite_Auslandslieferung/E25_01_steuerbefreite_Auslandslieferung_fx.pdf' => ['EN 16931', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/X01_01_600700800900_Dauermietrechnung_unbefristet__fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/X01_02_600700801000_NK_Abrechnung_zur_Dauermietrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/X01_03_7600700800900_Dauermietrechnung_KorrekturRE__fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X02_SubInvoiceLines_Buero_Material_Bsp3/X02_01_SubInvoiceLines_Buero_Material_Bsp3___fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X03_Abschlagsrechnung_SubInvoiceLine_u_LV_Nr/X03_01_Abschlagsrechnung_SubInvoiceLine_u_LV_Nr_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X04_Sammelrechnung_3_Bestellungen/X04_01_Sammelrechnung_3_Bestellungen_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X05_Bau_Rechnungen Abschlag_SchlussRechnung/X05_01_einfach_Bau__1_Abschlagsrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X05_Bau_Rechnungen Abschlag_SchlussRechnung/X05_02_einfach_Bau__2_Abschlagsrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X05_Bau_Rechnungen Abschlag_SchlussRechnung/X05_03_einfach_Bau_Schlussrechnung_mit_Steuer_Zusammenstellung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X06_Fallschutz-Set_SubInvoiceLine_Bsp5/X06_01_Fallschutz-Set_SubInvoiceLine_Bsp5_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X07_Fremdwaehrung/X07_01_Fremdwaehrung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X08_Herkunftsland_Zolltarif-Nummer__HS___UNSPSC__TST___eCl_ss__STQ/X08_01_Herkunftsland_Zolltarif-Nummer__HS___UNSPSC__TST___eCl_ss__STQ_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X09_InnergemeinschLieferungMehrereBestellungen/X09_01_InnergemeinschLieferungMehrereBestellungen_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X10_Kleinunternehmer_ohneUStId/X10_01_Kleinunternehmer_ohneUStId_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X11_Kostenrechnung/X11_01_Kostenrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X12_Maschinen-Serial_NR__BT-X-307/X12_01_Maschinen-Serial_NR__BT-X-307_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X13_Projektabschlussrechnung/X13_01_Projektabschlussrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X14_Rechnungskorrektur/X14_01_Rechnungskorrektur_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X15_steuerfreie_Innergemeinschaftliche_Lieferung/X15_01_steuerfreie_Innergemeinschaftliche_Lieferung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X16_Stornogutschrift_zur_Rechnung/X16_01_Stornogutschrift_zu_RE_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X16_Stornogutschrift_zur_Rechnung/X16_02_Rechnung_für_Storno_Gutschrift__fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X17_SubInvoiceLines_Hardware_Bsp2/X17_01_SubInvoiceLines_Hardware_Bsp2_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X18_Subinvoiceline Kaffee Bundle Set Bsp 4/X18_01_SubInvoiceLines_Kaffee_Bundle_Set_Bsp4____fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X19_Warenrechnung/X19_01_Warenrechnung_fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/4. EXTENDED/X20_SubInvoiceLines_Buero_Material_Bsp3__/X20_01_SubInvoiceLines_Buero_Material_Bsp3___fx.pdf' => ['EXTENDED', 'Data', null],
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Betriebskostenabrechnung/XRECHNUNG_Betriebskostenabrechnung_fx.pdf' => [null, 'Alternative', 'XRECHNUNG'],
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Einfach/XRECHNUNG_Einfach_fx.pdf' => ['XRECHNUNG', 'Alternative', 'XRECHNUNG'],
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Elektron/XRECHNUNG_Elektron_fx.pdf' => ['XRECHNUNG', 'Alternative', 'XRECHNUNG'],
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Reisekostenabrechnung/XRECHNUNG_Reisekostenabrechnung_fx.pdf' => ['XRECHNUNG', 'Alternative', 'XRECHNUNG'],
    ];

    /** The FeRD example folders' supporting documents, which carry no invoice. */
    public const array FERD_EXCLUDED = [
        'library/ZF252_Beispiele/3. EN16931/E08_Elektron_Anlage_XML/attachments/Aufmass.pdf',
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/attachments/Mietvertrag.pdf',
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/attachments/NKD_2026.pdf',
        'library/ZF252_Beispiele/4. EXTENDED/X01_Dauermietrechnung_u_Nebenkostenabrechnung/attachments/VIP_2026.pdf',
        'library/ZF252_Beispiele/4. EXTENDED/X05_Bau_Rechnungen Abschlag_SchlussRechnung/attachments/Zahlungsaufstellung.pdf',
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Betriebskostenabrechnung/attachments/Betriebskostenabrechnung.pdf',
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Elektron/attachments/ElektronRapport.pdf',
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Reisekostenabrechnung/attachments/Hotelrechnung-Immo.pdf',
        'library/ZF252_Beispiele/5. XRECHNUNG/XRECHNUNG_Reisekostenabrechnung/attachments/Taxi-Nue-und-Berlin.pdf',
    ];

    /** @var array<string, string> */
    private static array $xml = [];

    protected function setUp(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_round_trip_every_profile(): void
    {
        foreach (FacturXProfile::cases() as $profile) {
            $xml = self::xml($profile);
            $pdf = (new FacturXPdfWriter)->embed(self::basePdf(), $xml, modified: new DateTimeImmutable('2026-11-13T10:00:00+01:00'))->pdf;

            $document = (new FacturXPdfReader)->read($pdf);

            self::assertSame($xml, $document->xml, $profile->name);
            self::assertSame($profile->attachmentName(), $document->attachmentName, $profile->name);
            self::assertSame($profile, $document->profile, $profile->name);
            self::assertSame($profile === FacturXProfile::XRechnung ? AfRelationship::Alternative : AfRelationship::Data, $document->relationship, $profile->name);
            self::assertSame($profile->value, $document->xmpConformanceLevel, $profile->name);
            self::assertSame(3, $document->pdfaPart, $profile->name);
            self::assertSame([], $document->warnings, $profile->name);
        }
    }

    public function test_reads_from_names_tree_without_af(): void
    {
        $xml = self::xml(FacturXProfile::En16931);
        $pdf = $this->attachedPdf(
            $xml,
            filespec: '/F (factur-x.xml) /UF (factur-x.xml) /AFRelationship /Alternative',
            catalog: '/Names << /EmbeddedFiles << /Names [(factur-x.xml) 6 0 R] >> >>',
        );

        $document = (new FacturXPdfReader)->read($pdf);

        self::assertSame($xml, $document->xml);
        self::assertSame('factur-x.xml', $document->attachmentName);
        self::assertSame(FacturXProfile::En16931, $document->profile);
        self::assertSame(AfRelationship::Alternative, $document->relationship);
        self::assertNull($document->xmpConformanceLevel);
        self::assertNull($document->pdfaPart);
        self::assertCount(1, $document->warnings);
        self::assertStringContainsString('no XMP metadata', $document->warnings[0]);
    }

    public function test_reads_kids_name_tree(): void
    {
        $xml = self::xml(FacturXProfile::Extended);
        $pdf = $this->attachedPdf(
            $xml,
            // Only /F, as UTF-16BE with a byte order mark, and no /AFRelationship.
            filespec: '/F <FEFF006600610063007400750072002D0078002E0078006D006C>',
            catalog: '/Names << /EmbeddedFiles 8 0 R >>',
            objects: [
                8 => '<< /Kids [9 0 R 10 0 R] >>',
                9 => '<< /Limits [(a.txt) (a.txt)] /Names [(a.txt) 11 0 R] >>',
                10 => '<< /Limits [(factur-x.xml) (factur-x.xml)] /Names [(factur-x.xml) 6 0 R] >>',
                11 => '<< /Type /Filespec /F (a.txt) >>',
            ],
        );

        $document = (new FacturXPdfReader)->read($pdf);

        self::assertSame($xml, $document->xml);
        self::assertSame('factur-x.xml', $document->attachmentName);
        self::assertSame(FacturXProfile::Extended, $document->profile);
        self::assertNull($document->relationship);
        self::assertContains('The embedded invoice "factur-x.xml" has no /AFRelationship.', $document->warnings);
    }

    public function test_af_is_searched_before_the_name_tree_and_names_by_priority(): void
    {
        $xml = self::xml(FacturXProfile::XRechnung);
        $pdf = $this->attachedPdf(
            $xml,
            filespec: '/F (xrechnung.xml) /UF (xrechnung.xml) /AFRelationship /Alternative',
            // The tree's ZUGFeRD 1.0 entry would be refused; xrechnung.xml in /AF is found first.
            catalog: '/AF [12 0 R 6 0 R] /Names << /EmbeddedFiles << /Names [(ZUGFeRD-invoice.xml) 13 0 R] >> >>',
            objects: [
                12 => '<< /Type /Filespec /F (Aufmass.pdf) /UF (Aufmass.pdf) /AFRelationship /Supplement >>',
                13 => '<< /Type /Filespec /F (ZUGFeRD-invoice.xml) /UF (ZUGFeRD-invoice.xml) >>',
            ],
            xmp: self::XMP_PDFA3,
        );

        $document = (new FacturXPdfReader)->read($pdf);

        self::assertSame('xrechnung.xml', $document->attachmentName);
        self::assertSame(FacturXProfile::XRechnung, $document->profile);
        self::assertSame(3, $document->pdfaPart);
        self::assertContains('The XMP metadata declares no fx:ConformanceLevel.', $document->warnings);
    }

    public function test_af_beats_the_name_tree_for_the_same_name(): void
    {
        $af = self::xml(FacturXProfile::En16931);
        $tree = self::xml(FacturXProfile::Extended);

        foreach (['/UF (factur-x.xml)', '/F (factur-x.xml)'] as $afName) {
            // The tree's filespec has the stronger /UF match, but /AF is searched first.
            $document = (new FacturXPdfReader)->read($this->attachedPdf(
                $af,
                filespec: $afName.' /AFRelationship /Data',
                catalog: '/AF [6 0 R] /Names << /EmbeddedFiles << /Names [(factur-x.xml) 12 0 R] >> >>',
                objects: [12 => '<< /Type /Filespec /UF (factur-x.xml) /AFRelationship /Alternative /EF << /F 13 0 R >> >>'],
                streams: [13 => $tree],
            ));

            self::assertSame($af, $document->xml, $afName);
            self::assertSame(AfRelationship::Data, $document->relationship, $afName);
        }

        // Within one location, /UF beats /F.
        $document = (new FacturXPdfReader)->read($this->attachedPdf(
            $af,
            filespec: '/F (factur-x.xml)',
            catalog: '/AF [6 0 R 12 0 R]',
            objects: [12 => '<< /Type /Filespec /UF (factur-x.xml) /AFRelationship /Alternative /EF << /F 13 0 R >> >>'],
            streams: [13 => $tree],
        ));
        self::assertSame($tree, $document->xml);
    }

    public function test_indirect_names_and_relationship_are_resolved(): void
    {
        $xml = self::xml(FacturXProfile::En16931);
        $document = (new FacturXPdfReader)->read($this->attachedPdf(
            $xml,
            filespec: '/UF 12 0 R /AFRelationship 13 0 R',
            catalog: '/AF [6 0 R]',
            objects: [12 => '(factur-x.xml)', 13 => '/Alternative'],
        ));

        self::assertSame($xml, $document->xml);
        self::assertSame('factur-x.xml', $document->attachmentName);
        self::assertSame(AfRelationship::Alternative, $document->relationship);
    }

    /**
     * The guard around read() turns a PHP warning raised anywhere inside it, here while a class
     * is autoloaded, into UnreadablePdf. The process is fresh, so FacturXDocument is first
     * loaded inside the guard.
     */
    #[RunInSeparateProcess]
    public function test_warning_inside_read_becomes_unreadable_pdf(): void
    {
        $this->expectException(UnreadablePdf::class);
        $this->expectExceptionMessage('Unreadable PDF: raised while autoloading');

        $this->readWhileAutoloadingRaises(E_USER_WARNING);
    }

    #[RunInSeparateProcess]
    public function test_deprecation_inside_read_is_left_to_php(): void
    {
        $display = ini_set('display_errors', '0');
        $log = ini_set('log_errors', '0');
        error_clear_last();
        try {
            $document = $this->readWhileAutoloadingRaises(E_USER_DEPRECATED);
        } finally {
            ini_set('display_errors', (string) $display);
            ini_set('log_errors', (string) $log);
        }

        self::assertSame(self::xml(FacturXProfile::En16931), $document->xml);
        self::assertSame('raised while autoloading', error_get_last()['message'] ?? null);
    }

    public function test_corrupt_xmp_warns_and_a_breached_limit_throws(): void
    {
        $xml = self::xml(FacturXProfile::En16931);
        $corrupt = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";
        $document = (new FacturXPdfReader)->read($this->attachedPdf(
            $xml,
            filespec: '/UF (factur-x.xml) /AFRelationship /Data',
            catalog: '/AF [6 0 R] /Metadata 14 0 R',
            objects: [14 => '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length '.strlen($corrupt)." >>\nstream\n".$corrupt."\nendstream"],
        ));

        self::assertSame($xml, $document->xml, 'the invoice is still returned');
        self::assertNull($document->pdfaPart);
        self::assertCount(1, $document->warnings);
        self::assertStringStartsWith('The XMP metadata was ignored because it cannot be read: Corrupt FlateDecode data', $document->warnings[0]);

        $bomb = self::flateBomb(PdfParser::MAX_STREAM_BYTES + 1024 * 1024);
        $pdf = $this->attachedPdf(
            $xml,
            filespec: '/UF (factur-x.xml) /AFRelationship /Data',
            catalog: '/AF [6 0 R] /Metadata 14 0 R',
            objects: [14 => '<< /Type /Metadata /Subtype /XML /Filter [/FlateDecode /FlateDecode] /Length '.strlen($bomb)." >>\nstream\n".$bomb."\nendstream"],
        );
        try {
            (new FacturXPdfReader)->read($pdf);
            self::fail('A metadata stream beyond the decoding limit is refused.');
        } catch (UnreadablePdf $e) {
            self::assertSame(UnreadablePdf::REFUSED, $e->getCode());
        }
    }

    public function test_zugferd1_is_unsupported(): void
    {
        $pdf = $this->attachedPdf(
            '<rsm:CrossIndustryDocument xmlns:rsm="urn:ferd:CrossIndustryDocument:invoice:1p0"/>',
            filespec: '/F (ZUGFeRD-invoice.xml) /UF (ZUGFeRD-invoice.xml) /AFRelationship /Alternative',
            catalog: '/AF [6 0 R]',
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('ZUGFeRD 1.0 is not supported');

        (new FacturXPdfReader)->read($pdf);
    }

    public function test_no_invoice_lists_seen_names(): void
    {
        $pdf = $this->attachedPdf(
            'a;b',
            filespec: '/F (data.csv) /UF (data.csv)',
            catalog: '/AF [6 0 R] /Names << /EmbeddedFiles << /Names [(report.pdf) 12 0 R] >> >>',
            objects: [12 => '<< /Type /Filespec /F (report.pdf) >>'],
        );

        try {
            (new FacturXPdfReader)->read($pdf);
            self::fail('A PDF without an invoice is refused.');
        } catch (UnsupportedDocument $e) {
            self::assertStringContainsString('"data.csv"', $e->getMessage());
            self::assertStringContainsString('"report.pdf"', $e->getMessage());
        }

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('no embedded files');
        (new FacturXPdfReader)->read(self::basePdf());
    }

    public function test_xmp_and_guideline_disagree_warns(): void
    {
        $xml = self::xml(FacturXProfile::En16931);
        $pdf = $this->attachedPdf(
            $xml,
            filespec: '/F (factur-x.xml) /UF (factur-x.xml) /AFRelationship /Alternative',
            catalog: '/AF [6 0 R]',
            xmp: self::XMP_BASIC_ATTRIBUTES,
        );

        $document = (new FacturXPdfReader)->read($pdf);

        self::assertSame(FacturXProfile::En16931, $document->profile, 'the XML guideline identifier is authoritative');
        self::assertSame('BASIC', $document->xmpConformanceLevel);
        self::assertSame(3, $document->pdfaPart);
        self::assertSame(
            ['The XMP metadata declares the conformance level "BASIC", but the embedded XML declares the EN 16931 profile (guideline identifier urn:cen.eu:en16931:2017); the XML is authoritative.'],
            $document->warnings,
        );
    }

    public function test_unknown_guideline_id_warns(): void
    {
        $xml = str_replace('<ram:ID>urn:cen.eu:en16931:2017</ram:ID>', '<ram:ID>urn:example:unknown</ram:ID>', self::xml(FacturXProfile::En16931));
        $pdf = $this->attachedPdf($xml, filespec: '/UF (factur-x.xml) /AFRelationship /Unspecified', catalog: '/AF [6 0 R]', xmp: self::XMP_PDFA3);

        $document = (new FacturXPdfReader)->read($pdf);

        self::assertNull($document->profile);
        self::assertNull($document->relationship);
        self::assertContains('The embedded XML declares the guideline identifier "urn:example:unknown" (BT-24), which is not a supported Factur-X / ZUGFeRD profile.', $document->warnings);
        self::assertContains('The embedded invoice "factur-x.xml" has the /AFRelationship Unspecified; Factur-X expects Data, Alternative or Source.', $document->warnings);
    }

    public function test_line_less_profile_invoice_throws(): void
    {
        $read = static fn (FacturXProfile $profile) => (new FacturXPdfReader)->read(
            (new FacturXPdfWriter)->embed(self::basePdf(), self::xml($profile), modified: new DateTimeImmutable('2026-11-13'))->pdf,
        );

        self::assertSame('MIX-2026-0001', $read(FacturXProfile::En16931)->invoice()->invoice->number);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('MINIMUM');
        $read(FacturXProfile::Minimum)->invoice();
    }

    public function test_ferd_examples_profiles_and_relationships(): void
    {
        $table = [];
        $excluded = [];
        foreach (Fixtures::facturxExamplePdfs() as $name => $path) {
            if (preg_match('~/ZF252_Beispiele/([^/]+)/~', $name, $m) !== 1) {
                continue;
            }
            // Supporting documents of the examples sit in attachments/ folders; they carry no invoice.
            if (str_contains($name, '/attachments/')) {
                try {
                    (new FacturXPdfReader)->read((string) file_get_contents($path));
                    self::fail($name.' carries an invoice.');
                } catch (UnsupportedDocument $e) {
                    self::assertStringContainsString('no embedded files', $e->getMessage(), $name);
                }
                $excluded[] = $name;

                continue;
            }
            $document = (new FacturXPdfReader)->read((string) file_get_contents($path));

            $expected = isset(self::OLDER_GUIDELINE[$name]) ? null : self::FOLDERS[$m[1]];
            self::assertSame($expected, $document->profile, $name);
            if (isset(self::OLDER_GUIDELINE[$name])) {
                self::assertContains(sprintf('The embedded XML declares the guideline identifier "%s" (BT-24), which is not a supported Factur-X / ZUGFeRD profile.', self::OLDER_GUIDELINE[$name]), $document->warnings);
            }
            $table[$name] = [$document->profile?->value, $document->relationship?->value, $document->xmpConformanceLevel];
        }

        self::assertSame(self::FERD_EXCLUDED, $excluded);
        self::assertCount(61, $table);
        self::assertSame(self::FERD_TABLE, $table);
    }

    public function test_other_example_pdfs_read_or_refuse_cleanly(): void
    {
        foreach (Fixtures::facturxExamplePdfs() as $name => $path) {
            if (str_contains($name, '/ZF252_Beispiele/')) {
                continue;
            }
            try {
                $document = (new FacturXPdfReader)->read((string) file_get_contents($path));
                self::assertNotSame('', $document->xml, $name);
            } catch (UnsupportedDocument|UnreadablePdf|EncryptedPdf|UnsupportedPdfFeature) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Reads a valid Factur-X PDF while the first autoload of FacturXDocument raises $severity. */
    private function readWhileAutoloadingRaises(int $severity): FacturXDocument
    {
        $pdf = (new FacturXPdfWriter)->embed(self::basePdf(), self::xml(FacturXProfile::En16931))->pdf;
        self::assertFalse(class_exists(FacturXDocument::class, false), 'FacturXDocument is already loaded.');
        $loader = static function (string $class) use ($severity): void {
            if ($class === FacturXDocument::class) {
                trigger_error('raised while autoloading', $severity);
            }
        };
        spl_autoload_register($loader, prepend: true);
        try {
            return (new FacturXPdfReader)->read($pdf);
        } finally {
            spl_autoload_unregister($loader);
        }
    }

    /**
     * A one-page PDF with one filespec (object 6) whose embedded file (object 7) holds $data.
     *
     * @param  array<int, string>  $objects  object number => body
     * @param  array<int, string>  $streams  object number => embedded file data
     */
    private function attachedPdf(string $data, string $filespec, string $catalog, array $objects = [], ?string $xmp = null, array $streams = []): string
    {
        $builder = (new PdfBuilder('1.7'))
            ->object(1, '<< /Type /Catalog /Pages 2 0 R'.($xmp !== null ? ' /Metadata 5 0 R' : '').' '.$catalog.' >>')
            ->object(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>')
            ->object(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>')
            ->object(6, '<< /Type /Filespec '.$filespec.' /EF << /F 7 0 R >> >>')
            ->stream(7, '/Type /EmbeddedFile /Subtype /text#2Fxml', $data, flate: true);
        if ($xmp !== null) {
            $builder->stream(5, '/Type /Metadata /Subtype /XML', $xmp);
        }
        foreach ($objects as $number => $body) {
            $builder->object($number, $body);
        }
        foreach ($streams as $number => $stream) {
            $builder->stream($number, '/Type /EmbeddedFile', $stream, flate: true);
        }

        return $builder->trailer('/Root 1 0 R')->classic();
    }

    private static function basePdf(): string
    {
        return (new PdfBuilder('1.7'))
            ->object(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>')
            ->object(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>')
            ->object(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>')
            ->stream(5, '/Type /Metadata /Subtype /XML', self::XMP_PDFA3)
            ->trailer('/Root 1 0 R')
            ->classic();
    }

    /** Deflated twice, so a few hundred bytes decode to $size zero bytes. */
    private static function flateBomb(int $size): string
    {
        $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        self::assertNotFalse($context);
        $chunk = str_repeat("\0", 1024 * 1024);
        $inner = '';
        for ($written = 0; $written < $size; $written += strlen($chunk)) {
            $inner .= deflate_add($context, $chunk, ZLIB_NO_FLUSH);
        }

        return (string) gzcompress($inner.deflate_add($context, '', ZLIB_FINISH), 9);
    }

    private static function xml(FacturXProfile $profile): string
    {
        return self::$xml[$profile->value] ??= (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), $profile)->xml;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Support\FrenchPdfCarrier;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class FrenchFacturXTest extends TestCase
{
    private const LINE = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';

    private const GUIDELINE = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID';

    private const BUSINESS_PROCESS = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID';

    private static ?FranceValidator $validator = null;

    public function test_french_extended_facturx_keeps_ext_fr_fe_elements(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $reduced = (new FacturXXmlWriter)->write($invoice, FacturXProfile::Extended);

        self::assertSame([], $reduced->removed);
        $x = $this->xpath($reduced->xml);
        self::assertSame([FacturXProfile::Extended->guidelineId()], $this->values($x, self::GUIDELINE));
        self::assertSame(['B1'], $this->values($x, self::BUSINESS_PROCESS));
        self::assertSame(['GROUP', 'DETAIL', 'DETAIL', 'INFORMATION'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode'));
        self::assertSame(['1', '1'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:ParentLineID'));
        self::assertSame(['LE VENDEUR'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:Name'));
        self::assertSame(['F202500099'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        self::assertSame(['ENTREPOT NORD'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:Name'));
        self::assertSame(['TVA SUR LES DEBITS'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason'));
        self::assertSame(['5'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:DueDateTypeCode'));
        self::assertSame(['20.00'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:TaxTotalAmount'));
        self::assertSame(['220.00'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:GrandTotalAmount'));
        self::assertSame(['AAI'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:SubjectCode'));

        // Only the guideline identifier (BT-24) differs from the EXTENDED-CTC-FR CII document.
        $cii = (new CiiWriter)->write($invoice);
        self::assertSame($cii, str_replace(FacturXProfile::Extended->guidelineId().'<', FrenchProfile::ExtendedCtcFr->value.'<', $reduced->xml));

        $report = self::validator()->validateFacturX($reduced->xml, FacturXProfile::Extended);
        self::assertTrue($report->isValid(), self::describe($report));
    }

    public function test_french_extended_facturx_reads_back_as_extended_ctc_fr(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $read = (new CiiReader)->read((new FacturXXmlWriter)->write($invoice, FacturXProfile::Extended)->xml);

        self::assertSame([], $read->ignored);
        self::assertSame(FrenchProfile::ExtendedCtcFr, $read->invoice->extensions()->get(FrenchInvoice::class)?->profile);
        self::assertEquals($invoice->extensions(), $read->invoice->extensions());
        self::assertEquals($invoice->lines, $read->invoice->lines);
    }

    /**
     * @return iterable<string, array{FacturXProfile}>
     */
    public static function en16931Profiles(): iterable
    {
        yield 'EN 16931' => [FacturXProfile::En16931];
        yield 'BASIC WL' => [FacturXProfile::BasicWl];
    }

    #[DataProvider('en16931Profiles')]
    public function test_french_en16931_data_is_kept_in_facturx(FacturXProfile $profile): void
    {
        $reduced = (new FacturXXmlWriter)->write(FrenchInvoices::domesticB2b(), $profile);
        $x = $this->xpath($reduced->xml);
        $notes = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote/';
        $seller = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/';

        self::assertSame([$profile->guidelineId()], $this->values($x, self::GUIDELINE));
        self::assertSame(['B1'], $this->values($x, self::BUSINESS_PROCESS));
        self::assertSame(['PMT', 'PMD', 'AAB', 'BAR'], $this->values($x, $notes.'ram:SubjectCode'));
        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, $seller.'ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, $seller.'ram:URIUniversalCommunication/ram:URIID[@schemeID="0225"]'));

        $report = self::validator()->validateFacturX($reduced->xml, $profile);
        self::assertTrue($report->isValid(), self::describe($report));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function facturxExamples(): iterable
    {
        foreach (['FX/EN16931', 'FX/EXTENDED'] as $folder) {
            foreach (Fixtures::franceExamples($folder) as $name => $file) {
                yield $name => [$folder, $file];
            }
        }
    }

    #[DataProvider('facturxExamples')]
    public function test_french_facturx_examples_round_trip(string $folder, string $file): void
    {
        $source = (string) file_get_contents($file);
        $profile = FacturXProfile::fromGuidelineId($this->values($this->xpath($source), self::GUIDELINE)[0] ?? '');
        self::assertSame($folder === 'FX/EXTENDED' ? FacturXProfile::Extended : FacturXProfile::En16931, $profile);

        $read = (new CiiReader)->read($source);
        self::assertSame([], $read->ignored);
        $french = $read->invoice->extensions()->get(FrenchInvoice::class);
        self::assertSame($profile === FacturXProfile::Extended ? FrenchProfile::ExtendedCtcFr : FrenchProfile::En16931, $french?->profile);

        $reduced = (new FacturXXmlWriter)->write($read->invoice, $profile);
        self::assertSame([], $reduced->removed);
        $report = self::validator()->validateFacturX($reduced->xml, $profile);
        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));

        $again = (new CiiReader)->read($reduced->xml);
        self::assertSame([], $again->ignored);
        self::assertEquals($read->invoice, $again->invoice);
        self::assertSame(
            $this->values($this->xpath($source), self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'),
            $this->values($this->xpath($reduced->xml), self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'),
        );
    }

    /**
     * BASIC WL carries no invoice lines (BG-25), which the EN 16931 model requires, so its
     * examples cannot be read into the model; writing French data as BASIC WL is covered by
     * test_french_en16931_data_is_kept_in_facturx.
     */
    public function test_french_basic_wl_examples_are_not_read_into_the_model(): void
    {
        $examples = Fixtures::franceExamples('FX/BASICWL');
        self::assertCount(2, $examples);

        foreach ($examples as $name => $file) {
            try {
                (new CiiReader)->read((string) file_get_contents($file));
                self::fail("{$name} was read.");
            } catch (UnsupportedDocument $e) {
                self::assertStringContainsString('BG-25', $e->getMessage(), $name);
            }
            self::assertTrue(self::validator()->validateFacturX((string) file_get_contents($file), FacturXProfile::BasicWl)->isValid(), $name);
        }
    }

    public function test_french_facturx_pdf_embeds_and_validates(): void
    {
        $xml = (new FacturXXmlWriter)->write(FrenchInvoices::extendedCtcFr(), FacturXProfile::Extended)->xml;

        $embedded = (new FacturXPdfWriter)->embed(FrenchPdfCarrier::pdf(), $xml, FacturXProfile::Extended, modified: new DateTimeImmutable('2026-11-17T00:00:00Z'));
        $document = (new FacturXPdfReader)->read($embedded->pdf);

        self::assertSame('factur-x.xml', $document->attachmentName);
        self::assertSame(FacturXProfile::Extended, $document->profile);
        self::assertSame(3, $document->pdfaPart);
        self::assertSame($xml, $document->xml);
        $report = self::validator()->validateFacturX($document->xml, FacturXProfile::Extended);
        self::assertTrue($report->isValid(), self::describe($report));
        self::assertEquals(FrenchInvoices::extendedCtcFr()->lines, $document->invoice()->invoice->lines);
    }

    private static function validator(): FranceValidator
    {
        return self::$validator ??= FranceValidator::create(InstalledFranceRules::dir());
    }

    private static function describe(ValidationReport $report): string
    {
        $lines = [implode(' -> ', $report->ruleSets)];
        foreach ($report->schemaErrors as $error) {
            $lines[] = "schema {$error->line}: {$error->message}";
        }
        foreach ($report->dynamicErrors as $error) {
            $lines[] = "dynamic: {$error->ruleId} {$error->errorCode}: {$error->message}";
        }
        foreach ($report->findings as $finding) {
            $lines[] = "{$finding->ruleId} {$finding->severity->value} at {$finding->location}: {$finding->message}";
        }

        return implode("\n", $lines);
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $x = new DOMXPath($document);
        $x->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $x->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        return $x;
    }

    /**
     * @return list<string>
     */
    private function values(DOMXPath $x, string $expression): array
    {
        $nodes = $x->query($expression);
        self::assertNotFalse($nodes, $expression);
        $values = [];
        foreach ($nodes as $node) {
            $values[] = $node->nodeValue ?? '';
        }

        return $values;
    }
}

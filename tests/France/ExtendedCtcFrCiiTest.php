<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use Brick\Math\BigDecimal;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ExtendedCtcFrCiiTest extends TestCase
{
    private const LINE = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';

    private const SUMMATION = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/';

    private static ?FranceValidator $validator = null;

    public function test_group_detail_information_lines_written_in_line_order(): void
    {
        $x = $this->cii(FrenchInvoices::extendedCtcFr());

        self::assertSame(['1', '2', '3', '4'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'));
        self::assertSame(['GROUP', 'DETAIL', 'DETAIL', 'INFORMATION'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode'));
        self::assertSame(['1', '1'], $this->values($x, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:ParentLineID'));
        self::assertSame(['500.00', '200.00', '300.00', '0.00'], $this->values($x, self::LINE.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        self::assertSame(['500.00'], $this->values($x, self::SUMMATION.'ram:LineTotalAmount'));
    }

    public function test_line_seller_written_in_item_seller_party(): void
    {
        $x = $this->cii(FrenchInvoices::extendedCtcFr());
        $seller = self::LINE.'[2]/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/';

        self::assertSame(['587451236587'], $this->values($x, $seller.'ram:GlobalID[@schemeID="0088"]'));
        self::assertSame(['LE VENDEUR'], $this->values($x, $seller.'ram:Name'));
        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, $seller.'ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::assertSame(['FR'], $this->values($x, $seller.'ram:PostalTradeAddress/ram:CountryID'));
        self::assertSame(['FR88100000009'], $this->values($x, $seller.'ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
    }

    public function test_line_preceding_invoice_and_delivery_written(): void
    {
        $x = $this->cii(FrenchInvoices::extendedCtcFr());
        $line = self::LINE.'[3]/';
        $reference = $line.'ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/';
        $delivery = $line.'ram:SpecifiedLineTradeDelivery/';

        self::assertSame(['F202500099'], $this->values($x, $reference.'ram:IssuerAssignedID'));
        self::assertSame(['20251215'], $this->values($x, $reference.'ram:FormattedIssueDateTime/qdt:DateTimeString[@format="102"]'));
        self::assertSame(['ENTREPOT NORD'], $this->values($x, $delivery.'ram:ShipToTradeParty/ram:Name'));
        self::assertSame(['5 rue du quai'], $this->values($x, $delivery.'ram:ShipToTradeParty/ram:PostalTradeAddress/ram:LineOne'));
        self::assertSame(['LILLE'], $this->values($x, $delivery.'ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CityName'));
        self::assertSame(['FR'], $this->values($x, $delivery.'ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CountryID'));
        self::assertSame(['20251220'], $this->values($x, $delivery.'ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString'));
        self::assertSame(['AAI'], $this->values($x, $line.'ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:SubjectCode'));
    }

    public function test_line_vat_extras_written(): void
    {
        $x = $this->cii(FrenchInvoices::extendedCtcFr(lineVatExemptionReason: 'TVA SUR LES DEBITS'));
        $settlement = self::LINE.'[2]/ram:SpecifiedLineTradeSettlement/';

        self::assertSame(['TVA SUR LES DEBITS'], $this->values($x, $settlement.'ram:ApplicableTradeTax/ram:ExemptionReason'));
        self::assertSame(['5'], $this->values($x, $settlement.'ram:ApplicableTradeTax/ram:DueDateTypeCode'));
        self::assertSame(['20.00'], $this->values($x, $settlement.'ram:SpecifiedTradeSettlementLineMonetarySummation/ram:TaxTotalAmount[@currencyID="EUR"]'));
        self::assertSame(['220.00'], $this->values($x, $settlement.'ram:SpecifiedTradeSettlementLineMonetarySummation/ram:GrandTotalAmount'));
        self::assertSame(['S1', 'F202600040'], $this->values($x, $settlement.'ram:AdditionalReferencedDocument/ram:IssuerAssignedID'));
        self::assertSame(['AVV', 'AFL'], $this->values($x, $settlement.'ram:AdditionalReferencedDocument/ram:ReferenceTypeCode'));
    }

    public function test_extended_data_round_trips(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $read = (new CiiReader)->read((new CiiWriter)->write($invoice));

        self::assertSame([], $read->ignored);
        self::assertEquals($invoice, $read->invoice);
    }

    /**
     * Without the extended profile no EXT-FR-FE element is written, and the reader leaves them
     * unmapped.
     */
    public function test_extended_elements_are_not_read_outside_the_extended_profile(): void
    {
        $xml = (new CiiWriter)->write(FrenchInvoices::extendedCtcFr());
        $xml = str_replace(FrenchProfile::ExtendedCtcFr->value.'<', FrenchProfile::En16931->value.'<', $xml);

        $this->expectExceptionMessage('BT-151');

        (new CiiReader)->read($xml);
    }

    #[Group('france-rules')]
    public function test_written_extended_invoice_is_valid_under_the_french_rules(): void
    {
        $report = self::validator()->validate((new CiiWriter)->write(FrenchInvoices::extendedCtcFr()));

        self::assertTrue($report->isValid(), self::describe($report));
    }

    #[Group('france-rules')]
    public function test_paths_cover_every_ext_fr_fe_id_in_the_examples(): void
    {
        /** @var array<string, array{cii: ?string, ubl: ?string}> $paths */
        $paths = require __DIR__.'/ext-fr-fe-paths.php';
        $seen = 0;

        foreach (['cii' => 'CII/EXTENDED-CTC-FR', 'ubl' => 'UBL/EXTENDED-CTC-FR'] as $syntax => $folder) {
            $examples = Fixtures::franceExamples($folder);
            self::assertCount(5, $examples, $folder);
            foreach ($examples as $name => $file) {
                foreach (self::annotatedIds($file) as [$id, $path]) {
                    self::assertArrayHasKey($id, $paths, "{$name}: {$id} has no entry");
                    self::assertSame($paths[$id][$syntax], $path, "{$name}: {$id}");
                    $seen++;
                }
            }
        }

        self::assertGreaterThan(0, $seen);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        foreach (['UC10_', 'UC11_', 'UC12_', 'UC13_AUTOF', 'UC13_F'] as $prefix) {
            yield $prefix => [$prefix];
        }
    }

    #[Group('france-rules')]
    #[DataProvider('examples')]
    public function test_extended_ctc_fr_cii_examples_round_trip(string $prefix): void
    {
        $file = self::example($prefix);
        $source = (string) file_get_contents($file);

        $read = (new CiiReader)->read($source);

        self::assertSame([], $read->ignored);
        $invoice = $read->invoice;
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        self::assertSame(FrenchProfile::ExtendedCtcFr, $french?->profile);

        $written = (new CiiWriter)->write($invoice);
        $report = self::validator()->validate($written);
        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));

        $original = $this->xpath($source);
        $copy = $this->xpath($written);
        foreach (['ram:LineTotalAmount' => 'BT-106', 'ram:TaxBasisTotalAmount' => 'BT-109'] as $element => $term) {
            $expected = $this->values($original, self::SUMMATION.$element);
            self::assertCount(1, $expected, $term);
            self::assertSame($expected, $this->values($copy, self::SUMMATION.$element), $term);
        }

        // BT-106 is the sum of the regular lines; GROUP and INFORMATION lines stay outside it.
        $sum = BigDecimal::zero();
        foreach ($invoice->lines as $line) {
            $sum = $sum->plus($line->netAmount ?? BigDecimal::zero());
        }
        self::assertTrue($sum->isEqualTo($this->values($original, self::SUMMATION.'ram:LineTotalAmount')[0]), 'BT-106 is the sum of Invoice::$lines');

        // Every line, informational or not, is written back in document order.
        self::assertSame(
            $this->values($original, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'),
            $this->values($copy, self::LINE.'/ram:AssociatedDocumentLineDocument/ram:LineID'),
        );

        $again = (new CiiReader)->read($written);
        self::assertSame([], $again->ignored);
        self::assertEquals($invoice, $again->invoice);
        self::assertSame($written, (new CiiWriter)->write($again->invoice));
    }

    private static function example(string $prefix): string
    {
        foreach (Fixtures::franceExamples('CII/EXTENDED-CTC-FR') as $name => $file) {
            if (str_starts_with($name, $prefix)) {
                return $file;
            }
        }

        self::fail("No CII EXTENDED-CTC-FR example {$prefix}");
    }

    /**
     * EXT-FR-FE identifiers annotated in a commented example, each with the absolute path of the
     * element the annotation follows.
     *
     * @return list<array{string, string}>
     */
    private static function annotatedIds(string $file): array
    {
        $document = new DOMDocument;
        self::assertTrue($document->load($file));
        $result = [];
        foreach ((new DOMXPath($document))->query('//comment()') ?: [] as $comment) {
            if (! $comment instanceof DOMComment || preg_match_all('/(EXT-FR-FE-[A-Z0-9-]+?)\s*\(/', $comment->data, $match) < 1) {
                continue;
            }
            $element = $comment->previousSibling;
            while ($element !== null && ! $element instanceof DOMElement) {
                $element = $element->previousSibling;
            }
            self::assertInstanceOf(DOMElement::class, $element);
            $names = [];
            for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
                $names[] = $node->nodeName;
            }
            foreach ($match[1] as $id) {
                $result[] = [$id, '/'.implode('/', array_reverse($names))];
            }
        }

        return $result;
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

    private function cii(Invoice $invoice): DOMXPath
    {
        return $this->xpath((new CiiWriter)->write($invoice));
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $x = new DOMXPath($document);
        $x->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $x->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $x->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $x->registerNamespace('qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');

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

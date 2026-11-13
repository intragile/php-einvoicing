<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\ProfileReducer;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileReducerTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/facturx-reducer';

    private const LINE = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';

    /**
     * @return array<string, array{Invoice}>
     */
    public static function invoices(): array
    {
        return [
            'mixed VAT' => [TotalsCalculator::complete(InvoiceFactory::mixedVat())],
            'not subject to VAT' => [TotalsCalculator::complete(InvoiceFactory::notSubjectToVat())],
            'hostile text' => [InvoiceFactory::hostileText()],
        ];
    }

    #[DataProvider('invoices')]
    public function test_minimum_reduction_validates_against_minimum_xsd(Invoice $invoice): void
    {
        $xsd = RuleFiles::schema('MINIMUM');
        $reduced = (new ProfileReducer)->reduce((new CiiWriter)->write($invoice), $xsd);

        self::assertSame([], (new SchemaValidator)->validate(SafeXml::load($reduced->xml), $xsd));
        self::assertContains(self::LINE, $reduced->removed);
    }

    #[DataProvider('invoices')]
    public function test_basicwl_reduction_drops_lines_and_reports_them(Invoice $invoice): void
    {
        $xsd = RuleFiles::schema('BASIC-WL');
        $reduced = (new ProfileReducer)->reduce((new CiiWriter)->write($invoice), $xsd);

        self::assertContains(self::LINE, $reduced->removed);
        self::assertStringNotContainsString('IncludedSupplyChainTradeLineItem', $reduced->xml);
        self::assertSame([], (new SchemaValidator)->validate(SafeXml::load($reduced->xml), $xsd));
    }

    #[DataProvider('invoices')]
    public function test_removed_paths_are_sorted_and_distinct(Invoice $invoice): void
    {
        $removed = (new ProfileReducer)->reduce((new CiiWriter)->write($invoice), RuleFiles::schema('MINIMUM'))->removed;

        $expected = array_values(array_unique($removed));
        sort($expected, SORT_STRING);
        self::assertSame($expected, $removed);
    }

    #[DataProvider('invoices')]
    public function test_reducer_keeps_element_order(Invoice $invoice): void
    {
        $xml = (new CiiWriter)->write($invoice);

        foreach (['MINIMUM', 'BASIC-WL', 'BASIC'] as $profile) {
            $reduced = (new ProfileReducer)->reduce($xml, RuleFiles::schema($profile));

            // Removal depends only on the parent's type, so a path is removed everywhere or nowhere.
            $kept = array_values(array_filter(
                self::elementPaths(SafeXml::load($xml)),
                static function (string $path) use ($reduced): bool {
                    foreach ($reduced->removed as $removed) {
                        if ($path === $removed || str_starts_with($path, $removed.'/')) {
                            return false;
                        }
                    }

                    return true;
                },
            ));
            self::assertSame($kept, self::elementPaths(SafeXml::load($reduced->xml)), $profile);
        }
    }

    public function test_removes_elements_and_attributes_the_parent_type_does_not_allow(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" stray="1">
              <rsm:First listID="L" listAgencyID="6">A</rsm:First>
              <rsm:Unknown><rsm:Deep/></rsm:Unknown>
              <rsm:Second>
                <ram:Kept listID="K">x</ram:Kept>
                <ram:Dropped/>
              </rsm:Second>
              <rsm:Second>
                <ram:Dropped/>
              </rsm:Second>
              <rsm:Fourth>
                <rsm:Inner>i</rsm:Inner>
                <rsm:Extra/>
              </rsm:Fourth>
              <rsm:Fifth>
                <rsm:Gone/>
              </rsm:Fifth>
            </rsm:CrossIndustryInvoice>
            XML;

        $reduced = (new ProfileReducer)->reduce($xml, self::FIXTURES.'/small.xsd');

        self::assertSame([
            '/rsm:CrossIndustryInvoice/@stray',
            '/rsm:CrossIndustryInvoice/rsm:Fifth/rsm:Gone',
            '/rsm:CrossIndustryInvoice/rsm:First/@listAgencyID',
            '/rsm:CrossIndustryInvoice/rsm:Fourth/rsm:Extra',
            '/rsm:CrossIndustryInvoice/rsm:Second/ram:Dropped',
            '/rsm:CrossIndustryInvoice/rsm:Unknown',
        ], $reduced->removed);
        self::assertSame([], (new SchemaValidator)->validate(SafeXml::load($reduced->xml), self::FIXTURES.'/small.xsd'));
        self::assertStringContainsString('<rsm:First listID="L">A</rsm:First>', $reduced->xml);
        self::assertStringContainsString('<ram:Kept listID="K">x</ram:Kept>', $reduced->xml);
        self::assertStringContainsString('<rsm:Fifth/>', $reduced->xml);
    }

    public function test_nothing_is_removed_from_a_document_the_schema_allows(): void
    {
        $xml = (new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()));

        $reduced = (new ProfileReducer)->reduce($xml, RuleFiles::schema('EXTENDED'));

        self::assertSame([], $reduced->removed);
        self::assertSame($xml, $reduced->xml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableSchemas(): iterable
    {
        yield 'group' => ['unsupported-group.xsd', 'xs:group'];
        yield 'nested model group' => ['unsupported-nested.xsd', 'xs:choice in xs:sequence'];
        yield 'element reference' => ['unsupported-ref.xsd', 'ref'];
        yield 'network import' => ['network-import.xsd', 'http://example.invalid/remote.xsd'];
        yield 'missing file' => ['missing.xsd', 'missing.xsd'];
    }

    #[DataProvider('unusableSchemas')]
    public function test_unsupported_schema_constructs_are_refused(string $file, string $named): void
    {
        $this->expectException(UnusableSchema::class);
        $this->expectExceptionMessage($named);

        (new ProfileReducer)->reduce('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>', self::FIXTURES.'/'.$file);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapingSchemaLocations(): iterable
    {
        yield 'absolute path' => ['absolute-import.xsd', '/small-ram.xsd'];
        yield 'drive letter' => ['drive-import.xsd', 'C:/small-ram.xsd'];
        yield 'backslash root' => ['backslash-import.xsd', '\small-ram.xsd'];
        yield 'parent of the schema directory' => ['nested/escape-import.xsd', '../small-ram.xsd'];
    }

    #[DataProvider('escapingSchemaLocations')]
    public function test_absolute_schema_location_is_refused(string $file, string $location): void
    {
        try {
            (new ProfileReducer)->reduce('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>', self::FIXTURES.'/'.$file);
            self::fail('The schemaLocation "'.$location.'" was followed.');
        } catch (UnusableSchema $e) {
            self::assertStringContainsString('"'.$location.'"', $e->getMessage());
            self::assertStringContainsString('relative path inside the schema directory', $e->getMessage());
        }
    }

    public function test_parent_step_that_stays_inside_the_schema_directory_is_followed(): void
    {
        $reduced = (new ProfileReducer)->reduce('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100">x</rsm:CrossIndustryInvoice>', self::FIXTURES.'/inner-dotdot-import.xsd');

        self::assertSame([], $reduced->removed);
    }

    public function test_compiled_schemas_are_cached_by_real_path(): void
    {
        $reducer = new ProfileReducer;
        $xml = '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"><rsm:First>A</rsm:First></rsm:CrossIndustryInvoice>';

        $reducer->reduce($xml, self::FIXTURES.'/small.xsd');
        $reducer->reduce($xml, self::FIXTURES.'/nested/../small.xsd');
        $reducer->reduce($xml, self::FIXTURES.'/./small.xsd');

        $schemas = (new \ReflectionProperty(ProfileReducer::class, 'schemas'))->getValue($reducer);
        self::assertIsArray($schemas);
        self::assertSame([(string) realpath(self::FIXTURES.'/small.xsd')], array_keys($schemas));
    }

    public function test_comments_in_emptied_elements_are_removed(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
              <rsm:First>A</rsm:First>
              <!-- kept: its parent keeps elements -->
              <rsm:Fourth>
                <rsm:Inner>i</rsm:Inner>
              </rsm:Fourth>
              <rsm:Fifth>
                <!-- about the removed child -->
                <rsm:Gone/>
                <!-- trailing -->
              </rsm:Fifth>
            </rsm:CrossIndustryInvoice>
            XML;

        $reduced = (new ProfileReducer)->reduce($xml, self::FIXTURES.'/small.xsd');

        self::assertSame(['/rsm:CrossIndustryInvoice/rsm:Fifth/rsm:Gone'], $reduced->removed);
        self::assertStringContainsString('<rsm:Fifth/>', $reduced->xml);
        self::assertStringNotContainsString('removed child', $reduced->xml);
        self::assertStringNotContainsString('trailing', $reduced->xml);
        self::assertStringContainsString('<!-- kept: its parent keeps elements -->', $reduced->xml);
    }

    public function test_root_the_schema_does_not_declare_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ProfileReducer)->reduce('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>', RuleFiles::schema('MINIMUM'));
    }

    /**
     * Position-free paths of every element, in document order.
     *
     * @return list<string>
     */
    private static function elementPaths(DOMDocument $document): array
    {
        $paths = [];
        $walk = static function (DOMElement $element, string $parent) use (&$walk, &$paths): void {
            $path = $parent.'/'.$element->prefix.':'.$element->localName;
            $paths[] = $path;
            for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
                $walk($child, $path);
            }
        };
        self::assertInstanceOf(DOMElement::class, $document->documentElement);
        $walk($document->documentElement, '');

        return $paths;
    }
}

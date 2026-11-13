<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacturXXmlWriterTest extends TestCase
{
    /**
     * @return array<string, array{Invoice}>
     */
    public static function invoices(): array
    {
        return ProfileReducerTest::invoices();
    }

    /**
     * @return iterable<string, array{Invoice, FacturXProfile}>
     */
    public static function invoicesAndProfiles(): iterable
    {
        foreach (self::invoices() as $name => [$invoice]) {
            foreach (FacturXProfile::cases() as $profile) {
                if ($profile !== FacturXProfile::XRechnung) {
                    yield "{$name} as {$profile->value}" => [$invoice, $profile];
                }
            }
        }
    }

    #[DataProvider('invoicesAndProfiles')]
    public function test_every_profile_output_validates_against_its_schema(Invoice $invoice, FacturXProfile $profile): void
    {
        $reduced = (new FacturXXmlWriter)->write($invoice, $profile);
        $document = SafeXml::load($reduced->xml);

        self::assertSame([], (new SchemaValidator)->validate($document, RuleFiles::schema((string) $profile->ruleProfile())));
        self::assertSame($profile->guidelineId(), (new CiiDocument($document))->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
    }

    #[DataProvider('invoices')]
    public function test_en16931_and_extended_remove_nothing(Invoice $invoice): void
    {
        foreach ([FacturXProfile::En16931, FacturXProfile::Extended] as $profile) {
            self::assertSame([], (new FacturXXmlWriter)->write($invoice, $profile)->removed, $profile->value);
        }
    }

    #[DataProvider('invoices')]
    public function test_lineless_profiles_drop_the_lines(Invoice $invoice): void
    {
        foreach ([FacturXProfile::Minimum, FacturXProfile::BasicWl] as $profile) {
            self::assertContains(
                '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem',
                (new FacturXXmlWriter)->write($invoice, $profile)->removed,
                $profile->value,
            );
        }
    }

    public function test_business_process_type_is_kept_and_specification_replaced(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())
            ->withProcessControl(new ProcessControl('urn:example:other', 'urn:example:process'));

        $document = new CiiDocument(SafeXml::load((new FacturXXmlWriter)->write($invoice, FacturXProfile::Basic)->xml));

        self::assertSame(FacturXProfile::Basic->guidelineId(), $document->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('urn:example:process', $document->text('rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID'));
    }

    #[DataProvider('invoices')]
    public function test_xrechnung_is_the_full_cii(Invoice $invoice): void
    {
        $reduced = (new FacturXXmlWriter)->write($invoice, FacturXProfile::XRechnung);

        $expected = (new CiiWriter)->write($invoice->withProcessControl(new ProcessControl(FacturXProfile::XRechnung->guidelineId(), $invoice->processControl?->businessProcessType)));
        self::assertSame($expected, $reduced->xml);
        self::assertSame([], $reduced->removed);
    }
}

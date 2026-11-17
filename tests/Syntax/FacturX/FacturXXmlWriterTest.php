<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use Composer\Autoload\ClassLoader;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
    public function test_reduced_output_is_xsd_valid_for_every_profile(Invoice $invoice, FacturXProfile $profile): void
    {
        $reduced = (new FacturXXmlWriter)->write($invoice, $profile);
        $document = SafeXml::load($reduced->xml);

        self::assertSame([], (new SchemaValidator)->validate($document, RuleFiles::schema((string) $profile->ruleProfile())));
        self::assertSame($profile->guidelineId(), (new CiiDocument($document))->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
    }

    /**
     * Every Factur-X profile schema except MINIMUM allows repeated preceding invoice references
     * (BG-3), so they are written as they are; MINIMUM has no BG-3. XRECHNUNG is XRechnung CII,
     * which is always D16B and refuses a second reference.
     *
     * @return iterable<string, array{FacturXProfile, int}>
     */
    public static function precedingInvoiceCounts(): iterable
    {
        yield 'MINIMUM' => [FacturXProfile::Minimum, 0];
        yield 'BASIC WL' => [FacturXProfile::BasicWl, 2];
        yield 'BASIC' => [FacturXProfile::Basic, 2];
        yield 'EN 16931' => [FacturXProfile::En16931, 2];
        yield 'EXTENDED' => [FacturXProfile::Extended, 2];
    }

    #[DataProvider('precedingInvoiceCounts')]
    public function test_two_preceding_invoices_per_profile(FacturXProfile $profile, int $written): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withPrecedingInvoices([
            new PrecedingInvoiceReference(number: 'A-1'),
            new PrecedingInvoiceReference(number: 'A-2'),
        ]);

        $xml = (new FacturXXmlWriter)->write($invoice, $profile)->xml;

        self::assertSame($written, substr_count($xml, '<ram:InvoiceReferencedDocument>'));
    }

    public function test_xrechnung_profile_refuses_two_preceding_invoices(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withPrecedingInvoices([
            new PrecedingInvoiceReference(number: 'A-1'),
            new PrecedingInvoiceReference(number: 'A-2'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CII D16B carries one preceding invoice reference (BG-3)');

        (new FacturXXmlWriter)->write($invoice, FacturXProfile::XRechnung);
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

    /**
     * The nodes the MINIMUM rules prohibit although the MINIMUM schema allows them.
     */
    public function test_r3_removed_paths_are_pinned(): void
    {
        $removed = (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::Minimum)->removed;

        self::assertContains('/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress', $removed);
        self::assertContains('/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration', $removed);
    }

    public function test_failed_revalidation_throws_logic_exception_naming_the_profile(): void
    {
        // The full CII document still has its lines, which the MINIMUM schema does not allow.
        $full = SafeXml::load((new CiiWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat())));

        try {
            (new FacturXXmlWriter)->assertSchemaValid($full, FacturXProfile::Minimum, RuleFiles::schema('MINIMUM'));
            self::fail('A document the profile schema rejects passed the re-validation.');
        } catch (LogicException $e) {
            self::assertStringContainsString('MINIMUM', $e->getMessage());
            self::assertStringContainsString('IncludedSupplyChainTradeLineItem', $e->getMessage());
        }

        $reduced = SafeXml::load((new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::Minimum)->xml);
        (new FacturXXmlWriter)->assertSchemaValid($reduced, FacturXProfile::Minimum, RuleFiles::schema('MINIMUM'));
    }

    #[RunInSeparateProcess]
    public function test_missing_rules_package_names_the_composer_command(): void
    {
        self::assertFalse(class_exists(RuleFiles::class, false), 'The rules package is already loaded in this process.');
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('IntrAgile\\EInvoicing\\Rules\\FacturX\\', []);
        }
        self::assertFalse(class_exists(RuleFiles::class));

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('composer require intragile/einvoicing-rules-facturx');

        (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::Minimum);
    }
}

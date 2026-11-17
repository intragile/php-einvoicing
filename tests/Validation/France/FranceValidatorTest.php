<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use Composer\Autoload\ClassLoader;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles as FacturXRuleFiles;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class FranceValidatorTest extends TestCase
{
    private const EXAMPLES = 'FNFE_RFE_INVOICE/Z.example/TEST/';

    /** Example folders validated as standalone UBL or CII invoices. */
    private const STANDALONE = ['UBL/EN16931', 'UBL/EXTENDED-CTC-FR', 'CII/EN16931', 'CII/EXTENDED-CTC-FR'];

    /** Example folders holding the CII of Factur-X invoices. */
    private const FACTUR_X = ['FX/BASICWL', 'FX/EN16931', 'FX/EXTENDED'];

    /** The EN 16931 example whose VAT breakdown the EXTENDED-CTC-FR rules question. */
    private const UC13_CII = 'CII/EN16931/UC13_F202600037_EN16931_FX_CII_Commentee.xml';

    private const UC13_UBL = 'UBL/EN16931/UC13_F202600037_EN16931_UBL_Commentee.xml';

    private static ?FranceValidator $validator = null;

    private static function validator(): FranceValidator
    {
        return self::$validator ??= FranceValidator::create(InstalledFranceRules::dir());
    }

    /**
     * Every UBL, CII and Factur-X CII example of the pinned manifest, with the method that
     * validates it. Read from the committed manifest, so the list exists without the download.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function examples(): iterable
    {
        foreach ([...self::STANDALONE, ...self::FACTUR_X] as $folder) {
            $method = in_array($folder, self::FACTUR_X, true) ? 'validateFacturX' : 'validate';
            foreach (array_keys(FranceSources::files('example')) as $path) {
                $name = substr($path, strlen(self::EXAMPLES));
                if (str_starts_with($path, self::EXAMPLES.$folder.'/') && str_ends_with($path, '.xml') && dirname($name) === $folder) {
                    yield $name => [$name, $method];
                }
            }
        }
    }

    public function test_example_list_covers_every_invoice_folder(): void
    {
        $counts = [];
        foreach (self::examples() as $name => $_) {
            $counts[dirname($name)] = ($counts[dirname($name)] ?? 0) + 1;
        }

        self::assertSame([
            'UBL/EN16931' => 8,
            'UBL/EXTENDED-CTC-FR' => 5,
            'CII/EN16931' => 8,
            'CII/EXTENDED-CTC-FR' => 5,
            'FX/BASICWL' => 2,
            'FX/EN16931' => 8,
            'FX/EXTENDED' => 5,
        ], $counts);
    }

    #[DataProvider('examples')]
    public function test_every_fnfe_example_is_valid(string $name, string $method): void
    {
        $report = self::validator()->{$method}(self::example($name));
        self::assertInstanceOf(ValidationReport::class, $report);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        // Through its own chain, no example yields any finding, not even a warning.
        self::assertSame([], $report->verdict(), self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));
    }

    /**
     * UC13_F202600037 has a VAT breakdown of category E with the exemption reason "Exo1" (BT-120)
     * over lines that carry no exemption reason. The CEN rule BR-E-08 sums every E line and
     * passes. The EXTENDED-CTC-FR variant E-08rev sums only the lines whose exemption reason
     * equals the breakdown's, finds none and warns. Relabelled as EXTENDED-CTC-FR, the example
     * therefore runs that rule and yields exactly this one warning, while staying valid.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function extendedWarnings(): iterable
    {
        yield 'CII' => [self::UC13_CII, 'BR-FXEXT-E-08rev warning'];
        yield 'UBL' => [self::UC13_UBL, 'BR-FREXT-E-08rev warning'];
    }

    #[DataProvider('extendedWarnings')]
    public function test_uc13_vat_breakdown_warns_under_extended_ctc_fr_only(string $name, string $warning): void
    {
        $xml = self::example($name);
        self::assertSame([], self::validator()->validate($xml)->verdict());

        $report = self::validator()->validate(self::withSpecification($xml, FrenchProfile::En16931->value, FrenchProfile::ExtendedCtcFr->value));

        self::assertSame([$warning], $report->verdict(), self::describe($report));
        self::assertCount(1, $report->warnings());
        self::assertTrue($report->isValid(), self::describe($report));
    }

    /**
     * @return iterable<string, array{string, string, list<string>, Syntax}>
     */
    public static function chains(): iterable
    {
        $fr = '1.4.0.04';
        $fx = FacturXRuleFiles::VERSION;
        yield 'UBL EN 16931' => ['UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml', 'validate', ['en16931-ubl 1.3.16', "fr-flux2-ubl {$fr}"], Syntax::Ubl];
        yield 'UBL EXTENDED-CTC-FR' => ['UBL/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_UBL_Commentee.xml', 'validate', ["fr-extended-ctc-fr-ubl {$fr}", "fr-flux2-ubl {$fr}"], Syntax::Ubl];
        yield 'CII EN 16931' => ['CII/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml', 'validate', ['en16931-cii 1.3.16', "fr-flux2-cii {$fr}"], Syntax::Cii];
        yield 'CII EXTENDED-CTC-FR' => ['CII/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_CII_Commentee.xml', 'validate', ["fr-extended-ctc-fr-cii {$fr}", "fr-flux2-cii {$fr}"], Syntax::Cii];
        yield 'Factur-X BASIC WL' => ['FX/BASICWL/Facture_F20260023-LE_FOURNISSEUR-POUR-LE_CLIENT_BASICWL_FX_CII_Commentee.xml', 'validateFacturX', ["facturx-basicwl {$fx}", "fr-flux2-cii {$fr}"], Syntax::Cii];
        yield 'Factur-X EN 16931' => ['FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml', 'validateFacturX', ["facturx-en16931 {$fx}", "fr-flux2-cii {$fr}"], Syntax::Cii];
        yield 'Factur-X EXTENDED' => ['FX/EXTENDED/UC10_F202600004_MULTI-VENDEUR_EXTENDED_FX_CII_Commentee.xml', 'validateFacturX', ["fr-facturx-extended {$fr}", "fr-flux2-cii {$fr}"], Syntax::Cii];
    }

    /**
     * @param  list<string>  $ruleSets
     */
    #[DataProvider('chains')]
    public function test_report_lists_the_chain(string $name, string $method, array $ruleSets, Syntax $syntax): void
    {
        $report = self::validator()->{$method}(self::example($name));
        self::assertInstanceOf(ValidationReport::class, $report);

        self::assertSame($ruleSets, $report->ruleSets);
        self::assertSame($syntax, $report->syntax);
    }

    public function test_basic_profile_runs_the_facturx_basic_rules_then_flux2(): void
    {
        // The FNFE corpus has no BASIC example: an EN 16931 example relabelled as BASIC selects
        // the chain, whatever the verdict of the stricter profile rules.
        $xml = self::withSpecification(
            self::example('FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml'),
            FacturXProfile::En16931->guidelineId(),
            FacturXProfile::Basic->guidelineId(),
        );

        $report = self::validator()->validateFacturX($xml);

        self::assertSame(['facturx-basic '.FacturXRuleFiles::VERSION, 'fr-flux2-cii 1.4.0.04'], $report->ruleSets);
        self::assertSame(FacturXProfile::Basic->value, $report->scenario);
    }

    public function test_ubl_credit_note_is_checked_against_the_credit_note_schema(): void
    {
        // The FNFE corpus has no UBL credit note; a written one is schema-valid UBL 2.1, whatever
        // the French rules say about its content.
        $xml = (new UblWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat())->withType(InvoiceTypeCode::C_381));
        self::assertStringContainsString('<CreditNote', $xml);

        $report = self::validator()->validate($xml);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame(['en16931-ubl 1.3.16', 'fr-flux2-ubl 1.4.0.04'], $report->ruleSets);
    }

    public function test_ubl_extended_example_against_cen_is_not_the_chain(): void
    {
        foreach (['UBL/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_UBL_Commentee.xml', 'CII/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_CII_Commentee.xml'] as $name) {
            $report = self::validator()->validate(self::example($name));

            self::assertSame([], array_values(array_filter($report->ruleSets, static fn (string $id): bool => str_starts_with($id, 'en16931-'))), $name);
            self::assertSame([], array_values(array_filter($report->findings, static fn (Finding $f): bool => str_starts_with($f->ruleId, 'BR-') && ! str_starts_with($f->ruleId, 'BR-FR') && ! str_starts_with($f->ruleId, 'BR-FX'))), $name);
        }
    }

    public function test_minimum_profile_is_refused(): void
    {
        $xml = (new FacturXXmlWriter)->write(TotalsCalculator::complete(InvoiceFactory::mixedVat()), FacturXProfile::Minimum)->xml;

        try {
            self::validator()->validateFacturX($xml);
            self::fail('A Factur-X MINIMUM document was accepted.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString('MINIMUM', $exception->getMessage());
            self::assertStringContainsString('not a valid invoice under the French reform', $exception->getMessage());
        }
    }

    public function test_xrechnung_profile_is_refused(): void
    {
        $xml = self::withSpecification(
            self::example('FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml'),
            FacturXProfile::En16931->guidelineId(),
            XRechnung::CIUS,
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage(FacturXProfile::BasicWl->guidelineId());

        self::validator()->validateFacturX($xml);
    }

    public function test_unknown_specification_lists_accepted_ids(): void
    {
        $xml = self::withSpecification(
            self::example('UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml'),
            FrenchProfile::En16931->value,
            'urn:example:not-french',
        );

        try {
            self::validator()->validate($xml);
            self::fail('An unknown specification identifier was accepted.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString('urn:example:not-french', $exception->getMessage());
            foreach (FrenchProfile::cases() as $profile) {
                self::assertStringContainsString($profile->value, $exception->getMessage());
            }
        }
    }

    public function test_unknown_facturx_guideline_lists_accepted_ids(): void
    {
        $xml = self::withSpecification(
            self::example('FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml'),
            FacturXProfile::En16931->guidelineId(),
            'urn:example:not-french',
        );

        try {
            self::validator()->validateFacturX($xml);
            self::fail('An unknown guideline identifier was accepted.');
        } catch (UnsupportedDocument $exception) {
            foreach ([FacturXProfile::BasicWl, FacturXProfile::Basic, FacturXProfile::En16931, FacturXProfile::Extended] as $profile) {
                self::assertStringContainsString($profile->guidelineId(), $exception->getMessage());
            }
            self::assertStringNotContainsString(FacturXProfile::Minimum->guidelineId(), $exception->getMessage());
        }
    }

    public function test_facturx_identifier_in_a_standalone_cii_is_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('validateFacturX');

        self::validator()->validate(self::example('FX/BASICWL/Facture_F20260023-LE_FOURNISSEUR-POUR-LE_CLIENT_BASICWL_FX_CII_Commentee.xml'));
    }

    /**
     * validateFacturX() refuses MINIMUM and XRECHNUNG too, so pointing a caller to it would be
     * wrong; the refusal gives the reason instead.
     */
    public function test_refused_facturx_identifier_in_a_standalone_cii_gives_the_reason(): void
    {
        $example = self::example('FX/BASICWL/Facture_F20260023-LE_FOURNISSEUR-POUR-LE_CLIENT_BASICWL_FX_CII_Commentee.xml');
        foreach ([
            'MINIMUM' => [FacturXProfile::Minimum, 'not a valid invoice under the French reform'],
            'XRECHNUNG' => [FacturXProfile::XRechnung, 'not accepted for a French invoice'],
        ] as $case => [$profile, $reason]) {
            try {
                self::validator()->validate(self::withSpecification($example, FacturXProfile::BasicWl->guidelineId(), $profile->guidelineId()));
                self::fail("A Factur-X {$case} identifier was accepted.");
            } catch (UnsupportedDocument $exception) {
                self::assertStringContainsString($case, $exception->getMessage(), $case);
                self::assertStringContainsString($reason, $exception->getMessage(), $case);
                self::assertStringNotContainsString('validateFacturX', $exception->getMessage(), $case);
            }
        }
    }

    public function test_cdar_message_is_not_an_invoice(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CrossDomainAcknowledgementAndResponse is not an invoice');

        self::validator()->validate(self::example('CDAR/UC1_F202500003_02-CDV-202_Recue.xml'));
    }

    public function test_report_root_is_not_an_invoice(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Report is not an invoice');

        self::validator()->validate('<Report xmlns="urn:example:flux10"/>');
    }

    public function test_ubl_document_is_not_a_facturx_invoice(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CII CrossIndustryInvoice');

        self::validator()->validateFacturX(self::example('UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml'));
    }

    public function test_facturx_without_rules_map_names_composer_command(): void
    {
        $validator = new FranceValidator(InstalledFranceRules::dir(), [], []);

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('composer require intragile/einvoicing-rules-facturx');

        $validator->validateFacturX(self::example('FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml'));
    }

    #[RunInSeparateProcess]
    public function test_facturx_without_rules_package_names_composer_command(): void
    {
        self::assertFalse(class_exists(FacturXRuleFiles::class, false), 'The rules package is already loaded in this process.');
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('IntrAgile\\EInvoicing\\Rules\\FacturX\\', []);
        }
        self::assertFalse(class_exists(FacturXRuleFiles::class));

        // No French rule is needed before the missing package is noticed.
        $dir = BuildDir::create('france-validator-no-facturx');
        try {
            $this->expectException(RulesNotInstalled::class);
            $this->expectExceptionMessage('composer require intragile/einvoicing-rules-facturx');

            FranceValidator::create($dir)->validateFacturX(self::example('FX/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml'));
        } finally {
            BuildDir::remove($dir);
        }
    }

    public function test_missing_french_rules_name_the_install_command(): void
    {
        $dir = BuildDir::create('france-validator-empty');
        try {
            $this->expectException(RulesNotInstalled::class);
            $this->expectExceptionMessage('vendor/bin/einvoicing-rules install france');

            FranceValidator::create($dir)->validate(self::example('UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml'));
        } finally {
            BuildDir::remove($dir);
        }
    }

    private static function example(string $name): string
    {
        $xml = file_get_contents(Fixtures::franceRfeRoot().'/'.self::EXAMPLES.$name);
        self::assertIsString($xml, $name);

        return $xml;
    }

    private static function withSpecification(string $xml, string $from, string $to): string
    {
        $count = 0;
        $changed = preg_replace('/(<(?:ram:ID|cbc:CustomizationID)>\s*)'.preg_quote($from, '/').'(\s*<)/', '${1}'.$to.'${2}', $xml, 1, $count);
        self::assertSame(1, $count, 'The specification identifier was not found.');
        self::assertIsString($changed);

        return $changed;
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
}

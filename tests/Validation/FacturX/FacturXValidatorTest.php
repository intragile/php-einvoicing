<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\FacturX;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\ProfileMismatch;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacturXValidatorTest extends TestCase
{
    private static ?FacturXValidator $validator = null;

    private static function validator(): FacturXValidator
    {
        return self::$validator ??= FacturXValidator::create();
    }

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

    /**
     * @return iterable<string, array{FacturXProfile}>
     */
    public static function factorXProfiles(): iterable
    {
        foreach (FacturXProfile::cases() as $profile) {
            if ($profile !== FacturXProfile::XRechnung) {
                yield $profile->value => [$profile];
            }
        }
    }

    private static function xml(FacturXProfile $profile, ?Invoice $invoice = null): string
    {
        return (new FacturXXmlWriter)->write($invoice ?? TotalsCalculator::complete(InvoiceFactory::mixedVat()), $profile)->xml;
    }

    #[DataProvider('factorXProfiles')]
    public function test_each_profile_selected_from_guideline_id(FacturXProfile $profile): void
    {
        $report = self::validator()->validate(self::xml($profile));

        self::assertSame($profile->value, $report->scenario);
        self::assertSame(['facturx-'.strtolower(str_replace(['-', ' '], '', (string) $profile->ruleProfile())).' 1.09.2'], $report->ruleSets);
        self::assertSame(Syntax::Cii, $report->syntax);
    }

    public function test_unknown_guideline_id_lists_accepted_ids(): void
    {
        $xml = str_replace(FacturXProfile::En16931->guidelineId(), 'urn:example:unknown', self::xml(FacturXProfile::En16931));

        try {
            self::validator()->validate($xml);
            self::fail('Expected UnsupportedDocument.');
        } catch (UnsupportedDocument $e) {
            foreach (FacturXProfile::cases() as $profile) {
                self::assertStringContainsString($profile->guidelineId(), $e->getMessage());
            }
            self::assertStringContainsString(XRechnung::EXTENSION, $e->getMessage());
            self::assertStringContainsString('urn:example:unknown', $e->getMessage());
        }
    }

    public function test_missing_guideline_id_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);

        self::validator()->validate('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>');
    }

    public function test_doctype_is_rejected(): void
    {
        $this->expectException(UnsafeXml::class);

        self::validator()->validate('<!DOCTYPE x [<!ENTITY a "b">]>'.self::xml(FacturXProfile::Basic));
    }

    public function test_explicit_profile_mismatch_throws(): void
    {
        $this->expectException(ProfileMismatch::class);

        self::validator()->validate(self::xml(FacturXProfile::Basic), FacturXProfile::En16931);
    }

    public function test_profile_mismatch_is_an_unsupported_document(): void
    {
        self::assertInstanceOf(UnsupportedDocument::class, new ProfileMismatch('x'));
    }

    public function test_explicit_matching_profile_is_accepted(): void
    {
        $report = self::validator()->validate(self::xml(FacturXProfile::Basic), FacturXProfile::Basic);

        self::assertSame('BASIC', $report->scenario);
    }

    public function test_schema_invalid_still_runs_schematron(): void
    {
        // Dropping the invoice number (BT-1) violates the XSD and the profile's Schematron rules.
        $xml = (string) preg_replace('#(<rsm:ExchangedDocument>\s*)<ram:ID>[^<]*</ram:ID>#', '$1', self::xml(FacturXProfile::En16931));

        $report = self::validator()->validate($xml);

        self::assertNotSame([], $report->schemaErrors);
        self::assertSame(['facturx-en16931 1.09.2'], $report->ruleSets);
        self::assertNotSame([], $report->findings);
        self::assertFalse($report->isValid());
    }

    public function test_xrechnung_delegates_to_xrechnung_validator(): void
    {
        $xml = str_replace(
            FacturXProfile::En16931->guidelineId(),
            FacturXProfile::XRechnung->guidelineId(),
            self::xml(FacturXProfile::En16931),
        );
        $xrechnung = XRechnungValidator::create();

        $report = (new FacturXValidator([], [], $xrechnung))->validate($xml);

        self::assertEquals($xrechnung->validate($xml), $report);
        self::assertNotSame('XRECHNUNG', $report->scenario);
        self::assertSame([], array_filter($report->ruleSets, static fn (string $s): bool => str_starts_with($s, 'facturx-')));
    }

    public function test_xrechnung_extension_id_delegates_too(): void
    {
        $xml = str_replace(
            FacturXProfile::En16931->guidelineId(),
            XRechnung::EXTENSION,
            self::xml(FacturXProfile::En16931),
        );
        $xrechnung = XRechnungValidator::create();

        self::assertEquals($xrechnung->validate($xml), (new FacturXValidator([], [], $xrechnung))->validate($xml));
    }

    public function test_explicit_xrechnung_profile_matches_document(): void
    {
        $xml = str_replace(
            FacturXProfile::En16931->guidelineId(),
            FacturXProfile::XRechnung->guidelineId(),
            self::xml(FacturXProfile::En16931),
        );

        $this->expectException(ProfileMismatch::class);

        self::validator()->validate($xml, FacturXProfile::En16931);
    }

    /**
     * Writer output must be valid for every profile.
     */
    #[DataProvider('writerCases')]
    public function test_writer_output_is_valid_per_profile(Invoice $invoice, FacturXProfile $profile): void
    {
        $report = self::validator()->validate(self::xml($profile, $invoice));

        self::assertSame(
            true,
            $report->isValid(),
            json_encode(['schema' => $report->schemaErrors, 'fatal' => $report->fatal(), 'dynamic' => $report->dynamicErrors], JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '',
        );
    }

    /**
     * @return iterable<string, array{Invoice, FacturXProfile}>
     */
    public static function writerCases(): iterable
    {
        foreach (self::invoices() as $name => [$invoice]) {
            foreach (self::factorXProfiles() as [$profile]) {
                yield "{$name} as {$profile->value}" => [$invoice, $profile];
            }
        }
    }
}

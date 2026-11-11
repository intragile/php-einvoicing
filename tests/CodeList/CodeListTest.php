<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\CodeList;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\MimeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\CodeList\VatExemptionReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CodeListTest extends TestCase
{
    public function test_vat_category_ae_is_reverse_charge(): void
    {
        self::assertSame(VatCategory::ReverseCharge, VatCategory::from('AE'));
        self::assertSame('AE', VatCategory::ReverseCharge->value);
        self::assertSame(VatCategory::StandardRate, VatCategory::from('S'));
    }

    public function test_vat_category_contains_exactly_the_cen_categories(): void
    {
        $codes = array_map(static fn (VatCategory $c): string => $c->value, VatCategory::cases());
        sort($codes);

        self::assertSame(['AE', 'B', 'E', 'G', 'K', 'L', 'M', 'O', 'S', 'Z'], $codes);
    }

    public function test_credit_note_detection(): void
    {
        self::assertTrue(InvoiceTypeCode::from('381')->isCreditNote());
        self::assertFalse(InvoiceTypeCode::from('380')->isCreditNote());

        foreach (['381', '261', '262', '296', '308', '532'] as $code) {
            self::assertTrue(InvoiceTypeCode::from($code)->isCreditNote(), $code);
        }

        foreach (['380', '384', '389', '751'] as $code) {
            self::assertFalse(InvoiceTypeCode::from($code)->isCreditNote(), $code);
        }
    }

    /**
     * The CreditNoteTypeCode branch of UBL BR-CL-01 in the CEN artefacts 1.3.17.
     */
    private const CREDIT_NOTE_CODES = ['81', '83', '261', '262', '296', '308', '381', '396', '420', '458', '502', '503', '532'];

    /**
     * @return array<string, array{string}>
     */
    public static function creditNoteCodes(): array
    {
        $cases = [];
        foreach (self::CREDIT_NOTE_CODES as $code) {
            $cases['code '.$code] = [$code];
        }

        return $cases;
    }

    #[DataProvider('creditNoteCodes')]
    public function test_each_br_cl_01_credit_note_code_is_a_credit_note(string $code): void
    {
        self::assertTrue(InvoiceTypeCode::from($code)->isCreditNote());
    }

    public function test_every_other_type_code_is_an_invoice(): void
    {
        $invoices = array_filter(
            InvoiceTypeCode::cases(),
            static fn (InvoiceTypeCode $type): bool => ! in_array($type->value, self::CREDIT_NOTE_CODES, true),
        );

        self::assertCount(count(InvoiceTypeCode::cases()) - count(self::CREDIT_NOTE_CODES), $invoices);
        foreach ($invoices as $type) {
            self::assertFalse($type->isCreditNote(), $type->value);
        }
    }

    public function test_currency_codes(): void
    {
        self::assertTrue(CurrencyCode::isValid('HUF'));
        self::assertTrue(CurrencyCode::isValid('EUR'));
        self::assertFalse(CurrencyCode::isValid('XXX1'));
        self::assertFalse(CurrencyCode::isValid('huf'));
        self::assertSame(CurrencyCode::HUF, CurrencyCode::from('HUF'));
    }

    public function test_unit_codes(): void
    {
        self::assertTrue(UnitCode::isValid('C62'));
        self::assertTrue(UnitCode::isValid('H87'));
        self::assertTrue(UnitCode::isValid('1I'));
        self::assertFalse(UnitCode::isValid('PIECE'));
        self::assertSame('1I', UnitCode::C_1I->value);
    }

    public function test_electronic_address_scheme(): void
    {
        self::assertTrue(ElectronicAddressScheme::isValid('0208'));
        self::assertTrue(ElectronicAddressScheme::isValid('9910'));
        self::assertFalse(ElectronicAddressScheme::isValid('208'));
    }

    /**
     * @return iterable<string, array{class-string<\BackedEnum>, string, string}>
     */
    public static function samples(): iterable
    {
        yield 'invoice type' => [InvoiceTypeCode::class, '380', 'X380'];
        yield 'country' => [CountryCode::class, 'HU', 'XX'];
        yield 'payment means' => [PaymentMeansCode::class, '58', '99'];
        yield 'identifier scheme' => [IdentifierScheme::class, '0088', 'SEPA'];
        yield 'exemption reason' => [VatExemptionReason::class, 'VATEX-EU-AE', 'VATEX-EU-XX'];
        yield 'allowance reason' => [AllowanceReasonCode::class, '95', 'AA'];
        yield 'charge reason' => [ChargeReasonCode::class, 'FC', '95'];
        yield 'mime' => [MimeCode::class, 'application/pdf', 'application/zip'];
        yield 'text subject' => [TextSubjectCode::class, 'AAI', 'ZZZZ'];
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    #[DataProvider('samples')]
    public function test_is_valid_matches_try_from(string $enum, string $valid, string $invalid): void
    {
        $isValid = [$enum, 'isValid'];
        self::assertIsCallable($isValid);

        self::assertTrue($isValid($valid));
        self::assertNotNull($enum::tryFrom($valid));
        self::assertFalse($isValid($invalid));
        self::assertNull($enum::tryFrom($invalid));
    }

    public function test_generator_refuses_an_unexpected_extra_code_literal(): void
    {
        $official = dirname(__DIR__).'/fixtures/official/cen-1.3.17';
        $source = sys_get_temp_dir().'/einvoicing-codelists-'.bin2hex(random_bytes(4));
        foreach (['ubl', 'cii'] as $syntax) {
            $file = "{$syntax}/schematron/codelist/EN16931-".strtoupper($syntax).'-codes.sch';
            mkdir(dirname("{$source}/{$file}"), 0777, true);
            copy("{$official}/{$file}", "{$source}/{$file}");
        }
        $ubl = "{$source}/ubl/schematron/codelist/EN16931-UBL-codes.sch";
        $document = new DOMDocument;
        $document->load($ubl);
        $asserts = (new DOMXPath($document))->query('//*[@id="BR-CL-04"]');
        self::assertNotFalse($asserts);
        $assert = $asserts->item(0);
        self::assertInstanceOf(DOMElement::class, $assert);
        $assert->setAttribute('test', $assert->getAttribute('test')." or contains(' XTS ', concat(' ', normalize-space(.), ' '))");
        $document->save($ubl);

        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__, 2).'/bin/generate-codelists.php')
            .' --check '.escapeshellarg('--source='.$source).' 2>&1';
        exec($command, $output, $status);
        foreach (['ubl', 'cii'] as $syntax) {
            unlink("{$source}/{$syntax}/schematron/codelist/EN16931-".strtoupper($syntax).'-codes.sch');
            rmdir("{$source}/{$syntax}/schematron/codelist");
            rmdir("{$source}/{$syntax}/schematron");
            rmdir("{$source}/{$syntax}");
        }
        rmdir($source);

        self::assertSame(1, $status, implode("\n", $output));
        self::assertStringContainsString('BR-CL-04 has 2 code literals in ubl, expected 1', implode("\n", $output));
    }

    public function test_generated_enums_are_up_to_date(): void
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__, 2).'/bin/generate-codelists.php').' --check 2>&1';
        exec($command, $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
    }
}

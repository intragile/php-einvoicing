<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\CodeList;

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

    public function test_generated_enums_are_up_to_date(): void
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__, 2).'/bin/generate-codelists.php').' --check 2>&1';
        exec($command, $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
    }
}

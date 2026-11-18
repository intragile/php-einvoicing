<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatRateCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{VatCategory, ?string, ?CountryCode, VatRateCode}>
     */
    public static function rateRows(): iterable
    {
        yield 'S 23' => [VatCategory::StandardRate, '23', null, VatRateCode::Rate23];
        yield 'S 23.00' => [VatCategory::StandardRate, '23.00', CountryCode::PL, VatRateCode::Rate23];
        yield 'S 22' => [VatCategory::StandardRate, '22', null, VatRateCode::Rate22];
        yield 'S 8' => [VatCategory::StandardRate, '8', null, VatRateCode::Rate8];
        yield 'S 7' => [VatCategory::StandardRate, '7', null, VatRateCode::Rate7];
        yield 'S 5' => [VatCategory::StandardRate, '5.0', null, VatRateCode::Rate5];
        yield 'Z' => [VatCategory::ZeroRated, '0', null, VatRateCode::ZeroDomestic];
        yield 'K' => [VatCategory::IntraCommunitySupply, '0', CountryCode::DE, VatRateCode::ZeroIntraCommunity];
        yield 'G' => [VatCategory::ExportOutsideEu, '0', CountryCode::US, VatRateCode::ZeroExport];
        yield 'E' => [VatCategory::Exempt, '0', null, VatRateCode::Exempt];
        yield 'AE + PL' => [VatCategory::ReverseCharge, '0', CountryCode::PL, VatRateCode::ReverseCharge];
        yield 'AE + DE' => [VatCategory::ReverseCharge, '0', CountryCode::DE, VatRateCode::EuServicesArticle100];
        yield 'AE + null' => [VatCategory::ReverseCharge, '0', null, VatRateCode::ReverseCharge];
        yield 'O' => [VatCategory::NotSubjectToVat, null, CountryCode::DE, VatRateCode::OutsideTerritory];
    }

    #[DataProvider('rateRows')]
    public function test_rate_mapping_table(VatCategory $category, ?string $rate, ?CountryCode $buyer, VatRateCode $expected): void
    {
        self::assertSame(
            $expected,
            VatRateCode::fromModel($category, $rate === null ? null : BigDecimal::of($rate), $buyer),
        );
    }

    /**
     * @return iterable<string, array{VatCategory, ?string}>
     */
    public static function unmappableRows(): iterable
    {
        yield 'S 4' => [VatCategory::StandardRate, '4'];
        yield 'S 3' => [VatCategory::StandardRate, '3'];
        yield 'S 19' => [VatCategory::StandardRate, '19'];
        yield 'S without rate' => [VatCategory::StandardRate, null];
        yield 'L' => [VatCategory::CanaryIslands, '7'];
        yield 'M' => [VatCategory::CeutaAndMelilla, '4'];
        yield 'B' => [VatCategory::TransferredVat, '22'];
    }

    #[DataProvider('unmappableRows')]
    public function test_unmappable_rates_are_refused(VatCategory $category, ?string $rate): void
    {
        try {
            VatRateCode::fromModel($category, $rate === null ? null : BigDecimal::of($rate), CountryCode::PL);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString($category->value, $e->getMessage());
            self::assertStringContainsString($rate ?? 'no rate', $e->getMessage());
        }
    }

    public function test_code_to_bucket_table(): void
    {
        $expected = [
            '23' => '1', '22' => '1', '8' => '2', '7' => '2', '5' => '3', '4' => null, '3' => null,
            '0 KR' => '6_1', '0 WDT' => '6_2', '0 EX' => '6_3', 'zw' => '7', 'oo' => '10',
            'np I' => '8', 'np II' => '9',
        ];
        foreach (VatRateCode::cases() as $code) {
            self::assertSame($expected[$code->value], $code->bucket()?->value, $code->value);
        }
    }

    public function test_bucket_elements(): void
    {
        $vat = ['1' => 'P_14_1', '2' => 'P_14_2', '3' => 'P_14_3', '4' => 'P_14_4', '5' => 'P_14_5'];
        $pln = ['1' => 'P_14_1W', '2' => 'P_14_2W', '3' => 'P_14_3W', '4' => 'P_14_4W'];
        foreach (VatBucket::cases() as $bucket) {
            self::assertSame('P_13_'.$bucket->value, $bucket->netElement());
            self::assertSame($vat[$bucket->value] ?? null, $bucket->vatElement(), $bucket->value);
            self::assertSame($pln[$bucket->value] ?? null, $bucket->plnVatElement(), $bucket->value);
        }
    }

    public function test_reverse_mapping(): void
    {
        $expected = [
            '23' => ['S', '23'], '22' => ['S', '22'], '8' => ['S', '8'], '7' => ['S', '7'],
            '5' => ['S', '5'], '4' => ['S', '4'], '3' => ['S', '3'],
            '0 KR' => ['Z', '0'], '0 WDT' => ['K', '0'], '0 EX' => ['G', '0'], 'zw' => ['E', '0'],
            'oo' => ['AE', '0'], 'np II' => ['AE', '0'], 'np I' => ['O', null],
        ];
        foreach (VatRateCode::cases() as $code) {
            [$category, $rate] = $code->toModel();
            self::assertSame($expected[$code->value], [$category->value, $rate?->__toString()], $code->value);
        }

        [$category, $rate] = VatRateCode::from('0 WDT')->toModel();
        self::assertSame(VatCategory::IntraCommunitySupply, $category);
        self::assertTrue(BigDecimal::zero()->isEqualTo($rate ?? BigDecimal::one()));
        self::assertSame([VatCategory::NotSubjectToVat, null], VatRateCode::from('np I')->toModel());
    }
}

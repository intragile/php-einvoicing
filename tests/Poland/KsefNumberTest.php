<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use IntrAgile\EInvoicing\Poland\KsefNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KsefNumberTest extends TestCase
{
    private const DOCS_EXAMPLE = '5265877635-20250826-0100001AF629-AF';

    public function test_docs_example_number_is_valid(): void
    {
        $number = KsefNumber::parse(self::DOCS_EXAMPLE);

        self::assertSame(self::DOCS_EXAMPLE, $number->value);
        self::assertSame('5265877635', $number->issuerIdentifier);
        self::assertSame('2025-08-26', $number->acceptedOn->format('Y-m-d'));
        self::assertSame('0100001AF629', $number->technicalPart);
        self::assertTrue(KsefNumber::isValid(self::DOCS_EXAMPLE));
    }

    public function test_checksum_of_docs_example(): void
    {
        self::assertSame('AF', KsefNumber::checksum('5265877635-20250826-0100001AF629'));
    }

    public function test_brochure_example_number_fails_checksum(): void
    {
        self::assertFalse(KsefNumber::isValid('9999999999-20260714-D5FB0C9ED490-9A'));
        self::assertSame('19', KsefNumber::checksum('9999999999-20260714-D5FB0C9ED490'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('checksum');
        KsefNumber::parse('9999999999-20260714-D5FB0C9ED490-9A');
    }

    public function test_hyphenated_36_char_form_is_refused(): void
    {
        self::assertFalse(KsefNumber::isValid('5265877635-20250826-010000-1AF629-AF'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('35 characters');
        KsefNumber::parse('5265877635-20250826-010000-1AF629-AF');
    }

    public function test_lower_case_hex_is_refused(): void
    {
        self::assertFalse(KsefNumber::isValid('5265877635-20250826-0100001af629-AF'));
        self::assertFalse(KsefNumber::isValid('5265877635-20250826-0100001AF629-af'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('upper-case');
        KsefNumber::parse('5265877635-20250826-0100001af629-AF');
    }

    public function test_alternative_issuer_identifiers_are_accepted(): void
    {
        foreach (['M123456789', 'ABC1234567'] as $issuer) {
            $first32 = $issuer.'-20250826-0100001AF629';
            $number = KsefNumber::parse($first32.'-'.KsefNumber::checksum($first32));
            self::assertSame($issuer, $number->issuerIdentifier);
        }
    }

    public function test_defects_are_named(): void
    {
        $cases = [
            '0265877635-20250826-0100001AF629-AF' => 'issuer',
            '5265877635-20250230-0100001AF629-AF' => 'date',
            '5265877635-20190826-0100001AF629-AF' => 'date',
            '5265877635_20250826-0100001AF629-AF' => 'segment',
            '5265877635-20250826-0100001AG629-AF' => 'segment',
        ];

        foreach ($cases as $value => $defect) {
            try {
                KsefNumber::parse($value);
                self::fail('Accepted '.$value);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($defect, $e->getMessage(), $value);
                self::assertStringContainsString($value, $e->getMessage());
            }
            self::assertFalse(KsefNumber::isValid($value), $value);
        }
    }

    public function test_checksum_requires_32_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        KsefNumber::checksum('5265877635-20250826');
    }
}

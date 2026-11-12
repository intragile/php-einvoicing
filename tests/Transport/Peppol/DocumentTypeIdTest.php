<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Peppol;

use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentTypeIdTest extends TestCase
{
    public function test_sbdh_parts_of_the_invoice(): void
    {
        self::assertSame(
            ['standard' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'type' => 'Invoice', 'typeVersion' => '2.1'],
            (new DocumentTypeId(PeppolBis3::INVOICE))->sbdhParts(),
        );
    }

    public function test_sbdh_parts_of_the_credit_note(): void
    {
        self::assertSame(
            ['standard' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2', 'type' => 'CreditNote', 'typeVersion' => '2.1'],
            (new DocumentTypeId(PeppolBis3::CREDIT_NOTE))->sbdhParts(),
        );
    }

    public function test_uri_carries_the_scheme(): void
    {
        self::assertSame('busdox-docid-qns::'.PeppolBis3::INVOICE, (new DocumentTypeId(PeppolBis3::INVOICE))->toUri());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformed(): array
    {
        return [
            'no customization' => ['urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice'],
            'no version' => ['urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017'],
            'no local name' => ['urn:oasis:names:specification:ubl:schema:xsd:Invoice-2##urn:cen.eu:en16931:2017::2.1'],
            'empty version' => ['urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017::'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_value_has_no_sbdh_parts(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DocumentTypeId($value))->sbdhParts();
    }
}

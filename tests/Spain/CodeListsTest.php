<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain;

use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use PHPUnit\Framework\TestCase;

final class CodeListsTest extends TestCase
{
    public function test_regime_keys_per_tax(): void
    {
        self::assertFalse(RegimeKey::from('21')->allowedFor(TaxType::Iva));
        self::assertTrue(RegimeKey::from('21')->allowedFor(TaxType::Igic));
        self::assertTrue(RegimeKey::from('20')->allowedFor(TaxType::Iva));
        self::assertFalse(RegimeKey::from('02')->allowedFor(TaxType::Ipsi));
        self::assertTrue(RegimeKey::from('20')->allowedFor(TaxType::Ipsi));
        self::assertFalse(RegimeKey::from('01')->allowedFor(TaxType::Other));
        self::assertTrue(RegimeKey::from('01')->allowedFor(TaxType::Ipsi));
    }

    public function test_exempt_operations_per_tax(): void
    {
        self::assertFalse(ExemptOperation::E7->allowedFor(TaxType::Iva));
        self::assertFalse(ExemptOperation::E8->allowedFor(TaxType::Ipsi));
        self::assertTrue(ExemptOperation::E7->allowedFor(TaxType::Igic));
        self::assertTrue(ExemptOperation::E1->allowedFor(TaxType::Iva));
    }

    public function test_corrective_invoice_types(): void
    {
        foreach ([InvoiceType::R1, InvoiceType::R2, InvoiceType::R3, InvoiceType::R4, InvoiceType::R5] as $type) {
            self::assertTrue($type->isCorrective());
        }
        foreach ([InvoiceType::F1, InvoiceType::F2, InvoiceType::F3] as $type) {
            self::assertFalse($type->isCorrective());
        }
    }
}

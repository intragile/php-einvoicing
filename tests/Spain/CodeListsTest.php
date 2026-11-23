<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain;

use BackedEnum;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\GeneratedBy;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\Attributes\Group;
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

    /**
     * Each code-list enum holds exactly the values of its simple type in the official
     * SuministroInformacion.xsd.
     */
    #[Group('verifactu-official')]
    public function test_enum_values_match_the_xsd(): void
    {
        $document = new DOMDocument;
        self::assertTrue($document->load(Fixtures::verifactuSchemas()->informationXsd(), LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        /** @var array<string, class-string<BackedEnum>> $types */
        $types = [
            'ClaveTipoFacturaType' => InvoiceType::class,
            'ClaveTipoRectificativaType' => CorrectionType::class,
            'ImpuestoType' => TaxType::class,
            'IdOperacionesTrascendenciaTributariaType' => RegimeKey::class,
            'CalificacionOperacionType' => OperationQualification::class,
            'OperacionExentaType' => ExemptOperation::class,
            'PersonaFisicaJuridicaIDTypeType' => ForeignIdType::class,
            'TercerosODestinatarioType' => IssuedBy::class,
            'GeneradoPorType' => GeneratedBy::class,
        ];
        foreach ($types as $type => $enum) {
            $official = [];
            foreach ($xpath->query("//xs:simpleType[@name='{$type}']//xs:enumeration/@value") ?: [] as $value) {
                $official[] = (string) $value->nodeValue;
            }
            $ours = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());
            sort($official, SORT_STRING);
            sort($ours, SORT_STRING);

            self::assertNotSame([], $official, $type);
            self::assertSame($official, $ours, "{$enum} against {$type}");
        }
    }
}

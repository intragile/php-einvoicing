<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\Poland\CorrectionEffect;
use IntrAgile\EInvoicing\Poland\Gtu;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\PaymentForm;
use IntrAgile\EInvoicing\Poland\Procedure;
use IntrAgile\EInvoicing\Poland\TaxpayerStatus;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Schema;
use PHPUnit\Framework\TestCase;
use UnitEnum;

final class CodeListsTest extends TestCase
{
    public function test_enum_values_match_the_xsd(): void
    {
        $map = [
            'TRodzajFaktury' => InvoiceKind::class,
            'TStawkaPodatku' => VatRateCode::class,
            'TGTU' => Gtu::class,
            'TOznaczenieProcedury' => Procedure::class,
            'TFormaPlatnosci' => PaymentForm::class,
            'TRolaPodmiotu3' => ThirdPartyRole::class,
            'TTypKorekty' => CorrectionEffect::class,
            'TStatusInfoPodatnika' => TaxpayerStatus::class,
        ];

        foreach ($map as $type => $enum) {
            $expected = self::xsdEnumeration($type);
            self::assertNotSame([], $expected, $type);
            self::assertSame($expected, array_map(
                static fn (UnitEnum $case): string => (string) $case->value,
                $enum::cases(),
            ), $type);
        }
    }

    public function test_kind_from_type_code_and_back(): void
    {
        self::assertSame(InvoiceKind::Vat, InvoiceKind::fromTypeCode(InvoiceTypeCode::C_380));
        self::assertSame(InvoiceKind::Kor, InvoiceKind::fromTypeCode(InvoiceTypeCode::C_381));
        self::assertSame(InvoiceKind::Kor, InvoiceKind::fromTypeCode(InvoiceTypeCode::C_384));
        self::assertSame(InvoiceKind::Zal, InvoiceKind::fromTypeCode(InvoiceTypeCode::C_386));
        self::assertNull(InvoiceKind::fromTypeCode(InvoiceTypeCode::C_389));

        $expected = [
            'VAT' => '380', 'ROZ' => '380', 'UPR' => '380',
            'KOR' => '384', 'KOR_ZAL' => '384', 'KOR_ROZ' => '384',
            'ZAL' => '386',
        ];
        foreach (InvoiceKind::cases() as $kind) {
            self::assertSame($expected[$kind->value], $kind->typeCode()->value, $kind->value);
            self::assertSame(str_starts_with($kind->value, 'KOR'), $kind->isCorrection(), $kind->value);
        }
    }

    public function test_order_procedures_exclude_import_codes(): void
    {
        $allowed = array_values(array_map(
            static fn (Procedure $p): string => $p->value,
            array_filter(Procedure::cases(), static fn (Procedure $p): bool => $p->allowedInOrder()),
        ));

        self::assertSame(self::xsdEnumeration('TOznaczenieProceduryZ'), $allowed);
        self::assertFalse(Procedure::I_42->allowedInOrder());
        self::assertFalse(Procedure::I_63->allowedInOrder());
    }

    public function test_payment_form_mapping(): void
    {
        $forward = [
            '10' => PaymentForm::Cash, '48' => PaymentForm::Card, '54' => PaymentForm::Card,
            '55' => PaymentForm::Card, '20' => PaymentForm::Cheque, '30' => PaymentForm::Transfer,
            '31' => PaymentForm::Transfer, '42' => PaymentForm::Transfer, '58' => PaymentForm::Transfer,
        ];
        foreach ($forward as $code => $form) {
            self::assertSame($form, PaymentForm::fromMeansCode(PaymentMeansCode::from((string) $code)), (string) $code);
        }
        self::assertNull(PaymentForm::fromMeansCode(PaymentMeansCode::C_1));
        self::assertNull(PaymentForm::fromMeansCode(PaymentMeansCode::C_49));

        $back = [1 => '10', 2 => '48', 3 => '1', 4 => '20', 5 => '1', 6 => '30', 7 => '1'];
        foreach (PaymentForm::cases() as $form) {
            self::assertSame($back[$form->value], $form->meansCode()->value, $form->name);
        }
    }

    /**
     * @return list<string>
     */
    private static function xsdEnumeration(string $type): array
    {
        $dom = new DOMDocument;
        self::assertTrue($dom->load(Fa3Schema::path()));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $values = [];
        foreach ($xpath->query("//xs:simpleType[@name='{$type}']//xs:enumeration/@value") ?: [] as $node) {
            $values[] = (string) $node->nodeValue;
        }

        return $values;
    }
}

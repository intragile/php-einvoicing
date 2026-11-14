<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport;

use IntrAgile\EInvoicing\Transport\DocumentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentNumberTest extends TestCase
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function documents(): iterable
    {
        yield 'UBL invoice' => [
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="'.self::CBC.'" xmlns:cac="'.self::CAC.'">'
            .'<cbc:CustomizationID>x</cbc:CustomizationID><cbc:ID> INV-2026-001 </cbc:ID>'
            .'<cac:OrderReference><cbc:ID>PO-1</cbc:ID></cac:OrderReference></Invoice>',
            'INV-2026-001',
        ];
        yield 'UBL credit note, nested id first' => [
            '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" xmlns:cbc="'.self::CBC.'" xmlns:cac="'.self::CAC.'">'
            .'<cac:OrderReference><cbc:ID>PO-1</cbc:ID></cac:OrderReference><cbc:ID>CN-7</cbc:ID></CreditNote>',
            'CN-7',
        ];
        yield 'CII invoice' => [
            '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"'
            .' xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext><ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>spec</ram:ID></ram:GuidelineSpecifiedDocumentContextParameter></rsm:ExchangedDocumentContext>'
            .'<rsm:ExchangedDocument><ram:ID>CII-42</ram:ID></rsm:ExchangedDocument></rsm:CrossIndustryInvoice>',
            'CII-42',
        ];
        yield 'UBL without an id' => [
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="'.self::CBC.'" xmlns:cac="'.self::CAC.'">'
            .'<cac:OrderReference><cbc:ID>PO-1</cbc:ID></cac:OrderReference></Invoice>',
            null,
        ];
        yield 'UBL with a blank id' => [
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="'.self::CBC.'"><cbc:ID>  </cbc:ID></Invoice>',
            null,
        ];
        yield 'CII without an id' => [
            '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>',
            null,
        ];
        yield 'other root' => ['<Order xmlns:cbc="'.self::CBC.'"><cbc:ID>O-1</cbc:ID></Order>', null];
        yield 'not XML' => ['not xml', null];
        yield 'DOCTYPE' => ['<!DOCTYPE Invoice><Invoice/>', null];
    }

    #[DataProvider('documents')]
    public function test_document_number_is_read_from_the_root(string $xml, ?string $expected): void
    {
        self::assertSame($expected, DocumentNumber::of($xml));
    }
}

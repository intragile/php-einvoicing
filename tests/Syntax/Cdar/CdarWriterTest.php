<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cdar;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\France\Lifecycle\CdvProfile;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\CanonicalXml;
use IntrAgile\EInvoicing\Tests\Support\LifecycleMessages;
use PHPUnit\Framework\TestCase;

final class CdarWriterTest extends TestCase
{
    private const UC1_COLLECTED = 'FNFE_RFE_INVOICE/Z.example/TEST/CDAR/UC1_F202500003_07-CDV-212_Encaissee.xml';

    public function test_element_order_matches_fnfe_212_example(): void
    {
        $example = file_get_contents(Fixtures::franceRfeRoot().'/'.self::UC1_COLLECTED);
        self::assertIsString($example);

        $written = (new CdarWriter)->write(LifecycleMessages::uc1Collected());

        self::assertSame(CanonicalXml::of($example), CanonicalXml::of($written));
    }

    public function test_output_has_no_bom_comment_or_xsi_and_uses_the_fnfe_prefixes(): void
    {
        $xml = (new CdarWriter)->write(LifecycleMessages::uc1Collected());

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringNotContainsString('<!--', $xml);
        self::assertStringNotContainsString('xmlns:xsi', $xml);
        self::assertStringContainsString('<rsm:CrossDomainAcknowledgementAndResponse', $xml);
        self::assertStringContainsString('xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100"', $xml);
        self::assertStringContainsString('xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"', $xml);
        self::assertStringContainsString('xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100"', $xml);
        self::assertStringContainsString('xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"', $xml);
    }

    public function test_timestamps_are_written_in_paris_time(): void
    {
        $utc = new DateTimeZone('UTC');
        $base = LifecycleMessages::uc1Collected();
        $invoice = $base->invoice;
        $message = $base
            ->withIssuedAt(new DateTimeImmutable('2025-08-02 08:05:00', $utc))
            ->withStatusAt(new DateTimeImmutable('2025-12-02 09:00:00', $utc))
            ->withInvoice(new InvoiceReference(
                $invoice->number,
                $invoice->type,
                new DateTimeImmutable('2025-07-01 13:10:00', $utc),
                $invoice->issueDate,
                $invoice->issuer,
            ));

        $xpath = self::xpath((new CdarWriter)->write($message));

        self::assertSame('20250802100500', self::value($xpath, '/rsm:*/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format="204"]'));
        self::assertSame('20251202100000', self::value($xpath, '/rsm:*/rsm:AcknowledgementDocument/ram:IssueDateTime/udt:DateTimeString[@format="204"]'));
        self::assertSame('20250701151000', self::value($xpath, '//ram:ReferenceReferencedDocument/ram:ReceiptDateTime/udt:DateTimeString[@format="204"]'));
    }

    public function test_utc_instant_late_in_the_evening_is_written_on_the_next_paris_day(): void
    {
        $instant = new DateTimeImmutable('2026-11-17T23:30:00Z');
        $message = LifecycleMessages::uc1Collected()
            ->withStatusAt($instant)
            ->withDetails([new StatusDetail(1, referenceDate: $instant)]);

        $xpath = self::xpath((new CdarWriter)->write($message));

        self::assertSame('20261118003000', self::value($xpath, '/rsm:*/rsm:AcknowledgementDocument/ram:IssueDateTime/udt:DateTimeString[@format="204"]'));
        self::assertSame('20261118003000', self::value($xpath, '//ram:SpecifiedDocumentStatus/ram:ReferenceDateTime/udt:DateTimeString[@format="204"]'));
    }

    public function test_scope_omitted_for_ppf_profile_when_null(): void
    {
        $message = LifecycleMessages::uc1Collected()->withProfile(CdvProfile::EinvoicingF2)->withScope(null);

        $xpath = self::xpath((new CdarWriter)->write($message));

        self::assertSame(0, self::nodeCount($xpath, '//ram:BusinessProcessSpecifiedDocumentContextParameter'));
        self::assertSame(CdvProfile::EinvoicingF2->value, self::value($xpath, '/rsm:*/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
    }

    public function test_test_indicator_written_only_when_set(): void
    {
        $message = LifecycleMessages::uc1Collected();

        $plain = self::xpath((new CdarWriter)->write($message));
        $test = self::xpath((new CdarWriter)->write($message->withTest(true)));

        self::assertSame(0, self::nodeCount($plain, '//ram:TestIndicator'));
        self::assertSame('true', self::value($test, '/rsm:*/rsm:ExchangedDocumentContext/*[1][self::ram:TestIndicator]/udt:Indicator'));
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        return $xpath;
    }

    private static function value(DOMXPath $xpath, string $expression): ?string
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, $expression);

        return $nodes->item(0)?->nodeValue;
    }

    private static function nodeCount(DOMXPath $xpath, string $expression): int
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);

        return $nodes->length;
    }
}

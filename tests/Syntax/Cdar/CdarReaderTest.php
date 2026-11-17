<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cdar;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarReader;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarWriter;
use IntrAgile\EInvoicing\Tests\Support\LifecycleMessages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CdarReaderTest extends TestCase
{
    /**
     * @return iterable<string, array{LifecycleMessage}>
     */
    public static function messages(): iterable
    {
        foreach (LifecycleMessages::all() as $name => $message) {
            yield $name => [$message];
        }
    }

    #[DataProvider('messages')]
    public function test_round_trip_of_every_factory_output(LifecycleMessage $message): void
    {
        $result = (new CdarReader)->read((new CdarWriter)->write($message));

        self::assertSame([], $result->ignored);
        self::assertEquals($message, $result->message);
    }

    public function test_timestamps_are_read_in_paris_time(): void
    {
        $message = (new CdarReader)->read((new CdarWriter)->write(LifecycleMessages::uc1Collected()))->message;

        self::assertSame('Europe/Paris', $message->issuedAt->getTimezone()->getName());
        self::assertSame('2025-08-02 10:05:00', $message->issuedAt->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Paris', $message->statusAt->getTimezone()->getName());
        self::assertSame('Europe/Paris', $message->invoice->receivedAt->getTimezone()->getName());
    }

    public function test_instant_across_the_paris_day_boundary_reads_back_unchanged(): void
    {
        $instant = new DateTimeImmutable('2026-11-17T23:30:00Z');
        $written = LifecycleMessages::uc1Collected()
            ->withStatusAt($instant)
            ->withDetails([new StatusDetail(1, referenceDate: $instant)]);

        $message = (new CdarReader)->read((new CdarWriter)->write($written))->message;

        self::assertEquals($instant, $message->statusAt);
        self::assertSame('2026-11-18 00:30:00', $message->statusAt->format('Y-m-d H:i:s'));
        self::assertNotNull($message->details[0]->referenceDate);
        self::assertEquals($instant, $message->details[0]->referenceDate);
    }

    public function test_bom_is_tolerated(): void
    {
        $xml = "\u{FEFF}".(new CdarWriter)->write(LifecycleMessages::uc1Collected());

        self::assertEquals(LifecycleMessages::uc1Collected(), (new CdarReader)->read($xml)->message);
    }

    public function test_two_acknowledgement_documents_are_refused(): void
    {
        $document = self::document(LifecycleMessages::uc1Collected());
        $acknowledgement = self::element($document, 'AcknowledgementDocument');
        $document->documentElement?->appendChild($acknowledgement->cloneNode(true));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('AcknowledgementDocument');

        (new CdarReader)->read((string) $document->saveXML());
    }

    public function test_two_referenced_documents_are_refused(): void
    {
        $document = self::document(LifecycleMessages::uc1Collected());
        $reference = self::element($document, 'ReferenceReferencedDocument');
        $reference->parentNode?->appendChild($reference->cloneNode(true));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('ReferenceReferencedDocument');

        (new CdarReader)->read((string) $document->saveXML());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ppfFlowStatuses(): iterable
    {
        yield '250' => ['250', 'flow F1'];
        yield '251' => ['251', 'flow F1'];
        yield '300' => ['300', 'flow F10'];
        yield '301' => ['301', 'flow F10'];
        yield '400' => ['400', 'flow F13'];
        yield '401' => ['401', 'flow F13'];
        yield '500' => ['500', 'another flow'];
        yield '601' => ['601', 'the CDV flow'];
    }

    #[DataProvider('ppfFlowStatuses')]
    public function test_ppf_flow_status_is_refused_naming_the_flow(string $code, string $flow): void
    {
        $xml = str_replace(
            '<ram:ProcessConditionCode>212</ram:ProcessConditionCode>',
            "<ram:ProcessConditionCode>{$code}</ram:ProcessConditionCode>",
            (new CdarWriter)->write(LifecycleMessages::uc1Collected()),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage("Status {$code} is a public portal status of {$flow}, not an invoice lifecycle status.");

        (new CdarReader)->read($xml);
    }

    public function test_unknown_status_is_refused(): void
    {
        $xml = str_replace(
            '<ram:ProcessConditionCode>212</ram:ProcessConditionCode>',
            '<ram:ProcessConditionCode>299</ram:ProcessConditionCode>',
            (new CdarWriter)->write(LifecycleMessages::uc1Collected()),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage("Invalid code '299'");

        (new CdarReader)->read($xml);
    }

    public function test_unknown_reason_code_is_ignored_with_reason(): void
    {
        $written = (new CdarWriter)->write(LifecycleMessages::refused());
        $xml = str_replace('<ram:ReasonCode>TX_TVA_ERR</ram:ReasonCode>', '<ram:ReasonCode>ZZZ</ram:ReasonCode>', $written);
        self::assertNotSame($written, $xml);

        $result = (new CdarReader)->read($xml);

        self::assertSame([
            "/rsm:CrossDomainAcknowledgementAndResponse/rsm:AcknowledgementDocument/ram:ReferenceReferencedDocument/ram:SpecifiedDocumentStatus/ram:ReasonCode (invalid code 'ZZZ')",
        ], $result->ignored);
        self::assertNull($result->message->details[0]->reason);
        self::assertSame(LifecycleMessages::refused()->details[0]->reasonText, $result->message->details[0]->reasonText);
    }

    public function test_unmapped_element_is_ignored(): void
    {
        $document = self::document(LifecycleMessages::uc1Collected());
        $exchanged = self::element($document, 'ExchangedDocument');
        $exchanged->appendChild($document->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:Purpose', 'x'));

        $result = (new CdarReader)->read((string) $document->saveXML());

        self::assertSame(['/rsm:CrossDomainAcknowledgementAndResponse/rsm:ExchangedDocument/ram:Purpose'], $result->ignored);
    }

    public function test_invoice_root_is_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('expected a CDAR CrossDomainAcknowledgementAndResponse');

        (new CdarReader)->read('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>');
    }

    public function test_missing_message_identifier_is_refused(): void
    {
        $document = self::document(LifecycleMessages::uc1Collected());
        $id = self::element($document, 'ID', self::element($document, 'ExchangedDocument'));
        $id->parentNode?->removeChild($id);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('MDT-4');

        (new CdarReader)->read((string) $document->saveXML());
    }

    public function test_timestamp_in_another_format_is_refused(): void
    {
        $xml = str_replace(
            '<udt:DateTimeString format="204">20250802100500</udt:DateTimeString>',
            '<udt:DateTimeString format="102">20250802</udt:DateTimeString>',
            (new CdarWriter)->write(LifecycleMessages::uc1Collected()),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage("Invalid timestamp '20250802' in format '102'");

        (new CdarReader)->read($xml);
    }

    private static function document(LifecycleMessage $message): DOMDocument
    {
        $document = new DOMDocument;
        $document->loadXML((new CdarWriter)->write($message));

        return $document;
    }

    private static function element(DOMDocument $document, string $localName, ?DOMElement $context = null): DOMElement
    {
        $element = ($context ?? $document)->getElementsByTagNameNS('*', $localName)->item(0);
        self::assertInstanceOf(DOMElement::class, $element);

        return $element;
    }
}

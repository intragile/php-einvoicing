<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Parses reports captured from the KoSIT validator 1.6.3 daemon (XRechnung configuration
 * 2026-08-31) with the HTTP status it answered with. The inputs were Apache-2.0 KoSIT documents:
 *
 * - valid.xml: testsuite instance standard/01.01a-INVOICE_ubl.xml (HTTP 200);
 * - br-de-failure.xml: unit case ubl-inv/ubl-inv-br-de-16-negative-test-bt-95.xml (HTTP 406);
 * - schema-invalid.xml: 01.01a-INVOICE_ubl.xml with cbc:IssueDate moved after cbc:InvoiceTypeCode
 *   (HTTP 406);
 * - no-scenario.xml: 01.01a-INVOICE_ubl.xml with an unknown cbc:CustomizationID (HTTP 406);
 * - custom-level.xml: 01.01a-INVOICE_ubl.xml with the first unitCode set to "ZZZQ", which fires
 *   BR-CL-23, downgraded to warning by the scenario (HTTP 200);
 * - processing-error.xml: 01.01a-INVOICE_uncefact.xml with the header ram:LineTotalAmount
 *   repeated (Mutations::dynamicErrors()), on which the CEN Schematron step aborts with XPTY0004
 *   (HTTP 422).
 */
final class KositReportTest extends TestCase
{
    public function test_valid_report(): void
    {
        $report = KositReport::fromXml(self::fixture('valid'), 200);

        self::assertSame('EN16931 XRechnung (UBL Invoice)', $report->scenario);
        self::assertTrue($report->schemaValid);
        self::assertTrue($report->schematronRan);
        self::assertTrue($report->accepted);
        self::assertSame(['BR-DE-TMP-32 information'], $report->verdict);
    }

    public function test_br_de_failure_report(): void
    {
        $report = KositReport::fromXml(self::fixture('br-de-failure'), 406);

        self::assertSame('EN16931 XRechnung (UBL Invoice)', $report->scenario);
        self::assertTrue($report->schemaValid);
        self::assertTrue($report->schematronRan);
        self::assertFalse($report->accepted);
        self::assertContains('BR-DE-16 fatal', $report->verdict);
        self::assertSame(['BR-DE-16 fatal', 'BR-S-02 fatal', 'BR-S-03 fatal', 'UBL-SR-53 fatal'], $report->verdict);
    }

    public function test_schema_invalid_report(): void
    {
        $report = KositReport::fromXml(self::fixture('schema-invalid'), 406);

        self::assertSame('EN16931 XRechnung (UBL Invoice)', $report->scenario);
        self::assertFalse($report->schemaValid);
        // KoSIT skips every Schematron step once the XML Schema step has failed.
        self::assertFalse($report->schematronRan);
        self::assertSame([], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_no_scenario_report(): void
    {
        $report = KositReport::fromXml(self::fixture('no-scenario'), 406);

        self::assertNull($report->scenario);
        self::assertFalse($report->schemaValid);
        self::assertFalse($report->schematronRan);
        self::assertSame([], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_custom_level_from_the_matched_scenario_applies_to_the_verdict(): void
    {
        $report = KositReport::fromXml(self::fixture('custom-level'), 200);

        // The report keeps the Schematron flag (level="error") on the message; the scenario's
        // customLevel turns it into a warning, and the document is accepted.
        self::assertSame(['BR-CL-23 warning', 'BR-DE-TMP-32 information'], $report->verdict);
        self::assertTrue($report->accepted);
    }

    public function test_processing_error_report(): void
    {
        $report = KositReport::fromXml(self::fixture('processing-error'), 422);

        self::assertSame('EN16931 XRechnung (CII)', $report->scenario);
        self::assertTrue($report->schemaValid);
        self::assertTrue($report->schematronRan);
        self::assertTrue($report->processingError);
        // The aborted CEN step has no message; only the XRechnung step reports.
        self::assertSame(['BR-DE-TMP-32 information'], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_only_http_422_is_a_processing_error(): void
    {
        self::assertFalse(KositReport::fromXml(self::fixture('valid'), 200)->processingError);
        self::assertFalse(KositReport::fromXml(self::fixture('br-de-failure'), 406)->processingError);
        self::assertFalse(KositReport::fromXml(self::fixture('no-scenario'), 406)->processingError);
    }

    public function test_acceptance_must_agree_with_the_http_status(): void
    {
        $this->expectException(RuntimeException::class);

        KositReport::fromXml(self::fixture('valid'), 406);
    }

    public function test_custom_level_is_a_token_list(): void
    {
        $xml = self::edited('custom-level', '<s:customLevel level="warning">BR-CL-23</s:customLevel>', "<s:customLevel level=\"warning\">BR-CL-99\n BR-CL-23</s:customLevel>");

        self::assertSame(['BR-CL-23 warning', 'BR-DE-TMP-32 information'], KositReport::fromXml($xml, 200)->verdict);
    }

    public function test_code_with_two_custom_levels_throws(): void
    {
        $xml = self::edited('custom-level', '<s:customLevel level="warning">BR-CL-23</s:customLevel>', '<s:customLevel level="warning">BR-CL-23</s:customLevel><s:customLevel level="information">BR-CL-99 BR-CL-23</s:customLevel>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BR-CL-23');

        KositReport::fromXml($xml, 200);
    }

    public function test_accepted_report_with_a_fatal_verdict_throws(): void
    {
        $xml = self::edited('valid', 'level="information"', 'level="error"');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BR-DE-TMP-32 fatal');

        KositReport::fromXml($xml, 200);
    }

    public function test_rejected_schema_valid_report_without_a_fatal_verdict_throws(): void
    {
        $xml = self::edited('valid', '<rep:accept>', '<rep:reject>');
        $xml = str_replace('</rep:accept>', '</rep:reject>', $xml);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reject');

        KositReport::fromXml($xml, 406);
    }

    private static function edited(string $name, string $search, string $replace): string
    {
        $xml = self::fixture($name);
        self::assertSame(1, substr_count($xml, $search));

        return str_replace($search, $replace, $xml);
    }

    private static function fixture(string $name): string
    {
        $xml = file_get_contents(__DIR__.'/../../fixtures/kosit-reports/'.$name.'.xml');
        self::assertIsString($xml);

        return $xml;
    }
}

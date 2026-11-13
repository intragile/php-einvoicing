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
 *   BR-CL-23, downgraded to warning by the scenario (HTTP 200).
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

    public function test_acceptance_must_agree_with_the_http_status(): void
    {
        $this->expectException(RuntimeException::class);

        KositReport::fromXml(self::fixture('valid'), 406);
    }

    private static function fixture(string $name): string
    {
        $xml = file_get_contents(__DIR__.'/../../fixtures/kosit-reports/'.$name.'.xml');
        self::assertIsString($xml);

        return $xml;
    }
}

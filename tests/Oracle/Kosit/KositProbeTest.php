<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pins the observed behaviour of the KoSIT validator 1.6.3 daemon with the XRechnung
 * configuration 2026-08-31, so that an upgrade that changes it fails here first. Run it with
 * docker/kosit/run.sh; without KOSIT_ORACLE_URL the tests are skipped.
 *
 * Observed facts (2026-11-13):
 *
 * (a) Schematron does not run after an XML Schema failure. When the val-xsd step is invalid, the
 *     report still names the matched scenario, but contains no val-sch.* step at all (only val-xsd
 *     with its error messages and val-xml), so there are no Schematron messages, and the document
 *     is rejected.
 *
 * (b) HTTP status codes of a POST to the daemon root: 200 when the document is accepted (also
 *     when the Schematron reports warnings or information messages, or an error that a custom
 *     level downgrades); 406 when it is rejected, which covers a Schematron error, an XML Schema
 *     failure and a document that matches no scenario; 422 when the body is not well-formed XML,
 *     still with a report (rep:noScenarioMatched with a val-xml step valid="false" and a message
 *     code="generic-error"); 400 for an empty body; 405 for a GET. Any Content-Type value is
 *     accepted; a request without a Content-Type header gets 500 (seen with curl, not pinned here
 *     because PHP's http wrapper always sends one).
 *
 * (c) Information-level messages are reported: a rep:message with level="information" appears
 *     in the Schematron step (BR-DE-TMP-32 on every testsuite instance). Message levels are the
 *     flags of the Schematron asserts: custom levels are not written into rep:message/@level but
 *     only into the report's copy of the scenario (s:createReport/s:customLevel), and the
 *     acceptance follows the custom level (BR-CL-23 stays level="error" in the message, the
 *     scenario makes it a warning, and the document is accepted with HTTP 200).
 *
 * (d) When no scenario matches, the report has rep:noScenarioMatched with only the val-xml step
 *     and no s:scenario element, so there is no scenario name (the configuration's
 *     noScenarioReport resource "Default Report" is not named either); the scenario is null,
 *     there is no XML Schema or Schematron step, and the document is rejected with HTTP 406.
 *
 * (e) An XPath dynamic error in a Schematron step (a repeated element passed to a cast, XPTY0004)
 *     aborts the XSLT transformation of that step: the step is still reported with valid="true"
 *     but has no message at all, not even those of asserts that were evaluated before or after
 *     the failing one; the later Schematron steps run normally. The report names the scenario,
 *     the XML Schema step is valid, and the document is rejected with HTTP 422 (processing
 *     error), the status otherwise used for a body that is not well-formed. Saxon's error is only
 *     written to the daemon's log, not into the report.
 */
#[Group('kosit-oracle')]
final class KositProbeTest extends TestCase
{
    private const SCENARIO = 'EN16931 XRechnung (UBL Invoice)';

    private const VALID = __DIR__.'/../../fixtures/official/xrechnung/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml';

    private const BR_DE_FAILURE = __DIR__.'/../../fixtures/official/xrechnung/unit-2.6.0/ubl-inv/ubl-inv-br-de-16-negative-test-bt-95.xml';

    private KositClient $client;

    protected function setUp(): void
    {
        $client = KositClient::fromEnvironment();
        if ($client === null) {
            self::markTestSkipped('KOSIT_ORACLE_URL is not set; run docker/kosit/run.sh.');
        }
        $this->client = $client;
    }

    public function test_schematron_does_not_run_after_a_schema_failure(): void
    {
        $xml = (string) preg_replace(
            '#(\s*<cbc:IssueDate>[^<]*</cbc:IssueDate>)(\s*<cbc:InvoiceTypeCode>[^<]*</cbc:InvoiceTypeCode>)#',
            '$2$1',
            self::read(self::VALID),
            1,
        );

        [$status, $body] = $this->client->post($xml);
        $report = KositReport::fromXml($body, $status);

        self::assertSame(406, $status);
        self::assertSame(self::SCENARIO, $report->scenario);
        self::assertFalse($report->schemaValid);
        self::assertFalse($report->schematronRan);
        self::assertSame([], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_accepted_document_answers_200_with_information_messages(): void
    {
        [$status, $body] = $this->client->post(self::read(self::VALID));
        $report = KositReport::fromXml($body, $status);

        self::assertSame(200, $status);
        self::assertSame(self::SCENARIO, $report->scenario);
        self::assertTrue($report->schemaValid);
        self::assertTrue($report->schematronRan);
        self::assertSame(['BR-DE-TMP-32 information'], $report->verdict);
        self::assertStringContainsString('level="information"', $body);
    }

    public function test_schematron_error_answers_406(): void
    {
        [$status, $body] = $this->client->post(self::read(self::BR_DE_FAILURE));
        $report = KositReport::fromXml($body, $status);

        self::assertSame(406, $status);
        self::assertSame(['BR-DE-16 fatal', 'BR-S-02 fatal', 'BR-S-03 fatal', 'UBL-SR-53 fatal'], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_custom_level_is_applied_to_the_acceptance_not_to_the_message(): void
    {
        $xml = (string) preg_replace('#unitCode="[^"]*"#', 'unitCode="ZZZQ"', self::read(self::VALID), 1);

        [$status, $body] = $this->client->post($xml);
        $report = KositReport::fromXml($body, $status);

        self::assertSame(200, $status);
        self::assertMatchesRegularExpression('#<rep:message [^>]*level="error"[^>]*code="BR-CL-23"#', $body);
        self::assertStringContainsString('<s:customLevel level="warning">BR-CL-23</s:customLevel>', $body);
        self::assertSame(['BR-CL-23 warning', 'BR-DE-TMP-32 information'], $report->verdict);
    }

    public function test_no_matching_scenario_has_no_scenario_name_and_answers_406(): void
    {
        $xml = str_replace(
            'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0</cbc:CustomizationID>',
            'urn:example:unknown-customization</cbc:CustomizationID>',
            self::read(self::VALID),
        );

        [$status, $body] = $this->client->post($xml);
        $report = KositReport::fromXml($body, $status);

        self::assertSame(406, $status);
        self::assertStringContainsString('<rep:noScenarioMatched>', $body);
        self::assertStringNotContainsString('<s:scenario', $body);
        self::assertNull($report->scenario);
        self::assertFalse($report->schemaValid);
        self::assertFalse($report->schematronRan);
        self::assertFalse($report->accepted);
    }

    public function test_dynamic_error_aborts_its_schematron_step_with_http_422(): void
    {
        // The CEN step (val-sch.1) fails on the repeated ram:LineTotalAmount (BR-CO-10); it would
        // otherwise report CII-SR-477. The XRechnung step (val-sch.2) still reports BR-DE-TMP-32.
        [$status, $body] = $this->client->post(Mutations::dynamicErrors()['cii/line-total-amount-repeated']);
        $report = KositReport::fromXml($body, $status);

        self::assertSame(422, $status);
        self::assertSame('EN16931 XRechnung (CII)', $report->scenario);
        self::assertTrue($report->schemaValid);
        self::assertTrue($report->processingError);
        self::assertMatchesRegularExpression('#<rep:validationStepResult id="val-sch\.1" valid="true"><s:resource>.*?</s:resource></rep:validationStepResult>#', $body);
        self::assertSame(['BR-DE-TMP-32 information'], $report->verdict);
        self::assertFalse($report->accepted);

        // In the UBL CreditNote the XRechnung step (val-sch.2) fails on the repeated
        // cac:InvoicePeriod (PEPPOL-EN16931-R110); the CEN step before it reports UBL-SR-08.
        [$status, $body] = $this->client->post(Mutations::dynamicErrors()['ubl-creditnote/invoice-period-repeated']);
        $report = KositReport::fromXml($body, $status);

        self::assertSame(422, $status);
        self::assertMatchesRegularExpression('#<rep:validationStepResult id="val-sch\.2" valid="true"><s:resource>.*?</s:resource></rep:validationStepResult>#', $body);
        self::assertSame(['UBL-SR-08 fatal'], $report->verdict);
        self::assertFalse($report->accepted);
    }

    public function test_status_codes_of_malformed_requests(): void
    {
        [$status, $body] = $this->client->post('not xml at all');
        self::assertSame(422, $status);
        self::assertStringContainsString('code="generic-error"', $body);
        $report = KositReport::fromXml($body, $status);
        self::assertNull($report->scenario);
        self::assertFalse($report->accepted);

        self::assertSame(400, $this->client->post('')[0]);
        self::assertSame(200, $this->client->post(self::read(self::VALID), 'application/octet-stream')[0]);
    }

    public function test_check_returns_the_report(): void
    {
        self::assertTrue($this->client->check(self::read(self::VALID))->accepted);
        self::assertFalse($this->client->check(self::read(self::BR_DE_FAILURE))->accepted);
    }

    private static function read(string $path): string
    {
        $xml = file_get_contents($path);
        self::assertIsString($xml);

        return $xml;
    }
}

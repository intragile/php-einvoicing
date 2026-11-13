<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeWriter;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Validator;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PeppolEnvelopeValidatorTest extends TestCase
{
    /** The buyer's electronic address in the Peppol base example. */
    private const BUYER_ENDPOINT = '<cbc:EndpointID schemeID="0002">FR23342</cbc:EndpointID>';

    public function test_envelope_written_for_the_base_example_is_valid(): void
    {
        $report = self::validator()->validate(self::envelope(self::baseExample()));

        self::assertSame(['peppol-envelope 3.0.22'], $report->ruleSets);
        self::assertSame(Syntax::Ubl, $report->syntax);
        self::assertSame([], $report->dynamicErrors);
        self::assertSame([], $report->findings);
        self::assertTrue($report->isValid());
    }

    /**
     * The verdict of each document of the official envelope tests (rules/unit-SBDH), as stated by
     * the success and error expectations of its VEFA test. PeppolEnvelopeConformanceTest (oracle
     * group) proves the same verdicts against Saxon.
     */
    public function test_official_envelope_tests(): void
    {
        $expected = [
            '#0' => [], '#1' => ['BR-SBDH-1 fatal'], '#2' => ['BR-SBDH-2 fatal'],
            '#3' => [], '#4' => ['BR-SBDH-3 fatal'], '#5' => ['BR-SBDH-4 fatal'], '#6' => [],
            '#7' => [], '#8' => ['BR-SBDH-1 fatal'], '#9' => ['BR-SBDH-2 fatal'],
            '#10' => [], '#11' => ['BR-SBDH-3 fatal'], '#12' => ['BR-SBDH-4 fatal'], '#13' => [],
        ];

        $actual = [];
        foreach (Fixtures::peppolEnvelopeTests() as $document) {
            self::assertStringStartsWith('peppol/unit-SBDH/BR-SBDH.xml#', $document->name());
            $report = self::validator()->validate($document->xml);
            self::assertSame([], $report->dynamicErrors, $document->name());
            $actual[strstr($document->name(), '#') ?: ''] = $report->verdict();
        }

        self::assertSame($expected, $actual);
    }

    public function test_other_receiver_is_fatal(): void
    {
        $sbd = self::withReceiver(self::envelope(self::baseExample()), '0002:FR23342', '0002:FR99999');

        $report = self::validator()->validate($sbd);

        self::assertFalse($report->isValid());
        self::assertSame(['BR-SBDH-1 fatal'], $report->verdict());
    }

    public function test_receiver_comparison_is_case_sensitive(): void
    {
        // The official rule compares the strings with XPath "=", so a receiver identifier that
        // differs from the invoice only in letter case is refused, even though Peppol participant
        // identifiers are case-insensitive.
        $xml = str_replace(self::BUYER_ENDPOINT, '<cbc:EndpointID schemeID="0002">fr23342</cbc:EndpointID>', self::baseExample());
        $sbd = self::envelope($xml);
        self::assertTrue(self::validator()->validate($sbd)->isValid());

        $report = self::validator()->validate(self::withReceiver($sbd, '0002:fr23342', '0002:FR23342'));

        self::assertSame(['BR-SBDH-1'], array_map(static fn (Finding $finding): string => $finding->ruleId, $report->fatal()));
    }

    public function test_bare_invoice_is_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('StandardBusinessDocument');

        self::validator()->validate(self::baseExample());
    }

    public function test_envelope_without_an_invoice_is_refused(): void
    {
        $header = '<StandardBusinessDocumentHeader><HeaderVersion>1.0</HeaderVersion></StandardBusinessDocumentHeader>';
        $documents = [
            'no payload' => '<StandardBusinessDocument xmlns="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader">'.$header.'</StandardBusinessDocument>',
            'an order' => '<StandardBusinessDocument xmlns="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader">'.$header
                .'<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/></StandardBusinessDocument>',
        ];

        foreach ($documents as $label => $xml) {
            try {
                self::validator()->validate($xml);
                self::fail("An envelope with {$label} must be refused.");
            } catch (UnsupportedDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The envelope validator expects a Standard Business Document; writing an invoice model and
     * running the envelope rules on the bare invoice would only end in a misleading refusal.
     */
    public function test_envelope_validator_refuses_validate_invoice(): void
    {
        $invoice = (new UblReader)->read(self::baseExample())->invoice;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('validateInvoice() needs an invoice validator; this one validates SBDH envelopes');

        self::validator()->validateInvoice($invoice);
    }

    public function test_envelope_validator_refuses_a_folder_without_the_envelope_rules(): void
    {
        $dir = BuildDir::create('no-envelope-rules');
        try {
            $this->expectException(RulesNotInstalled::class);
            $this->expectExceptionMessage("vendor/bin/einvoicing-rules install peppol --dir=\"{$dir}\"");

            Validator::peppolEnvelope($dir);
        } finally {
            BuildDir::remove($dir);
        }
    }

    private static function validator(): Validator
    {
        return Validator::peppolEnvelope(InstalledRules::dir());
    }

    private static function baseExample(): string
    {
        $xml = Fixtures::peppolExamples()['examples/base-example.xml'] ?? self::fail('The Peppol base example is missing.');
        self::assertStringContainsString(self::BUYER_ENDPOINT, $xml);

        return $xml;
    }

    private static function envelope(string $xml): string
    {
        return (new EnvelopeWriter)->wrap(
            OutboundDocument::fromXml($xml, 'BE'),
            'f81d4fae-7dec-41d0-a765-00a0c91e6bf6',
            new DateTimeImmutable('2026-11-12T09:30:00Z'),
        );
    }

    private static function withReceiver(string $sbd, string $from, string $to): string
    {
        $element = '<Receiver><Identifier Authority="iso6523-actorid-upis">%s</Identifier></Receiver>';
        self::assertStringContainsString(sprintf($element, $from), $sbd);

        return str_replace(sprintf($element, $from), sprintf($element, $to), $sbd);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\RecordingValidator;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeppolPayloadValidatorTest extends TestCase
{
    private const BIS = PeppolBis3::CUSTOMIZATION;

    public function test_routes_bis_to_bis_validator(): void
    {
        $bis = self::recorder('bis');
        $xrechnung = self::recorder('xrechnung');
        $xml = Fixtures::peppolExamples()['examples/base-example.xml'];

        $report = (new PeppolPayloadValidator($bis, $xrechnung))->validate($xml);

        self::assertSame(['bis 1.0'], $report->ruleSets);
        self::assertSame([$xml], $bis->seen);
        self::assertSame([], $xrechnung->seen);
    }

    public function test_routes_bis_credit_note_to_bis_validator(): void
    {
        $bis = self::recorder('bis');

        (new PeppolPayloadValidator($bis, self::recorder('xrechnung')))->validate(Fixtures::peppolExamples()['examples/base-creditnote-correction.xml']);

        self::assertCount(1, $bis->seen);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function xrechnungDocuments(): iterable
    {
        yield 'ubl invoice' => [(new UblWriter)->write(self::germanInvoice(XRechnung::CIUS))];
        yield 'ubl credit note' => [(new UblWriter)->write(self::creditNote(self::germanInvoice(XRechnung::CIUS)))];
        yield 'cii invoice' => [(new CiiWriter)->write(self::germanInvoice(XRechnung::CIUS))];
        yield 'ubl extension invoice' => [(new UblWriter)->write(self::germanInvoice(XRechnung::EXTENSION))];
        yield 'cii extension invoice' => [(new CiiWriter)->write(self::germanInvoice(XRechnung::EXTENSION))];
    }

    #[DataProvider('xrechnungDocuments')]
    public function test_routes_xrechnung_to_xrechnung_validator(string $xml): void
    {
        $bis = self::recorder('bis');
        $xrechnung = self::recorder('xrechnung');

        $report = (new PeppolPayloadValidator($bis, $xrechnung))->validate($xml);

        self::assertSame(['xrechnung 1.0'], $report->ruleSets);
        self::assertSame([$xml], $xrechnung->seen);
        self::assertSame([], $bis->seen);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownSpecifications(): iterable
    {
        $bis = Fixtures::peppolExamples()['examples/base-example.xml'];

        yield 'plain en 16931' => [(new UblWriter)->write(self::germanInvoice('urn:cen.eu:en16931:2017'))];
        yield 'bis with a trailing space' => [str_replace(self::BIS.'</cbc:CustomizationID>', self::BIS.' </cbc:CustomizationID>', $bis)];
        yield 'bis in cii' => [(new CiiWriter)->write(self::germanInvoice(self::BIS))];
        yield 'xrechnung 2.3' => [(new UblWriter)->write(self::germanInvoice('urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.3'))];
        yield 'xrechnung cvd' => [(new UblWriter)->write(self::germanInvoice(XRechnung::CVD))];
        yield 'xrechnung ubl credit note extension' => [(new UblWriter)->write(self::creditNote(self::germanInvoice(XRechnung::EXTENSION)))];
        yield 'xrechnung cius with a trailing space' => [str_replace(XRechnung::CIUS.'</cbc:CustomizationID>', XRechnung::CIUS.' </cbc:CustomizationID>', (new UblWriter)->write(self::germanInvoice(XRechnung::CIUS)))];
        yield 'no specification identifier' => [str_replace('<cbc:CustomizationID>'.self::BIS.'</cbc:CustomizationID>', '', $bis)];
        yield 'other ubl document' => ['<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>'];
    }

    #[DataProvider('unknownSpecifications')]
    public function test_router_refuses_unknown_specification(string $xml): void
    {
        $bis = self::recorder('bis');
        $xrechnung = self::recorder('xrechnung');

        try {
            (new PeppolPayloadValidator($bis, $xrechnung))->validate($xml);
            self::fail('UnsupportedDocument was not thrown.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString(self::BIS, $exception->getMessage());
            self::assertStringContainsString(XRechnung::CIUS.' ', $exception->getMessage());
            self::assertStringContainsString(XRechnung::EXTENSION, $exception->getMessage());
        }
        self::assertSame([], $bis->seen);
        self::assertSame([], $xrechnung->seen);
    }

    public function test_missing_xrechnung_rules_names_the_composer_command(): void
    {
        $bis = self::recorder('bis');

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('composer require intragile/einvoicing-rules-xrechnung');

        (new PeppolPayloadValidator($bis))->validate((new CiiWriter)->write(self::germanInvoice(XRechnung::CIUS)));
    }

    public function test_bis_needs_no_xrechnung_rules(): void
    {
        $bis = self::recorder('bis');

        (new PeppolPayloadValidator($bis))->validate(Fixtures::peppolExamples()['examples/base-example.xml']);

        self::assertCount(1, $bis->seen);
    }

    public function test_real_router_accepts_writer_output(): void
    {
        $validator = PeppolPayloadValidator::create(InstalledRules::dir());
        $documents = [
            'bis ubl' => (new UblWriter)->write(self::germanInvoice(self::BIS)),
            'xrechnung ubl' => (new UblWriter)->write(self::germanInvoice(XRechnung::CIUS)),
            'xrechnung cii' => (new CiiWriter)->write(self::germanInvoice(XRechnung::CIUS)),
        ];

        foreach ($documents as $name => $xml) {
            $report = $validator->validate($xml);
            self::assertSame([], $report->schemaErrors, $name);
            self::assertSame([], $report->dynamicErrors, $name);
            self::assertTrue($report->isValid(), $name.":\n".implode("\n", $report->verdict()));
        }
        self::assertNull($validator->validate($documents['bis ubl'])->scenario);
        self::assertSame('EN16931 XRechnung (CII)', $validator->validate($documents['xrechnung cii'])->scenario);
    }

    public function test_real_router_reports_an_invalid_xrechnung_invoice(): void
    {
        $invoice = self::germanInvoice(XRechnung::CIUS)->withBuyerReference(null);

        $report = PeppolPayloadValidator::create(InstalledRules::dir())->validate((new CiiWriter)->write($invoice));

        self::assertFalse($report->isValid());
        self::assertContains('BR-DE-15', array_map(static fn ($f): string => $f->ruleId, $report->fatal()));
    }

    private static function recorder(string $name): RecordingValidator
    {
        return new RecordingValidator($name);
    }

    private static function germanInvoice(string $specification): Invoice
    {
        return ReleaseInvoices::bis3(ReleaseInvoices::INVOICE_NUMBER)->withProcessControl(new ProcessControl($specification, PeppolBis3::PROCESS));
    }

    private static function creditNote(Invoice $invoice): Invoice
    {
        return $invoice
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoice(new PrecedingInvoiceReference('RE-2026-0814'));
    }
}

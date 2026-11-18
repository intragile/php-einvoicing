<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Poland;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Fa3ValidatorTest extends TestCase
{
    use CapturesThrowable;

    /**
     * @return iterable<string, array{string}>
     */
    public static function officialNames(): iterable
    {
        foreach (PolishSamples::officialNames() as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('officialNames')]
    public function test_every_official_template_is_valid_after_substitution(string $name): void
    {
        $report = Fa3Validator::create()->validate(PolishSamples::official($name));

        self::assertSame([], $report->schemaErrors);
        self::assertTrue($report->isValid());
    }

    /**
     * Published as is, the template fails only on the NIP pattern, which shows that the schema
     * really runs.
     */
    public function test_raw_template_reports_the_nip_placeholder(): void
    {
        $report = Fa3Validator::create()->validate(PolishSamples::raw('invoice-template-fa-3.xml'));

        self::assertFalse($report->isValid());
        self::assertCount(1, $report->schemaErrors);
        self::assertStringContainsString('#nip#', $report->schemaErrors[0]->message);
    }

    public function test_non_fa3_root_is_unsupported(): void
    {
        $ubl = (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/official/xrechnung/testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml');

        $exception = self::capture(static fn () => Fa3Validator::create()->validate($ubl));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('{urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice', $exception->getMessage());
    }

    public function test_report_names_the_schema_run(): void
    {
        $report = Fa3Validator::create()->validate(PolishSamples::official('invoice-template-fa-3.xml'));

        self::assertSame(MessageType::Fa3, $report->type);
        self::assertSame('fa3', MessageType::Fa3->value);
        self::assertSame(['fa3-xsd 1-0E'], $report->ruleSets);
        self::assertSame([], $report->findings);
        self::assertSame([], $report->dynamicErrors);
    }
}

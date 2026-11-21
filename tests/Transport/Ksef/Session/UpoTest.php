<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\Ksef\Session\Upo;
use IntrAgile\EInvoicing\Validation\MessageType;
use PHPUnit\Framework\TestCase;

final class UpoTest extends TestCase
{
    use CapturesThrowable;

    private const SCHEMA_DIR = __DIR__.'/../../../../resources/poland/upo-4-3';

    private const FIXTURE = __DIR__.'/../../../fixtures/ksef/upo-te.xml';

    /**
     * The bundle is byte-identical to the Ministry of Finance files pinned in SOURCE.md.
     */
    public function test_bundled_schema_matches_pinned_hashes(): void
    {
        $files = [
            'upo-v4-3.xsd' => '1e5ff386a29324021a9e0126319680aec0b1e0d4f4a18add30b2f5d12ce6fa86',
            'LICENSE.txt' => 'f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b',
        ];

        foreach ($files as $file => $sha256) {
            self::assertSame($sha256, hash_file('sha256', self::SCHEMA_DIR.'/'.$file), $file);
            self::assertStringContainsString($sha256, (string) file_get_contents(self::SCHEMA_DIR.'/SOURCE.md'), $file);
        }
        self::assertStringContainsString('targetNamespace="'.Upo::NAMESPACE.'"', (string) file_get_contents(self::SCHEMA_DIR.'/upo-v4-3.xsd'));
    }

    public function test_spike_upo_parses(): void
    {
        $xml = self::fixture();

        $upo = Upo::parse($xml);

        self::assertSame($xml, $upo->xml);
        self::assertSame('20261120-SO-4F70173000-DBF8CA6741-F1', $upo->sessionReference);
        self::assertSame('4425138491', $upo->contextNip);
        self::assertSame(['4425138491-20261121-00F89C000000-00'], $upo->ksefNumbers);
        self::assertSame('h80JHEi52rTSqzPZpbHdVEHWl3VWcFcjU+PEfd1Iy3k=', $upo->documentHash);
        self::assertSame('2026-11-21T00:08:17.014000+01:00', $upo->acceptedAt?->format('Y-m-d\TH:i:s.uP'));
    }

    public function test_te_upo_validates_on_test_only(): void
    {
        $upo = Upo::parse(self::fixture());

        $test = $upo->validate(KsefEnvironment::Test);
        self::assertSame(MessageType::Upo, $test->type);
        self::assertSame('upo', MessageType::Upo->value);
        self::assertSame(['upo-xsd 4-3'], $test->ruleSets);
        self::assertSame([], $test->schemaErrors);
        self::assertTrue($test->isValid());

        $production = $upo->validate(KsefEnvironment::Production);
        self::assertFalse($production->isValid());
        self::assertCount(1, $production->schemaErrors);
        self::assertStringContainsString('NazwaPodmiotuPrzyjmujacego', $production->schemaErrors[0]->message);
    }

    public function test_production_receiver_name_is_valid_everywhere(): void
    {
        $upo = Upo::parse(str_replace('Ministerstwo Finansów - środowisko testowe (TE)', 'Ministerstwo Finansów', self::fixture()));

        self::assertTrue($upo->validate(KsefEnvironment::Production)->isValid());
        self::assertTrue($upo->validate(KsefEnvironment::Test)->isValid());
    }

    public function test_validation_reports_schema_errors_besides_the_signature(): void
    {
        $upo = Upo::parse(str_replace('<TrybWysylki>Online</TrybWysylki>', '<TrybWysylki>Pigeon</TrybWysylki>', self::fixture()));

        $report = $upo->validate(KsefEnvironment::Test);

        self::assertCount(1, $report->schemaErrors);
        self::assertStringContainsString('TrybWysylki', $report->schemaErrors[0]->message);
    }

    public function test_session_upo_with_several_documents(): void
    {
        $xml = self::fixture();
        $start = strpos($xml, '<Dokument>');
        $end = strpos($xml, '</Dokument>');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $document = substr($xml, $start, $end + strlen('</Dokument>') - $start);
        $second = str_replace('4425138491-20261121-00F89C000000-00', '4425138491-20261121-00F89C000001-00', $document);
        $xml = substr_replace($xml, $document.$second, $start, strlen($document));

        $upo = Upo::parse($xml);

        self::assertSame(['4425138491-20261121-00F89C000000-00', '4425138491-20261121-00F89C000001-00'], $upo->ksefNumbers);
        self::assertNull($upo->documentHash);
        self::assertNull($upo->acceptedAt);
    }

    public function test_upo_with_doctype_is_refused(): void
    {
        $xml = str_replace('<?xml version="1.0" encoding="utf-8"?>', '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE Potwierdzenie [<!ENTITY x "y">]>', self::fixture());

        self::assertInstanceOf(UnsafeXml::class, self::capture(static fn () => Upo::parse($xml)));
    }

    public function test_non_upo_root_is_unsupported(): void
    {
        $exception = self::capture(static fn () => Upo::parse('<?xml version="1.0"?><Faktura xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/"/>'));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('Potwierdzenie', $exception->getMessage());
    }

    public function test_upo_without_session_reference_is_unsupported(): void
    {
        $xml = (string) preg_replace('#<NumerReferencyjnySesji>[^<]*</NumerReferencyjnySesji>#', '', self::fixture());

        $exception = self::capture(static fn () => Upo::parse($xml));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('NumerReferencyjnySesji', $exception->getMessage());
    }

    private static function fixture(): string
    {
        $xml = file_get_contents(self::FIXTURE);
        self::assertIsString($xml);

        return $xml;
    }
}

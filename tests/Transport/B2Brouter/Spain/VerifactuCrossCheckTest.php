<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\SpanishInvoices;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CheckStatus;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\ChillerlanQrDecoder;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckMode;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckResult;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckStep;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\Difference;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\QrDecoder;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\TaxReport;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\VerifactuCrossCheck;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('verifactu-official')]
final class VerifactuCrossCheckTest extends TestCase
{
    private const API_DIR = __DIR__.'/../../../fixtures/spain/b2brouter/api';

    private const HUELLA_1064490 = '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73';

    private const HUELLA_1064493 = 'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812';

    public function test_spike_f1_passes_every_step(): void
    {
        $result = self::check()->verify(self::report('1064493'), self::report('1064490'), SpanishInvoices::domestic());

        foreach ([CrossCheckStep::Schema, CrossCheckStep::Fingerprint, CrossCheckStep::Chain, CrossCheckStep::Semantics, CrossCheckStep::Qr] as $step) {
            self::assertSame(CheckStatus::Passed, $result->steps[$step->value], $step->name."\n".$result->summary());
        }
        self::assertSame([], $result->differences);
        self::assertSame('1064493', $result->taxReportId);
        self::assertTrue($result->passed());
        self::assertTrue(self::hasNote($result, 'fallback'));
    }

    public function test_first_record_needs_no_previous(): void
    {
        $result = self::check()->verify(self::report('1064490'), null, SpanishInvoices::domestic()->withNumber('PROBE-ES-0001'));

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Chain->value], $result->summary());
        self::assertTrue($result->passed(), $result->summary());
    }

    public function test_missing_previous_is_skipped_for_a_chained_record(): void
    {
        $result = self::check()->verify(self::report('1064493'), null, SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Skipped, $result->steps[CrossCheckStep::Chain->value]);
        self::assertTrue(self::hasNote($result, 'previous'));
        self::assertTrue($result->passed(), $result->summary());
    }

    public function test_first_record_with_a_previous_report_fails_step_3(): void
    {
        $result = self::check()->verify(self::report('1064490'), self::report('1064493'), SpanishInvoices::domestic()->withNumber('PROBE-ES-0001'));

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Chain->value]);
        self::assertSame(['PrimerRegistro'], self::fields($result->failures()));
    }

    public function test_tampered_huella_fails_step_2(): void
    {
        $tampered = str_repeat('A', 64);
        $xml = str_replace('<sum1:Huella>'.self::HUELLA_1064493.'</sum1:Huella>', '<sum1:Huella>'.$tampered.'</sum1:Huella>', self::xml('1064493'));
        $report = self::reportFrom('1064493', $xml, $tampered);

        $result = self::check()->verify($report, self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Schema->value]);
        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Fingerprint->value]);
        $failure = $result->failures()[0];
        self::assertSame('Huella', $failure->field);
        self::assertSame(self::HUELLA_1064493, $failure->ours);
        self::assertSame($tampered, $failure->theirs);
        self::assertFalse($result->passed());
    }

    public function test_report_fingerprint_mismatch_fails_step_2(): void
    {
        $report = self::reportFrom('1064493', self::xml('1064493'), str_repeat('B', 64));

        $result = self::check()->verify($report, self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Fingerprint->value]);
        self::assertSame(['fingerprint'], self::fields($result->failures()));
        self::assertSame(str_repeat('B', 64), $result->failures()[0]->theirs);
    }

    public function test_wrong_previous_fails_step_3(): void
    {
        $result = self::check()->verify(self::report('1064500'), self::report('1064490'), self::exemptProbe(ExemptOperation::E1));

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Chain->value]);
        self::assertSame(['RegistroAnterior.IDFactura', 'RegistroAnterior.Huella'], self::fields($result->failures()));
    }

    public function test_previous_fingerprint_mismatch_fails_step_3(): void
    {
        $previous = self::reportFrom('1064490', self::xml('1064490'), str_repeat('C', 64));

        $result = self::check()->verify(self::report('1064493'), $previous, SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Chain->value]);
        $failures = $result->failures();
        self::assertSame(['RegistroAnterior.Huella'], self::fields($failures));
        self::assertSame(str_repeat('C', 64), $failures[0]->ours);
        self::assertSame(self::HUELLA_1064490, $failures[0]->theirs);
    }

    public function test_exempt_code_is_known_divergence(): void
    {
        $result = self::check()->verify(self::report('1064500'), self::report('1064493'), self::exemptProbe(ExemptOperation::E6));

        $divergences = $result->knownDivergences();
        self::assertCount(1, $divergences, $result->summary());
        self::assertStringEndsWith('OperacionExenta', $divergences[0]->field);
        self::assertSame('E6', $divergences[0]->ours);
        self::assertSame('E1', $divergences[0]->theirs);
        self::assertSame(CrossCheckStep::Semantics, $divergences[0]->step);
        self::assertSame(CheckStatus::KnownDivergence, $result->steps[CrossCheckStep::Semantics->value]);
        self::assertSame([], $result->failures());
        self::assertTrue($result->passed());
        self::assertFalse($result->failsIn(CrossCheckMode::Strict));
    }

    public function test_positive_credit_note_is_known_divergence(): void
    {
        $result = self::creditNoteCheck()->verify(self::report('1064501', 'PNG'), self::report('1064500'), self::creditNote(InvoiceType::R1));

        self::assertSame([], $result->failures(), $result->summary());
        $divergences = $result->knownDivergences();
        self::assertSame(
            ['DetalleDesglose[1].BaseImponibleOimporteNoSujeto', 'DetalleDesglose[1].CuotaRepercutida', 'CuotaTotal', 'ImporteTotal'],
            self::fields($divergences),
        );
        self::assertSame(['-100.00', '100.0'], [$divergences[0]->ours, $divergences[0]->theirs]);
        self::assertSame(['-21.00', '21.0'], [$divergences[1]->ours, $divergences[1]->theirs]);
        self::assertSame(['-21.00', '21.0'], [$divergences[2]->ours, $divergences[2]->theirs]);
        self::assertSame(['-121.00', '121.0'], [$divergences[3]->ours, $divergences[3]->theirs]);
        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value]);
        self::assertTrue($result->passed());
    }

    public function test_positive_credit_note_passes_the_qr_fallback_with_a_note(): void
    {
        // Without a decoded QR, step 5 accepts B2Brouter's positive importe because step 4 recorded
        // the documented sign divergence on ImporteTotal; step 5 itself never yields one.
        $result = self::check()->verify(self::report('1064501'), self::report('1064500'), self::creditNote(InvoiceType::R1));

        self::assertSame(CheckStatus::KnownDivergence, $result->steps[CrossCheckStep::Semantics->value]);
        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value], $result->summary());
        self::assertSame([], $result->failures());
        self::assertSame([], array_values(array_filter($result->differences, static fn (Difference $difference): bool => $difference->step === CrossCheckStep::Qr)));
        self::assertTrue(self::hasNote($result, 'fallback'));
        self::assertTrue(self::hasNote($result, 'sign divergence'));
        self::assertTrue($result->passed());
    }

    public function test_credit_note_qr_importe_differing_by_more_than_sign_fails(): void
    {
        $invoice = SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '200.00', VatCategory::StandardRate, '21')],
            spanish: new SpanishInvoice(type: InvoiceType::R1, correctionType: CorrectionType::Difference),
            number: 'PROBE-ES-CN-0005',
            type: InvoiceTypeCode::C_381,
        )->withPrecedingInvoice(new PrecedingInvoiceReference(SpanishInvoices::NUMBER, new DateTimeImmutable(SpanishInvoices::ISSUE_DATE)));

        $result = self::check()->verify(self::report('1064501'), self::report('1064500'), $invoice);

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Qr->value]);
        $qr = array_values(array_filter($result->failures(), static fn (Difference $difference): bool => $difference->step === CrossCheckStep::Qr));
        self::assertSame(['importe'], self::fields($qr));
        self::assertSame(['-242.00', '121.0'], [$qr[0]->ours, $qr[0]->theirs]);
        self::assertFalse(self::hasNote($result, 'sign divergence'));
    }

    public function test_type_difference_fails(): void
    {
        $result = self::creditNoteCheck()->verify(self::report('1064501', 'PNG'), self::report('1064500'), self::creditNote(InvoiceType::R4));

        $failures = $result->failures();
        self::assertSame(['TipoFactura'], self::fields($failures));
        self::assertSame(['R4', 'R1'], [$failures[0]->ours, $failures[0]->theirs]);
        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Semantics->value]);
        self::assertTrue($result->failsIn(CrossCheckMode::Strict));
    }

    public function test_amount_difference_fails(): void
    {
        $invoice = SpanishInvoices::invoice([SpanishInvoices::line('1', '200.00', VatCategory::StandardRate, '21')]);

        $result = self::check()->verify(self::report('1064493'), self::report('1064490'), $invoice);

        self::assertContains('ImporteTotal', self::fields($result->failures()));
        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Semantics->value]);
        self::assertFalse($result->passed());
    }

    public function test_mapping_refusal_fails_step_4(): void
    {
        $invoice = SpanishInvoices::creditNote()->withNumber('PROBE-ES-CN-0005')->withExtensions(Extensions::none());

        $result = self::check()->verify(self::report('1064501'), self::report('1064500'), $invoice);

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Semantics->value]);
        $failures = $result->failures();
        self::assertCount(1, $failures);
        self::assertStringContainsString('TipoFactura', $failures[0]->message);
        self::assertStringContainsString('TipoRectificativa', $failures[0]->message);
    }

    public function test_unreadable_record_fails_step_1_and_skips_the_rest(): void
    {
        $report = self::reportFrom('1064493', '<not-a-record', self::HUELLA_1064493);

        $result = self::check()->verify($report, self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Schema->value]);
        foreach ([CrossCheckStep::Fingerprint, CrossCheckStep::Chain, CrossCheckStep::Semantics, CrossCheckStep::Qr] as $step) {
            self::assertSame(CheckStatus::Skipped, $result->steps[$step->value], $step->name);
        }
        self::assertFalse($result->passed());
    }

    public function test_schema_error_fails_step_1(): void
    {
        $xml = str_replace('<sum1:IDVersion>1.0</sum1:IDVersion>', '<sum1:IDVersion>1.0</sum1:IDVersion><sum1:Unknown>x</sum1:Unknown>', self::xml('1064493'));

        $result = self::check()->verify(self::reportFrom('1064493', $xml, self::HUELLA_1064493), null, SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Schema->value]);
        self::assertSame(CheckStatus::Skipped, $result->steps[CrossCheckStep::Fingerprint->value]);
        self::assertNotSame([], $result->failures());
    }

    public function test_missing_xml_fails_step_1(): void
    {
        $report = TaxReport::fromArray(['id' => 1064493, 'state' => 'registered', 'fingerprint' => self::HUELLA_1064493]);

        $result = self::check()->verify($report, null, SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Schema->value]);
        self::assertSame(CheckStatus::Skipped, $result->steps[CrossCheckStep::Qr->value]);
        self::assertSame('xml_base64', $result->failures()[0]->field);
    }

    public function test_qr_mismatch_fails_step_5(): void
    {
        $decoder = self::decoder(static fn (): string => 'https://example.invalid/ValidarQR?nif=B64829179');

        $result = self::check($decoder)->verify(self::report('1064493', 'PNG'), self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Qr->value]);
        $failure = $result->failures()[0];
        self::assertSame('qr', $failure->field);
        self::assertSame(self::expectedUrl('1064493', VerifactuEnvironment::Test), $failure->ours);
        self::assertSame('https://example.invalid/ValidarQR?nif=B64829179', $failure->theirs);
    }

    public function test_decoded_qr_matching_the_record_passes(): void
    {
        $url = self::expectedUrl('1064493', VerifactuEnvironment::Production);
        $decoder = self::decoder(static fn (string $png): string => $png === 'PNG' ? $url : '');

        $result = (new VerifactuCrossCheck(Fixtures::verifactuSchemas(), VerifactuEnvironment::Production, $decoder))
            ->verify(self::report('1064493', 'PNG'), self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value], $result->summary());
        self::assertFalse(self::hasNote($result, 'fallback'));
    }

    public function test_undecodable_png_falls_back(): void
    {
        $decoder = self::decoder(static fn (): string => throw new RuntimeException('No QR code found.'));

        $result = self::check($decoder)->verify(self::report('1064493', 'PNG'), self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value]);
        self::assertTrue(self::hasNote($result, 'fallback'));
        self::assertTrue(self::hasNote($result, 'No QR code found.'));
    }

    public function test_decoder_errors_other_than_runtime_exceptions_propagate(): void
    {
        foreach ([new \TypeError('Bad argument.'), new \Error('Broken decoder.')] as $error) {
            $decoder = self::decoder(static fn (): string => throw $error);

            $thrown = null;
            try {
                self::check($decoder)->verify(self::report('1064493', 'PNG'), self::report('1064490'), SpanishInvoices::domestic());
            } catch (\Throwable $e) {
                $thrown = $e;
            }

            self::assertSame($error, $thrown);
        }
    }

    public function test_fallback_compares_amount_by_value(): void
    {
        // B2Brouter writes importe=121.0; our draft carries 121.00.
        self::assertStringEndsWith('&importe=121.0', self::expectedUrl('1064493', VerifactuEnvironment::Test));

        $result = self::check()->verify(self::report('1064493'), self::report('1064490'), SpanishInvoices::domestic());

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value]);
    }

    public function test_fallback_fails_on_a_different_parameter(): void
    {
        // Semantics fails on IDFactura too; the QR step compares the four parameters on its own.
        $result = self::check()->verify(self::report('1064493'), self::report('1064490'), SpanishInvoices::domestic()->withNumber('OTHER-1'));

        self::assertSame(CheckStatus::Failed, $result->steps[CrossCheckStep::Qr->value]);
        $qr = array_values(array_filter($result->failures(), static fn (Difference $difference): bool => $difference->step === CrossCheckStep::Qr));
        self::assertSame(['numserie'], self::fields($qr));
    }

    public function test_report_mode_never_fails(): void
    {
        $result = self::creditNoteCheck()->verify(self::report('1064501', 'PNG'), self::report('1064500'), self::creditNote(InvoiceType::R4));

        self::assertFalse($result->passed());
        self::assertTrue($result->failsIn(CrossCheckMode::Strict));
        self::assertFalse($result->failsIn(CrossCheckMode::Report));
    }

    public function test_summary_lists_every_difference(): void
    {
        $result = self::creditNoteCheck()->verify(self::report('1064501', 'PNG'), self::report('1064500'), self::creditNote(InvoiceType::R4));

        $summary = $result->summary();
        foreach (CrossCheckStep::cases() as $step) {
            self::assertStringContainsString($step->name, $summary);
        }
        foreach ($result->differences as $difference) {
            self::assertStringContainsString($difference->field, $summary);
        }
        self::assertCount(5, $result->differences);
        self::assertGreaterThanOrEqual(5 + 5, count(explode("\n", $summary)));
    }

    #[RequiresPhpExtension('gd')]
    public function test_captured_reports_pass_every_step_with_the_decoder(): void
    {
        $report = TaxReport::fromArray(self::json('tax-report.json'));
        $previous = TaxReport::fromArray(self::json('tax-report-previous.json'));
        $check = new VerifactuCrossCheck(Fixtures::verifactuSchemas(), VerifactuEnvironment::Production, new ChillerlanQrDecoder);

        $result = $check->verify($report, $previous, SpanishInvoices::domestic()->withNumber('LIVE-75058f2f4e70'));

        foreach (CrossCheckStep::cases() as $step) {
            self::assertSame(CheckStatus::Passed, $result->steps[$step->value], $step->name."\n".$result->summary());
        }
        self::assertFalse(self::hasNote($result, 'fallback'));
    }

    #[RequiresPhpExtension('gd')]
    public function test_default_environment_reads_b2brouter_qr_codes(): void
    {
        $report = TaxReport::fromArray(self::json('tax-report.json'));
        $previous = TaxReport::fromArray(self::json('tax-report-previous.json'));
        $check = new VerifactuCrossCheck(Fixtures::verifactuSchemas(), qrDecoder: new ChillerlanQrDecoder);

        $result = $check->verify($report, $previous, SpanishInvoices::domestic()->withNumber('LIVE-75058f2f4e70'));

        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value], $result->summary());
        self::assertFalse(self::hasNote($result, 'fallback'));
    }

    private static function check(?QrDecoder $decoder = null): VerifactuCrossCheck
    {
        return new VerifactuCrossCheck(Fixtures::verifactuSchemas(), VerifactuEnvironment::Test, $decoder);
    }

    /** A check whose decoder reads the QR of record 1064501 as B2Brouter printed it. */
    private static function creditNoteCheck(): VerifactuCrossCheck
    {
        $url = self::expectedUrl('1064501', VerifactuEnvironment::Test);

        return self::check(self::decoder(static fn (): string => $url));
    }

    /**
     * @param  \Closure(string): string  $decode
     */
    private static function decoder(\Closure $decode): QrDecoder
    {
        return new class($decode) implements QrDecoder
        {
            /** @param  \Closure(string): string  $decode */
            public function __construct(private readonly \Closure $decode) {}

            public function decode(string $png): string
            {
                return ($this->decode)($png);
            }
        };
    }

    /** The exempt probe invoice: one exempt line of 100.00 under the given article. */
    private static function exemptProbe(ExemptOperation $article): Invoice
    {
        return SpanishInvoices::invoice(
            [SpanishInvoices::line('1', '100.00', VatCategory::Exempt, null)],
            spanish: new SpanishInvoice(breakdown: ['E|-' => new BreakdownOverride(exemption: $article)]),
            number: 'PROBE-ES-UBL-E-0003',
        );
    }

    private static function creditNote(InvoiceType $type): Invoice
    {
        return SpanishInvoices::creditNote()
            ->withNumber('PROBE-ES-CN-0005')
            ->withExtension(new SpanishInvoice(type: $type, correctionType: CorrectionType::Difference));
    }

    private static function xml(string $number): string
    {
        $xml = Fixtures::verifactuB2Brouter()['record-'.$number.'.xml'] ?? null;
        self::assertIsString($xml);

        return $xml;
    }

    /** A report built from a spike record: its Huella as fingerprint and an opaque previous_id. */
    private static function report(string $number, ?string $qrPng = null): TaxReport
    {
        $xml = self::xml($number);
        self::assertSame(1, preg_match('#<sum1:Huella>([0-9A-F]{64})</sum1:Huella>\s*</sum1:RegistroAlta>#', $xml, $match));

        return self::reportFrom($number, $xml, $match[1] ?? '', $qrPng);
    }

    private static function reportFrom(string $number, string $xml, string $fingerprint, ?string $qrPng = null): TaxReport
    {
        $body = [
            'id' => (int) $number,
            'state' => 'registered',
            'fingerprint' => $fingerprint,
            'previous_id' => 290000 + ((int) $number % 1000),
            'xml_base64' => base64_encode($xml),
        ];
        if ($qrPng !== null) {
            $body['qr'] = base64_encode($qrPng);
        }

        return TaxReport::fromArray(['tax_report' => $body]);
    }

    private static function expectedUrl(string $number, VerifactuEnvironment $environment): string
    {
        $record = self::report($number)->record();
        self::assertInstanceOf(RegistrationRecord::class, $record);

        return VerifactuQr::url($record, $environment);
    }

    /**
     * @param  list<Difference>  $differences
     * @return list<string>
     */
    private static function fields(array $differences): array
    {
        return array_map(static fn (Difference $difference): string => $difference->field, $differences);
    }

    private static function hasNote(CrossCheckResult $result, string $needle): bool
    {
        foreach ($result->notes as $note) {
            if (str_contains($note, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    private static function json(string $file): array
    {
        $data = json_decode((string) file_get_contents(self::API_DIR.'/'.$file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}

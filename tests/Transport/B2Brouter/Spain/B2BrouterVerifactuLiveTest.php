<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\B2BrouterSpainLive;
use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\SpanishInvoices;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\B2BrouterVerifactu;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CheckStatus;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\ChillerlanQrDecoder;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckResult;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\CrossCheckStep;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\Difference;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\IssuedInvoice;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\TaxReport;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\VerifactuCrossCheck;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Issues Spanish invoices through the B2Brouter sandbox and cross-checks the VERI*FACTU records
 * B2Brouter registers against our own engine.
 *
 * Needs B2BROUTER_ES_API_KEY and B2BROUTER_ES_ACCOUNT_ID and skips without them. Only a "test_"
 * key on https://api.b2brouter.net is accepted; anything else fails before the first request.
 * The QR codes are read with chillerlan/php-qrcode, which must be installed.
 *
 * Every case chains its record to the report registered just before it in the same run, so that
 * step 3 is checked against a previous report from the caller's own store (B2Brouter's
 * previous_id is not a tax report id). When no report of this run is at hand, for example
 * because a case runs alone, a domestic invoice is issued first to provide one.
 *
 * Each registration can take up to three minutes, and every case creates one or two sandbox
 * invoices. Output never contains the API key or the account id.
 */
#[Group('b2brouter-spain-live')]
final class B2BrouterVerifactuLiveTest extends TestCase
{
    private const TAG = B2BrouterSpainLive::TAG;

    /** The fields of a correction by difference whose sign B2Brouter writes positive. */
    private const AMOUNT_FIELDS = ['BaseImponibleOimporteNoSujeto', 'CuotaRepercutida', 'CuotaTotal', 'ImporteTotal'];

    /** The last report registered in this run: the previous record of the next one. */
    private static ?TaxReport $chainHead = null;

    /** The number and issue date of the first domestic invoice registered in this run. */
    private static ?PrecedingInvoiceReference $original = null;

    private Redactor $redactor;

    private B2BrouterVerifactu $transport;

    private VerifactuCrossCheck $crossCheck;

    private float $startedAt = 0.0;

    /** @var list<string> */
    private array $seen = [];

    protected function setUp(): void
    {
        $config = B2BrouterSpainLive::configFromEnvironment();
        if ($config === null) {
            self::markTestSkipped(sprintf('Set %s and %s of a B2Brouter Spanish sandbox account.', B2BrouterSpainLive::KEY_ENV, B2BrouterSpainLive::ACCOUNT_ENV));
        }
        $refusal = B2BrouterSpainLive::keyRefusal($config);
        if ($refusal !== null) {
            self::fail($refusal);
        }
        if (! ChillerlanQrDecoder::isAvailable()) {
            self::fail('The Spanish live test reads the QR codes with chillerlan/php-qrcode and ext-gd or ext-imagick; install them.');
        }

        $this->redactor = B2BrouterSpainLive::redactor($config);
        $this->transport = new B2BrouterVerifactu($config, B2BrouterSpainLive::http($config, $this->observe(...)));
        // B2Brouter prints the production ValidarQR host in its QR codes, in the sandbox too.
        $this->crossCheck = new VerifactuCrossCheck(Fixtures::verifactuSchemas(), VerifactuEnvironment::Production, new ChillerlanQrDecoder);
    }

    public function test_second_invoice_chains_to_the_first(): void
    {
        LiveTransport::failRedacted($this->redactor, function (): void {
            [, $first] = $this->issueRegistered('chain, first', SpanishInvoices::domestic(), domestic: true);
            [$invoice, $second] = $this->issueRegistered('chain, second', SpanishInvoices::domestic());

            $result = $this->check('chain, second', $second, $first, $invoice);

            self::assertNotSame($first->id, $second->id);
            self::assertTrue($result->passed(), $result->summary());
            foreach (CrossCheckStep::cases() as $step) {
                self::assertSame(CheckStatus::Passed, $result->steps[$step->value] ?? null, $step->name."\n".$result->summary());
            }
            $this->assertQrDecoded($result);
        });
    }

    public function test_invoice_reaches_registered_and_cross_checks_strictly(): void
    {
        LiveTransport::failRedacted($this->redactor, function (): void {
            $previous = $this->previous();
            $lookup = 'not called';
            $found = null;
            [$invoice, $report, $issued] = $this->issueRegistered('F1', SpanishInvoices::domestic(), function (Invoice $invoice) use (&$found, &$lookup): void {
                // B2Brouter matches the number filter of the invoice list as a prefix; the
                // transport must still find exactly the invoice just imported.
                try {
                    $found = $this->transport->findByNumber($invoice->number);
                    $lookup = $found === null ? 'null' : sprintf('invoice %s (number %s, state %s)', $found->invoiceId, $found->number ?? '-', $found->state);
                } catch (Throwable $e) {
                    $lookup = $e::class.': '.$e->getMessage();
                }
                LiveTransport::report(self::TAG, sprintf('F1: findByNumber(%s) returned %s.', $invoice->number, $lookup), $this->redactor);
            });
            $result = $this->check('F1', $report, $previous, $invoice);

            self::assertNotNull($found, 'findByNumber() did not find the invoice just imported: '.$lookup);
            self::assertSame($issued->invoiceId, $found->invoiceId);
            self::assertTrue($result->passed(), $result->summary());
            foreach (CrossCheckStep::cases() as $step) {
                self::assertSame(CheckStatus::Passed, $result->steps[$step->value] ?? null, $step->name."\n".$result->summary());
            }
            $this->assertQrDecoded($result);
        });
    }

    public function test_exempt_invoice_differs_only_by_known_divergence(): void
    {
        LiveTransport::failRedacted($this->redactor, function (): void {
            $previous = $this->previous();
            [$invoice, $report] = $this->issueRegistered('E6', SpanishInvoices::exempt(ExemptOperation::E6));

            $result = $this->check('E6', $report, $previous, $invoice);

            self::assertTrue($result->passed(), $result->summary());
            foreach ([CrossCheckStep::Schema, CrossCheckStep::Fingerprint, CrossCheckStep::Chain, CrossCheckStep::Qr] as $step) {
                self::assertSame(CheckStatus::Passed, $result->steps[$step->value] ?? null, $step->name."\n".$result->summary());
            }
            self::assertContains($result->steps[CrossCheckStep::Semantics->value] ?? null, [CheckStatus::Passed, CheckStatus::KnownDivergence], $result->summary());
            foreach ($result->knownDivergences() as $divergence) {
                self::assertStringEndsWith('OperacionExenta', $divergence->field, $result->summary());
            }
            $this->assertQrDecoded($result);
        });
    }

    public function test_credit_note_differs_only_by_known_sign_divergence(): void
    {
        LiveTransport::failRedacted($this->redactor, function (): void {
            $previous = $this->previous();
            $original = self::$original ?? throw new LogicException('No original invoice was registered in this run.');
            LiveTransport::report(self::TAG, sprintf('R1/I: corrects %s of %s.', $original->number, $original->issueDate?->format('d-m-Y') ?? '-'), $this->redactor);
            $creditNote = SpanishInvoices::creditNote()->withPrecedingInvoices([$original]);
            [$invoice, $report] = $this->issueRegistered('R1/I', $creditNote);

            $result = $this->check('R1/I', $report, $previous, $invoice);

            self::assertTrue($result->passed(), $result->summary());
            foreach ([CrossCheckStep::Schema, CrossCheckStep::Fingerprint, CrossCheckStep::Chain, CrossCheckStep::Qr] as $step) {
                self::assertSame(CheckStatus::Passed, $result->steps[$step->value] ?? null, $step->name."\n".$result->summary());
            }
            self::assertSame(CheckStatus::KnownDivergence, $result->steps[CrossCheckStep::Semantics->value] ?? null, $result->summary());
            foreach ($result->knownDivergences() as $divergence) {
                self::assertContains(self::fieldName($divergence), self::AMOUNT_FIELDS, $result->summary());
            }
            $this->assertQrDecoded($result);
        });
    }

    /**
     * The report registered just before the next invoice. A domestic invoice is issued first when
     * this run has none, so that the next record's chain can be checked.
     */
    private function previous(): TaxReport
    {
        if (self::$chainHead === null) {
            $this->issueRegistered('chain head', SpanishInvoices::domestic(), domestic: true);
        }

        return self::$chainHead ?? throw new LogicException('No chain head was registered.');
    }

    /**
     * Issues the invoice with a fresh number dated today, waits for its tax report and requires it
     * to be registered. The registered report becomes the chain head of the run, and the first
     * domestic invoice the original a credit note of the run corrects.
     *
     * @param  (Closure(Invoice, IssuedInvoice): void)|null  $afterIssue  runs right after the import
     * @return array{Invoice, TaxReport, IssuedInvoice}
     */
    private function issueRegistered(string $label, Invoice $invoice, ?Closure $afterIssue = null, bool $domestic = false): array
    {
        self::$chainHead = null;
        $invoice = $this->numbered($invoice);

        $this->startedAt = microtime(true);
        $this->seen = [];
        $issued = $this->transport->issue($invoice);
        $this->reportIssued($label, $issued);
        if ($afterIssue !== null) {
            $afterIssue($invoice, $issued);
        }
        $report = $this->awaitRegistered($label, $issued);

        self::assertTrue($report->isRegistered(), sprintf('%s: tax report %s is %s.', $label, $report->id, $report->state));
        self::$chainHead = $report;
        if ($domestic && self::$original === null) {
            self::$original = new PrecedingInvoiceReference($invoice->number, $invoice->issueDate);
        }

        return [$invoice, $report, $issued];
    }

    private function awaitRegistered(string $label, IssuedInvoice $issued): TaxReport
    {
        $report = $this->transport->awaitRegistered($issued->invoiceId);
        LiveTransport::report(self::TAG, sprintf(
            '%s: invoice %s, tax report %s, final state %s after %.1f s; seen: %s.',
            $label,
            $issued->invoiceId,
            $report->id ?? '-',
            $report->state ?? 'none',
            microtime(true) - $this->startedAt,
            implode(', ', $this->seen),
        ), $this->redactor);

        self::assertNotNull($report, $label.': the invoice listed no tax report within the polling bound.');
        self::assertTrue($report->isFinal(), sprintf('%s: tax report %s still %s at the polling bound.', $label, $report->id, $report->state));

        return $report;
    }

    private function check(string $label, TaxReport $report, ?TaxReport $previous, Invoice $invoice): CrossCheckResult
    {
        $result = $this->crossCheck->verify($report, $previous, $invoice);
        $host = '-';
        if ($report->qrPng !== null) {
            try {
                $host = (string) parse_url((new ChillerlanQrDecoder)->decode($report->qrPng), PHP_URL_HOST);
            } catch (Throwable $e) {
                $host = 'undecodable: '.$e->getMessage();
            }
        }
        LiveTransport::report(self::TAG, sprintf(
            "%s: previous report %s; decoded QR host %s.\n%s",
            $label,
            $previous->id ?? 'none',
            $host,
            $result->summary(),
        ), $this->redactor);

        return $result;
    }

    /** Step 5 must be decided by the decoder, not by the parameter fallback. */
    private function assertQrDecoded(CrossCheckResult $result): void
    {
        self::assertSame(CheckStatus::Passed, $result->steps[CrossCheckStep::Qr->value] ?? null, $result->summary());
        foreach ($result->notes as $note) {
            self::assertStringNotContainsString('fallback', $note, 'Step 5 passed only through the QR fallback: '.$note);
        }
    }

    private function numbered(Invoice $invoice): Invoice
    {
        return $invoice
            ->withNumber(LiveTransport::invoiceNumber())
            ->withIssueDate(new DateTimeImmutable('today', new DateTimeZone('Europe/Madrid')));
    }

    private function reportIssued(string $label, IssuedInvoice $issued): void
    {
        LiveTransport::report(self::TAG, sprintf(
            '%s: issued %s as invoice %s, state %s, tax reports [%s].',
            $label,
            $issued->number ?? '-',
            $issued->invoiceId,
            $issued->state,
            implode(', ', $issued->taxReportIds),
        ), $this->redactor);
    }

    /** Records the state of every invoice and tax report read, with the seconds since the issue. */
    private function observe(RequestInterface $request, ResponseInterface $response): void
    {
        if ($request->getMethod() !== 'GET' || $response->getStatusCode() !== 200
            || preg_match('#/(invoices|tax_reports)/(\d+)$#', $request->getUri()->getPath(), $match) !== 1) {
            return;
        }
        $body = json_decode((string) $response->getBody(), true);
        $key = $match[1] === 'invoices' ? 'invoice' : 'tax_report';
        $object = is_array($body) && is_array($body[$key] ?? null) ? $body[$key] : [];
        $state = is_string($object['state'] ?? null) ? $object['state'] : '?';
        $this->seen[] = sprintf('%s %s %s@%.1fs', $key, $match[2], $state, microtime(true) - $this->startedAt);
    }

    /** The element name of a difference, without the breakdown line prefix. */
    private static function fieldName(Difference $difference): string
    {
        $dot = strrpos($difference->field, '.');

        return $dot === false ? $difference->field : substr($difference->field, $dot + 1);
    }
}

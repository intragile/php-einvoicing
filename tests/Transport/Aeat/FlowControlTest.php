<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Aeat\FlowControl;
use IntrAgile\EInvoicing\Transport\Aeat\SubmissionResponse;
use IntrAgile\EInvoicing\Transport\Aeat\SubmissionState;
use IntrAgile\EInvoicing\Transport\Aeat\ThrottleKey;
use IntrAgile\EInvoicing\Transport\Aeat\ThrottleScope;
use IntrAgile\EInvoicing\Transport\Aeat\ThrottleState;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FlowControlTest extends TestCase
{
    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-23 '.$time);
    }

    private function control(string $now): FlowControl
    {
        return new FlowControl(fn (): DateTimeImmutable => $this->at($now));
    }

    private function response(int $wait): SubmissionResponse
    {
        return new SubmissionResponse(null, null, null, $wait, SubmissionState::Correcto, [], '');
    }

    private function sentAt(string $time, int $wait): ThrottleState
    {
        return (new FlowControl)->afterResponse(new ThrottleState, $this->response($wait), $this->at($time));
    }

    public function test_first_send_is_immediate(): void
    {
        $decision = $this->control('10:00:00')->decide(new ThrottleState, 5);

        self::assertTrue($decision->sendNow);
        self::assertSame(5, $decision->batchSize);
        self::assertNull($decision->nextSendAt);
    }

    public function test_waits_tiempo_espera_envio(): void
    {
        $state = $this->sentAt('10:00:00', 120);

        $early = $this->control('10:01:59')->decide($state, 3);
        self::assertFalse($early->sendNow);
        self::assertEquals($this->at('10:02:00'), $early->nextSendAt);
        self::assertSame(0, $early->batchSize);

        $due = $this->control('10:02:00')->decide($state, 3);
        self::assertTrue($due->sendNow);
        self::assertSame(3, $due->batchSize);
    }

    public function test_initial_wait_is_sixty_seconds_before_any_response(): void
    {
        $state = (new FlowControl)->afterFailure(new ThrottleState, $this->at('10:00:00'));

        self::assertEquals($this->at('10:01:00'), $state->nextSendAt());
        self::assertFalse($this->control('10:00:59')->decide($state, 1)->sendNow);
        self::assertTrue($this->control('10:01:00')->decide($state, 1)->sendNow);
    }

    public function test_thousand_pending_sends_at_once(): void
    {
        $state = $this->sentAt('10:00:00', 120);

        $decision = $this->control('10:00:05')->decide($state, 1000);
        self::assertTrue($decision->sendNow);
        self::assertSame(1000, $decision->batchSize);

        self::assertFalse($this->control('10:00:05')->decide($state, 999)->sendNow);
    }

    public function test_zero_wait_allows_immediate_send(): void
    {
        $state = $this->sentAt('10:00:00', 0);

        self::assertTrue($this->control('10:00:00')->decide($state, 1)->sendNow);
    }

    public function test_failure_keeps_the_last_wait(): void
    {
        $state = (new FlowControl)->afterFailure($this->sentAt('10:00:00', 120), $this->at('10:02:00'));

        self::assertSame(120, $state->waitSeconds);
        self::assertEquals($this->at('10:04:00'), $state->nextSendAt());
    }

    public function test_nothing_pending_never_sends(): void
    {
        $decision = $this->control('10:00:00')->decide(new ThrottleState, 0);

        self::assertFalse($decision->sendNow);
        self::assertSame(0, $decision->batchSize);
        self::assertNull($decision->nextSendAt);
    }

    public function test_batch_size_is_capped(): void
    {
        $decision = $this->control('10:00:00')->decide(new ThrottleState, 2500);

        self::assertTrue($decision->sendNow);
        self::assertSame(1000, $decision->batchSize);
    }

    public function test_negative_wait_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ThrottleState(null, -1);
    }

    public function test_batches_group_per_issuer_in_chain_order(): void
    {
        $a1 = $this->record('A', 'A1');
        $b1 = $this->record('B', 'B1');
        $a2 = $this->record('A', 'A2');

        self::assertSame([[$a1, $a2], [$b1]], FlowControl::batches([$a1, $b1, $a2]));
        self::assertSame([], FlowControl::batches([]));
    }

    public function test_batches_split_at_1000(): void
    {
        $records = [];
        for ($i = 1; $i <= 2001; $i++) {
            $records[] = $this->record('A', 'N'.$i);
        }

        $batches = FlowControl::batches($records);

        self::assertSame([1000, 1000, 1], array_map('count', $batches));
        self::assertSame($records[1000], $batches[1][0]);
        self::assertSame($records[2000], $batches[2][0]);
    }

    public function test_keys(): void
    {
        $system = VerifactuSamples::system();

        self::assertSame('383|test', ThrottleKey::for($system, VerifactuEnvironment::Test)->toString());
        self::assertSame(
            'B64829179|383|test',
            ThrottleKey::for($system, VerifactuEnvironment::Test, ThrottleScope::Issuer, Nif::parse('B64829179'))->toString(),
        );

        $odd = new ComputerSystem(
            'Test Producer S.L.',
            Nif::parse('A39200019'),
            null,
            'TestSIF',
            '77',
            '1.0.03',
            'a|b',
            true,
            false,
            false,
        );
        self::assertSame('a%7Cb|test', ThrottleKey::for($odd, VerifactuEnvironment::Test)->toString());
    }

    public function test_issuer_scope_needs_an_issuer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ThrottleKey::for(VerifactuSamples::system(), VerifactuEnvironment::Test, ThrottleScope::Issuer);
    }

    private function record(string $issuer, string $number): BillingRecord
    {
        $nif = $issuer === 'A' ? Nif::parse(VerifactuSamples::ISSUER_NIF) : Nif::parse('B64829179');
        $id = new InvoiceId($nif, $number, new DateTimeImmutable('2026-11-23'));

        return new class($id) implements BillingRecord
        {
            public function __construct(private readonly InvoiceId $id) {}

            public function invoiceId(): InvoiceId
            {
                return $this->id;
            }

            public function chainLink(): ChainLink
            {
                throw new LogicException('not needed');
            }

            public function generatedAt(): RecordTimestamp
            {
                throw new LogicException('not needed');
            }

            public function fingerprint(): string
            {
                return '';
            }

            public function system(): ComputerSystem
            {
                throw new LogicException('not needed');
            }

            public function hashFields(): array
            {
                return [];
            }
        };
    }
}

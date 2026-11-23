<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Chainer;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainHead;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainKey;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\ChainConflict;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\InMemoryChainStore;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use PHPUnit\Framework\TestCase;

final class ChainerTest extends TestCase
{
    use CapturesThrowable;

    private const HASH_1 = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';

    private const HASH_2 = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';

    private const HASH_3 = '177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68';

    private const SEED_HASH = '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73';

    public function test_first_record_has_primer_registro(): void
    {
        [$record, $head] = $this->chainer()->append(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            null,
            new DateTimeImmutable('2024-01-01T18:20:30Z'),
        );

        $this->assertTrue($record->chainLink()->isFirst());
        $this->assertSame('', $record->chainLink()->previousFingerprint());
        $this->assertSame(self::HASH_1, $record->fingerprint());
        $this->assertSame(1, $head->sequence);
        $this->assertSame(self::HASH_1, $head->fingerprint);
        $this->assertSame('2024-01-01T19:20:30+01:00', $head->generatedAt?->format('Y-m-d\TH:i:sP'));
        $this->assertFalse($head->seeded);
    }

    public function test_official_chain_through_chainer(): void
    {
        $instants = ['2024-01-01T18:20:30Z', '2024-01-01T18:20:35Z', '2024-01-01T18:20:40Z'];
        $clock = static function () use (&$instants): DateTimeImmutable {
            $next = array_shift($instants);
            self::assertIsString($next);

            return new DateTimeImmutable($next);
        };
        $system = VerifactuSamples::system();
        $chainer = new Chainer($system, $clock);
        $store = new InMemoryChainStore;
        $key = ChainKey::for(Nif::parse(VerifactuSamples::ISSUER_NIF), $system, VerifactuEnvironment::Test);

        $first = $chainer->appendTo($store, $key, VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'));
        $second = $chainer->appendTo($store, $key, VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'));
        $third = $chainer->appendTo($store, $key, CancellationDraft::of(VerifactuSamples::invoiceId('12345679/G34', '01-01-2024')));

        $this->assertInstanceOf(RegistrationRecord::class, $first);
        $this->assertInstanceOf(RegistrationRecord::class, $second);
        $this->assertInstanceOf(CancellationRecord::class, $third);
        $this->assertSame([self::HASH_1, self::HASH_2, self::HASH_3], [$first->fingerprint(), $second->fingerprint(), $third->fingerprint()]);
        $this->assertSame(
            ['2024-01-01T19:20:30+01:00', '2024-01-01T19:20:35+01:00', '2024-01-01T19:20:40+01:00'],
            [$first->generatedAt()->text, $second->generatedAt()->text, $third->generatedAt()->text],
        );
        $this->assertSame(self::HASH_2, $third->chainLink()->previousFingerprint());
        $this->assertSame([$first, $second, $third], $store->records($key));
        $this->assertSame(3, $store->head($key)?->sequence);
        $this->assertSame(self::HASH_3, $store->head($key)->fingerprint);
    }

    public function test_earlier_timestamp_is_refused(): void
    {
        $chainer = $this->chainer();
        [, $head] = $chainer->append(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            null,
            new DateTimeImmutable('2024-01-01T19:20:35+01:00'),
        );

        $thrown = self::capture(fn () => $chainer->append(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            $head,
            new DateTimeImmutable('2024-01-01T19:20:34+01:00'),
        ));

        $this->assertInstanceOf(ChainConflict::class, $thrown);
        $this->assertStringContainsString('2024-01-01T19:20:35+01:00', $thrown->getMessage());
        $this->assertStringContainsString('2024-01-01T19:20:34+01:00', $thrown->getMessage());
    }

    public function test_same_second_is_ordered_by_sequence(): void
    {
        $chainer = $this->chainer();
        $now = new DateTimeImmutable('2024-01-01T19:20:35+01:00');

        [$first, $head] = $chainer->append(VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'), null, $now);
        [$second, $next] = $chainer->append(VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'), $head, $now->modify('+400 milliseconds'));

        $this->assertSame(1, $head->sequence);
        $this->assertSame(2, $next->sequence);
        $this->assertSame($first->generatedAt()->text, $second->generatedAt()->text);
        $this->assertTrue($second->chainLink()->previous()?->equals($first->invoiceId()));
        $this->assertSame($first->fingerprint(), $second->chainLink()->previousFingerprint());
    }

    public function test_seeded_head_is_continued(): void
    {
        $seed = ChainHead::seeded($this->spikeId('PROBE-ES-0001'), self::SEED_HASH);
        $this->assertSame(0, $seed->sequence);
        $this->assertNull($seed->generatedAt);
        $this->assertTrue($seed->seeded);

        // A seeded head carries no timestamp, so even a long-past instant is accepted.
        [$record, $head] = $this->chainer()->append(
            $this->spikeDraft(),
            $seed,
            new DateTimeImmutable('2000-01-01T00:00:00Z'),
        );

        $previous = $record->chainLink()->previous();
        $this->assertNotNull($previous);
        $this->assertSame('B64829179', $previous->issuer->value);
        $this->assertSame('PROBE-ES-0001', $previous->number);
        $this->assertSame('23-11-2026', $previous->dateText());
        $this->assertSame(self::SEED_HASH, $record->chainLink()->previousFingerprint());
        $this->assertSame(1, $head->sequence);
        $this->assertFalse($head->seeded);
    }

    public function test_spike_invoice_hashes_its_own_text(): void
    {
        [$record] = $this->chainer()->append(
            $this->spikeDraft(),
            ChainHead::seeded($this->spikeId('PROBE-ES-0001'), self::SEED_HASH),
            new DateTimeImmutable('2026-11-23T13:06:56+01:00'),
        );

        $this->assertSame(
            'IDEmisorFactura=B64829179&NumSerieFactura=PROBE-ES-UBL-0002&FechaExpedicionFactura=23-11-2026'
            .'&TipoFactura=F1&CuotaTotal=21.00&ImporteTotal=121.00&Huella='.self::SEED_HASH
            .'&FechaHoraHusoGenRegistro=2026-11-23T13:06:56+01:00',
            Fingerprint::input($record),
        );
        // B2Brouter wrote 21.0 / 121.0 and got D60F9FD2…; our canonical text hashes differently.
        $this->assertSame('F4C25A28410FCBA3AAA42ED2BCAC66E966C0C8F19B71FD7600D00D1F8EDA6BBB', $record->fingerprint());
    }

    public function test_draft_of_another_issuer_is_refused(): void
    {
        $system = VerifactuSamples::system();
        $store = new InMemoryChainStore;
        $key = new ChainKey(Nif::parse('B64829179'), $system->installationNumber, VerifactuEnvironment::Test);
        $seed = $store->seed($key, $this->spikeId('PROBE-ES-0001'), self::SEED_HASH);
        $chainer = new Chainer($system, static fn (): DateTimeImmutable => new DateTimeImmutable('2024-01-01T18:20:30Z'));

        $registration = self::capture(fn () => $chainer->appendTo($store, $key, VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024')));
        $this->assertInstanceOf(ChainConflict::class, $registration);
        $this->assertStringContainsString('89890001K', $registration->getMessage());

        $cancellation = self::capture(fn () => $chainer->appendTo(
            $store,
            $key,
            CancellationDraft::of(VerifactuSamples::invoiceId('12345678/G33', '01-01-2024')),
        ));
        $this->assertInstanceOf(ChainConflict::class, $cancellation);

        $this->assertEquals($seed, $store->head($key));
        $this->assertSame([], $store->records($key));
    }

    public function test_system_of_another_installation_is_refused(): void
    {
        $store = new InMemoryChainStore;
        $key = new ChainKey(Nif::parse(VerifactuSamples::ISSUER_NIF), '384', VerifactuEnvironment::Test);
        $chainer = new Chainer(VerifactuSamples::system(), static fn (): DateTimeImmutable => new DateTimeImmutable('2024-01-01T18:20:30Z'));

        $thrown = self::capture(fn () => $chainer->appendTo($store, $key, VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024')));

        $this->assertInstanceOf(ChainConflict::class, $thrown);
        $this->assertStringContainsString('"383"', $thrown->getMessage());
        $this->assertNull($store->head($key));
        $this->assertSame([], $store->records($key));
    }

    public function test_stored_head_is_restored(): void
    {
        $at = new DateTimeImmutable('2024-01-01T19:20:35+01:00');
        $head = ChainHead::restore(VerifactuSamples::invoiceId('12345678/G33', '01-01-2024'), self::HASH_1, $at, 7, false);

        $this->assertSame(self::HASH_1, $head->fingerprint);
        $this->assertSame($at, $head->generatedAt);
        $this->assertSame(7, $head->sequence);
        $this->assertFalse($head->seeded);

        [$record, $next] = $this->chainer()->append(VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'), $head, $at);
        $this->assertSame(self::HASH_2, $record->fingerprint());
        $this->assertSame(8, $next->sequence);

        $seeded = ChainHead::restore($this->spikeId('PROBE-ES-0001'), self::SEED_HASH, null, 0, true);
        $this->assertEquals(ChainHead::seeded($this->spikeId('PROBE-ES-0001'), self::SEED_HASH), $seeded);
    }

    public function test_restore_checks_the_head_invariants(): void
    {
        $id = VerifactuSamples::invoiceId('12345678/G33', '01-01-2024');
        $at = new DateTimeImmutable('2024-01-01T19:20:35+01:00');

        foreach ([
            'seeded with a sequence' => [null, 1, true],
            'seeded with a timestamp' => [$at, 0, true],
            'unseeded without a timestamp' => [null, 1, false],
            'unseeded with sequence 0' => [$at, 0, false],
            'negative sequence' => [$at, -1, false],
        ] as $case => [$generatedAt, $sequence, $seeded]) {
            $thrown = self::capture(static fn () => ChainHead::restore($id, self::HASH_1, $generatedAt, $sequence, $seeded));
            $this->assertInstanceOf(InvalidRecord::class, $thrown, $case);
        }
    }

    private function chainer(): Chainer
    {
        return new Chainer(VerifactuSamples::system());
    }

    private function spikeId(string $number): InvoiceId
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', '23-11-2026');
        $this->assertInstanceOf(DateTimeImmutable::class, $date);

        return new InvoiceId(Nif::parse('B64829179'), $number, $date);
    }

    private function spikeDraft(): RegistrationDraft
    {
        return new RegistrationDraft(
            id: $this->spikeId('PROBE-ES-UBL-0002'),
            issuerName: 'IntrAgile Test Espana S.L.',
            type: InvoiceType::F1,
            correctionType: null,
            corrected: [],
            substituted: [],
            correctionAmounts: null,
            operationDate: null,
            description: 'Factura PROBE-ES-UBL-0002',
            simplifiedArt7273: false,
            withoutRecipientArt61d: false,
            macrodato: false,
            issuedBy: null,
            thirdParty: null,
            recipients: [new TaxParty('Cliente Prueba S.A.', Nif::parse('A58818501'), null)],
            coupon: false,
            breakdown: [new TaxBreakdownLine(
                TaxType::Iva,
                RegimeKey::Key01,
                OperationQualification::S1,
                null,
                TaxRate::of('21.00'),
                Amount::of('100'),
                null,
                Amount::of('21'),
                null,
                null,
            )],
            totalTax: Amount::of('21'),
            total: Amount::of('121'),
            externalReference: null,
            agreementNumber: null,
            systemAgreementId: null,
        );
    }
}

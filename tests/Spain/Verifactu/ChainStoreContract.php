<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainHead;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainKey;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainStore;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\ChainConflict;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The behaviour every ChainStore implementation must show; each implementation's test extends it.
 */
abstract class ChainStoreContract extends TestCase
{
    use CapturesThrowable;

    private const HASH_1 = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';

    private const SEED_HASH = '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73';

    /** A store whose chains are all empty. */
    abstract protected function store(): ChainStore;

    public function test_append_moves_the_head(): void
    {
        $store = $this->store();
        $key = $this->key();
        $empty = $store->head($key);
        $this->assertNull($empty);

        $first = $store->append($key, fn (?ChainHead $head): BillingRecord => $this->record('12345678/G33', $head?->link() ?? ChainLink::first()));
        $this->assertSame(self::HASH_1, $first->fingerprint());

        $head = $store->head($key);
        $this->assertNotNull($head);
        $this->assertSame(self::HASH_1, $head->fingerprint);
        $this->assertTrue($head->invoiceId->equals($first->invoiceId()));
        $this->assertSame(1, $head->sequence);
        $this->assertFalse($head->seeded);

        $second = $store->append($key, fn (?ChainHead $head): BillingRecord => $this->record('12345679/G34', $head?->link() ?? ChainLink::first()));
        $this->assertSame(self::HASH_1, $second->chainLink()->previousFingerprint());

        $head = $store->head($key);
        $this->assertNotNull($head);
        $this->assertSame($second->fingerprint(), $head->fingerprint);
        $this->assertSame(2, $head->sequence);
    }

    public function test_record_not_linked_to_head_is_refused(): void
    {
        $store = $this->store();
        $key = $this->key();
        $store->append($key, fn (): BillingRecord => $this->record('12345678/G33', ChainLink::first()));
        $before = $store->head($key);

        // A second record that claims to start the chain again forks it.
        $fork = self::capture(fn () => $store->append($key, fn (): BillingRecord => $this->record('12345679/G34', ChainLink::first())));
        $this->assertInstanceOf(ChainConflict::class, $fork);

        // A record linked to the right invoice but a different fingerprint is refused as well.
        $stale = self::capture(fn () => $store->append($key, fn (): BillingRecord => $this->record(
            '12345679/G34',
            ChainLink::after(VerifactuSamples::invoiceId('12345678/G33', '01-01-2024'), str_repeat('A', 64)),
        )));
        $this->assertInstanceOf(ChainConflict::class, $stale);

        // On an empty chain a record linked to a predecessor is refused.
        $other = $this->key(VerifactuEnvironment::Production);
        $orphan = self::capture(fn () => $store->append($other, fn (): BillingRecord => $this->record(
            '12345679/G34',
            ChainLink::after(VerifactuSamples::invoiceId('12345678/G33', '01-01-2024'), self::HASH_1),
        )));
        $this->assertInstanceOf(ChainConflict::class, $orphan);
        $this->assertNull($store->head($other));

        $this->assertEquals($before, $store->head($key));
    }

    public function test_failed_build_stores_nothing(): void
    {
        $store = $this->store();
        $key = $this->key();
        $store->append($key, fn (): BillingRecord => $this->record('12345678/G33', ChainLink::first()));
        $before = $store->head($key);

        $thrown = self::capture(fn () => $store->append($key, static function (): BillingRecord {
            throw new RuntimeException('build failed');
        }));

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('build failed', $thrown->getMessage());
        $this->assertEquals($before, $store->head($key));

        // The chain still continues from the untouched head.
        $next = $store->append($key, fn (?ChainHead $head): BillingRecord => $this->record('12345679/G34', $head?->link() ?? ChainLink::first()));
        $this->assertSame(self::HASH_1, $next->chainLink()->previousFingerprint());
    }

    public function test_seed_on_started_chain_is_refused(): void
    {
        $store = $this->store();
        $key = $this->key();
        $last = VerifactuSamples::invoiceId('PROBE-ES-0001', '23-11-2026');

        $seeded = $store->seed($key, $last, self::SEED_HASH);
        $this->assertTrue($seeded->seeded);
        $this->assertSame(0, $seeded->sequence);
        $this->assertNull($seeded->generatedAt);
        $this->assertEquals($seeded, $store->head($key));

        $again = self::capture(fn () => $store->seed($key, $last, self::HASH_1));
        $this->assertInstanceOf(ChainConflict::class, $again);
        $this->assertEquals($seeded, $store->head($key));

        $started = $this->key(VerifactuEnvironment::Production);
        $store->append($started, fn (): BillingRecord => $this->record('12345678/G33', ChainLink::first()));
        $before = $store->head($started);
        $this->assertInstanceOf(ChainConflict::class, self::capture(fn () => $store->seed($started, $last, self::SEED_HASH)));
        $this->assertEquals($before, $store->head($started));
    }

    public function test_seeded_chain_is_continued(): void
    {
        $store = $this->store();
        $key = $this->key();
        $last = VerifactuSamples::invoiceId('PROBE-ES-0001', '23-11-2026');
        $store->seed($key, $last, self::SEED_HASH);

        $record = $store->append($key, fn (?ChainHead $head): BillingRecord => $this->record('12345678/G33', $head?->link() ?? ChainLink::first()));

        $previous = $record->chainLink()->previous();
        $this->assertNotNull($previous);
        $this->assertTrue($previous->equals($last));
        $this->assertSame(self::SEED_HASH, $record->chainLink()->previousFingerprint());

        $head = $store->head($key);
        $this->assertNotNull($head);
        $this->assertSame(1, $head->sequence);
        $this->assertFalse($head->seeded);
        $this->assertTrue($head->invoiceId->equals($record->invoiceId()));
        $this->assertSame($record->fingerprint(), $head->fingerprint);
        $this->assertNotNull($head->generatedAt);
    }

    public function test_chains_are_isolated_per_key(): void
    {
        $store = $this->store();
        $test = $this->key(VerifactuEnvironment::Test);
        $production = $this->key(VerifactuEnvironment::Production);
        $this->assertSame('383', $test->installationNumber);

        $first = $store->append($test, fn (?ChainHead $head): BillingRecord => $this->record('12345678/G33', $head?->link() ?? ChainLink::first()));
        $other = $store->append($production, fn (?ChainHead $head): BillingRecord => $this->record('12345678/G33', $head?->link() ?? ChainLink::first()));

        $this->assertTrue($first->chainLink()->isFirst());
        $this->assertTrue($other->chainLink()->isFirst());
        $this->assertSame(1, $store->head($test)?->sequence);
        $this->assertSame(1, $store->head($production)?->sequence);
    }

    public function test_key_string(): void
    {
        $issuer = Nif::parse('B64829179');

        $this->assertSame('B64829179|383|test', (new ChainKey($issuer, '383', VerifactuEnvironment::Test))->toString());
        $this->assertSame('B64829179|a%7Cb|test', (new ChainKey($issuer, 'a|b', VerifactuEnvironment::Test))->toString());
        $this->assertSame(
            'B64829179|383|production',
            ChainKey::for($issuer, VerifactuSamples::system(), VerifactuEnvironment::Production)->toString(),
        );
    }

    private function key(VerifactuEnvironment $environment = VerifactuEnvironment::Test): ChainKey
    {
        return ChainKey::for(Nif::parse(VerifactuSamples::ISSUER_NIF), $this->system(), $environment);
    }

    private function system(): ComputerSystem
    {
        return VerifactuSamples::system();
    }

    private function record(string $number, ChainLink $link): BillingRecord
    {
        $at = RecordTimestamp::of(new DateTimeImmutable('2024-01-01T18:20:30Z'), new DateTimeZone('Europe/Madrid'));

        return RegistrationRecord::create(VerifactuSamples::vectorDraft($number, '01-01-2024'), $link, $this->system(), $at);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainHead;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainKey;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainStore;
use IntrAgile\EInvoicing\Spain\Verifactu\InMemoryChainStore;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;

final class InMemoryChainStoreTest extends ChainStoreContract
{
    protected function store(): ChainStore
    {
        return new InMemoryChainStore;
    }

    public function test_records_are_kept_in_append_order(): void
    {
        $store = new InMemoryChainStore;
        $system = VerifactuSamples::system();
        $key = ChainKey::for(Nif::parse(VerifactuSamples::ISSUER_NIF), $system, VerifactuEnvironment::Test);
        $at = RecordTimestamp::of(new DateTimeImmutable('2024-01-01T18:20:30Z'), new DateTimeZone('Europe/Madrid'));
        $build = static fn (string $number) => static fn (?ChainHead $head) => RegistrationRecord::create(
            VerifactuSamples::vectorDraft($number, '01-01-2024'),
            $head?->link() ?? ChainLink::first(),
            $system,
            $at,
        );

        $this->assertSame([], $store->records($key));
        $first = $store->append($key, $build('1/A'));
        $second = $store->append($key, $build('2/A'));

        $this->assertSame([$first, $second], $store->records($key));
    }
}

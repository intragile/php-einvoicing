<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Crypto;

use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PublicKeyIdTest extends TestCase
{
    /**
     * Computed independently with:
     * openssl x509 -in tests/fixtures/ksef/test-certificate.pem -pubkey -noout
     *   | openssl pkey -pubin -outform DER | openssl dgst -sha256 -binary | base64
     */
    private const FIXTURE_ID = 'rLeCsTQl5CTRdcCFiCJI8DNnmIW5D1Trj1dkb49OHeA=';

    public function test_public_key_id_matches_spike_algorithm(): void
    {
        $pem = (string) file_get_contents(__DIR__.'/../../../fixtures/ksef/test-certificate.pem');

        self::assertSame(self::FIXTURE_ID, PublicKeyId::of($pem));
    }

    public function test_invalid_certificate_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicKeyId::of("-----BEGIN CERTIFICATE-----\nbm90IGEgY2VydA==\n-----END CERTIFICATE-----");
    }
}

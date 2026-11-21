<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use PHPUnit\Framework\TestCase;

final class KsefEnvironmentTest extends TestCase
{
    public function test_api_base_urls(): void
    {
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2', KsefEnvironment::Test->apiBaseUrl());
        self::assertSame('https://api-demo.ksef.mf.gov.pl/v2', KsefEnvironment::Demo->apiBaseUrl());
        self::assertSame('https://api.ksef.mf.gov.pl/v2', KsefEnvironment::Production->apiBaseUrl());
    }

    public function test_qr_base_urls_are_unchanged(): void
    {
        self::assertSame('https://qr-test.ksef.mf.gov.pl', KsefEnvironment::Test->qrBaseUrl());
        self::assertSame('https://qr-demo.ksef.mf.gov.pl', KsefEnvironment::Demo->qrBaseUrl());
        self::assertSame('https://qr.ksef.mf.gov.pl', KsefEnvironment::Production->qrBaseUrl());
    }
}

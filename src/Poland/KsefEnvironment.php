<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * The KSeF environments: TE (test, release candidate), DEMO (pre-production) and PROD.
 */
enum KsefEnvironment: string
{
    case Test = 'test';
    case Demo = 'demo';
    case Production = 'production';

    /**
     * Base URL of the KSeF API 2.0 of the environment, without a trailing slash.
     */
    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://api-test.ksef.mf.gov.pl/v2',
            self::Demo => 'https://api-demo.ksef.mf.gov.pl/v2',
            self::Production => 'https://api.ksef.mf.gov.pl/v2',
        };
    }

    /**
     * Base URL of the verification links (QR codes) of the environment, without a trailing slash.
     */
    public function qrBaseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://qr-test.ksef.mf.gov.pl',
            self::Demo => 'https://qr-demo.ksef.mf.gov.pl',
            self::Production => 'https://qr.ksef.mf.gov.pl',
        };
    }

    /**
     * Base URL of Latarnia, the public KSeF availability service, for the environment, or null
     * for DEMO, for which the Ministry of Finance publishes no Latarnia.
     */
    public function latarniaBaseUrl(): ?string
    {
        return match ($this) {
            self::Test => 'https://api-latarnia-test.ksef.mf.gov.pl/',
            self::Demo => null,
            self::Production => 'https://api-latarnia.ksef.mf.gov.pl/',
        };
    }
}

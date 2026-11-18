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
}

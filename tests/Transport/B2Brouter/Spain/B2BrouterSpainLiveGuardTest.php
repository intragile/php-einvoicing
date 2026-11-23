<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Tests\Support\B2BrouterSpainLive;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use PHPUnit\Framework\TestCase;

/**
 * The key, host and environment guard of the opt-in Spanish B2Brouter live test, checked offline
 * so that it runs with the default suite.
 */
final class B2BrouterSpainLiveGuardTest extends TestCase
{
    private const ACCOUNT_ID = 78234;

    private const VARIABLES = [B2BrouterSpainLive::KEY_ENV, B2BrouterSpainLive::ACCOUNT_ENV, 'B2BROUTER_BASE_URL'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
    }

    public function test_only_a_sandbox_key_on_the_sandbox_host_is_accepted(): void
    {
        self::assertNull(B2BrouterSpainLive::keyRefusal(new B2BrouterConfig('test_FAKEKEY000000', self::ACCOUNT_ID, B2BrouterConfig::DEFAULT_BASE_URL)));

        $cases = [
            ['stag_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            ['stag_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['test_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            ['test_FAKEKEY000000', 'https://api.example.test'],
            ['prod_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['FAKEKEY0000000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['TEST_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
        ];
        foreach ($cases as [$key, $baseUrl]) {
            $refusal = B2BrouterSpainLive::keyRefusal(new B2BrouterConfig($key, self::ACCOUNT_ID, $baseUrl));

            self::assertNotNull($refusal, $key.' at '.$baseUrl);
            self::assertStringContainsString('test_', $refusal);
            self::assertStringContainsString('https://api.b2brouter.net', $refusal);
            self::assertStringNotContainsString($key, $refusal);
            self::assertStringNotContainsString((string) self::ACCOUNT_ID, $refusal);
        }
    }

    public function test_base_url_variable_is_ignored(): void
    {
        putenv(B2BrouterSpainLive::KEY_ENV.'=test_FAKEKEY000000');
        putenv(B2BrouterSpainLive::ACCOUNT_ENV.'='.self::ACCOUNT_ID);
        putenv('B2BROUTER_BASE_URL=https://api-staging.b2brouter.net');

        $config = B2BrouterSpainLive::configFromEnvironment();

        self::assertNotNull($config);
        self::assertSame(B2BrouterConfig::DEFAULT_BASE_URL, $config->baseUrl);
        self::assertSame(self::ACCOUNT_ID, $config->accountId);
    }

    public function test_missing_variables_give_null(): void
    {
        putenv(B2BrouterSpainLive::KEY_ENV);
        putenv(B2BrouterSpainLive::ACCOUNT_ENV);
        self::assertNull(B2BrouterSpainLive::configFromEnvironment());

        putenv(B2BrouterSpainLive::KEY_ENV.'=test_FAKEKEY000000');
        self::assertNull(B2BrouterSpainLive::configFromEnvironment());

        putenv(B2BrouterSpainLive::KEY_ENV);
        putenv(B2BrouterSpainLive::ACCOUNT_ENV.'='.self::ACCOUNT_ID);
        self::assertNull(B2BrouterSpainLive::configFromEnvironment());
    }

    public function test_redactor_hides_the_key_and_the_account_id(): void
    {
        $config = new B2BrouterConfig('test_FAKEKEY000000', self::ACCOUNT_ID);

        $redacted = B2BrouterSpainLive::redactor($config)->redact('key test_FAKEKEY000000 account '.self::ACCOUNT_ID);

        self::assertStringNotContainsString('test_FAKEKEY000000', $redacted);
        self::assertStringNotContainsString((string) self::ACCOUNT_ID, $redacted);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use PHPUnit\Framework\TestCase;

/**
 * The key and environment guard of the opt-in French B2Brouter live test, checked offline so that
 * it runs with the default suite.
 */
final class B2BrouterFranceLiveGuardTest extends TestCase
{
    private const ACCOUNT_ID = 78234;

    private const FR_ACCOUNT_ID = '91827';

    public function test_production_key_is_refused(): void
    {
        $cases = [
            ['prod_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['FAKEKEY0000000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['TEST_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['STAG_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            ['prod_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            // A sandbox or staging key is refused on any host other than its own.
            ['test_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            ['stag_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['test_FAKEKEY000000', 'https://api.example.test'],
            ['stag_FAKEKEY000000', 'https://api.example.test'],
        ];
        foreach ($cases as [$key, $baseUrl]) {
            $refusal = B2BrouterFranceLiveTest::keyRefusal(new B2BrouterConfig($key, self::ACCOUNT_ID, $baseUrl));

            self::assertNotNull($refusal, $key.' at '.$baseUrl);
            self::assertStringContainsString('test_', $refusal);
            self::assertStringContainsString('stag_', $refusal);
            self::assertStringNotContainsString($key, $refusal);
            self::assertStringNotContainsString((string) self::ACCOUNT_ID, $refusal);
        }
    }

    public function test_french_cases_need_an_accepted_environment_and_account(): void
    {
        $staging = new B2BrouterConfig('stag_FAKEKEY000000', self::ACCOUNT_ID, B2BrouterConfig::STAGING_BASE_URL);
        $sandbox = new B2BrouterConfig('test_FAKEKEY000000', self::ACCOUNT_ID);
        self::assertSame('needs a B2Brouter French sandbox or staging account', B2BrouterFranceLiveTest::FRENCH_ACCOUNT_SKIP);

        foreach ([$staging, $sandbox] as $config) {
            self::assertNull(B2BrouterFranceLiveTest::keyRefusal($config));
            self::assertNull(B2BrouterFranceLiveTest::frenchAccountSkip($config, self::FR_ACCOUNT_ID));
            // Without the French account the send and e-reporting cases skip.
            self::assertSame(B2BrouterFranceLiveTest::FRENCH_ACCOUNT_SKIP, B2BrouterFranceLiveTest::frenchAccountSkip($config, ''));
        }
    }

    public function test_french_cases_skip_on_a_refused_key_or_host(): void
    {
        $cases = [
            ['stag_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
            ['stag_FAKEKEY000000', 'https://api.example.test'],
            ['test_FAKEKEY000000', B2BrouterConfig::STAGING_BASE_URL],
            ['test_FAKEKEY000000', 'https://api.example.test'],
            ['prod_FAKEKEY000000', B2BrouterConfig::DEFAULT_BASE_URL],
        ];
        foreach ($cases as [$key, $baseUrl]) {
            $config = new B2BrouterConfig($key, self::ACCOUNT_ID, $baseUrl);

            self::assertSame(B2BrouterFranceLiveTest::FRENCH_ACCOUNT_SKIP, B2BrouterFranceLiveTest::frenchAccountSkip($config, self::FR_ACCOUNT_ID), $key.' at '.$baseUrl);
        }
    }
}

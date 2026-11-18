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

    public function test_staging_cases_need_staging_host_and_key(): void
    {
        $staging = new B2BrouterConfig('stag_FAKEKEY000000', self::ACCOUNT_ID, B2BrouterConfig::STAGING_BASE_URL);
        self::assertNull(B2BrouterFranceLiveTest::keyRefusal($staging));
        self::assertNull(B2BrouterFranceLiveTest::stagingSkip($staging, self::FR_ACCOUNT_ID));

        // Without the French staging account the staging cases skip.
        self::assertSame(B2BrouterFranceLiveTest::STAGING_SKIP, B2BrouterFranceLiveTest::stagingSkip($staging, ''));
        self::assertSame('needs a B2Brouter French staging account', B2BrouterFranceLiveTest::STAGING_SKIP);

        // A staging key on any other host never reaches the staging cases.
        foreach ([B2BrouterConfig::DEFAULT_BASE_URL, 'https://api.example.test'] as $baseUrl) {
            $elsewhere = new B2BrouterConfig('stag_FAKEKEY000000', self::ACCOUNT_ID, $baseUrl);
            self::assertSame(B2BrouterFranceLiveTest::STAGING_SKIP, B2BrouterFranceLiveTest::stagingSkip($elsewhere, self::FR_ACCOUNT_ID), $baseUrl);
        }
    }

    public function test_sandbox_key_allows_lookup_only(): void
    {
        $sandbox = new B2BrouterConfig('test_FAKEKEY000000', self::ACCOUNT_ID);

        self::assertNull(B2BrouterFranceLiveTest::keyRefusal($sandbox));
        self::assertSame(B2BrouterFranceLiveTest::STAGING_SKIP, B2BrouterFranceLiveTest::stagingSkip($sandbox, self::FR_ACCOUNT_ID));
        self::assertSame(B2BrouterFranceLiveTest::STAGING_SKIP, B2BrouterFranceLiveTest::stagingSkip($sandbox, ''));
    }
}

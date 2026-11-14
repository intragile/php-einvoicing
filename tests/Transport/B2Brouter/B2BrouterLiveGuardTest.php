<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use PHPUnit\Framework\TestCase;

/**
 * The production guard of the opt-in B2Brouter live test, checked offline so that it runs with
 * the default suite.
 */
final class B2BrouterLiveGuardTest extends TestCase
{
    public function test_live_guard_refuses_production_key(): void
    {
        foreach (['prod_FAKEKEY000000', 'FAKEKEY0000000000', 'stag_FAKEKEY000000', 'TEST_FAKEKEY000000'] as $key) {
            $refusal = B2BrouterLiveTest::productionKeyRefusal(new B2BrouterConfig($key, 78234));

            self::assertNotNull($refusal, $key);
            self::assertStringContainsString('test_', $refusal);
            self::assertStringNotContainsString($key, $refusal);
            self::assertStringNotContainsString('78234', $refusal);
        }
    }

    public function test_live_guard_accepts_sandbox_key(): void
    {
        self::assertNull(B2BrouterLiveTest::productionKeyRefusal(new B2BrouterConfig('test_FAKEKEY000000', 78234)));
    }
}

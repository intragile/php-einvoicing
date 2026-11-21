<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KsefConfigTest extends TestCase
{
    use KsefFixtures;

    public function test_defaults_and_base_url_come_from_the_environment(): void
    {
        $config = new KsefConfig(KsefEnvironment::Demo, ContextIdentifier::nip('5265877635'), new TokenCredentials(self::KSEF_TOKEN));

        self::assertSame('https://api-demo.ksef.mf.gov.pl/v2', $config->baseUrl);
        self::assertSame(KsefEnvironment::Demo, $config->environment);
        self::assertSame(30, $config->authPollAttempts);
        self::assertSame(1000, $config->authPollWaitMs);
        self::assertSame(30, $config->statusPollAttempts);
        self::assertSame(2000, $config->statusPollWaitMs);
        self::assertSame(60, $config->exportPollAttempts);
        self::assertSame(2000, $config->exportPollWaitMs);
        self::assertSame(100_000_000, $config->batchPartBytes);
    }

    public function test_sleep_uses_the_injected_closure(): void
    {
        $config = $this->config();
        $config->sleep(1500);
        $config->sleep(0);

        self::assertSame([1500], $this->sleeps);
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function invalidBounds(): iterable
    {
        yield 'zero auth attempts' => [['authPollAttempts' => 0]];
        yield 'negative auth wait' => [['authPollWaitMs' => -1]];
        yield 'zero status attempts' => [['statusPollAttempts' => 0]];
        yield 'zero status wait' => [['statusPollWaitMs' => 0]];
        yield 'zero export attempts' => [['exportPollAttempts' => 0]];
        yield 'zero export wait' => [['exportPollWaitMs' => 0]];
        yield 'zero batch part bytes' => [['batchPartBytes' => 0]];
    }

    /**
     * @param  array<string, int>  $bounds
     */
    #[DataProvider('invalidBounds')]
    public function test_config_rejects_zero_attempts(array $bounds): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KsefConfig(KsefEnvironment::Test, ContextIdentifier::nip('5265877635'), new TokenCredentials(self::KSEF_TOKEN), ...$bounds);
    }

    public function test_secrets_hold_the_ksef_token_and_debug_info_hides_it(): void
    {
        $config = $this->config();

        self::assertSame([self::KSEF_TOKEN], $config->secrets());

        ob_start();
        var_dump($config);
        print_r($config);
        $dump = (string) ob_get_clean();
        self::assertStringNotContainsString(self::KSEF_TOKEN, $dump);
        self::assertStringContainsString(Redactor::REDACTED, $dump);
    }
}

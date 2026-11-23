<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Helpers of the opt-in Spanish B2Brouter live tests: the environment, the sandbox-only guard,
 * the HTTP client and the redactor.
 *
 * Only the B2Brouter sandbox is reached: a "test_" key on https://api.b2brouter.net. No base-URL
 * variable is read, so staging and production cannot be selected from the environment, and any
 * other key or host is refused before the first request.
 */
final class B2BrouterSpainLive
{
    public const TAG = 'b2brouter-spain-live';

    public const KEY_ENV = 'B2BROUTER_ES_API_KEY';

    public const ACCOUNT_ENV = 'B2BROUTER_ES_ACCOUNT_ID';

    /** The timeout of every request. */
    public const HTTP_TIMEOUT_SECONDS = 20.0;

    private function __construct() {}

    /**
     * Why the configuration must not be used, or null when it is a sandbox key on the sandbox
     * host. The message names neither the key nor the account.
     */
    public static function keyRefusal(B2BrouterConfig $config): ?string
    {
        if ($config->isSandboxKey() && $config->baseUrl === B2BrouterConfig::DEFAULT_BASE_URL) {
            return null;
        }

        return 'The Spanish live test runs only with a "test_" key on https://api.b2brouter.net (sandbox); it refuses any other key or host, staging and production included.';
    }

    /**
     * The configuration from the key and account variables, always on the sandbox host, or null
     * when either variable is missing.
     */
    public static function configFromEnvironment(): ?B2BrouterConfig
    {
        $key = (string) getenv(self::KEY_ENV);
        $accountId = (string) getenv(self::ACCOUNT_ENV);
        if ($key === '' || $accountId === '') {
            return null;
        }

        return new B2BrouterConfig($key, $accountId, B2BrouterConfig::DEFAULT_BASE_URL);
    }

    /**
     * A client with a fixed timeout that keeps the key and the account id out of its messages.
     */
    public static function http(B2BrouterConfig $config): HttpClient
    {
        $factory = new Psr17Factory;

        return (new HttpClient(new StreamHttpClient(self::HTTP_TIMEOUT_SECONDS), $factory, $factory))->withSecrets(self::secrets($config));
    }

    /**
     * The redactor for the key and the account id.
     */
    public static function redactor(B2BrouterConfig $config): Redactor
    {
        return new Redactor(self::secrets($config));
    }

    /**
     * @return list<string>
     */
    private static function secrets(B2BrouterConfig $config): array
    {
        return [...$config->secrets(), (string) $config->accountId];
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Throwable;

/**
 * Shared helpers of the KSeF API client tests.
 */
trait KsefFixtures
{
    use CapturesThrowable;

    private const KSEF_TOKEN = '20251121-EC-0A1B2C3D4E-5F6A7B8C9D-AB|nip-5265877635|feedfacecafebeef0123456789abcdef';

    private const BEARER = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJhY2Nlc3MifQ.c2lnbmF0dXJlLW9mLXRoZS1hY2Nlc3MtdG9rZW4';

    /** @var list<int> */
    private array $sleeps = [];

    private function config(?KsefEnvironment $environment = null): KsefConfig
    {
        return new KsefConfig(
            $environment ?? KsefEnvironment::Test,
            ContextIdentifier::nip('5265877635'),
            new TokenCredentials(self::KSEF_TOKEN),
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    /**
     * The client over a plain HTTP client built without secrets: the client must add the KSeF
     * token and the bearer to the redacted values itself.
     */
    private function api(RecordingHttpClient $recorder): KsefApi
    {
        $factory = new Psr17Factory;

        return new KsefApi($this->config(), new HttpClient($recorder, $factory, $factory));
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private static function fixture(int $status, string $name, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, self::read($name));
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, string|list<string>>  $headers
     */
    private static function json(int $status, array $data, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    private static function decoded(string $name): array
    {
        /** @var array<mixed> */
        return json_decode(self::read($name), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function read(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../../fixtures/ksef/responses/'.$name);
        self::assertIsString($contents);

        return $contents;
    }

    private static function assertNoSecret(Throwable $exception): void
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $text = $current->getMessage();
            if ($current instanceof UnexpectedResponse) {
                $text .= $current->bodyExcerpt;
            }
            if ($current instanceof DocumentRejected) {
                $text .= json_encode($current->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            foreach ([self::BEARER, self::KSEF_TOKEN, base64_encode(self::KSEF_TOKEN), rawurlencode(self::KSEF_TOKEN)] as $secret) {
                self::assertStringNotContainsString($secret, $text, $current::class);
            }
        }
    }
}

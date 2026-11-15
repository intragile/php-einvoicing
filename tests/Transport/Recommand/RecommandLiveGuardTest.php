<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The production guard of the opt-in Recommand live test, checked offline so that it runs with
 * the default suite.
 */
final class RecommandLiveGuardTest extends TestCase
{
    private const KEY_ID = 'FAKEKEYID0000000';

    private const SECRET = 'FAKESECRET000000';

    private const COMPANY_ID = 'c_FAKECOMPANY000';

    public function test_refuses_without_playground_before_any_post(): void
    {
        $client = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], '{"success":true,"playground":null}'));

        $refusal = RecommandLiveTest::playgroundRefusal(self::http($client), self::config());

        self::assertSame('The API key does not belong to a playground team; the live test refuses to send from a production team.', $refusal);
        self::assertSame(0, self::countMethod($client, 'POST'));
        self::assertCount(1, $client->requests());
        self::assertSame('GET', $client->requests()[0]->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/playgrounds/current', (string) $client->requests()[0]->getUri());
    }

    public function test_refuses_when_the_playground_cannot_be_confirmed(): void
    {
        $client = new RecordingHttpClient(new Response(401, ['Content-Type' => 'application/json'], '{"success":false,"errors":{"auth":["Invalid key '.self::KEY_ID.'"]}}'));

        $refusal = RecommandLiveTest::playgroundRefusal(self::http($client), self::config());

        self::assertNotNull($refusal);
        self::assertStringContainsString('HTTP 401', $refusal);
        self::assertStringNotContainsString(self::KEY_ID, $refusal);
        self::assertSame(0, self::countMethod($client, 'POST'));
    }

    public function test_accepts_a_playground_team(): void
    {
        $client = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], '{"success":true,"playground":{"id":"pg_FAKE","isPlayground":true,"useTestNetwork":true}}'));

        self::assertNull(RecommandLiveTest::playgroundRefusal(self::http($client), self::config()));
        self::assertSame(0, self::countMethod($client, 'POST'));
    }

    public function test_test_network_refuses_a_playground_off_the_test_network_before_any_post(): void
    {
        $client = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], '{"success":true,"playground":{"id":"pg_FAKE","isPlayground":true,"useTestNetwork":false}}'));

        $refusal = RecommandLiveTest::networkRefusal(self::http($client), self::config());

        self::assertSame('The playground does not use the Peppol test network; create one with useTestNetwork: true.', $refusal);
        self::assertSame(0, self::countMethod($client, 'POST'));
        self::assertCount(1, $client->requests());
    }

    public function test_test_network_refuses_without_playground_or_success(): void
    {
        foreach (['{"success":true,"playground":null}', '{"success":false,"playground":{"useTestNetwork":true}}'] as $body) {
            $client = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], $body));

            $refusal = RecommandLiveTest::networkRefusal(self::http($client), self::config());

            self::assertSame('The API key does not belong to a playground team; the live test refuses to send from a production team.', $refusal, $body);
            self::assertSame(0, self::countMethod($client, 'POST'));
        }
    }

    public function test_test_network_accepts_a_test_network_playground(): void
    {
        $client = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], '{"success":true,"playground":{"id":"pg_FAKE","isPlayground":true,"useTestNetwork":true}}'));

        self::assertNull(RecommandLiveTest::networkRefusal(self::http($client), self::config()));
        self::assertCount(1, $client->requests());
    }

    private static function config(): RecommandConfig
    {
        return new RecommandConfig(self::KEY_ID, self::SECRET, self::COMPANY_ID);
    }

    private static function http(RecordingHttpClient $client): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($client, $factory, $factory);
    }

    private static function countMethod(RecordingHttpClient $client, string $method): int
    {
        return count(array_filter($client->requests(), static fn (RequestInterface $request): bool => $request->getMethod() === $method));
    }
}

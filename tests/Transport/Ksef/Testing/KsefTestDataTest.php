<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Testing;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\KsefTestData;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KsefTestDataTest extends TestCase
{
    use AuthFixtures;

    /** The categories of ApiRateLimitsOverride, all required by POST /testdata/rate-limits. */
    private const CATEGORIES = [
        'onlineSession', 'batchSession', 'invoiceSend', 'invoiceStatus', 'sessionList', 'sessionInvoiceList',
        'sessionMisc', 'invoiceMetadata', 'invoiceExport', 'invoiceExportStatus', 'invoiceDownload',
        'collectiveIdentifier', 'other',
    ];

    /**
     * @return iterable<string, array{KsefEnvironment}>
     */
    public static function nonTestEnvironments(): iterable
    {
        yield 'demo' => [KsefEnvironment::Demo];
        yield 'production' => [KsefEnvironment::Production];
    }

    #[DataProvider('nonTestEnvironments')]
    public function test_non_te_environment_is_refused(KsefEnvironment $environment): void
    {
        $recorder = new RecordingHttpClient;
        $factory = new Psr17Factory;
        $config = new KsefConfig($environment, ContextIdentifier::nip('5265877635'), new TokenCredentials(self::KSEF_TOKEN));

        try {
            new KsefTestData($config, new HttpClient($recorder, $factory, $factory));
            self::fail('A non-TE configuration must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('TEST', $exception->getMessage());
        }
        self::assertSame([], $recorder->requests());
    }

    public function test_rate_limit_body_shape(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::json(200, self::effectiveLimits()), new Response(200)]);
        $testData = $this->client($recorder);

        $testData->setRateLimits(['invoiceMetadata' => ['perSecond' => 1, 'perMinute' => 1, 'perHour' => 1], 'invoiceStatus' => ['perMinute' => 2]]);

        $requests = $recorder->requests();
        self::assertCount(7, $requests);
        self::assertSame('GET', $requests[5]->getMethod());
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/rate-limits', (string) $requests[5]->getUri());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $requests[5]->getHeaderLine('Authorization'));
        self::assertSame('POST', $requests[6]->getMethod());
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/testdata/rate-limits', (string) $requests[6]->getUri());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $requests[6]->getHeaderLine('Authorization'));

        $body = self::body($requests[6]);
        self::assertSame(['rateLimits'], array_keys($body));
        $limits = $body['rateLimits'];
        self::assertIsArray($limits);
        // Exactly the override categories: the effective-only ones (global, anonymous, ...) are left out.
        self::assertSame(self::CATEGORIES, array_keys($limits));
        self::assertSame(['perSecond' => 1, 'perMinute' => 1, 'perHour' => 1], $limits['invoiceMetadata']);
        // A partial override keeps the current values of the other periods.
        self::assertSame(['perSecond' => 10, 'perMinute' => 2, 'perHour' => 1200], $limits['invoiceStatus']);
        self::assertSame(['perSecond' => 10, 'perMinute' => 60, 'perHour' => 1200], $limits['other']);
    }

    public function test_reset_rate_limits_deletes_the_override(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), new Response(200)]);

        $this->client($recorder)->resetRateLimits();

        $request = $recorder->requests()[5];
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/testdata/rate-limits', (string) $request->getUri());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function badLimits(): iterable
    {
        yield 'unknown category' => [['global' => ['perSecond' => 1]], 'global'];
        yield 'unknown period' => [['invoiceSend' => ['perDay' => 1]], 'perDay'];
        yield 'not positive' => [['invoiceSend' => ['perSecond' => 0]], 'positive'];
        yield 'not a map' => [['invoiceSend' => 5], 'invoiceSend'];
        yield 'empty' => [[], 'at least one'];
    }

    /**
     * @param  array<mixed>  $limits
     */
    #[DataProvider('badLimits')]
    public function test_bad_rate_limits_are_refused_before_any_request(array $limits, string $message): void
    {
        $recorder = new RecordingHttpClient;

        try {
            // @phpstan-ignore argument.type (the invalid shapes are the point of the test)
            $this->client($recorder)->setRateLimits($limits);
            self::fail('Invalid rate limits must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        self::assertSame([], $recorder->requests());
    }

    public function test_unauthenticated_calls_send_the_documented_bodies(): void
    {
        $recorder = new RecordingHttpClient(new Response(200), new Response(200), new Response(200));
        $testData = $this->client($recorder);

        $testData->enableAttachments('5265877635');
        $testData->createSubject('5265877635', 'VatGroup', 'Synthetic VAT group');
        $permissions = [
            'contextIdentifier' => ['type' => 'Nip', 'value' => '5265877635'],
            'authorizedIdentifier' => ['type' => 'Nip', 'value' => '7762811692'],
            'permissions' => [['permissionType' => 'InvoiceRead', 'description' => 'Synthetic read access']],
        ];
        $testData->grantPermissions($permissions);

        $requests = $recorder->requests();
        self::assertSame(['/v2/testdata/attachment', '/v2/testdata/subject', '/v2/testdata/permissions'], array_map(static fn ($r): string => $r->getUri()->getPath(), $requests));
        foreach ($requests as $request) {
            self::assertSame('POST', $request->getMethod());
            self::assertSame('', $request->getHeaderLine('Authorization'));
        }
        self::assertSame(['nip' => '5265877635'], self::body($requests[0]));
        self::assertSame(['subjectNip' => '5265877635', 'subjectType' => 'VatGroup', 'description' => 'Synthetic VAT group'], self::body($requests[1]));
        self::assertSame($permissions, self::body($requests[2]));
    }

    public function test_bad_subject_arguments_are_refused(): void
    {
        $testData = $this->client(new RecordingHttpClient);

        foreach ([['123', 'JST', 'Synthetic unit'], ['5265877635', 'Company', 'Synthetic unit'], ['5265877635', 'JST', 'abc']] as [$nip, $type, $description]) {
            try {
                $testData->createSubject($nip, $type, $description);
                self::fail('Invalid subject arguments must be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $testData->enableAttachments('12345');
    }

    private function client(RecordingHttpClient $recorder): KsefTestData
    {
        $factory = new Psr17Factory;

        return new KsefTestData($this->authConfig(), new HttpClient($recorder, $factory, $factory), $this->now());
    }

    /**
     * An EffectiveApiRateLimits answer of GET /rate-limits, with the TE defaults' shape.
     *
     * @return array<string, array{perSecond: int, perMinute: int, perHour: int}>
     */
    private static function effectiveLimits(): array
    {
        $limits = [];
        foreach ([...self::CATEGORIES, 'onlineSessionClose', 'batchSessionClose', 'global', 'anonymous'] as $category) {
            $limits[$category] = ['perSecond' => 10, 'perMinute' => 60, 'perHour' => 1200];
        }

        return $limits;
    }
}

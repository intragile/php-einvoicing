<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Inbound;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\KsefExport;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use ZipArchive;

#[RequiresPhpExtension('zip')]
final class KsefExportTest extends TestCase
{
    use AuthFixtures;

    private const EXPORT_REF = '20251010-EH-1B6C9EB000-4B15D3AEB9-89';

    private const PART_URL = 'https://ksef-api-storage/storage/00/export/part-%d.zip.aes?sv=2025-01-05&se=2025-06-26T10%%3A10%%3A54Z&sr=b&sp=r&sig=8mKZEU8Reuz%%2Fn7wHi4T%%2FY8BzLeD5l8bR2xJsBxIgDEY%%3D';

    private const FROM = '2025-08-28T09:22:13.388+00:00';

    private const HWM = '2025-09-11T12:00:40.266578+00:00';

    /** @var list<RequestInterface> */
    private array $requests = [];

    /** @var list<ResponseInterface|Closure(RequestInterface): ResponseInterface> */
    private array $queue = [];

    /** @var array<int, array{string, string}>|null the plain and encrypted parts of the current round */
    private ?array $pending = null;

    public function test_export_decrypts_parts_and_unzips(): void
    {
        $first = self::number('010080DD2B5E');
        $second = self::number('010080DD2B5F');
        $files = [
            $first.'.xml' => '<Faktura>1</Faktura>',
            $second.'.xml' => '<Faktura>2</Faktura>',
            '_metadata.json' => json_encode(['invoices' => [['ksefNumber' => $first], ['ksefNumber' => $second]]], JSON_THROW_ON_ERROR),
        ];
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            self::exportStatus(100),
            $this->finished($files, 2),
            ...$this->parts(2),
        ];

        $temporary = glob(sys_get_temp_dir().'/ksef-export-*') ?: [];

        $result = $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM)));

        self::assertSame($temporary, glob(sys_get_temp_dir().'/ksef-export-*') ?: [], 'The temporary archive file is removed.');
        self::assertSame([$first => '<Faktura>1</Faktura>', $second => '<Faktura>2</Faktura>'], $result->invoices);
        self::assertSame(['invoices' => [['ksefNumber' => $first], ['ksefNumber' => $second]]], $result->metadata);
        self::assertEquals(new DateTimeImmutable(self::HWM), $result->hwm);
        self::assertSame(1, $result->rounds);
        self::assertFalse($result->isTruncated);
        self::assertSame([2000], $this->sleeps);

        $start = $this->requestsTo('POST', '/v2/invoices/exports');
        self::assertCount(1, $start);
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $start[0]->getHeaderLine('Authorization'));
        $body = self::body($start[0]);
        self::assertSame(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))->json(), $body['filters']);
        self::assertIsArray($body['encryption']);
        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), $body['encryption']['publicKeyId']);
        self::assertSame('/v2/invoices/exports/'.self::EXPORT_REF, $this->requests[6]->getUri()->getPath());
        foreach ($this->partRequests() as $download) {
            self::assertSame('GET', $download->getMethod());
            self::assertFalse($download->hasHeader('Authorization'));
        }
        self::assertCount(2, $this->partRequests());
    }

    public function test_export_part_hash_mismatch_is_unexpected_response(): void
    {
        foreach (['encryptedPartHash', 'partHash'] as $field) {
            $this->requests = [];
            $this->queue = [
                ...self::opening(),
                self::json(201, ['referenceNumber' => self::EXPORT_REF]),
                $this->finished([self::number('010080DD2B5E').'.xml' => '<Faktura/>'], 1, corrupt: $field),
                ...$this->parts(1),
            ];

            $exception = self::capture(fn () => $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

            self::assertInstanceOf(UnexpectedResponse::class, $exception, $field);
            self::assertStringContainsString($field, $exception->getMessage());
            self::assertStringContainsString(self::EXPORT_REF, $exception->getMessage());
            self::assertStringNotContainsString('sig=', $exception->getMessage());
        }
    }

    public function test_part_over_the_response_cap_is_refused_before_any_download(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            // KSeF parts reach 50 MB; the default HttpClient reads 20,000,000 bytes at most.
            $this->finished([self::number('010080DD2B5E').'.xml' => '<Faktura/>'], 1, declaredSize: 22_425_072),
        ];

        $exception = self::capture(fn () => $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

        self::assertInstanceOf(LogicException::class, $exception);
        self::assertStringContainsString('22425072', $exception->getMessage());
        self::assertStringContainsString('20000000', $exception->getMessage());
        self::assertStringContainsString('maxResponseBytes', $exception->getMessage());
        self::assertStringContainsString('52428816', $exception->getMessage());
        self::assertStringNotContainsString('sig=', $exception->getMessage());
        self::assertSame([], $this->partRequests());
    }

    public function test_part_within_a_raised_response_cap_is_downloaded(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            $this->finished([self::number('010080DD2B5E').'.xml' => '<Faktura/>'], 1),
            ...$this->parts(1),
        ];

        $result = $this->export(maxResponseBytes: KsefExport::MAX_PART_BYTES)->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM)));

        self::assertSame([self::number('010080DD2B5E') => '<Faktura/>'], $result->invoices);
    }

    public function test_unusable_download_url_is_reported_without_chaining_the_url(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            // https, but with user info, which the client refuses before any request is built.
            $this->finished([self::number('010080DD2B5E').'.xml' => '<Faktura/>'], 1, url: str_replace('https://', 'https://user:pass@', self::PART_URL)),
        ];

        $exception = self::capture(fn () => $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('unusable download URL for part 1', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertStringNotContainsString('sig=', $exception->getMessage());
        self::assertSame([], $this->partRequests());
    }

    public function test_export_continues_when_truncated_at_most_10_rounds(): void
    {
        $this->queue = self::opening();
        $expected = [];
        for ($round = 1; $round <= 10; $round++) {
            $number = self::number(sprintf('0100800000%02d', $round));
            $expected[] = $number;
            $last = sprintf('2025-09-%02dT11:40:40.266578+00:00', $round);
            array_push(
                $this->queue,
                self::json(201, ['referenceNumber' => self::EXPORT_REF]),
                $this->finished([$number.'.xml' => '<Faktura>'.$round.'</Faktura>'], 1, truncated: $last),
                ...$this->parts(1),
            );
        }

        $result = $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM)));

        self::assertSame(10, $result->rounds);
        self::assertTrue($result->isTruncated);
        self::assertSame($expected, array_keys($result->invoices));
        self::assertEquals(new DateTimeImmutable('2025-09-10T11:40:40.266578+00:00'), $result->hwm);
        $starts = $this->requestsTo('POST', '/v2/invoices/exports');
        self::assertCount(10, $starts);
        $froms = array_map(static function (RequestInterface $request): mixed {
            $filters = self::body($request)['filters'];
            self::assertIsArray($filters);
            self::assertIsArray($filters['dateRange']);

            return $filters['dateRange']['from'];
        }, $starts);
        self::assertSame('2025-08-28T09:22:13.388000+00:00', $froms[0]);
        self::assertSame('2025-09-01T11:40:40.266578+00:00', $froms[1]);
        self::assertSame('2025-09-09T11:40:40.266578+00:00', $froms[9]);
        // Every round uses a fresh symmetric key.
        $keys = array_map(static fn (RequestInterface $request): mixed => self::body($request)['encryption'], $starts);
        self::assertCount(10, array_unique(array_map(static fn (mixed $e): string => json_encode($e, JSON_THROW_ON_ERROR), $keys)));
    }

    public function test_export_stops_when_continuation_is_not_truncated(): void
    {
        $first = self::number('010080DD2B5E');
        $second = self::number('010080DD2B5F');
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            $this->finished([$first.'.xml' => '<a/>', '_metadata.json' => '{"invoices":[{"ksefNumber":"'.$first.'"}]}'], 1, truncated: '2025-09-01T11:40:40.266578+00:00'),
            ...$this->parts(1),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            $this->finished([$first.'.xml' => '<a/>', $second.'.xml' => '<b/>', '_metadata.json' => '{"invoices":[{"ksefNumber":"'.$first.'"},{"ksefNumber":"'.$second.'"}]}'], 1),
            ...$this->parts(1),
        ];

        $result = $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM)));

        self::assertSame(2, $result->rounds);
        self::assertFalse($result->isTruncated);
        self::assertSame([$first => '<a/>', $second => '<b/>'], $result->invoices);
        self::assertSame(['invoices' => [['ksefNumber' => $first], ['ksefNumber' => $second]]], $result->metadata);
        self::assertEquals(new DateTimeImmutable(self::HWM), $result->hwm);
    }

    public function test_truncated_export_without_progress_stops(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            $this->finished([self::number('010080DD2B5E').'.xml' => '<a/>'], 1, truncated: self::FROM),
            ...$this->parts(1),
        ];

        $exception = self::capture(fn () => $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString(self::EXPORT_REF, $exception->getMessage());
        self::assertStringContainsString('cannot continue', $exception->getMessage());
        self::assertCount(1, $this->requestsTo('POST', '/v2/invoices/exports'));
        self::assertSame([], $this->queue);
    }

    public function test_issue_date_export_stuck_on_one_day_stops_after_the_repeat(): void
    {
        $this->queue = self::opening();
        foreach ([1, 2] as $round) {
            array_push(
                $this->queue,
                self::json(201, ['referenceNumber' => self::EXPORT_REF]),
                $this->finished([self::number(sprintf('0100800000%02d', $round)).'.xml' => '<a/>'], 1, truncated: '2025-09-01', lastField: 'lastIssueDate'),
                ...$this->parts(1),
            );
        }

        $exception = self::capture(fn () => $this->export()->run(new InvoiceQueryFilters('Subject2', 'Issue', new DateTimeImmutable('2025-08-28T00:00:00+00:00'))));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('Issue', $exception->getMessage());
        $starts = $this->requestsTo('POST', '/v2/invoices/exports');
        self::assertCount(2, $starts);
        $filters = self::body($starts[1])['filters'];
        self::assertIsArray($filters);
        self::assertIsArray($filters['dateRange']);
        self::assertSame('2025-09-01T00:00:00.000000+00:00', $filters['dateRange']['from']);
    }

    public function test_export_poll_is_bounded(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            self::exportStatus(100),
            self::exportStatus(100),
            self::exportStatus(100),
        ];

        $exception = self::capture(fn () => $this->export(pollAttempts: 3, pollWaitMs: 1500)->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringContainsString(self::EXPORT_REF, $exception->getMessage());
        self::assertSame([1500, 1500], $this->sleeps);
        self::assertCount(3, $this->requestsTo('GET', '/v2/invoices/exports/'.self::EXPORT_REF));
        self::assertSame([], $this->queue);
    }

    public function test_range_beyond_hwm_is_an_empty_result(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            self::exportStatus(420, 'Zakres filtrowania wykracza poza dostępny zakres danych'),
        ];

        $result = $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM)));

        self::assertSame([], $result->invoices);
        self::assertNull($result->metadata);
        self::assertEquals(new DateTimeImmutable(self::FROM), $result->hwm);
        self::assertSame(1, $result->rounds);
    }

    public function test_failed_export_status_is_unexpected_response(): void
    {
        $this->queue = [
            ...self::opening(),
            self::json(201, ['referenceNumber' => self::EXPORT_REF]),
            self::exportStatus(415, 'Błąd odszyfrowania dostarczonego klucza'),
        ];

        $exception = self::capture(fn () => $this->export()->run(InvoiceQueryFilters::incoming(new DateTimeImmutable(self::FROM))));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('415', $exception->getMessage());
        self::assertStringContainsString(self::EXPORT_REF, $exception->getMessage());
    }

    private function export(int $pollAttempts = 60, int $pollWaitMs = 2000, int $maxResponseBytes = 20_000_000): KsefExport
    {
        $config = new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip('5265877635'),
            new TokenCredentials(self::KSEF_TOKEN),
            exportPollAttempts: $pollAttempts,
            exportPollWaitMs: $pollWaitMs,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
        $factory = new Psr17Factory;
        $client = new class($this->respond(...)) implements ClientInterface
        {
            /** @param Closure(RequestInterface): ResponseInterface $responder */
            public function __construct(private readonly Closure $responder) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return ($this->responder)($request);
            }
        };
        $api = new KsefApi($config, new HttpClient($client, $factory, $factory, $maxResponseBytes));
        $keys = new PublicKeyRegistry($api, $this->now());
        $authenticator = new Authenticator($api, $config, $keys, now: $this->now());

        return new KsefExport($api->withAuthenticator($authenticator), $keys, $config);
    }

    private function respond(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            Assert::fail(sprintf('Unexpected HTTP request %s %s: no response is queued.', $request->getMethod(), $request->getUri()->getPath()));
        }

        return $next instanceof Closure ? $next($request) : $next;
    }

    /**
     * The key list and a KSeF-token authentication, in the order an export start needs them.
     *
     * @return list<Response>
     */
    private static function opening(): array
    {
        return [self::keysResponse(), self::challengeResponse(), self::initResponse(), self::successStatus(), self::redeemResponse()];
    }

    private static function exportStatus(int $code, string $description = 'Eksport faktur w toku'): Response
    {
        return self::json(200, ['status' => ['code' => $code, 'description' => $description]]);
    }

    /**
     * A finished InvoiceExportStatusResponse whose parts are built lazily: the part hashes
     * depend on the symmetric key of the latest export start, known only when it was sent.
     *
     * @param  array<string, string>  $files
     */
    private function finished(array $files, int $partCount, ?string $truncated = null, ?string $corrupt = null, string $lastField = 'lastPermanentStorageDate', ?int $declaredSize = null, string $url = self::PART_URL): Closure
    {
        return function () use ($files, $partCount, $truncated, $corrupt, $lastField, $declaredSize, $url): Response {
            $this->prepare($files, $partCount);
            $parts = [];
            foreach ($this->pending ?? [] as $ordinal => [$plain, $encrypted]) {
                $parts[] = [
                    'ordinalNumber' => $ordinal,
                    'partName' => sprintf('%s-%03d.zip.aes', self::EXPORT_REF, $ordinal),
                    'method' => 'GET',
                    'url' => sprintf($url, $ordinal),
                    'partSize' => strlen($plain),
                    'partHash' => base64_encode(hash('sha256', $corrupt === 'partHash' ? $plain.'x' : $plain, true)),
                    'encryptedPartSize' => $declaredSize ?? strlen($encrypted),
                    'encryptedPartHash' => base64_encode(hash('sha256', $corrupt === 'encryptedPartHash' ? $encrypted.'x' : $encrypted, true)),
                    'expirationDate' => '2025-09-16T17:09:40.901091+00:00',
                ];
            }
            Assert::assertCount($partCount, $parts);
            // KSeF lists the parts in any order; the client must sort them.
            $parts = array_reverse($parts);
            $package = [
                'invoiceCount' => 1,
                'size' => 100,
                'parts' => $parts,
                'isTruncated' => $truncated !== null,
                'permanentStorageHwmDate' => self::HWM,
                'compressionType' => 'Zip',
            ];
            if ($truncated !== null) {
                $package[$lastField] = $truncated;
            }

            return self::json(200, [
                'status' => ['code' => 200, 'description' => 'Eksport faktur zakończony sukcesem'],
                'completedDate' => '2025-09-16T16:09:40.901091+00:00',
                'package' => $package,
            ]);
        };
    }

    /**
     * Splits a zip of the files into parts encrypted with the symmetric key the latest export
     * start sent (decrypted here with the test Ministry key).
     *
     * @param  array<string, string>  $files
     */
    private function prepare(array $files, int $count): void
    {
        $key = $this->exportKey();
        $zip = self::zip($files);
        $size = max(1, (int) ceil(strlen($zip) / $count));
        $this->pending = [];
        foreach (str_split($zip, $size) as $index => $plain) {
            $this->pending[$index + 1] = [$plain, InvoiceCipher::encrypt($plain, $key)];
        }
        Assert::assertCount($count, $this->pending);
    }

    /**
     * The download answers of the parts the latest finished status listed.
     *
     * @return list<Closure(RequestInterface): ResponseInterface>
     */
    private function parts(int $count): array
    {
        $downloads = [];
        for ($ordinal = 1; $ordinal <= $count; $ordinal++) {
            $downloads[] = function (RequestInterface $request) use ($ordinal): ResponseInterface {
                Assert::assertSame(sprintf(self::PART_URL, $ordinal), (string) $request->getUri());

                return new Response(200, ['Content-Type' => 'application/octet-stream'], ($this->pending ?? [])[$ordinal][1] ?? '');
            };
        }

        return $downloads;
    }

    private function exportKey(): SymmetricKey
    {
        $starts = $this->requestsTo('POST', '/v2/invoices/exports');
        Assert::assertNotSame([], $starts);
        $encryption = self::body($starts[count($starts) - 1])['encryption'];
        Assert::assertIsArray($encryption);
        Assert::assertIsString($encryption['encryptedSymmetricKey']);
        Assert::assertIsString($encryption['initializationVector']);

        return new SymmetricKey(
            self::oaepDecrypt((string) base64_decode($encryption['encryptedSymmetricKey'], true), self::mfKey(1)),
            (string) base64_decode($encryption['initializationVector'], true),
        );
    }

    /**
     * @param  array<string, string>  $files
     */
    private static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ksef-export-test-');
        if ($path === false) {
            throw new RuntimeException('No temporary file.');
        }
        try {
            $archive = new ZipArchive;
            Assert::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            foreach ($files as $name => $contents) {
                $archive->addFromString($name, $contents);
            }
            $archive->close();
            $zip = file_get_contents($path);
            Assert::assertIsString($zip);

            return $zip;
        } finally {
            unlink($path);
        }
    }

    private static function number(string $technical): string
    {
        $first = '5265877635-20250626-'.$technical;

        return $first.'-'.KsefNumber::checksum($first);
    }

    /**
     * @return list<RequestInterface>
     */
    private function requestsTo(string $method, string $path): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (RequestInterface $request): bool => $request->getMethod() === $method && $request->getUri()->getPath() === $path,
        ));
    }

    /**
     * @return list<RequestInterface>
     */
    private function partRequests(): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (RequestInterface $request): bool => $request->getUri()->getHost() === 'ksef-api-storage',
        ));
    }
}

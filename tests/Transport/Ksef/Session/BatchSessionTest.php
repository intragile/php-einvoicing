<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchPackage;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchSession;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Throwable;

final class BatchSessionTest extends TestCase
{
    use AuthFixtures;

    private const KSEF_NUMBER = '5265877635-20250626-010080DD2B5E-26';

    private const BATCH_REF = '20250626-SB-213D593000-4DE10D80A5-E9';

    private const SIGNATURE = '8mKZEU8Reuz%2Fn7wHi4T%2FY8BzLeD5l8bR2xJsBxIgDEY%3D';

    private const UPO_REF = '20250918-EU-2EBD6FA000-242EB9B66D-43';

    public function test_open_declares_encrypted_part_hashes(): void
    {
        $package = self::package(3);
        $recorder = new RecordingHttpClient(...[...self::batchOpening(count($package->parts)), ...self::uploads(count($package->parts)), new Response(204)]);

        $reference = $this->batch($recorder)->send($package);

        self::assertSame(self::BATCH_REF, $reference);
        $open = self::requestsTo($recorder, 'POST', '/sessions/batch');
        self::assertCount(1, $open);
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $open[0]->getHeaderLine('Authorization'));
        $body = self::body($open[0]);
        self::assertSame(['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'], $body['formCode']);
        self::assertFalse($body['offlineMode']);
        self::assertIsArray($body['encryption']);
        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), $body['encryption']['publicKeyId']);
        self::assertIsArray($body['batchFile']);
        self::assertSame($package->fileSize, $body['batchFile']['fileSize']);
        self::assertSame($package->fileHash, $body['batchFile']['fileHash']);

        $key = self::batchKey($open[0]);
        $declared = $body['batchFile']['fileParts'];
        self::assertIsArray($declared);
        self::assertCount(count($package->parts), $declared);
        $puts = self::uploadRequests($recorder);
        foreach ($package->parts as $ordinal => $part) {
            $put = $puts[$ordinal - 1];
            $ciphertext = (string) $put->getBody();
            // Each part is encrypted on its own with the one session key and IV.
            self::assertSame($part, InvoiceCipher::decrypt($ciphertext, $key));
            self::assertSame([
                'ordinalNumber' => $ordinal,
                'fileSize' => strlen($ciphertext),
                'fileHash' => base64_encode(hash('sha256', $ciphertext, true)),
            ], $declared[$ordinal - 1]);
        }
    }

    public function test_offline_mode_is_declared(): void
    {
        $recorder = new RecordingHttpClient(...[...self::batchOpening(1), ...self::uploads(1), new Response(204)]);

        $this->batch($recorder)->send(self::package(1), true);

        self::assertTrue(self::body(self::requestsTo($recorder, 'POST', '/sessions/batch')[0])['offlineMode']);
    }

    public function test_upload_uses_returned_url_and_headers_without_bearer(): void
    {
        $recorder = new RecordingHttpClient(...[...self::batchOpening(2), ...self::uploads(2), new Response(204)]);

        $this->batch($recorder)->send(self::package(2));

        $puts = self::uploadRequests($recorder);
        self::assertCount(2, $puts);
        foreach ($puts as $index => $put) {
            self::assertSame('PUT', $put->getMethod());
            self::assertSame(self::partUrl($index + 1), (string) $put->getUri());
            self::assertSame('BlockBlob', $put->getHeaderLine('x-ms-blob-type'));
            self::assertSame('part-'.($index + 1), $put->getHeaderLine('x-ms-meta-part'));
            self::assertFalse($put->hasHeader('Authorization'));
            self::assertFalse($put->hasHeader('x-ms-null'));
        }
    }

    public function test_upload_requests_are_matched_by_ordinal_number(): void
    {
        $package = self::package(2);
        $recorder = new RecordingHttpClient(...[...self::batchOpening(2, reversed: true), ...self::uploads(2), new Response(204)]);
        $this->batch($recorder)->send($package);

        $key = self::batchKey(self::requestsTo($recorder, 'POST', '/sessions/batch')[0]);
        $puts = self::uploadRequests($recorder);
        self::assertSame(self::partUrl(1), (string) $puts[0]->getUri());
        self::assertSame($package->parts[1], InvoiceCipher::decrypt((string) $puts[0]->getBody(), $key));
        self::assertSame(self::partUrl(2), (string) $puts[1]->getUri());
        self::assertSame($package->parts[2], InvoiceCipher::decrypt((string) $puts[1]->getBody(), $key));
    }

    public function test_missing_upload_request_is_unexpected_response(): void
    {
        // Two parts are declared, but KSeF returns an upload request for the first one only.
        $recorder = new RecordingHttpClient(...self::batchOpening(1));

        $exception = self::capture(fn () => $this->batch($recorder)->send(self::package(2)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString(self::BATCH_REF, $exception->getMessage());
        self::assertSame([], self::uploadRequests($recorder));
        self::assertSame([], self::requestsTo($recorder, 'POST', '/close'));
    }

    public function test_close_is_sent_after_all_parts(): void
    {
        $recorder = new RecordingHttpClient(...[...self::batchOpening(3), ...self::uploads(3), new Response(204)]);

        $this->batch($recorder)->send(self::package(3));

        $requests = array_map(
            static fn (RequestInterface $request): string => $request->getMethod().' '.$request->getUri()->getHost().$request->getUri()->getPath(),
            $recorder->requests(),
        );
        self::assertSame([
            'PUT ksef-api-storage.example/storage/00/batch-parts/1',
            'PUT ksef-api-storage.example/storage/00/batch-parts/2',
            'PUT ksef-api-storage.example/storage/00/batch-parts/3',
            'POST api-test.ksef.mf.gov.pl/v2/sessions/batch/'.self::BATCH_REF.'/close',
        ], array_slice($requests, -4));
        $close = self::requestsTo($recorder, 'POST', '/close')[0];
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $close->getHeaderLine('Authorization'));
    }

    public function test_upload_failure_does_not_close(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::batchOpening(3),
            new Response(201),
            new Response(403, ['Content-Type' => 'application/xml'], '<Error><Code>AuthenticationFailed</Code><Message>Signature did not match for '.self::partUrl(2).'</Message></Error>'),
        ]);

        $exception = self::capture(fn () => $this->batch($recorder)->send(self::package(3)));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString(self::BATCH_REF, $exception->getMessage());
        self::assertStringContainsString('part 2', $exception->getMessage());
        self::assertNotNull($exception->getPrevious());
        self::assertCount(2, self::uploadRequests($recorder));
        self::assertSame([], self::requestsTo($recorder, 'POST', '/close'));
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $text = $current->getMessage().($current instanceof UnexpectedResponse ? $current->bodyExcerpt : '');
            self::assertStringNotContainsString(self::SIGNATURE, $text);
            self::assertStringNotContainsString(rawurldecode(self::SIGNATURE), $text);
            self::assertStringNotContainsString('?', str_replace('?)', '', $text));
        }
    }

    public function test_result_pages_with_continuation_token(): void
    {
        $recorder = new RecordingHttpClient(
            ...self::tokenAuthResponses(),
            ...[
                self::sessionStatus(200, 'Sesja wsadowa przetworzona pomyślnie', ['invoiceCount' => 3, 'successfulInvoiceCount' => 2, 'failedInvoiceCount' => 1]),
                self::json(200, ['continuationToken' => 'page-2-token', 'invoices' => [self::invoiceEntry(1, 'a.xml', 200), self::invoiceEntry(2, 'b.xml', 200)]]),
                self::json(200, ['continuationToken' => null, 'invoices' => [self::invoiceEntry(3, 'c.xml', 450, 'Błąd weryfikacji semantyki dokumentu faktury')]]),
            ],
        );

        $result = $this->batch($recorder)->status(self::BATCH_REF);

        self::assertSame(200, $result->code);
        self::assertSame('Sesja wsadowa przetworzona pomyślnie', $result->description);
        self::assertSame(3, $result->invoiceCount);
        self::assertSame(2, $result->successful);
        self::assertSame(1, $result->failed);
        self::assertSame(self::UPO_REF, $result->upoReference);
        self::assertTrue($result->isFinal());
        self::assertTrue($result->isSuccessful());
        self::assertNull($result->failure());
        self::assertSame(['a.xml', 'b.xml', 'c.xml'], array_keys($result->invoices));
        self::assertSame(DeliveryState::Delivered, $result->invoices['a.xml']->deliveryState());
        self::assertSame(self::KSEF_NUMBER, $result->invoices['a.xml']->ksefNumber);
        self::assertSame(FailureCategory::Validation, $result->invoices['c.xml']->failure()?->category);

        $pages = self::requestsTo($recorder, 'GET', '/sessions/'.self::BATCH_REF.'/invoices');
        self::assertCount(2, $pages);
        self::assertFalse($pages[0]->hasHeader('x-continuation-token'));
        self::assertSame('page-2-token', $pages[1]->getHeaderLine('x-continuation-token'));
        foreach ($pages as $page) {
            self::assertSame('pageSize=1000', $page->getUri()->getQuery());
            self::assertSame('Bearer '.self::ACCESS_TOKEN, $page->getHeaderLine('Authorization'));
        }
    }

    public function test_repeated_continuation_token_is_unexpected_response(): void
    {
        $recorder = new RecordingHttpClient(
            ...self::tokenAuthResponses(),
            ...[
                self::sessionStatus(200, 'Sesja wsadowa przetworzona pomyślnie'),
                self::json(200, ['continuationToken' => 'same', 'invoices' => [self::invoiceEntry(1, 'a.xml', 200)]]),
                self::json(200, ['continuationToken' => 'same', 'invoices' => [self::invoiceEntry(2, 'b.xml', 200)]]),
            ],
        );

        $exception = self::capture(fn () => $this->batch($recorder)->status(self::BATCH_REF));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_batch_status_445_is_final_failure(): void
    {
        $recorder = new RecordingHttpClient(
            ...self::tokenAuthResponses(),
            ...[
                self::sessionStatus(150, 'Trwa przetwarzanie', upo: false),
                self::sessionStatus(445, 'Błąd weryfikacji, brak poprawnych faktur', ['invoiceCount' => 1, 'successfulInvoiceCount' => 0, 'failedInvoiceCount' => 1], upo: false),
                self::json(200, ['invoices' => [self::invoiceEntry(1, 'a.xml', 450, 'Błąd weryfikacji semantyki dokumentu faktury')]]),
            ],
        );

        $result = $this->batch($recorder)->awaitFinal(self::BATCH_REF);

        self::assertSame(445, $result->code);
        self::assertTrue($result->isFinal());
        self::assertFalse($result->isSuccessful());
        $failure = $result->failure();
        self::assertNotNull($failure);
        self::assertSame(FailureCategory::Validation, $failure->category);
        self::assertSame('445', $failure->providerCode);
        self::assertNull($result->upoReference);
        self::assertSame(1, $result->failed);
        self::assertSame(['a.xml'], array_keys($result->invoices));
        self::assertSame([2000], $this->sleeps);
        // The invoice list is read once, after the final status.
        self::assertCount(1, self::requestsTo($recorder, 'GET', '/invoices'));
    }

    public function test_await_final_is_bounded(): void
    {
        $recorder = new RecordingHttpClient(
            ...self::tokenAuthResponses(),
            ...[
                self::sessionStatus(100, 'Sesja wsadowa rozpoczęta', upo: false),
                self::sessionStatus(150, 'Trwa przetwarzanie', upo: false),
                self::sessionStatus(150, 'Trwa przetwarzanie', upo: false),
                self::json(200, ['invoices' => []]),
            ],
        );

        $result = $this->batch($recorder, $this->batchConfig(statusPollAttempts: 3, statusPollWaitMs: 500))->awaitFinal(self::BATCH_REF);

        self::assertSame(150, $result->code);
        self::assertFalse($result->isFinal());
        self::assertNull($result->failure());
        self::assertSame(0, $result->invoiceCount);
        self::assertSame([], $result->invoices);
        self::assertSame([500, 500], $this->sleeps);
    }

    private function batchConfig(int $statusPollAttempts = 30, int $statusPollWaitMs = 2000): KsefConfig
    {
        return new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip('5265877635'),
            new TokenCredentials(self::KSEF_TOKEN),
            statusPollAttempts: $statusPollAttempts,
            statusPollWaitMs: $statusPollWaitMs,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    private function batch(RecordingHttpClient $recorder, ?KsefConfig $config = null): BatchSession
    {
        $config ??= $this->batchConfig();
        $api = $this->plainApi($recorder, $config);
        $keys = new PublicKeyRegistry($api, $this->now());
        $authenticator = new Authenticator($api, $config, $keys, now: $this->now());

        return new BatchSession($api->withAuthenticator($authenticator), $keys, $config);
    }

    /**
     * A package of the given number of parts: random content does not compress, so the archive
     * size is predictable enough to split.
     */
    private static function package(int $parts): BatchPackage
    {
        $invoices = [];
        for ($i = 1; $i <= 3; $i++) {
            $invoices[sprintf('invoice-%d.xml', $i)] = '<Faktura>'.bin2hex(random_bytes(300)).'</Faktura>';
        }
        $size = strlen(BatchPackage::build($invoices, 100_000_000)->zip);

        $package = BatchPackage::build($invoices, (int) ceil($size / $parts));
        self::assertCount($parts, $package->parts);

        return $package;
    }

    /**
     * The answers up to the opened batch session: the key list, a KSeF-token authentication and
     * the open answer with one upload request per part.
     *
     * @return list<Response>
     */
    private static function batchOpening(int $parts, bool $reversed = false): array
    {
        $requests = array_map(self::uploadRequest(...), range(1, $parts));

        return [
            self::keysResponse(),
            ...self::tokenAuthResponses(withKeys: false),
            self::json(201, [
                'referenceNumber' => self::BATCH_REF,
                'partUploadRequests' => $reversed ? array_reverse($requests) : $requests,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function uploadRequest(int $ordinal): array
    {
        return [
            'ordinalNumber' => $ordinal,
            'method' => 'PUT',
            'url' => self::partUrl($ordinal),
            'headers' => ['x-ms-blob-type' => 'BlockBlob', 'x-ms-meta-part' => 'part-'.$ordinal, 'x-ms-null' => null],
        ];
    }

    private static function partUrl(int $ordinal): string
    {
        return sprintf('https://ksef-api-storage.example/storage/00/batch-parts/%d?skoid=1ad7cfe8-2cb2-406b-b96c-6eefb55794db&sv=2025-01-05&sr=b&sp=w&sig=%s', $ordinal, self::SIGNATURE);
    }

    /**
     * @return list<Response>
     */
    private static function uploads(int $parts): array
    {
        return array_map(static fn (): Response => new Response(201), range(1, $parts));
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function sessionStatus(int $code, string $description, array $counts = [], bool $upo = true): Response
    {
        $data = [
            'status' => ['code' => $code, 'description' => $description],
            'dateCreated' => '2026-11-21T10:00:30+00:00',
            'dateUpdated' => '2026-11-21T10:01:20+00:00',
        ] + $counts;
        if ($upo) {
            $data['upo'] = ['pages' => [[
                'referenceNumber' => self::UPO_REF,
                'downloadUrl' => 'https://api-test.ksef.mf.gov.pl/storage/01/session-upo/upo_00.xml?sv=2025-01-05&sig=ZlQO6Xtzu3VQQDwmEMfb0VryMxe9WcUgWtkdiB6X2Qo%3D',
                'downloadUrlExpirationDate' => '2026-11-24T15:00:50+00:00',
            ]]];
        }

        return self::json(200, $data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function invoiceEntry(int $ordinal, string $fileName, int $code, string $description = 'Sukces'): array
    {
        $entry = [
            'ordinalNumber' => $ordinal,
            'referenceNumber' => sprintf('20250918-EE-2F15D39000-242207E5C4-%02d', $ordinal),
            'invoiceHash' => base64_encode(hash('sha256', $fileName, true)),
            'invoiceFileName' => $fileName,
            'invoicingDate' => '2026-11-21T10:00:56.0154302+00:00',
            'status' => ['code' => $code, 'description' => $description],
        ];
        if ($code === 200) {
            $entry['ksefNumber'] = self::KSEF_NUMBER;
            $entry['acquisitionDate'] = '2026-11-21T10:01:16.0154302+00:00';
        }

        return $entry;
    }

    /**
     * The part uploads, in the order they were sent.
     *
     * @return list<RequestInterface>
     */
    private static function uploadRequests(RecordingHttpClient $recorder): array
    {
        return array_values(array_filter(
            $recorder->requests(),
            static fn (RequestInterface $request): bool => $request->getUri()->getHost() === 'ksef-api-storage.example',
        ));
    }

    /**
     * The session key of a recorded open request, decrypted with the in-memory Ministry key.
     */
    private static function batchKey(RequestInterface $open): SymmetricKey
    {
        $encryption = self::body($open)['encryption'] ?? null;
        self::assertIsArray($encryption);
        self::assertIsString($encryption['encryptedSymmetricKey']);
        self::assertIsString($encryption['initializationVector']);
        $key = self::oaepDecrypt((string) base64_decode($encryption['encryptedSymmetricKey'], true), self::mfKey(1));

        return new SymmetricKey($key, (string) base64_decode($encryption['initializationVector'], true));
    }

    /**
     * @return list<RequestInterface>
     */
    private static function requestsTo(RecordingHttpClient $recorder, string $method, string $pathSuffix): array
    {
        return array_values(array_filter(
            $recorder->requests(),
            static fn (RequestInterface $request): bool => $request->getMethod() === $method && str_ends_with($request->getUri()->getPath(), $pathSuffix),
        ));
    }

    private static function capture(callable $call): ?Throwable
    {
        try {
            $call();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }
}

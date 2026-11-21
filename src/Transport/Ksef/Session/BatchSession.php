<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyUsage;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\RsaOaep;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use InvalidArgumentException;
use JsonException;

/**
 * A KSeF batch session for FA(3) invoices: opens a session for a package, uploads its encrypted
 * parts, closes the session and reads the processing result.
 *
 * Every send() uses a new session with a fresh symmetric key. Each part of the package is
 * encrypted on its own with that key and IV; the open request declares the size and SHA-256
 * digest of every encrypted part, and KSeF answers with one upload request per part. The parts
 * are uploaded to the URLs KSeF returns, with exactly the headers it returns and without the
 * access token. The upload URLs are signed: they never appear in an exception message.
 *
 * status() and awaitFinal() take the reference of any batch session. Every request to the KSeF
 * API uses the access token of the KsefApi, which must come from withAuthenticator().
 */
final class BatchSession
{
    /** Invoices read per page of the session invoice list (the largest page KSeF allows). */
    public const INVOICE_PAGE_SIZE = 1000;

    /** Pages of the session invoice list read at most: 10,000 invoices fit in 10. */
    private const MAX_INVOICE_PAGES = 20;

    /** HTTP methods accepted in an upload request. */
    private const METHOD = '/\A[A-Za-z]{1,16}\z/';

    private const OPEN_OPERATION = 'The KSeF request POST /sessions/batch';

    private const STATUS_OPERATION = 'The KSeF request GET /sessions/{referenceNumber}';

    private const INVOICES_OPERATION = 'The KSeF request GET /sessions/{referenceNumber}/invoices';

    /**
     * @param  KsefApi  $api  a client made by withAuthenticator()
     * @param  PublicKeyRegistry  $keys  the Ministry of Finance key that encrypts the session key
     * @param  KsefConfig  $config  the bounds of awaitFinal()
     */
    public function __construct(
        private readonly KsefApi $api,
        private readonly PublicKeyRegistry $keys,
        private readonly KsefConfig $config,
    ) {}

    /**
     * Opens a batch session for the package, uploads every part and closes the session, which
     * starts the processing. Returns the session reference to read the result with.
     *
     * Only refusals KSeF did not process are repeated: a request refused with HTTP 401 (or
     * 21301/21304) once with a renewed access token, and the session open refused with 21470 once
     * with a fresh key list. When an upload fails, the session is left open and not
     * closed, and an OutcomeUnknown names the session reference: KSeF cancels a session whose
     * parts were not all uploaded in time (status 440). Sending the package again in a new
     * session is safe; KSeF answers each invoice already accepted with status 440 and its
     * original KSeF number.
     *
     * @param  bool  $offlineMode  true for invoices issued in offline mode
     *
     * @throws OutcomeUnknown when a part upload fails
     * @throws UnexpectedResponse when the open answer cannot be used
     * @throws TransportException when opening or closing the session fails, or KSeF refuses it
     */
    public function send(BatchPackage $package, bool $offlineMode = false): string
    {
        $key = SymmetricKey::generate();
        $encrypted = [];
        $declared = [];
        foreach ($package->parts as $ordinal => $part) {
            $ciphertext = InvoiceCipher::encrypt($part, $key);
            $encrypted[$ordinal] = $ciphertext;
            $declared[] = [
                'ordinalNumber' => $ordinal,
                'fileSize' => strlen($ciphertext),
                'fileHash' => base64_encode(hash('sha256', $ciphertext, true)),
            ];
        }

        $body = fn (string $certificatePem, string $keyId): array => [
            'formCode' => FormCode::fa3()->json(),
            'batchFile' => [
                'fileSize' => $package->fileSize,
                'fileHash' => $package->fileHash,
                'fileParts' => $declared,
            ],
            'encryption' => [
                'encryptedSymmetricKey' => base64_encode(RsaOaep::encrypt($key->key(), $certificatePem)),
                'initializationVector' => base64_encode($key->iv),
                'publicKeyId' => $keyId,
            ],
            'offlineMode' => $offlineMode,
        ];
        $answer = $this->keys->using(
            PublicKeyUsage::SymmetricKeyEncryption,
            fn (string $certificatePem, string $keyId): array => $this->api->authorized(
                fn (string $bearer): array => $this->api->json('POST', '/sessions/batch', $body($certificatePem, $keyId), $bearer),
            ),
        );

        $reference = KsefAnswer::string($answer, ['referenceNumber'], self::OPEN_OPERATION);
        $uploads = self::uploadRequests($answer, array_keys($encrypted), $reference);

        $total = count($encrypted);
        foreach ($encrypted as $ordinal => $ciphertext) {
            [$method, $url, $headers] = $uploads[$ordinal];
            try {
                $this->api->raw($method, $url, $ciphertext, $headers);
            } catch (TransportException $exception) {
                throw new OutcomeUnknown(sprintf(
                    'Uploading part %d of %d of the KSeF batch session %s failed, so the session was left open and not closed; KSeF cancels it once the upload time is over. Send the package again in a new session: %s',
                    $ordinal,
                    $total,
                    $reference,
                    $exception->getMessage(),
                ), $exception);
            } catch (InvalidArgumentException) {
                // Not chained: a PSR-7 implementation may quote the whole signed URL.
                throw new UnexpectedResponse(sprintf('%s for the batch session %s returned an unusable upload URL for part %d.', self::OPEN_OPERATION, $reference, $ordinal));
            }
        }

        $path = '/sessions/batch/'.rawurlencode($reference).'/close';
        $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, null, $bearer));

        return $reference;
    }

    /**
     * The current state of a batch session with the status of each invoice in it, read once.
     *
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function status(string $ref): BatchResult
    {
        return $this->withInvoices($this->sessionStatus($ref), $ref);
    }

    /**
     * Reads the session state until it is final, at most statusPollAttempts times with
     * statusPollWaitMs between reads, then reads the invoice list once. The result is still
     * pending when the attempts ran out.
     *
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function awaitFinal(string $ref): BatchResult
    {
        for ($attempt = 1; ; $attempt++) {
            $status = $this->sessionStatus($ref);
            if ($status->isFinal() || $attempt >= $this->config->statusPollAttempts) {
                return $this->withInvoices($status, $ref);
            }
            $this->config->sleep($this->config->statusPollWaitMs);
        }
    }

    /**
     * The session state without the invoice list.
     *
     * @throws TransportException
     */
    private function sessionStatus(string $ref): BatchResult
    {
        $path = '/sessions/'.rawurlencode($ref);
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', $path, null, $bearer));

        $statusInfo = is_array($answer['status'] ?? null) ? $answer['status'] : [];
        $details = $statusInfo['details'] ?? null;
        $details = is_array($details)
            ? array_values(array_map(trim(...), array_filter($details, static fn (mixed $detail): bool => is_string($detail) && trim($detail) !== '')))
            : [];
        $upo = is_array($answer['upo'] ?? null) ? $answer['upo'] : [];
        $pages = $upo['pages'] ?? null;
        $upoReference = is_array($pages) && is_array($pages[0] ?? null) && is_string($pages[0]['referenceNumber'] ?? null) && $pages[0]['referenceNumber'] !== ''
            ? $pages[0]['referenceNumber']
            : null;

        return new BatchResult(
            KsefAnswer::int($answer, ['status', 'code'], self::STATUS_OPERATION),
            KsefAnswer::string($answer, ['status', 'description'], self::STATUS_OPERATION),
            self::count($answer['invoiceCount'] ?? null),
            self::count($answer['successfulInvoiceCount'] ?? null),
            self::count($answer['failedInvoiceCount'] ?? null),
            [],
            $upoReference,
            $details,
        );
    }

    /**
     * The state with the invoice list, read page by page with the continuation token.
     *
     * @throws TransportException
     */
    private function withInvoices(BatchResult $status, string $ref): BatchResult
    {
        $path = '/sessions/'.rawurlencode($ref).'/invoices?pageSize='.self::INVOICE_PAGE_SIZE;
        $invoices = [];
        $token = null;
        $seen = [];
        for ($page = 1; ; $page++) {
            $headers = ['Accept' => 'application/json', 'X-Error-Format' => 'problem-details'];
            if ($token !== null) {
                $headers['x-continuation-token'] = $token;
            }
            $response = $this->api->authorized(fn (string $bearer) => $this->api->raw('GET', $path, null, $headers, $bearer));
            try {
                $answer = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $answer = null;
            }
            if (! is_array($answer) || ! is_array($answer['invoices'] ?? null)) {
                throw new UnexpectedResponse(sprintf('%s was answered without an "invoices" list.', self::INVOICES_OPERATION), $response->getStatusCode());
            }
            foreach ($answer['invoices'] as $entry) {
                if (! is_array($entry)) {
                    throw new UnexpectedResponse(sprintf('%s was answered with an invoice entry that is not an object.', self::INVOICES_OPERATION), $response->getStatusCode());
                }
                $name = $entry['invoiceFileName'] ?? null;
                $name = is_string($name) && $name !== '' ? $name : KsefAnswer::string($entry, ['referenceNumber'], self::INVOICES_OPERATION);
                $invoices[$name] = KsefInvoiceStatus::fromAnswer($entry, self::INVOICES_OPERATION);
            }

            $next = $answer['continuationToken'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }
            if (isset($seen[$next]) || $page >= self::MAX_INVOICE_PAGES) {
                throw new UnexpectedResponse(sprintf('%s kept answering with further pages after %d pages.', self::INVOICES_OPERATION, $page), $response->getStatusCode());
            }
            $seen[$next] = true;
            $token = $next;
        }

        return new BatchResult(
            $status->code,
            $status->description,
            $status->invoiceCount,
            $status->successful,
            $status->failed,
            $invoices,
            $status->upoReference,
            $status->details,
        );
    }

    /**
     * The upload request of every declared part, by ordinal number: method, URL and headers.
     *
     * @param  array<mixed>  $answer
     * @param  list<int>  $ordinals
     * @return array<int, array{string, string, array<string, string>}>
     *
     * @throws UnexpectedResponse
     */
    private static function uploadRequests(array $answer, array $ordinals, string $reference): array
    {
        $unusable = static fn (string $what): UnexpectedResponse => new UnexpectedResponse(
            // The upload URLs are signed, so the answer is never quoted.
            sprintf('%s for the batch session %s was answered with %s; no part was uploaded and the session was not closed.', self::OPEN_OPERATION, $reference, $what),
        );

        $list = $answer['partUploadRequests'] ?? null;
        if (! is_array($list)) {
            throw $unusable('no "partUploadRequests" list');
        }
        $uploads = [];
        foreach ($list as $request) {
            $ordinal = is_array($request) ? ($request['ordinalNumber'] ?? null) : null;
            if (! is_array($request) || ! is_int($ordinal)) {
                throw $unusable('an upload request without an ordinal number');
            }
            $method = $request['method'] ?? null;
            $url = $request['url'] ?? null;
            $headers = $request['headers'] ?? [];
            if (! is_string($method) || preg_match(self::METHOD, $method) !== 1) {
                throw $unusable(sprintf('an unusable method for part %d', $ordinal));
            }
            if (! is_string($url) || ! str_starts_with(strtolower($url), 'https://')) {
                throw $unusable(sprintf('an upload URL for part %d that is not https', $ordinal));
            }
            if (! is_array($headers)) {
                throw $unusable(sprintf('unusable headers for part %d', $ordinal));
            }
            $usable = [];
            foreach ($headers as $name => $value) {
                if ($value === null) {
                    continue;
                }
                if (! is_string($name) || $name === '' || ! is_string($value)) {
                    throw $unusable(sprintf('unusable headers for part %d', $ordinal));
                }
                $usable[$name] = $value;
            }
            if (isset($uploads[$ordinal])) {
                throw $unusable(sprintf('two upload requests for part %d', $ordinal));
            }
            $uploads[$ordinal] = [strtoupper($method), $url, $usable];
        }

        foreach ($ordinals as $ordinal) {
            if (! isset($uploads[$ordinal])) {
                throw $unusable(sprintf('no upload request for part %d of %d', $ordinal, count($ordinals)));
            }
        }
        if (count($uploads) !== count($ordinals)) {
            throw $unusable('upload requests for parts that were not declared');
        }

        return $uploads;
    }

    private static function count(mixed $value): int
    {
        return is_int($value) && $value >= 0 ? $value : 0;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
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
use LogicException;
use RuntimeException;
use ZipArchive;

/**
 * Exports invoices as encrypted packages (POST /invoices/exports, then
 * GET /invoices/exports/{referenceNumber}, InvoiceExportStatusResponse).
 *
 * Each round starts an export with a fresh AES-256 key, encrypted for the Ministry of Finance
 * symmetric-key certificate, and polls its status within the export polling bounds of the
 * configuration. The finished package is a zip archive split into parts; each part is downloaded
 * from the URL KSeF returns (without the access token; the URL is signed and never quoted in an
 * exception), checked against its encryptedPartHash, decrypted, checked against its partHash,
 * and appended in ordinal order to a temporary archive file, which is then unzipped. The archive
 * holds one {ksefNumber}.xml file per invoice and a _metadata.json file.
 *
 * Memory: while a package is downloaded, one encrypted part and its plaintext (KSeF parts are at
 * most 50 MB) are held at a time; the joined archive lives only in the temporary file. The
 * extracted XML files of all rounds are held in the ExportResult, so a run over a large inbox
 * needs memory for every invoice it returns: narrow the date range for very large exports.
 *
 * A package is truncated when it reached KSeF's limits (10,000 invoices or 1 GB compressed);
 * the export then continues from the date of the last exported invoice, at most MAX_ROUNDS
 * rounds per run(). A truncated package whose last date does not pass the start of its round
 * (more invoices sharing one date than an export holds) ends the run with UnexpectedResponse
 * instead of repeating the same export. Status 420 (the start is beyond the high water mark with
 * restrictToPermanentStorageHwmDate) means there is nothing to export yet.
 *
 * Needs the PHP zip extension. Every request to the KSeF API uses the access token of the
 * KsefApi, which must come from withAuthenticator().
 */
final class KsefExport
{
    /** Export rounds of one run() at most. */
    public const MAX_ROUNDS = 10;

    /** The name of the metadata file in an export package. */
    public const METADATA_FILE = '_metadata.json';

    private const STATUS_IN_PROGRESS = 100;

    private const STATUS_DONE = 200;

    private const STATUS_BEYOND_HWM = 420;

    private const STATUS_CANCELLED = 550;

    /** HTTP methods accepted for a part download. */
    private const METHOD = '/\A[A-Za-z]{1,16}\z/';

    private const START_OPERATION = 'The KSeF request POST /invoices/exports';

    private const STATUS_OPERATION = 'The KSeF request GET /invoices/exports/{referenceNumber}';

    /**
     * @param  KsefApi  $api  a client made by withAuthenticator()
     * @param  PublicKeyRegistry  $keys  the Ministry of Finance key that encrypts the export key
     * @param  KsefConfig  $config  the export polling bounds
     */
    public function __construct(
        private readonly KsefApi $api,
        private readonly PublicKeyRegistry $keys,
        private readonly KsefConfig $config,
    ) {}

    /**
     * Exports the invoices matching the filters, continuing while the package is truncated.
     *
     * @throws LogicException when the zip extension is not loaded
     * @throws TransportUnavailable when an export is still in progress after the polling bounds,
     *                              or KSeF cancelled it (status 550); run it again
     * @throws UnexpectedResponse when an export fails, or a part does not match its digest or
     *                            cannot be decrypted or unzipped
     * @throws TransportException when a request fails or KSeF refuses it
     */
    public function run(InvoiceQueryFilters $filters): ExportResult
    {
        if (! extension_loaded('zip')) {
            throw new LogicException('KSeF invoice exports need the PHP zip extension (ext-zip), which is not loaded. Install or enable it to export invoices.');
        }

        $invoices = [];
        $metadata = null;
        $metadataInvoices = [];
        for ($round = 1; ; $round++) {
            $key = SymmetricKey::generate();
            $reference = $this->start($filters, $key);
            $package = $this->await($reference);
            if ($package === null) {
                return new ExportResult($invoices, self::metadata($metadata, $metadataInvoices), $filters->from, $round);
            }

            foreach ($this->files($package, $key, $reference) as $name => $contents) {
                if ($name === self::METADATA_FILE) {
                    $metadata = self::decodeMetadata($contents, $reference);
                    foreach (self::metadataEntries($metadata) as $entry) {
                        $number = is_array($entry) ? ($entry['ksefNumber'] ?? null) : null;
                        if (is_string($number)) {
                            $metadataInvoices[$number] = $entry;
                        } else {
                            $metadataInvoices[] = $entry;
                        }
                    }
                } elseif (strtolower(substr($name, -4)) === '.xml') {
                    $invoices[substr(basename($name), 0, -4)] = $contents;
                }
            }

            if (($package['isTruncated'] ?? null) !== true) {
                $hwm = isset($package['permanentStorageHwmDate']) && is_string($package['permanentStorageHwmDate'])
                    ? KsefAnswer::parseDateTime($package['permanentStorageHwmDate'])
                    : null;

                return new ExportResult($invoices, self::metadata($metadata, $metadataInvoices), $hwm, $round);
            }

            $continueFrom = self::lastDate($package, $filters->dateType, $reference);
            if ($continueFrom <= $filters->from) {
                // Repeating the export from the same point would return the same package.
                throw new UnexpectedResponse(sprintf(
                    'The KSeF export %s was truncated without passing its start %s: more invoices share that %s date than one export holds, so KSeF cannot continue past it. Narrow the filters (for example by seller NIP or a PermanentStorage date range).',
                    $reference,
                    $filters->from->format('Y-m-d\\TH:i:s.uP'),
                    $filters->dateType,
                ));
            }
            if ($round >= self::MAX_ROUNDS) {
                return new ExportResult($invoices, self::metadata($metadata, $metadataInvoices), $continueFrom, $round, true);
            }
            $filters = $filters->withFrom($continueFrom);
        }
    }

    /**
     * Starts one export and returns its reference number.
     *
     * @throws TransportException
     */
    private function start(InvoiceQueryFilters $filters, SymmetricKey $key): string
    {
        $answer = $this->keys->using(
            PublicKeyUsage::SymmetricKeyEncryption,
            fn (string $certificatePem, string $keyId): array => $this->api->authorized(
                fn (string $bearer): array => $this->api->json('POST', '/invoices/exports', [
                    'encryption' => [
                        'encryptedSymmetricKey' => base64_encode(RsaOaep::encrypt($key->key, $certificatePem)),
                        'initializationVector' => base64_encode($key->iv),
                        'publicKeyId' => $keyId,
                    ],
                    'filters' => $filters->json(),
                ], $bearer),
            ),
        );

        return KsefAnswer::string($answer, ['referenceNumber'], self::START_OPERATION);
    }

    /**
     * Polls the export until it is finished and returns its package, or null when KSeF had
     * nothing beyond the high water mark.
     *
     * @return array<mixed>|null
     *
     * @throws TransportException
     */
    private function await(string $reference): ?array
    {
        $path = '/invoices/exports/'.rawurlencode($reference);
        for ($attempt = 1; ; $attempt++) {
            $status = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', $path, null, $bearer));
            $code = KsefAnswer::int($status, ['status', 'code'], self::STATUS_OPERATION);
            $statusInfo = is_array($status['status'] ?? null) ? $status['status'] : [];
            $description = is_string($statusInfo['description'] ?? null) ? $statusInfo['description'] : '';

            if ($code === self::STATUS_DONE) {
                $package = $status['package'] ?? null;
                if (! is_array($package)) {
                    throw new UnexpectedResponse(sprintf('%s for the export %s was answered with status 200 but without a package.', self::STATUS_OPERATION, $reference));
                }

                return $package;
            }
            if ($code === self::STATUS_BEYOND_HWM) {
                return null;
            }
            if ($code === self::STATUS_CANCELLED) {
                throw new TransportUnavailable(sprintf('KSeF cancelled the export %s (status %d: %s); start it again.', $reference, $code, $description));
            }
            if ($code !== self::STATUS_IN_PROGRESS) {
                throw new UnexpectedResponse(sprintf('The KSeF export %s failed with status %d: %s', $reference, $code, $description));
            }
            if ($attempt >= $this->config->exportPollAttempts) {
                throw new TransportUnavailable(sprintf(
                    'The KSeF export %s was still in progress after %d status reads; start the export again later.',
                    $reference,
                    $attempt,
                ));
            }
            $this->config->sleep($this->config->exportPollWaitMs);
        }
    }

    /**
     * The files of the package by their name in the archive.
     *
     * @param  array<mixed>  $package
     * @return array<string, string>
     *
     * @throws TransportException
     */
    private function files(array $package, SymmetricKey $key, string $reference): array
    {
        $unusable = static fn (string $what): UnexpectedResponse => new UnexpectedResponse(
            // The part URLs are signed, so the answer is never quoted.
            sprintf('%s for the export %s was answered with %s.', self::STATUS_OPERATION, $reference, $what),
        );

        $list = $package['parts'] ?? null;
        if (! is_array($list)) {
            throw $unusable('a package without a "parts" list');
        }
        $parts = [];
        foreach ($list as $part) {
            $ordinal = is_array($part) ? ($part['ordinalNumber'] ?? null) : null;
            if (! is_array($part) || ! is_int($ordinal)) {
                throw $unusable('a package part without an ordinal number');
            }
            $method = $part['method'] ?? 'GET';
            $url = $part['url'] ?? null;
            $hash = $part['partHash'] ?? null;
            $encryptedHash = $part['encryptedPartHash'] ?? null;
            if (! is_string($method) || preg_match(self::METHOD, $method) !== 1) {
                throw $unusable(sprintf('an unusable method for part %d', $ordinal));
            }
            if (! is_string($url) || ! str_starts_with(strtolower($url), 'https://')) {
                throw $unusable(sprintf('a download URL for part %d that is not https', $ordinal));
            }
            if (! is_string($hash) || ! is_string($encryptedHash)) {
                throw $unusable(sprintf('no partHash or encryptedPartHash for part %d', $ordinal));
            }
            if (isset($parts[$ordinal])) {
                throw $unusable(sprintf('two entries for part %d', $ordinal));
            }
            $parts[$ordinal] = [strtoupper($method), $url, $hash, $encryptedHash];
        }
        if ($parts === []) {
            return [];
        }
        ksort($parts);

        $path = tempnam(sys_get_temp_dir(), 'ksef-export-');
        if ($path === false) {
            throw new RuntimeException('The KSeF export package could not be unpacked: no temporary file is available.');
        }
        try {
            $archive = fopen($path, 'wb');
            if ($archive === false) {
                throw new RuntimeException('The KSeF export package could not be written to a temporary file.');
            }
            try {
                foreach ($parts as $ordinal => [$method, $url, $hash, $encryptedHash]) {
                    $plain = $this->part($ordinal, $method, $url, $hash, $encryptedHash, $key, $reference);
                    if (fwrite($archive, $plain) !== strlen($plain)) {
                        throw new RuntimeException('The KSeF export package could not be written to a temporary file.');
                    }
                    // Only one part is held in memory at a time.
                    unset($plain);
                }
            } finally {
                fclose($archive);
            }

            return self::unzip($path, $reference);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * One part downloaded, checked against both digests and decrypted.
     *
     * @throws TransportException
     */
    private function part(int $ordinal, string $method, string $url, string $hash, string $encryptedHash, SymmetricKey $key, string $reference): string
    {
        try {
            $encrypted = (string) $this->api->raw($method, $url)->getBody();
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponse(sprintf('%s for the export %s returned an unusable download URL for part %d.', self::STATUS_OPERATION, $reference, $ordinal), 0, '', $exception);
        }
        if (! hash_equals($encryptedHash, base64_encode(hash('sha256', $encrypted, true)))) {
            throw new UnexpectedResponse(sprintf('Part %d of the KSeF export %s does not match its encryptedPartHash; the download is incomplete or corrupted.', $ordinal, $reference));
        }
        try {
            $plain = InvoiceCipher::decrypt($encrypted, $key);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponse(sprintf('Part %d of the KSeF export %s could not be decrypted with the export key.', $ordinal, $reference), 0, '', $exception);
        }
        if (! hash_equals($hash, base64_encode(hash('sha256', $plain, true)))) {
            throw new UnexpectedResponse(sprintf('Part %d of the KSeF export %s does not match its partHash after decryption.', $ordinal, $reference));
        }

        return $plain;
    }

    /**
     * The files of the zip archive at the path, by name.
     *
     * @return array<string, string>
     *
     * @throws UnexpectedResponse
     */
    private static function unzip(string $path, string $reference): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new UnexpectedResponse(sprintf('The package of the KSeF export %s is not a readable zip archive.', $reference));
        }
        try {
            $files = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $contents = $archive->getFromIndex($index);
                if ($contents === false) {
                    throw new UnexpectedResponse(sprintf('The file %s of the KSeF export %s could not be unzipped.', $name, $reference));
                }
                $files[$name] = $contents;
            }

            return $files;
        } finally {
            $archive->close();
        }
    }

    /**
     * @return array<mixed>
     *
     * @throws UnexpectedResponse
     */
    private static function decodeMetadata(string $contents, string $reference): array
    {
        try {
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $metadata = null;
        }
        if (! is_array($metadata)) {
            throw new UnexpectedResponse(sprintf('The %s file of the KSeF export %s is not a JSON object.', self::METADATA_FILE, $reference));
        }

        return $metadata;
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<mixed>
     */
    private static function metadataEntries(array $metadata): array
    {
        $entries = $metadata['invoices'] ?? [];

        return is_array($entries) ? $entries : [];
    }

    /**
     * The last metadata file with its invoice list replaced by the merged list of all rounds.
     *
     * @param  array<mixed>|null  $last
     * @param  array<mixed>  $invoices
     * @return array<mixed>|null
     */
    private static function metadata(?array $last, array $invoices): ?array
    {
        return $last === null ? null : ['invoices' => array_values($invoices)] + $last;
    }

    /**
     * The date of the last exported invoice of a truncated package, for the filtered date type.
     *
     * @param  array<mixed>  $package
     *
     * @throws UnexpectedResponse
     */
    private static function lastDate(array $package, string $dateType, string $reference): DateTimeImmutable
    {
        $field = match ($dateType) {
            InvoiceQueryFilters::DATE_ISSUE => 'lastIssueDate',
            InvoiceQueryFilters::DATE_INVOICING => 'lastInvoicingDate',
            default => 'lastPermanentStorageDate',
        };
        $value = $package[$field] ?? null;
        $date = null;
        if (is_string($value)) {
            $date = $field === 'lastIssueDate'
                ? (DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) ?: null)
                : KsefAnswer::parseDateTime($value);
        }
        if ($date === null) {
            throw new UnexpectedResponse(sprintf('%s for the export %s was answered with a truncated package without a usable "%s".', self::STATUS_OPERATION, $reference, $field));
        }

        return $date;
    }
}

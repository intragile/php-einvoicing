<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Reads invoices addressed to or issued by the authenticated context: one page of the metadata
 * query (POST /invoices/query/metadata, QueryInvoicesMetadataResponse) at a time, and a single
 * invoice by its KSeF number (GET /invoices/ksef/{ksefNumber}).
 *
 * Results are sorted ascending by the filtered date, as incremental reading requires. Paging:
 * while hasMore is true and isTruncated false, read the next pageOffset; when both are true, the
 * 10,000 record limit of one filter set was reached, and the query must start again from the
 * date of the last record with pageOffset 0. Every request uses the access token of the
 * KsefApi, which must come from withAuthenticator().
 */
final class MetadataQuery
{
    public const MIN_PAGE_SIZE = 10;

    public const MAX_PAGE_SIZE = 250;

    private const QUERY_OPERATION = 'The KSeF request POST /invoices/query/metadata';

    private const DOWNLOAD_OPERATION = 'The KSeF request GET /invoices/ksef/{ksefNumber}';

    public function __construct(private readonly KsefApi $api) {}

    /**
     * One page of the metadata query. Its "hwm" is the permanentStorageHwmDate, which KSeF
     * reports for PermanentStorage queries only and which is the same on every page.
     *
     * @param  int  $pageOffset  the page index, 0 for the first page
     * @param  int  $pageSize  10 to 250 records
     * @return array{invoices: list<InvoiceMetadata>, hasMore: bool, isTruncated: bool, hwm: ?DateTimeImmutable}
     *
     * @throws InvalidArgumentException when the page offset or size is out of range
     * @throws TransportException when the request fails or the answer cannot be read
     */
    public function page(InvoiceQueryFilters $filters, int $pageOffset = 0, int $pageSize = 100): array
    {
        if ($pageOffset < 0) {
            throw new InvalidArgumentException(sprintf('The KSeF page offset must not be negative, %d given.', $pageOffset));
        }
        if ($pageSize < self::MIN_PAGE_SIZE || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf('The KSeF page size must be between %d and %d, %d given.', self::MIN_PAGE_SIZE, self::MAX_PAGE_SIZE, $pageSize));
        }

        $path = '/invoices/query/metadata?'.http_build_query(['pageOffset' => $pageOffset, 'pageSize' => $pageSize, 'sortOrder' => 'Asc']);
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, $filters->json(), $bearer));

        $invoices = [];
        $list = $answer['invoices'] ?? null;
        if (! is_array($list) || ! array_is_list($list)) {
            throw new UnexpectedResponse(sprintf('%s was answered without an "invoices" list.', self::QUERY_OPERATION));
        }
        foreach ($list as $index => $entry) {
            if (! is_array($entry)) {
                throw new UnexpectedResponse(sprintf('%s was answered with invoice %d that is not an object.', self::QUERY_OPERATION, $index));
            }
            $invoices[] = InvoiceMetadata::fromAnswer($entry, self::QUERY_OPERATION);
        }

        $hwm = null;
        if (($answer['permanentStorageHwmDate'] ?? null) !== null) {
            $hwm = KsefAnswer::dateTime($answer, ['permanentStorageHwmDate'], self::QUERY_OPERATION);
        }

        return [
            'invoices' => $invoices,
            'hasMore' => self::flag($answer, 'hasMore'),
            'isTruncated' => self::flag($answer, 'isTruncated'),
            'hwm' => $hwm,
        ];
    }

    /**
     * The invoice file with the given KSeF number, exactly as KSeF stores it. When KSeF sends the
     * SHA-256 digest of the file (x-ms-meta-hash), the file is checked against it.
     *
     * @throws InvalidArgumentException when the KSeF number is not valid; no request is made
     * @throws TransportException when the request fails, KSeF refuses it (21164: unknown number,
     *                            21165: not available yet) or the digest does not match
     */
    public function download(string $ksefNumber): string
    {
        if (! KsefNumber::isValid($ksefNumber)) {
            throw new InvalidArgumentException('The invoice to download needs a valid KSeF number.');
        }

        $path = '/invoices/ksef/'.rawurlencode($ksefNumber);
        $response = $this->api->authorized(fn (string $bearer): ResponseInterface => $this->api->raw(
            'GET',
            $path,
            null,
            ['Accept' => 'application/xml', 'X-Error-Format' => 'problem-details'],
            $bearer,
        ));
        $xml = (string) $response->getBody();

        $expected = $response->getHeaderLine('x-ms-meta-hash');
        if ($expected !== '' && ! hash_equals($expected, base64_encode(hash('sha256', $xml, true)))) {
            throw new UnexpectedResponse(sprintf('%s for the invoice %s was answered with a file whose SHA-256 digest does not match its x-ms-meta-hash header.', self::DOWNLOAD_OPERATION, $ksefNumber), $response->getStatusCode());
        }

        return $xml;
    }

    /**
     * @param  array<mixed>  $answer
     *
     * @throws UnexpectedResponse
     */
    private static function flag(array $answer, string $name): bool
    {
        $value = $answer[$name] ?? null;
        if (! is_bool($value)) {
            throw new UnexpectedResponse(sprintf('%s was answered without a boolean in "%s".', self::QUERY_OPERATION, $name));
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Transport\Http\ApiKey;
use IntrAgile\EInvoicing\Transport\Http\BaseUrl;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Connection settings for the e-invoice.be Peppol Access Point API.
 *
 * The API key is kept inside an ApiKey, so it stays out of var_export(), print_r(), var_dump()
 * and json_encode() output. e-invoice.be uses one host for test and production: the company the
 * key belongs to decides whether a send reaches the Peppol network.
 */
final readonly class EInvoiceBeConfig
{
    public const DEFAULT_BASE_URL = 'https://api.e-invoice.be';

    /** Bounds of the inbound look-back, in days. */
    private const LOOKBACK_DAYS = [1, 365];

    /** Bounds of the inbox pages read by one pending() call. */
    private const MAX_PAGES = [1, 100];

    /** The base URL without a trailing slash. */
    public string $baseUrl;

    private ApiKey $apiKey;

    private Redactor $redactor;

    /**
     * @param  int  $inboundLookbackDays  how far back pending() lists received documents, 1 to 365 days
     * @param  int  $inboundMaxPages  inbox pages of 100 read by one pending() call, 1 to 100
     *
     * @throws InvalidArgumentException when the API key is shorter than eight characters, the base
     *                                  URL is not an https:// URL without user info, query or
     *                                  fragment, or a limit is out of range
     */
    public function __construct(
        #[SensitiveParameter] string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        public int $inboundLookbackDays = 7,
        public int $inboundMaxPages = 10,
    ) {
        $this->apiKey = new ApiKey($apiKey, 'e-invoice.be API key');
        $this->baseUrl = BaseUrl::normalize($baseUrl, 'e-invoice.be base URL');
        if ($inboundLookbackDays < self::LOOKBACK_DAYS[0] || $inboundLookbackDays > self::LOOKBACK_DAYS[1]) {
            throw new InvalidArgumentException(sprintf(
                'The e-invoice.be inbound look-back must be %d to %d days.',
                self::LOOKBACK_DAYS[0],
                self::LOOKBACK_DAYS[1],
            ));
        }
        if ($inboundMaxPages < self::MAX_PAGES[0] || $inboundMaxPages > self::MAX_PAGES[1]) {
            throw new InvalidArgumentException(sprintf(
                'The e-invoice.be inbound page limit must be %d to %d pages.',
                self::MAX_PAGES[0],
                self::MAX_PAGES[1],
            ));
        }
        $this->redactor = new Redactor($this->secrets());
    }

    /**
     * The headers every API request carries: the API key as a bearer token and the accepted
     * response type. A signed download URL is fetched without them.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey->reveal(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * The values an HttpClient must keep out of exception messages and body excerpts.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        return [$this->apiKey->reveal()];
    }

    /**
     * The redactor for the API key.
     *
     * @internal
     */
    public function redactor(): Redactor
    {
        return $this->redactor;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => Redactor::REDACTED,
            'baseUrl' => $this->baseUrl,
            'inboundLookbackDays' => $this->inboundLookbackDays,
            'inboundMaxPages' => $this->inboundMaxPages,
        ];
    }
}

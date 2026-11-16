<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use IntrAgile\EInvoicing\Transport\Http\ApiKey;
use IntrAgile\EInvoicing\Transport\Http\BaseUrl;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Connection settings for the B2Brouter API.
 *
 * The API key is kept inside an ApiKey, so it stays out of var_export(), print_r(), var_dump()
 * and json_encode() output. B2Brouter routes a request to its sandbox or to production by the
 * key alone: sandbox keys start with "test_", and both use the same base URL.
 */
final readonly class B2BrouterConfig
{
    public const DEFAULT_BASE_URL = 'https://api.b2brouter.net';

    /** The API version the driver was built against, pinned so that account settings cannot change the response shapes. */
    public const API_VERSION = '2026-06-26';

    /** B2Brouter document type codes of the Peppol document types sent without configuration. */
    public const DEFAULT_DOCUMENT_TYPE_CODES = [
        PeppolBis3::INVOICE => 'xml.ubl.invoice.bis3',
        PeppolBis3::CREDIT_NOTE => 'xml.ubl.credit_note.bis3',
        XRechnungPeppol::UBL_INVOICE => 'xml.ubl.invoice.xrechnung.v3.0',
        XRechnungPeppol::UBL_CREDIT_NOTE => 'xml.ubl.credit_note.xrechnung.v3.0',
        XRechnungPeppol::CII_INVOICE => 'xml.cii.cross_industry_invoice.xrechnung.v3.0',
        XRechnungPeppol::UBL_INVOICE_EXTENSION => 'xml.ubl.invoice.xrechnung.extension.v3.0',
        XRechnungPeppol::CII_INVOICE_EXTENSION => 'xml.cii.cross_industry_invoice.xrechnung.extension.v3.0',
    ];

    /** Prefix of the keys that B2Brouter routes to the sandbox. */
    private const SANDBOX_KEY_PREFIX = 'test_';

    private const DOCUMENT_TYPE_CODE_PATTERN = '/^[a-z0-9._]{3,100}\z/';

    /** The base URL without a trailing slash. */
    public string $baseUrl;

    /** The B2Brouter account that sends and receives. */
    public int $accountId;

    /**
     * B2Brouter document type codes by Peppol document type identifier.
     *
     * @var array<string, string>
     */
    public array $documentTypeCodes;

    private ApiKey $apiKey;

    private Redactor $redactor;

    /**
     * @param  string|int  $accountId  a positive integer, given as an int or as digits
     * @param  array<string, string>  $documentTypeCodes  B2Brouter document type codes by Peppol
     *                                                    document type identifier, merged over
     *                                                    DEFAULT_DOCUMENT_TYPE_CODES
     *
     * @throws InvalidArgumentException when the API key is shorter than eight characters, the
     *                                  account id is not a positive integer, the base URL is not an
     *                                  https:// URL without user info, query or fragment, or a
     *                                  document type code is malformed
     */
    public function __construct(
        #[SensitiveParameter] string $apiKey,
        string|int $accountId,
        string $baseUrl = self::DEFAULT_BASE_URL,
        array $documentTypeCodes = [],
    ) {
        $this->apiKey = new ApiKey($apiKey, 'B2Brouter API key');
        $this->accountId = self::accountId($accountId);
        $this->baseUrl = BaseUrl::normalize($baseUrl, 'B2Brouter base URL');
        $this->documentTypeCodes = self::documentTypeCodes($documentTypeCodes);
        $this->redactor = new Redactor($this->secrets());
    }

    /**
     * Whether the key is a sandbox key, which B2Brouter never routes to production.
     */
    public function isSandboxKey(): bool
    {
        return str_starts_with($this->apiKey->reveal(), self::SANDBOX_KEY_PREFIX);
    }

    /**
     * The B2Brouter document type code for a Peppol document type identifier, or null when none
     * is configured.
     */
    public function documentTypeCode(string $documentType): ?string
    {
        return $this->documentTypeCodes[$documentType] ?? null;
    }

    /**
     * The headers every request carries: the API key, the pinned API version and the accepted
     * response type.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'X-B2B-API-Key' => $this->apiKey->reveal(),
            'X-B2B-API-Version' => self::API_VERSION,
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
            'accountId' => $this->accountId,
            'baseUrl' => $this->baseUrl,
            'documentTypeCodes' => $this->documentTypeCodes,
        ];
    }

    private static function accountId(string|int $accountId): int
    {
        // At most 18 digits, so that the value always fits a 64-bit integer.
        if (is_string($accountId) && preg_match('/^[0-9]{1,18}\z/', $accountId) === 1) {
            $accountId = (int) $accountId;
        }
        if (! is_int($accountId) || $accountId < 1) {
            throw new InvalidArgumentException('The B2Brouter account id must be a positive integer, given as an int or as digits.');
        }

        return $accountId;
    }

    /**
     * @param  array<mixed>  $codes
     * @return array<string, string>
     */
    private static function documentTypeCodes(array $codes): array
    {
        foreach ($codes as $documentType => $code) {
            if (! is_string($documentType) || trim($documentType) === '') {
                throw new InvalidArgumentException('Each B2Brouter document type code must be keyed by a Peppol document type identifier.');
            }
            if (! is_string($code) || preg_match(self::DOCUMENT_TYPE_CODE_PATTERN, $code) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'The B2Brouter document type code for "%s" must be 3 to 100 characters of a-z, 0-9, "." and "_".',
                    $documentType,
                ));
            }
        }

        return array_replace(self::DEFAULT_DOCUMENT_TYPE_CODES, $codes);
    }
}

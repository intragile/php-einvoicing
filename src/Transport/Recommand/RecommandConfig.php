<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use Closure;
use IntrAgile\EInvoicing\Transport\Http\ApiKey;
use IntrAgile\EInvoicing\Transport\Http\BaseUrl;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Connection settings for the Recommand Peppol access point API.
 *
 * The API key id and secret are kept inside a closure, so they stay out of var_export(),
 * print_r(), var_dump() and json_encode() output; the class exposes only the Authorization header
 * built from them and a helper that removes them from text.
 */
final readonly class RecommandConfig
{
    public const DEFAULT_BASE_URL = 'https://app.recommand.eu';

    /** Shortest accepted API key id and secret; Recommand issues much longer ones. */
    public const MIN_CREDENTIAL_LENGTH = ApiKey::MIN_LENGTH;

    /** The base URL without a trailing slash. */
    public string $baseUrl;

    /** @var Closure(): array{string, string} */
    private Closure $credentials;

    private Redactor $redactor;

    /** The Recommand company id of the sending company, without surrounding whitespace. */
    public string $companyId;

    /**
     * @throws InvalidArgumentException when the API key id or secret is shorter than eight
     *                                  characters, the company id is empty or the base URL is not
     *                                  an https:// URL
     */
    public function __construct(
        #[SensitiveParameter] string $apiKeyId,
        #[SensitiveParameter] string $secret,
        string $companyId,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        // The values are never quoted: even a wrong one may be a real credential.
        if (strlen(trim($apiKeyId)) < self::MIN_CREDENTIAL_LENGTH || strlen(trim($secret)) < self::MIN_CREDENTIAL_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The Recommand API key id and secret must each be at least %d characters long.',
                self::MIN_CREDENTIAL_LENGTH,
            ));
        }
        $this->companyId = trim($companyId);
        if ($this->companyId === '') {
            throw new InvalidArgumentException('The Recommand company id must not be empty.');
        }
        $this->baseUrl = BaseUrl::normalize($baseUrl, 'Recommand base URL');
        $this->credentials = static fn (): array => [$apiKeyId, $secret];
        // The pair's Base64 form, which the Authorization header carries, is one of its variants.
        $this->redactor = new Redactor([$apiKeyId.':'.$secret, $apiKeyId, $secret]);
    }

    /**
     * The HTTP client with the API key id and secret added to the secrets it keeps out of
     * exception messages and body excerpts.
     */
    public function redactingClient(HttpClient $http): HttpClient
    {
        return $http->withSecrets(($this->credentials)());
    }

    /**
     * The value of the HTTP Basic Authorization header.
     */
    public function authorization(): string
    {
        [$apiKeyId, $secret] = ($this->credentials)();

        return 'Basic '.base64_encode($apiKeyId.':'.$secret);
    }

    /**
     * The text with the API key id, the secret and the encoded credentials (the Basic pair, and
     * each credential percent-encoded and Base64-encoded) replaced by "[redacted]", for text that
     * came back from the network.
     */
    public function redact(string $text): string
    {
        return $this->redactor->redact($text);
    }

    /**
     * The redactor for the API key id, the secret and the Basic pair.
     *
     * @internal
     */
    public function redactor(): Redactor
    {
        return $this->redactor;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKeyId' => Redactor::REDACTED,
            'secret' => Redactor::REDACTED,
            'companyId' => $this->companyId,
            'baseUrl' => $this->baseUrl,
        ];
    }
}

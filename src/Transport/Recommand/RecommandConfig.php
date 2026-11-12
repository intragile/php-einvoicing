<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use Closure;
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

    /** The base URL without a trailing slash. */
    public string $baseUrl;

    /** @var Closure(): array{string, string} */
    private Closure $credentials;

    /**
     * @throws InvalidArgumentException when a value is empty or the base URL is not an https:// URL
     */
    public function __construct(
        #[SensitiveParameter] string $apiKeyId,
        #[SensitiveParameter] string $secret,
        /** The Recommand company id of the sending company. */
        public string $companyId,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        if (trim($apiKeyId) === '' || trim($secret) === '') {
            throw new InvalidArgumentException('The Recommand API key id and secret must not be empty.');
        }
        if (trim($companyId) === '') {
            throw new InvalidArgumentException('The Recommand company id must not be empty.');
        }
        $this->baseUrl = self::assertBaseUrl($baseUrl);
        $this->credentials = static fn (): array => [$apiKeyId, $secret];
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
     * The text with the API key id, the secret and the encoded credentials replaced by
     * "[redacted]", for text that came back from the network.
     */
    public function redact(string $text): string
    {
        [$apiKeyId, $secret] = ($this->credentials)();
        $pair = $apiKeyId.':'.$secret;
        // Longest first, so that the encoded pair and the pair go before their parts.
        $secrets = [base64_encode($pair), $pair, $secret, $apiKeyId];
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($secrets, '[redacted]', $text);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKeyId' => '[redacted]',
            'secret' => '[redacted]',
            'companyId' => $this->companyId,
            'baseUrl' => $this->baseUrl,
        ];
    }

    private static function assertBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (
            $parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(sprintf(
                'The Recommand base URL "%s" must be an https:// URL without user info, query or fragment.',
                preg_replace('#//[^/@]*@#', '//[redacted]@', $baseUrl),
            ));
        }

        return rtrim($baseUrl, '/');
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use RuntimeException;

/**
 * Sends documents to a KoSIT validator daemon (docker/kosit) and returns its verdict.
 *
 * The daemon validates the body of a POST to its root URL. It answers 200 with the report when
 * the document is accepted, 406 when it is rejected and 422 when the body is not well-formed XML
 * (still with a report); any other status is an error of the harness.
 */
final readonly class KositClient
{
    public const ENVIRONMENT_VARIABLE = 'KOSIT_ORACLE_URL';

    private const TIMEOUT_SECONDS = 60.0;

    public function __construct(private string $url) {}

    /**
     * The client for the daemon named by KOSIT_ORACLE_URL, or null when the variable is unset.
     */
    public static function fromEnvironment(): ?self
    {
        $url = getenv(self::ENVIRONMENT_VARIABLE);

        return is_string($url) && $url !== '' ? new self($url) : null;
    }

    public function check(string $xml): KositReport
    {
        [$status, $body] = $this->post($xml);
        if (! in_array($status, [200, 406, 422], true)) {
            throw new RuntimeException(sprintf('The KoSIT daemon answered HTTP %d: %s', $status, substr($body, 0, 500)));
        }

        return KositReport::fromXml($body, $status);
    }

    /**
     * Posts a raw body and returns the HTTP status and the response body unchecked.
     *
     * @return array{int, string}
     */
    public function post(string $body, string $contentType = 'application/xml'): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => ['Content-Type: '.$contentType],
                'content' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
        ]);
        $stream = @fopen($this->url, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException('The KoSIT daemon at '.$this->url.' is not reachable.');
        }
        try {
            $response = stream_get_contents($stream);
            $meta = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }
        if ($response === false || $meta['timed_out']) {
            throw new RuntimeException('Reading the KoSIT answer failed or timed out.');
        }

        return [self::status($meta['wrapper_data'] ?? []), $response];
    }

    private static function status(mixed $headers): int
    {
        $status = 0;
        if (is_array($headers)) {
            foreach ($headers as $header) {
                // The last status line wins, after any interim response.
                if (is_string($header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                    $status = (int) $match[1];
                }
            }
        }
        if ($status === 0) {
            throw new RuntimeException('The KoSIT answer has no HTTP status line.');
        }

        return $status;
    }
}

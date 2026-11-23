<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A minimal PSR-18 client on PHP's http stream wrapper, for opt-in network tests only: no
 * redirects, a fixed timeout, certificate verification on.
 */
final class StreamHttpClient implements ClientInterface
{
    /**
     * @param  array<string, mixed>  $ssl  extra "ssl" stream context options, such as a client
     *                                     certificate or a CA file; peer verification stays on
     */
    public function __construct(
        private readonly float $timeoutSeconds = 20.0,
        private readonly array $ssl = [],
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name.': '.$value;
            }
        }
        $context = stream_context_create([
            'http' => [
                'method' => $request->getMethod(),
                'header' => $headers,
                'content' => (string) $request->getBody(),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true] + $this->ssl,
        ]);

        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        $body = false;
        $responseHeaders = [];
        try {
            $handle = fopen((string) $request->getUri(), 'rb', false, $context);
            if ($handle !== false) {
                $meta = stream_get_meta_data($handle);
                $responseHeaders = array_values(array_filter((array) ($meta['wrapper_data'] ?? []), is_string(...)));
                $body = stream_get_contents($handle);
                fclose($handle);
            }
        } finally {
            restore_error_handler();
        }
        if ($body === false || $responseHeaders === []) {
            throw new class($error ?? 'The request failed.', $request) extends RuntimeException implements NetworkExceptionInterface
            {
                public function __construct(string $message, private readonly RequestInterface $request)
                {
                    parent::__construct($message);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }

        $status = preg_match('/\AHTTP\/\S+\s+(\d{3})/', $responseHeaders[0], $match) === 1 ? (int) $match[1] : 0;
        $parsed = [];
        foreach (array_slice($responseHeaders, 1) as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $parsed[trim($name)][] = trim($value);
        }

        return new Response($status, $parsed, $body);
    }
}

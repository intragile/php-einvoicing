<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * A client of Latarnia, the public KSeF availability service of the Polish Ministry of Finance.
 * It reports the current availability of KSeF and the announced maintenance windows and failures,
 * which decide the offline invoicing modes and their delivery deadlines.
 *
 * Latarnia needs no authentication. A failed request, a server error (5xx) or a rate limit (429)
 * is thrown as TransportUnavailable, so the caller may repeat it later; any other unsuccessful
 * answer, and a body that does not have the documented shape, is thrown as UnexpectedResponse.
 * Nothing is repeated here. Timeouts of a single request belong to the injected PSR-18 client.
 */
final class Latarnia
{
    private readonly string $baseUrl;

    /**
     * @param  string  $baseUrl  an https:// URL with a host, with or without a trailing slash
     *
     * @throws InvalidArgumentException when the base URL is not such a URL, or carries user info,
     *                                  a query or a fragment
     */
    public function __construct(string $baseUrl, private readonly HttpClient $http)
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
            throw new InvalidArgumentException('The Latarnia base URL must be an https:// URL with a host and without user info, query or fragment.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * The client for the Latarnia instance of a KSeF environment.
     *
     * @throws LogicException for DEMO, for which the Ministry of Finance publishes no Latarnia;
     *                        pass a base URL to the constructor instead
     */
    public static function forEnvironment(KsefEnvironment $environment, HttpClient $http): self
    {
        $baseUrl = $environment->latarniaBaseUrl()
            ?? throw new LogicException(sprintf('There is no Latarnia for the KSeF %s environment; pass a base URL to the Latarnia constructor instead.', strtoupper($environment->value)));

        return new self($baseUrl, $http);
    }

    /**
     * The current availability of KSeF and the messages it follows from.
     *
     * @throws TransportUnavailable when Latarnia cannot be reached or is unavailable
     * @throws UnexpectedResponse when the answer is not a status object of the documented shape
     * @throws TransportException for any other transport failure
     */
    public function status(): LatarniaStatus
    {
        [$response, $data] = $this->get('/status');
        if (array_is_list($data) && $data !== []) {
            throw UnexpectedResponse::forResponse($response, 'The Latarnia status answer is not a JSON object');
        }
        $status = $data['status'] ?? null;
        $availability = is_string($status) ? KsefAvailability::tryFrom($status) : null;
        if ($availability === null) {
            throw UnexpectedResponse::forResponse($response, sprintf(
                'The Latarnia status answer carries an unknown status %s',
                is_string($status) ? '"'.$status.'"' : 'value',
            ));
        }
        $messages = $data['messages'] ?? [];
        if (! is_array($messages) || ! array_is_list($messages)) {
            throw UnexpectedResponse::forResponse($response, 'The Latarnia status messages are not a JSON array');
        }

        return new LatarniaStatus($availability, self::messageList($messages, $response));
    }

    /**
     * Every current message: announced and running maintenance windows and failures, and those
     * that ended in the last 30 days, newest publication first.
     *
     * @return list<LatarniaMessage>
     *
     * @throws TransportUnavailable when Latarnia cannot be reached or is unavailable
     * @throws UnexpectedResponse when the answer is not a message array of the documented shape
     * @throws TransportException for any other transport failure
     */
    public function messages(): array
    {
        [$response, $data] = $this->get('/messages');
        if (! array_is_list($data)) {
            throw UnexpectedResponse::forResponse($response, 'The Latarnia messages answer is not a JSON array');
        }

        return self::messageList($data, $response);
    }

    /**
     * Sends a GET to a path under the base URL and returns the response and its decoded body.
     *
     * @return array{ResponseInterface, array<mixed>}
     *
     * @throws TransportException
     */
    private function get(string $path): array
    {
        $operation = sprintf('The Latarnia request GET %s', $path);
        $response = $this->http->send('GET', $this->baseUrl.$path, ['Accept' => 'application/json']);
        $status = $response->getStatusCode();
        if ($status === 429 || ($status >= 500 && $status <= 599)) {
            throw new TransportUnavailable(sprintf('%s failed: Latarnia is unavailable (HTTP %d).', $operation, $status));
        }
        if ($status >= 300 && $status <= 399) {
            // No body excerpt: a redirect body only repeats the Location.
            throw UnexpectedResponse::forResponse($response, sprintf('%s failed: Latarnia answered with a redirect, which is not followed', $operation), '');
        }
        if ($status < 200 || $status > 299) {
            throw UnexpectedResponse::forResponse($response, sprintf('%s failed with an unexpected answer from Latarnia', $operation));
        }

        $body = (string) $response->getBody();
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UnexpectedResponse::forResponse($response, sprintf('%s was answered with a body that is not JSON', $operation), $body, $exception);
        }
        if (! is_array($data)) {
            throw UnexpectedResponse::forResponse($response, sprintf('%s was answered with a body that is not a JSON object or array', $operation), $body);
        }

        return [$response, $data];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<LatarniaMessage>
     *
     * @throws UnexpectedResponse
     */
    private static function messageList(array $items, ResponseInterface $response): array
    {
        $messages = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw UnexpectedResponse::forResponse($response, sprintf('The Latarnia message at index %d is not a JSON object', $index));
            }
            try {
                $messages[] = LatarniaMessage::fromArray($item);
            } catch (UnexpectedValueException $exception) {
                throw UnexpectedResponse::forResponse($response, sprintf('The Latarnia message at index %d cannot be read: %s', $index, $exception->getMessage()), null, $exception);
            }
        }

        return $messages;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Decodes JSON response bodies.
 */
final class JsonResponse
{
    /**
     * The body decoded as a JSON object or array; objects become associative arrays.
     *
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not a JSON object or array
     */
    public static function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UnexpectedResponse::forResponse($response, 'The response body is not valid JSON', $body, $exception);
        }
        if (! is_array($data)) {
            throw UnexpectedResponse::forResponse($response, 'The response body is not a JSON object or array', $body);
        }

        return $data;
    }
}

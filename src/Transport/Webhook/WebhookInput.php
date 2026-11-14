<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Webhook;

use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use JsonException;
use Throwable;

/**
 * Reads the headers and the JSON body of a webhook request, shared by the webhook parsers.
 *
 * @internal
 */
final class WebhookInput
{
    /**
     * The value of a header, matched case-insensitively, or null when it is missing or present
     * more than once, whether the values are identical or not. A repeated signature header is a
     * sign of tampering or a misconfigured proxy, so it is never collapsed into one value.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public static function header(array $headers, string $name): ?string
    {
        $values = [];
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                array_push($values, ...(is_array($value) ? $value : [$value]));
            }
        }

        return count($values) === 1 ? $values[0] : null;
    }

    /**
     * The decoded JSON object of a notification body.
     *
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not valid JSON or not a JSON object
     */
    public static function object(string $rawBody, string $provider): array
    {
        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::malformed($provider, 'is not valid JSON', $rawBody, $exception);
        }
        // Decoded to an array, "{}" and "[]" look the same; only a body that opens with a brace is
        // an object.
        if (! is_array($data) || ! str_starts_with(ltrim($rawBody, " \t\n\r"), '{')) {
            throw self::malformed($provider, 'is not a JSON object', $rawBody);
        }

        return $data;
    }

    /**
     * An UnexpectedResponse for a signed notification body this client cannot use, keeping the
     * start of the body for diagnosis.
     *
     * @param  string  $what  the problem, phrased to follow "The <provider> webhook notification"
     */
    public static function malformed(string $provider, string $what, string $rawBody, ?Throwable $previous = null): UnexpectedResponse
    {
        return new UnexpectedResponse(
            sprintf('The %s webhook notification %s.', $provider, $what),
            0,
            substr($rawBody, 0, UnexpectedResponse::EXCERPT_BYTES),
            $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use Closure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

/**
 * Verifies and parses webhook notifications sent by Recommand.
 *
 * Recommand signs each delivery with "X-Signature: sha256=<hex>", an HMAC-SHA256 of the raw
 * request body keyed with the webhook secret. Pass the body exactly as received: a body that was
 * decoded and encoded again no longer matches its signature. The secret is kept inside a closure,
 * so it stays out of var_export(), print_r(), var_dump() and json_encode() output.
 */
final class RecommandWebhook
{
    private const SIGNATURE_HEADER = 'X-Signature';

    private const IDEMPOTENCY_HEADER = 'X-Idempotency-Key';

    private const SIGNATURE_PREFIX = 'sha256=';

    /** @var Closure(): string */
    private readonly Closure $secret;

    /**
     * @throws InvalidArgumentException when the secret is empty
     */
    public function __construct(#[SensitiveParameter] string $secret)
    {
        if (trim($secret) === '') {
            throw new InvalidArgumentException('The Recommand webhook secret must not be empty.');
        }
        $this->secret = static fn (): string => $secret;
    }

    /**
     * Whether the signature header is a valid signature of the raw body. The header must start
     * with exactly "sha256=" followed by 64 hexadecimal digits in either case; the comparison
     * takes constant time. An empty body is never valid.
     */
    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        if ($rawBody === '' || $signatureHeader === null || ! str_starts_with($signatureHeader, self::SIGNATURE_PREFIX)) {
            return false;
        }
        $hex = substr($signatureHeader, strlen(self::SIGNATURE_PREFIX));
        if (preg_match('/\A[0-9a-fA-F]{64}\z/', $hex) !== 1) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, ($this->secret)()), strtolower($hex));
    }

    /**
     * The event of a signed notification. Header names are matched case-insensitively.
     *
     * Only document events are supported: a notification without a documentId, such as
     * "company.verification", raises UnexpectedResponse even when its signature is valid. Catch it
     * and still answer the delivery with 2xx when such events should be ignored rather than retried.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws AuthenticationFailed when the signature is missing or does not match the body
     * @throws UnexpectedResponse when the signed body is not a notification with an event type and a document id
     */
    public function parse(string $rawBody, array $headers): WebhookEvent
    {
        if (! $this->verify($rawBody, self::header($headers, self::SIGNATURE_HEADER))) {
            throw new AuthenticationFailed('The Recommand webhook notification is refused: its X-Signature header is missing or does not match the body.');
        }

        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::malformed('is not valid JSON', $rawBody, $exception);
        }
        if (! is_array($data)) {
            throw self::malformed('is not a JSON object', $rawBody);
        }

        $type = $data['eventType'] ?? null;
        if (! is_string($type) || $type === '') {
            throw self::malformed('has no eventType', $rawBody);
        }
        $documentId = $data['documentId'] ?? null;
        if (! is_string($documentId) || $documentId === '') {
            throw self::malformed('has no documentId', $rawBody);
        }
        $companyId = $data['companyId'] ?? null;

        // The failure belongs to one delivery; only a Peppol delivery is reported, as in the
        // document status.
        $peppolDelivery = ($data['channel'] ?? null) === 'peppol';
        if (array_key_exists('deliveryStatus', $data)) {
            $state = self::state($data['deliveryStatus']);
        } elseif ($peppolDelivery && array_key_exists('status', $data)) {
            $state = self::state($data['status']);
        } else {
            $state = null;
        }

        return new WebhookEvent(
            $type,
            $documentId,
            is_string($companyId) && $companyId !== '' ? $companyId : null,
            self::header($headers, self::IDEMPOTENCY_HEADER),
            $state,
            $peppolDelivery ? ResponseMapper::failureOf($data['failure'] ?? null) : null,
        );
    }

    /**
     * The delivery state of a status the event reports. A status event that reports null says
     * nothing about where delivery stands, so it is Unknown; Pending would claim that delivery
     * has not finished yet.
     */
    private static function state(mixed $status): DeliveryState
    {
        return $status === null ? DeliveryState::Unknown : ResponseMapper::stateOf($status);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]'];
    }

    /**
     * The value of a header, matched case-insensitively, or null when it is missing or carries
     * different values.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $values = [];
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                array_push($values, ...(is_array($value) ? $value : [$value]));
            }
        }
        $values = array_values(array_unique($values));

        return count($values) === 1 ? $values[0] : null;
    }

    private static function malformed(string $what, string $rawBody, ?JsonException $previous = null): UnexpectedResponse
    {
        return new UnexpectedResponse(
            sprintf('The Recommand webhook notification %s.', $what),
            0,
            substr($rawBody, 0, UnexpectedResponse::EXCERPT_BYTES),
            $previous,
        );
    }
}

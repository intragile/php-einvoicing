<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\EInvoiceBe;

use Closure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookEvent;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookInput;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookParser;
use InvalidArgumentException;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Verifies and parses webhook notifications sent by e-invoice.be.
 *
 * e-invoice.be signs each delivery with "X-Signature: sha256=<hex>", an HMAC-SHA256 keyed with the
 * webhook secret, computed not over the request body but over the canonical JSON string of the
 * event (see CanonicalJson). The body is therefore decoded and its canonical form rebuilt before
 * the comparison; a body whose canonical form cannot be rebuilt, such as one carrying a number
 * with a fraction, is refused. The secret is kept inside a closure, so it stays out of
 * var_export(), print_r(), var_dump() and json_encode() output.
 *
 * Deliveries may arrive more than once and in any order: always read the document state rather
 * than trusting the order of events.
 */
final class EInvoiceBeWebhook implements WebhookParser
{
    private const PROVIDER = 'e-invoice.be';

    private const SIGNATURE_HEADER = 'X-Signature';

    private const SIGNATURE_PATTERN = '/\Asha256=([0-9a-fA-F]{64})\z/';

    /** @var Closure(): string */
    private readonly Closure $secret;

    /**
     * @throws InvalidArgumentException when the secret is empty
     */
    public function __construct(#[SensitiveParameter] string $secret)
    {
        if (trim($secret) === '') {
            throw new InvalidArgumentException('The e-invoice.be webhook secret must not be empty.');
        }
        $this->secret = static fn (): string => $secret;
    }

    /**
     * Whether the X-Signature header is a valid signature of the event in the body. The header
     * must appear exactly once and read exactly "sha256=" followed by 64 hexadecimal digits in
     * either case; the HMAC covers the canonical JSON string of the body and the comparison takes
     * constant time. A body without a canonical form, including an empty one, is never valid.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public function verify(string $rawBody, array $headers): bool
    {
        return $this->check($rawBody, $headers) === null;
    }

    /**
     * The event of a signed notification. Header names are matched case-insensitively.
     *
     * "document.received" is DocumentReceived. "document.sent" is DeliveryChanged with the state
     * Delivered. "document.sent.failed" is DeliveryChanged without a state, so read the status,
     * which also reports the failure. "document.received.failed" and any other type are Other. The
     * document id is data.document_id; the account id is tenant_id, or null when the notification
     * names none.
     *
     * The deduplication key is "<type>:<document id>": the event id changes with every attempt of
     * one delivery, so it cannot be used.
     *
     * Only document events are supported: a notification without a data.document_id raises
     * UnexpectedResponse even when its signature is valid. Catch it and still answer the delivery
     * with 2xx when such events should be ignored rather than retried.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws AuthenticationFailed when the signature is missing or wrong, or the body has no canonical form
     * @throws UnexpectedResponse when the signed body is not a notification with an event type and a document id
     */
    public function parse(string $rawBody, array $headers): WebhookEvent
    {
        $refusal = $this->check($rawBody, $headers);
        if ($refusal !== null) {
            throw new AuthenticationFailed('The e-invoice.be webhook notification is refused: '.$refusal);
        }

        $notification = WebhookInput::object($rawBody, self::PROVIDER);

        $type = $notification['type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            throw WebhookInput::malformed(self::PROVIDER, 'has no event type', $rawBody);
        }
        $data = $notification['data'] ?? null;
        $documentId = is_array($data) && ! array_is_list($data) ? ($data['document_id'] ?? null) : null;
        if (! is_string($documentId) || trim($documentId) === '') {
            throw WebhookInput::malformed(self::PROVIDER, 'has no document id (data.document_id)', $rawBody);
        }
        $tenantId = $notification['tenant_id'] ?? null;

        [$kind, $state] = match ($type) {
            'document.received' => [WebhookKind::DocumentReceived, null],
            'document.sent' => [WebhookKind::DeliveryChanged, DeliveryState::Delivered],
            'document.sent.failed' => [WebhookKind::DeliveryChanged, null],
            default => [WebhookKind::Other, null],
        };

        return new WebhookEvent(
            $kind,
            $type,
            $documentId,
            is_string($tenantId) && trim($tenantId) !== '' ? $tenantId : null,
            $type.':'.$documentId,
            $state,
            null,
        );
    }

    /**
     * Why the notification is not authentic, or null when its signature is valid.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    private function check(string $rawBody, array $headers): ?string
    {
        $signatureHeader = WebhookInput::header($headers, self::SIGNATURE_HEADER);
        if ($signatureHeader === null || preg_match(self::SIGNATURE_PATTERN, $signatureHeader, $match) !== 1) {
            return 'its X-Signature header is missing, repeated or not "sha256=" followed by 64 hexadecimal digits.';
        }
        try {
            $canonical = CanonicalJson::fromRaw($rawBody);
        } catch (UnexpectedValueException) {
            return 'its body has no canonical JSON form (it is not valid JSON or carries a number with a fraction or an out-of-range integer), so its X-Signature header cannot be checked.';
        }

        return hash_equals(hash_hmac('sha256', $canonical, ($this->secret)()), strtolower($match[1]))
            ? null
            : 'its X-Signature header does not match the body.';
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]'];
    }
}

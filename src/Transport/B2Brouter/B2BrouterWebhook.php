<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\LifecycleMapping;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookEvent;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookInput;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookParser;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Verifies and parses webhook notifications sent by B2Brouter.
 *
 * B2Brouter signs each delivery with "X-B2Brouter-Signature: t=<unix time>,s=<hex>", an
 * HMAC-SHA256 of "<t>.<raw request body>" keyed with the webhook secret. Pass the body exactly as
 * received: a body that was decoded and encoded again no longer matches its signature. The secret
 * is kept inside a closure, so it stays out of var_export(), print_r(), var_dump() and
 * json_encode() output.
 *
 * The signed timestamp is not checked by default. B2Brouter retries a delivery for about a week
 * and treats a 4xx answer as final, and it does not say whether a retry is signed again, so a
 * tolerance could refuse a legitimate late retry for good. Set one only when replayed deliveries
 * matter more than that.
 */
final class B2BrouterWebhook implements WebhookParser
{
    private const PROVIDER = 'B2Brouter';

    private const SIGNATURE_HEADER = 'X-B2Brouter-Signature';

    private const DELIVERY_ID_HEADER = 'X-B2Brouter-Delivery-Id';

    /** The event of a ledger (e-reporting transmission) changing state. */
    private const LEDGER_STATE_CHANGE = 'ledger.state_change';

    private const SIGNATURE_PATTERN = '/\At=([0-9]{1,12}),s=([0-9a-fA-F]{64})\z/';

    /** @var Closure(): string */
    private readonly Closure $secret;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /**
     * @param  int|null  $toleranceSeconds  how far the signed timestamp may lie from the current
     *                                      time, in either direction; null checks no timestamp
     * @param  (Closure(): DateTimeImmutable)|null  $clock  the current time, for tests
     *
     * @throws InvalidArgumentException when the secret is empty or the tolerance is not positive
     */
    public function __construct(
        #[SensitiveParameter] string $secret,
        private readonly ?int $toleranceSeconds = null,
        ?Closure $clock = null,
    ) {
        if (trim($secret) === '') {
            throw new InvalidArgumentException('The B2Brouter webhook secret must not be empty.');
        }
        if ($toleranceSeconds !== null && $toleranceSeconds < 1) {
            throw new InvalidArgumentException('The B2Brouter webhook timestamp tolerance must be a positive number of seconds, or null to check no timestamp.');
        }
        $this->secret = static fn (): string => $secret;
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * Whether the X-B2Brouter-Signature header is a valid signature of the raw body. The header
     * must appear exactly once and read exactly "t=<1 to 12 digits>,s=<64 hexadecimal digits in
     * either case>"; the comparison takes constant time. With a tolerance, the timestamp must lie
     * within it. An empty body is never valid.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public function verify(string $rawBody, array $headers): bool
    {
        $signatureHeader = WebhookInput::header($headers, self::SIGNATURE_HEADER);
        if ($rawBody === '' || $signatureHeader === null || preg_match(self::SIGNATURE_PATTERN, $signatureHeader, $match) !== 1) {
            return false;
        }
        [, $timestamp, $hex] = $match;

        $valid = hash_equals(hash_hmac('sha256', $timestamp.'.'.$rawBody, ($this->secret)()), strtolower($hex));

        return $valid && ($this->toleranceSeconds === null
            || abs(($this->clock)()->getTimestamp() - (int) $timestamp) <= $this->toleranceSeconds);
    }

    /**
     * The event of a signed notification. Header names are matched case-insensitively.
     *
     * "issued_invoice.state_change" is DeliveryChanged, with the delivery state of data.state
     * (null when the notification has no state, so read the status); the failure is always null,
     * because the notification carries no error code. "received_invoice.created" is
     * DocumentReceived; any other event with an invoice id is Other. The account id is
     * data.account_id, or null when the notification names none.
     *
     * For an account that sends and receives French invoices, the lifecycle status is the French
     * status (CDV code) that data.state stands for: for "issued_invoice.state_change" as the
     * seller's status, for "received_invoice.state_change" (kind Other) as the buyer's response.
     * It is null for any other event, and for a state that stands for no status.
     *
     * The deduplication key is the X-B2Brouter-Delivery-Id header, the same on every attempt of
     * one delivery. When it is missing, blank or repeated, the key is
     * "<code>:<invoice id>:<data.event_id>", or "<code>:<invoice id>:<data.state>" (empty without
     * a state) when the notification has no event id either.
     *
     * "ledger.state_change" reports a deposited Flux 10 e-reporting file (a B2Brouter ledger)
     * changing state. It is Other, its document id is the ledger id and it carries no delivery
     * state, failure or lifecycle status: read the state with B2BrouterFrance::eReportStatus().
     * Two shapes are read: {"code":"ledger.state_change","data":{"object":{"object":"ledger",
     * "id":…}}} with the account id in data.account_id, and the documented
     * {"account_id":…,"object":{"object":"ledger","id":…,"state":…}} without an event code, which
     * counts as "ledger.state_change" because its object is a ledger. Without a usable delivery id
     * header its deduplication key is "ledger.state_change:<ledger id>:<event id>", or
     * "ledger.state_change:<ledger id>:<ledger state>" (empty without a state).
     *
     * Apart from ledger events, only invoice events are supported: a notification without a
     * data.invoice_id raises UnexpectedResponse even when its signature is valid. Catch it and
     * still answer the delivery with 2xx when such events should be ignored rather than retried.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws AuthenticationFailed when the signature is missing or does not match the body
     * @throws UnexpectedResponse when the signed body is not a notification with an event code and
     *                            an invoice id, nor a ledger notification with a ledger id
     */
    public function parse(string $rawBody, array $headers): WebhookEvent
    {
        if (! $this->verify($rawBody, $headers)) {
            throw new AuthenticationFailed(sprintf(
                'The B2Brouter webhook notification is refused: its X-B2Brouter-Signature header is missing, malformed or does not match the body%s.',
                $this->toleranceSeconds === null ? '' : ', or its timestamp is outside the tolerance',
            ));
        }

        $notification = WebhookInput::object($rawBody, self::PROVIDER);

        $code = $notification['code'] ?? null;
        if (! is_string($code) || trim($code) === '') {
            if (! self::isLedger($notification['object'] ?? null)) {
                throw WebhookInput::malformed(self::PROVIDER, 'has no event code', $rawBody);
            }
            $code = self::LEDGER_STATE_CHANGE;
        }
        if ($code === self::LEDGER_STATE_CHANGE) {
            return self::ledgerEvent($notification, $headers, $rawBody);
        }
        $data = $notification['data'] ?? null;
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw WebhookInput::malformed(self::PROVIDER, 'has no data object', $rawBody);
        }
        $invoiceId = self::positiveId($data['invoice_id'] ?? null)
            ?? throw WebhookInput::malformed(self::PROVIDER, 'has no invoice id (data.invoice_id)', $rawBody);

        $kind = match ($code) {
            'issued_invoice.state_change' => WebhookKind::DeliveryChanged,
            'received_invoice.created' => WebhookKind::DocumentReceived,
            default => WebhookKind::Other,
        };
        // A state that is not a string says nothing about the delivery, like a missing one.
        $state = $kind === WebhookKind::DeliveryChanged && is_string($data['state'] ?? null)
            ? ResponseMapper::stateOf($data['state'])
            : null;
        $platformState = is_string($data['state'] ?? null) ? $data['state'] : null;
        $lifecycleStatus = match (true) {
            $platformState === null => null,
            $code === 'issued_invoice.state_change' => LifecycleMapping::forIssued($platformState),
            $code === 'received_invoice.state_change' => LifecycleMapping::forReceived($platformState),
            default => null,
        };

        return new WebhookEvent(
            $kind,
            $code,
            $invoiceId,
            self::accountId($data['account_id'] ?? null),
            self::deduplicationKey($headers, $code, $invoiceId, $data),
            $state,
            null,
            $lifecycleStatus,
        );
    }

    /**
     * The event of a ledger notification: the ledger is data.object, or the top-level object of
     * a notification without data.object; the account id is account_id, or data.account_id.
     *
     * @param  array<mixed>  $notification
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws UnexpectedResponse when the notification has no ledger with an id
     */
    private static function ledgerEvent(array $notification, array $headers, string $rawBody): WebhookEvent
    {
        $data = $notification['data'] ?? null;
        $data = is_array($data) ? $data : [];
        $ledger = $data['object'] ?? $notification['object'] ?? null;
        if (! is_array($ledger) || array_is_list($ledger)) {
            throw WebhookInput::malformed(self::PROVIDER, 'is a ledger event without a ledger object', $rawBody);
        }
        $ledgerId = self::positiveId($ledger['id'] ?? null)
            ?? throw WebhookInput::malformed(self::PROVIDER, 'has no ledger id (object.id)', $rawBody);

        $deduplicationData = ['event_id' => $data['event_id'] ?? $notification['event_id'] ?? null, 'state' => $ledger['state'] ?? null];

        return new WebhookEvent(
            WebhookKind::Other,
            self::LEDGER_STATE_CHANGE,
            $ledgerId,
            self::accountId($notification['account_id'] ?? $data['account_id'] ?? null),
            self::deduplicationKey($headers, self::LEDGER_STATE_CHANGE, $ledgerId, $deduplicationData),
            null,
            null,
        );
    }

    /**
     * Whether the value is a B2Brouter object whose "object" member names a ledger.
     */
    private static function isLedger(mixed $object): bool
    {
        return is_array($object) && ($object['object'] ?? null) === 'ledger';
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     * @param  array<mixed>  $data
     */
    private static function deduplicationKey(array $headers, string $code, string $documentId, array $data): string
    {
        $deliveryId = trim(WebhookInput::header($headers, self::DELIVERY_ID_HEADER) ?? '');
        if ($deliveryId !== '') {
            return $deliveryId;
        }
        $eventId = self::scalarText($data['event_id'] ?? null);
        if ($eventId !== null) {
            return $code.':'.$documentId.':'.$eventId;
        }

        return $code.':'.$documentId.':'.(self::scalarText($data['state'] ?? null) ?? '');
    }

    /**
     * A B2Brouter id, which is a positive integer, as a string, given as an int or as digits;
     * null otherwise.
     */
    private static function positiveId(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/^[1-9][0-9]{0,17}\z/', $value) === 1) {
            return $value;
        }

        return is_int($value) && $value > 0 ? (string) $value : null;
    }

    /**
     * The account id as a string when it is a non-blank string, trimmed, or a positive integer;
     * null otherwise.
     */
    private static function accountId(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) && $value > 0 ? (string) $value : null;
    }

    /**
     * A non-blank string or an integer as text, or null for anything else.
     */
    private static function scalarText(mixed $value): ?string
    {
        return match (true) {
            is_int($value) => (string) $value,
            is_string($value) && trim($value) !== '' => $value,
            default => null,
        };
    }

    /**
     * @return array<string, string|int|null>
     */
    public function __debugInfo(): array
    {
        return ['secret' => Redactor::REDACTED, 'toleranceSeconds' => $this->toleranceSeconds];
    }
}

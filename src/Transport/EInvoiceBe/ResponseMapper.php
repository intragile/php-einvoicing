<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\EInvoiceBe;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Timestamp;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps e-invoice.be API responses to transport results and exceptions. Unknown JSON fields are
 * ignored; every text taken from a response has the API key removed.
 *
 * @internal
 */
final class ResponseMapper
{
    private const PROVIDER = 'e-invoice.be';

    /**
     * The shape of an e-invoice.be document id, inferred from the published examples ("doc-…");
     * every id is checked against it before it becomes part of a URL.
     */
    private const DOCUMENT_ID_PATTERN = '/^doc-[A-Za-z0-9]{1,64}\z/';

    /** A lowercase or uppercase SHA-256 digest in hexadecimal. */
    private const SHA256_PATTERN = '/^[0-9a-fA-F]{64}\z/';

    /** The timeline event of a failed transmission. */
    private const SEND_FAILED = 'send_failed';

    /** The failure text of a FAILED document; e-invoice.be keeps the technical reason to itself. */
    private const SEND_FAILED_MESSAGE = 'transmission failed; e-invoice.be reports no technical reason';

    /** Bytes of a provider error message quoted in an exception message. */
    private const QUOTED_BYTES = 300;

    public function __construct(private readonly Redactor $redactor) {}

    /**
     * True when the value is an e-invoice.be document id.
     */
    public static function isDocumentId(mixed $value): bool
    {
        return is_string($value) && preg_match(self::DOCUMENT_ID_PATTERN, $value) === 1;
    }

    /**
     * The id of the draft a UBL upload created, or the exception its response stands for.
     *
     * @param  string  $subject  the invoice, as named in messages, for example 'invoice number "INV-1"'
     *
     * @throws OutcomeUnknown when e-invoice.be answered a 5xx status or a 201 answer cannot be
     *                        read; the draft may exist
     * @throws TransportException when the upload was refused
     */
    public function createdDraft(ResponseInterface $response, string $subject): string
    {
        $status = $response->getStatusCode();
        if ($status === 201) {
            // A 201 means the draft exists, so a body that cannot be read leaves the outcome open
            // rather than making it a failure that invites a resend.
            try {
                $id = $this->body($response)['id'] ?? null;
                if (! self::isDocumentId($id)) {
                    throw $this->unexpected($response, 'The e-invoice.be upload response has no document id of the form "doc-<letters and digits>"');
                }
            } catch (UnexpectedResponse $exception) {
                throw new OutcomeUnknown(
                    sprintf(
                        'e-invoice.be answered the upload of %s with HTTP 201, but the response cannot be read, so the draft id is not known: %s %s',
                        $subject,
                        $exception->getMessage(),
                        self::searchDraftsFor($subject),
                    ),
                    $exception,
                );
            }

            /** @var string $id */
            return $id;
        }

        $operation = sprintf('Uploading %s to e-invoice.be', $subject);
        if ($status === 401 || $status === 403) {
            throw new AuthenticationFailed(sprintf('%s failed: e-invoice.be refused the API key (HTTP %d).', $operation, $status));
        }
        if ($status === 415 || $status === 422) {
            throw $this->rejected($response, sprintf('e-invoice.be rejected %s (HTTP %d)', $subject, $status));
        }
        if ($status >= 500 && $status <= 599) {
            // The draft may have been created before the server error, or behind a failing gateway.
            throw new OutcomeUnknown(sprintf(
                '%s failed with an e-invoice.be server error (HTTP %d), which can come after the draft was created, so it may or may not exist. %s',
                $operation,
                $status,
                self::searchDraftsFor($subject),
            ));
        }

        throw $this->readFailure($response, $operation);
    }

    /**
     * The result of sending a draft, or the exception the send response stands for. An answer
     * that cannot be read keeps the draft id with an Unknown state, because the send was accepted.
     *
     * @throws OutcomeUnknown when e-invoice.be answered a 5xx status; the draft may have been sent
     * @throws TransportException when the send was refused
     */
    public function sendResult(ResponseInterface $response, string $draftId, string $subject): SendResult
    {
        $status = $response->getStatusCode();
        if ($status === 200) {
            try {
                $state = $this->body($response)['state'] ?? null;
            } catch (UnexpectedResponse) {
                $state = null;
            }

            return new SendResult($draftId, self::stateOf($state));
        }

        $draft = sprintf('draft "%s" of %s', $draftId, $subject);
        if ($status === 400 || $status === 406 || $status === 422) {
            throw $this->rejected($response, sprintf('e-invoice.be refused to send %s (HTTP %d)', $draft, $status));
        }
        if ($status === 409) {
            throw $this->rejected($response, sprintf(
                "e-invoice.be refused to send %s (HTTP 409): the sender is not one of the company's Peppol identifiers",
                $draft,
            ));
        }
        if ($status === 405) {
            throw $this->unexpected($response, sprintf(
                'e-invoice.be did not send %s: it is not in DRAFT or FAILED; read its state',
                $draft,
            ));
        }
        if ($status >= 500 && $status <= 599) {
            throw new OutcomeUnknown(sprintf(
                'Sending %s failed with an e-invoice.be server error (HTTP %d), so it may or may not have been sent: %s',
                $draft,
                $status,
                self::checkDraftBeforeResending(),
            ));
        }

        throw $this->readFailure($response, sprintf('Sending %s', $draft));
    }

    /**
     * The decoded document of a document response, or the exception its response stands for.
     *
     * @return array<mixed>
     *
     * @throws TransportException when the response is not a document
     */
    public function document(ResponseInterface $response, string $documentId): array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, sprintf('Querying the status of e-invoice.be document "%s"', $documentId));
        }

        return $this->body($response);
    }

    /**
     * The failure of a FAILED document and the time of its newest send_failed event, read from
     * the document's timeline; null when the timeline holds no send_failed event.
     *
     * @return array{DeliveryFailure, ?DateTimeImmutable}|null
     *
     * @throws TransportException when the response is not a timeline
     */
    public function sendFailure(ResponseInterface $response, string $documentId): ?array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, sprintf('Reading the timeline of e-invoice.be document "%s"', $documentId));
        }
        $events = $this->body($response)['events'] ?? null;
        if (! is_array($events) || ! array_is_list($events)) {
            throw $this->unexpected($response, 'The e-invoice.be timeline response has no event list');
        }

        $found = false;
        $newest = null;
        foreach ($events as $event) {
            if (! is_array($event) || ($event['event_type'] ?? null) !== self::SEND_FAILED) {
                continue;
            }
            $found = true;
            $time = Timestamp::parse($event['timestamp'] ?? null, microseconds: true);
            if ($time !== null && ($newest === null || $time > $newest)) {
                $newest = $time;
            }
        }
        if (! $found) {
            return null;
        }

        return [new DeliveryFailure(FailureCategory::Transport, self::SEND_FAILED_MESSAGE, self::SEND_FAILED), $newest];
    }

    /**
     * The received documents listed by one inbox page, in the order given, and whether another
     * page follows.
     *
     * @return array{list<InboundReference>, bool}
     *
     * @throws TransportException when the response is not an inbox page
     */
    public function inboxPage(ResponseInterface $response): array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, 'Listing the e-invoice.be inbox');
        }

        $body = $this->body($response);
        $items = $body['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw $this->unexpected($response, 'The e-invoice.be inbox response has no item list');
        }

        $references = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw $this->unexpected($response, 'The e-invoice.be inbox contains an entry that is not an object');
            }
            $id = $item['id'] ?? null;
            if (! self::isDocumentId($id)) {
                throw $this->unexpected($response, 'The e-invoice.be inbox contains a document without an id of the form "doc-<letters and digits>"');
            }
            /** @var string $id */
            $receivedAt = Timestamp::parse($item['created_at'] ?? null, microseconds: true)
                ?? throw $this->unexpected($response, sprintf('The e-invoice.be document "%s" has no valid created_at time (ISO 8601 with a time zone)', $id));

            // The inbox reports neither Peppol participant identifiers nor a Peppol document type.
            $references[] = new InboundReference($id, null, null, null, $receivedAt);
        }

        return [$references, ($body['has_next_page'] ?? false) === true];
    }

    /**
     * The download details of a document's UBL record: the signed URL, the participants and the
     * SHA-256 digest when the record carries one of that shape.
     *
     * The record holds the signed URL, which is a credential, so no exception built from a
     * successful record quotes its body.
     *
     * @return array{url: string, sender: ?ParticipantId, receiver: ?ParticipantId, sha256: ?string}
     *
     * @throws TransportException when the response is not a UBL record with an https signed URL
     */
    public function ublRecord(ResponseInterface $response, string $documentId): array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, sprintf('Reading the UBL record of e-invoice.be document "%s"', $documentId));
        }
        try {
            $record = JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw self::withoutBody($response, sprintf('The UBL record of e-invoice.be document "%s" is not JSON', $documentId));
        }

        $url = $record['signed_url'] ?? null;
        if (! is_string($url) || ! self::isHttpsUrl($url)) {
            throw self::withoutBody($response, sprintf(
                'The UBL record of e-invoice.be document "%s" has no signed download URL of the form https://host/path, so the document was not downloaded',
                $documentId,
            ));
        }
        $hash = $record['file_hash'] ?? null;

        return [
            'url' => $url,
            'sender' => self::participant($record['sender_peppol_scheme'] ?? null, $record['sender_peppol_id'] ?? null),
            'receiver' => self::participant($record['receiver_peppol_scheme'] ?? null, $record['receiver_peppol_id'] ?? null),
            'sha256' => is_string($hash) && preg_match(self::SHA256_PATTERN, $hash) === 1 ? strtolower($hash) : null,
        ];
    }

    /**
     * The body of a signed-URL download, or the exception its response stands for. Neither the
     * URL nor the body is quoted, as both may carry the signature.
     *
     * @throws TransportUnavailable when the storage host answered a 5xx status
     * @throws UnexpectedResponse when it answered any other status than 200
     */
    public function download(ResponseInterface $response, string $documentId): string
    {
        $status = $response->getStatusCode();
        if ($status === 200) {
            return (string) $response->getBody();
        }
        $operation = sprintf('Downloading e-invoice.be document "%s" from its signed URL', $documentId);
        if ($status >= 500 && $status <= 599) {
            throw new TransportUnavailable(sprintf('%s failed: the storage host is unavailable (HTTP %d).', $operation, $status));
        }

        throw self::withoutBody($response, sprintf('%s failed; the URL may have expired, so fetch the document again', $operation));
    }

    /**
     * Checks a downloaded document against the SHA-256 digest of its UBL record.
     *
     * @throws UnexpectedResponse when they differ
     */
    public static function assertDigest(string $xml, ?string $sha256, string $documentId): void
    {
        if ($sha256 !== null && ! hash_equals($sha256, hash('sha256', $xml))) {
            throw new UnexpectedResponse(sprintf(
                'The downloaded document of e-invoice.be document "%s" does not match the file hash of its UBL record (SHA-256 %s), so it was not accepted.',
                $documentId,
                $sha256,
            ), 200);
        }
    }

    /**
     * The delivery state of an e-invoice.be document state. A state this version does not know,
     * and RECEIVED, which belongs to an inbound document, are Unknown rather than an error.
     */
    public static function stateOf(mixed $state): DeliveryState
    {
        return match ($state) {
            'DRAFT', 'TRANSIT' => DeliveryState::Pending,
            'SENT' => DeliveryState::Delivered,
            'FAILED' => DeliveryState::Failed,
            default => DeliveryState::Unknown,
        };
    }

    /**
     * An UnexpectedResponse with the API key removed from its message and body excerpt.
     */
    public function sanitize(UnexpectedResponse $exception, ?string $body = null): UnexpectedResponse
    {
        return $this->redactor->sanitize($exception, $body);
    }

    /**
     * The sentence that tells how to find out whether an upload created a draft.
     */
    public static function searchDraftsFor(string $subject): string
    {
        return sprintf(
            'To avoid a duplicate, search the e-invoice.be drafts for %s before resending, and send an existing draft rather than uploading the document again.',
            $subject,
        );
    }

    /**
     * The sentence that tells what to do after a send whose outcome is not known.
     */
    public static function checkDraftBeforeResending(): string
    {
        return 'the draft exists; read its state, and send it again only when it is DRAFT or FAILED.';
    }

    private function readFailure(ResponseInterface $response, string $operation): TransportException
    {
        return ReadFailure::of($response, self::PROVIDER, $operation, $this->redactor);
    }

    private function unexpected(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return $this->redactor->unexpected($response, $reason);
    }

    /**
     * A DocumentRejected that quotes the provider's "detail" and keeps it, redacted, as its errors.
     */
    private function rejected(ResponseInterface $response, string $reason): DocumentRejected
    {
        try {
            $detail = JsonResponse::decode($response)['detail'] ?? null;
        } catch (UnexpectedResponse) {
            $detail = null;
        }

        return new DocumentRejected(
            sprintf('%s: %s', $reason, $this->quote($detail)),
            $detail === null ? [] : ['detail' => $this->redactor->redactValue($detail)],
        );
    }

    /**
     * The "detail" of an error response in quotes, redacted and shortened: the text of an
     * ErrorResponse, or "location: message" pairs of an HTTPValidationError.
     */
    private function quote(mixed $detail): string
    {
        $text = null;
        if (is_string($detail) && trim($detail) !== '') {
            $text = $detail;
        } elseif (is_array($detail)) {
            $messages = [];
            foreach ($detail as $error) {
                if (! is_array($error) || ! is_string($error['msg'] ?? null)) {
                    continue;
                }
                $location = is_array($error['loc'] ?? null)
                    ? implode('.', array_filter($error['loc'], static fn (mixed $part): bool => is_string($part) || is_int($part)))
                    : '';
                $messages[] = ($location === '' ? '' : $location.': ').$error['msg'];
            }
            $text = $messages === [] ? null : implode('; ', $messages);
        }
        if ($text === null) {
            return 'no details given.';
        }
        $text = $this->redactor->redact($text);
        if (strlen($text) > self::QUOTED_BYTES) {
            $text = mb_strcut($text, 0, self::QUOTED_BYTES, 'UTF-8').'...';
        }

        return '"'.$text.'"';
    }

    /**
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not JSON
     */
    private function body(ResponseInterface $response): array
    {
        try {
            $body = JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw $this->sanitize($exception, (string) $response->getBody());
        }
        if (array_is_list($body) && $body !== []) {
            throw $this->unexpected($response, 'The e-invoice.be response is a JSON array, not an object');
        }

        return $body;
    }

    /**
     * An UnexpectedResponse that keeps no body excerpt, for a response that may carry a credential.
     */
    private static function withoutBody(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse($response, $reason, '');
    }

    /**
     * True for an absolute https URL with a DNS host name and without user info or whitespace.
     */
    private static function isHttpsUrl(string $url): bool
    {
        if (preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);

        return $parts !== false
            && strtolower($parts['scheme'] ?? '') === 'https'
            && preg_match('/\A(?:[A-Za-z0-9-]+\.)*[A-Za-z0-9-]+\z/', $parts['host'] ?? '') === 1
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    /**
     * The participant of a scheme and value pair, or null when either is missing or the pair is
     * not an ISO 6523 participant identifier.
     */
    private static function participant(mixed $scheme, mixed $value): ?ParticipantId
    {
        if (! is_string($scheme) || ! is_string($value)) {
            return null;
        }
        try {
            return new ParticipantId($scheme, $value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}

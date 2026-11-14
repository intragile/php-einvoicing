<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\RecipientUnreachable;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\SendResult;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps Recommand API responses to transport results and exceptions. Unknown JSON fields are
 * ignored; every text taken from a response has the credentials removed.
 *
 * @internal
 */
final class ResponseMapper
{
    /** Number of provider error messages quoted in an exception message. */
    private const QUOTED_ERRORS = 5;

    public function __construct(private readonly RecommandConfig $config) {}

    /**
     * The result of a send, or the exception its response stands for.
     *
     * @throws OutcomeUnknown when Recommand answered any 5xx status except 503, or a 200 answer
     *                        cannot be read; the document may have been accepted
     * @throws TransportException when the response is not a successful send
     */
    public function sendResult(ResponseInterface $response, ParticipantId $receiver): SendResult
    {
        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw $this->sendFailure($response, $receiver);
        }

        // A 200 means the send went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            $data = $this->successBody($response);
            $documentId = $this->documentId($response, $data);
        } catch (UnexpectedResponse $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Recommand answered the document sent to %s with HTTP 200, but the response cannot be read, so Recommand may or may not have accepted it: %s Check the document list before resending to avoid a duplicate.',
                    $receiver->toString(),
                    $exception->getMessage(),
                ),
                $exception,
            );
        }
        [$failure] = $this->peppolDelivery($data);

        return new SendResult(
            $documentId,
            self::stateOf($data['deliveryStatus'] ?? null),
            self::optionalString($data['peppolMessageId'] ?? null),
            self::optionalString($data['envelopeId'] ?? null),
            $failure,
        );
    }

    /**
     * The delivery status of a document, or the exception its response stands for.
     *
     * @throws TransportException when the response is not a document
     */
    public function deliveryStatus(ResponseInterface $response, string $documentId): DeliveryStatus
    {
        $status = $response->getStatusCode();
        if ($status === 404) {
            throw $this->unexpected($response, sprintf('Recommand does not know the document "%s"', $documentId));
        }
        if ($status !== 200) {
            throw $this->commonFailure($response, sprintf('Querying the status of document "%s"', $documentId));
        }

        $data = $this->successBody($response);
        $document = $data['document'] ?? null;
        if (! is_array($document)) {
            throw $this->unexpected($response, 'The Recommand document response has no document');
        }
        [$failure, $changedAt] = $this->peppolDelivery($document);

        return new DeliveryStatus(
            $documentId,
            self::stateOf($document['deliveryStatus'] ?? null),
            $failure,
            $changedAt,
        );
    }

    /**
     * The unread incoming documents listed by an inbox response, in the order given.
     *
     * @return list<InboundReference>
     *
     * @throws TransportException when the response is not an inbox listing
     */
    public function inboundReferences(ResponseInterface $response): array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->commonFailure($response, 'Listing the Recommand inbox');
        }

        $documents = $this->successBody($response)['documents'] ?? null;
        if (! is_array($documents) || ! array_is_list($documents)) {
            throw $this->unexpected($response, 'The Recommand inbox response has no document list');
        }

        $references = [];
        foreach ($documents as $document) {
            if (! is_array($document)) {
                throw $this->unexpected($response, 'The Recommand inbox response lists a document that is not an object');
            }
            $references[] = $this->inboundReference($response, $document);
        }

        return $references;
    }

    /**
     * The reference and the XML exactly as stored by Recommand of a document response.
     *
     * @return array{InboundReference, string}
     *
     * @throws TransportException when the response is not a document with XML
     */
    public function inboundDocument(ResponseInterface $response, string $documentId): array
    {
        $status = $response->getStatusCode();
        if ($status === 404) {
            throw $this->unexpected($response, sprintf('Recommand does not know the document "%s"', $documentId));
        }
        if ($status !== 200) {
            throw $this->commonFailure($response, sprintf('Fetching document "%s"', $documentId));
        }

        $document = $this->successBody($response)['document'] ?? null;
        if (! is_array($document)) {
            throw $this->unexpected($response, 'The Recommand document response has no document');
        }
        $xml = $document['xml'] ?? null;
        if (! is_string($xml)) {
            throw $this->unexpected($response, sprintf('Recommand returned document "%s", but the document has no XML', $documentId));
        }

        return [$this->inboundReference($response, $document), $xml];
    }

    /**
     * Checks that a mark-as-read response reports success.
     *
     * @throws TransportException when it does not
     */
    public function acknowledged(ResponseInterface $response, string $documentId): void
    {
        $status = $response->getStatusCode();
        if ($status === 404) {
            throw $this->unexpected($response, sprintf('Recommand does not know the document "%s"', $documentId));
        }
        if ($status !== 200) {
            throw $this->commonFailure($response, sprintf('Marking document "%s" as read', $documentId));
        }

        $this->successBody($response);
    }

    /**
     * The delivery state of a Recommand delivery status value. A missing status means delivery
     * has not started yet; a status this version does not know is Unknown rather than an error,
     * because the document was accepted and an exception would invite a duplicate send.
     */
    public static function stateOf(mixed $value): DeliveryState
    {
        if ($value === null) {
            return DeliveryState::Pending;
        }

        return is_string($value) ? (DeliveryState::tryFrom($value) ?? DeliveryState::Unknown) : DeliveryState::Unknown;
    }

    /**
     * The failure described by a Recommand delivery failure object, or null when there is none.
     * The message is returned as given.
     */
    public static function failureOf(mixed $failure): ?DeliveryFailure
    {
        if (! is_array($failure)) {
            return null;
        }
        $category = $failure['category'] ?? null;

        return new DeliveryFailure(
            is_string($category) ? FailureCategory::fromValue($category) : FailureCategory::Other,
            self::optionalString($failure['message'] ?? null),
            self::optionalString($failure['providerCode'] ?? null),
        );
    }

    /**
     * An UnexpectedResponse with the credentials removed from its message and body excerpt. When
     * the full body is given, the excerpt is cut from it after redaction, so that a credential
     * cut in two by the excerpt limit cannot survive in part.
     */
    public function sanitize(UnexpectedResponse $exception, ?string $body = null): UnexpectedResponse
    {
        return $this->config->redactor()->sanitize($exception, $body);
    }

    private function sendFailure(ResponseInterface $response, ParticipantId $receiver): TransportException
    {
        $status = $response->getStatusCode();
        $data = $this->errorBody($response);

        if ($status === 400) {
            $errors = [];
            foreach (['errors', 'invalidInputDetails'] as $key) {
                if (array_key_exists($key, $data)) {
                    $errors[$key] = $this->redactValue($data[$key]);
                }
            }

            return new DocumentRejected(
                sprintf('Recommand rejected the document (HTTP 400): %s', $this->quote($errors)),
                $errors,
            );
        }
        if ($status === 422) {
            $deliveryFailure = $data['deliveryFailure'] ?? null;
            $category = is_array($deliveryFailure) && is_string($deliveryFailure['category'] ?? null)
                ? FailureCategory::fromValue($deliveryFailure['category'])
                : FailureCategory::Other;

            return new RecipientUnreachable(
                sprintf(
                    'Recommand cannot deliver the document to %s (%s, HTTP 422): %s',
                    $receiver->toString(),
                    $category->value,
                    $this->quote(['errors' => $data['errors'] ?? null]),
                ),
                $category,
            );
        }
        if ($status === 504) {
            // A gateway timeout means the request reached a proxy that gave up waiting for the
            // application, which may still have accepted the document.
            return new OutcomeUnknown(sprintf(
                'Sending the document to %s timed out at the Recommand gateway (HTTP 504), so Recommand may or may not have accepted it. Check the document list before resending to avoid a duplicate.',
                $receiver->toString(),
            ));
        }
        if ($status >= 500 && $status <= 599 && $status !== 503) {
            // A server error can follow storing the document, and a proxy error (502, 524) usually
            // means the request reached the application before the connection broke. Only 503
            // says the request was refused before processing.
            return new OutcomeUnknown(sprintf(
                'Sending the document to %s failed with a Recommand server error (HTTP %d), which can come after the document was accepted, so Recommand may or may not have accepted it. Check the document list before resending to avoid a duplicate.',
                $receiver->toString(),
                $status,
            ));
        }

        return $this->commonFailure($response, sprintf('Sending the document to %s', $receiver->toString()));
    }

    /**
     * @param  string  $operation  what was attempted, phrased to start a sentence
     */
    private function commonFailure(ResponseInterface $response, string $operation): TransportException
    {
        $status = $response->getStatusCode();

        if ($status === 401 || $status === 403) {
            return new AuthenticationFailed(sprintf(
                '%s failed: Recommand refused the API credentials or their permission (HTTP %d).',
                $operation,
                $status,
            ));
        }
        if ($status === 429) {
            $retryAfter = trim($response->getHeaderLine('Retry-After'));

            return new RateLimited(
                sprintf('%s failed: Recommand rate limit reached (HTTP 429).', $operation),
                ctype_digit($retryAfter) ? (int) $retryAfter : null,
            );
        }
        if ($status >= 500 && $status <= 599) {
            return new TransportUnavailable(sprintf('%s failed: Recommand is unavailable (HTTP %d).', $operation, $status));
        }

        return $this->unexpected($response, sprintf('%s failed with an unexpected status from Recommand', $operation));
    }

    /**
     * The decoded body of a 200 response that reports success.
     *
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not JSON or does not report success
     */
    private function successBody(ResponseInterface $response): array
    {
        try {
            $data = JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw $this->sanitize($exception, (string) $response->getBody());
        }
        if (($data['success'] ?? null) !== true) {
            throw $this->unexpected($response, 'The Recommand response does not report success');
        }

        return $data;
    }

    /**
     * The decoded body of an error response, or an empty array when it is not JSON.
     *
     * @return array<mixed>
     */
    private function errorBody(ResponseInterface $response): array
    {
        try {
            return JsonResponse::decode($response);
        } catch (UnexpectedResponse) {
            return [];
        }
    }

    /**
     * @param  array<mixed>  $data
     */
    private function documentId(ResponseInterface $response, array $data): string
    {
        $id = $data['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw $this->unexpected($response, 'The Recommand send response has no document id');
        }

        return $id;
    }

    /**
     * The failure and the status change time of the first Peppol delivery.
     *
     * @param  array<mixed>  $data
     * @return array{?DeliveryFailure, ?DateTimeImmutable}
     */
    private function peppolDelivery(array $data): array
    {
        $deliveries = $data['deliveries'] ?? null;
        if (! is_array($deliveries)) {
            return [null, null];
        }
        foreach ($deliveries as $delivery) {
            if (! is_array($delivery) || ($delivery['channel'] ?? null) !== 'peppol') {
                continue;
            }

            return [$this->failure($delivery['failure'] ?? null), $this->time($delivery['statusChangedAt'] ?? null)];
        }

        return [null, null];
    }

    private function failure(mixed $failure): ?DeliveryFailure
    {
        $parsed = self::failureOf($failure);
        if ($parsed === null || $parsed->message === null) {
            return $parsed;
        }

        return new DeliveryFailure($parsed->category, $this->config->redact($parsed->message), $parsed->providerCode);
    }

    /**
     * @param  array<mixed>  $document
     *
     * @throws UnexpectedResponse when the document has no id or no valid creation time
     */
    private function inboundReference(ResponseInterface $response, array $document): InboundReference
    {
        $id = $document['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw $this->unexpected($response, 'The Recommand response lists a document without an id');
        }
        $receivedAt = $this->time($document['createdAt'] ?? null)
            ?? throw $this->unexpected($response, sprintf('The Recommand document "%s" has no valid createdAt time (ISO 8601 with a time zone)', $id));

        return new InboundReference(
            $id,
            self::participant($document['senderId'] ?? null),
            self::participant($document['receiverId'] ?? null),
            self::optionalString($document['docTypeId'] ?? null),
            $receivedAt,
        );
    }

    /**
     * The participant of an identifier as reported by Recommand, or null when it is missing or
     * not a participant identifier.
     */
    private static function participant(mixed $value): ?ParticipantId
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return ParticipantId::parse($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The time of an ISO 8601 timestamp with a time zone, in the DATE_ATOM form
     * (2026-11-12T08:02:00+00:00) or with milliseconds (2026-11-12T07:45:10.000Z); null for any
     * other value, including a date that does not exist.
     */
    private function time(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        foreach ([DATE_ATOM, DATE_RFC3339_EXTENDED] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $time;
            }
        }

        return null;
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function unexpected(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return $this->config->redactor()->unexpected($response, $reason);
    }

    /**
     * The first provider error messages, each prefixed with the field it belongs to; an
     * invalidInputDetails entry is quoted as "path: message", its path segments joined with dots.
     *
     * @param  array<mixed>  $errors
     */
    private function quote(array $errors): string
    {
        $messages = [];
        foreach ($errors as $group => $value) {
            if ($group === 'invalidInputDetails' && is_array($value) && array_is_list($value)) {
                foreach ($value as $detail) {
                    $message = self::inputDetail($detail);
                    if ($message !== null) {
                        $messages[] = $message;
                    }
                }

                continue;
            }
            if (is_string($value)) {
                $value = [$group => $value];
            }
            if (! is_array($value)) {
                continue;
            }
            array_walk_recursive($value, static function (mixed $item, int|string $key) use (&$messages): void {
                if (is_string($item) && trim($item) !== '') {
                    $messages[] = is_string($key) && $key !== 'message' ? $key.': '.$item : $item;
                }
            });
        }
        if ($messages === []) {
            return 'no details given.';
        }
        $quoted = implode('; ', array_slice($messages, 0, self::QUOTED_ERRORS));
        if (count($messages) > self::QUOTED_ERRORS) {
            $quoted .= sprintf(' and %d more', count($messages) - self::QUOTED_ERRORS);
        }

        return $this->config->redact(rtrim($quoted, '.').'.');
    }

    /**
     * "path: message" for an invalidInputDetails entry with a message, or null without one.
     */
    private static function inputDetail(mixed $detail): ?string
    {
        if (! is_array($detail) || ! is_string($detail['message'] ?? null) || trim($detail['message']) === '') {
            return null;
        }
        $path = $detail['path'] ?? null;
        $segments = is_array($path)
            ? array_map(static fn (mixed $segment): string => is_scalar($segment) ? (string) $segment : '?', array_values($path))
            : (is_string($path) ? [$path] : []);
        $segments = array_filter($segments, static fn (string $segment): bool => $segment !== '');

        return $segments === [] ? $detail['message'] : implode('.', $segments).': '.$detail['message'];
    }

    /**
     * The value with the credentials removed from every string in it.
     */
    private function redactValue(mixed $value): mixed
    {
        return $this->config->redactor()->redactValue($value);
    }
}

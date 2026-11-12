<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use DateTimeImmutable;
use Exception;
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
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\SendResult;
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
     * @throws TransportException when the response is not a successful send
     */
    public function sendResult(ResponseInterface $response, ParticipantId $receiver): SendResult
    {
        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw $this->sendFailure($response, $receiver);
        }

        $data = $this->successBody($response);
        [$failure] = $this->peppolDelivery($data);

        return new SendResult(
            $this->documentId($response, $data),
            $this->state($data['deliveryStatus'] ?? null),
            $this->optionalString($data['peppolMessageId'] ?? null),
            $this->optionalString($data['envelopeId'] ?? null),
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
            $this->state($document['deliveryStatus'] ?? null),
            $failure,
            $changedAt,
        );
    }

    /**
     * An UnexpectedResponse with the credentials removed from its message and body excerpt.
     */
    public function sanitize(UnexpectedResponse $exception): UnexpectedResponse
    {
        return new UnexpectedResponse(
            $this->config->redact($exception->getMessage()),
            $exception->status,
            $this->config->redact($exception->bodyExcerpt),
            $exception->getPrevious(),
        );
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
            throw $this->sanitize($exception);
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
     * A missing status means delivery has not started yet; a status this version does not know
     * is Unknown rather than an error, because the document was accepted and an exception would
     * invite a duplicate send.
     */
    private function state(mixed $value): DeliveryState
    {
        if ($value === null) {
            return DeliveryState::Pending;
        }

        return is_string($value) ? (DeliveryState::tryFrom($value) ?? DeliveryState::Unknown) : DeliveryState::Unknown;
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
        if (! is_array($failure)) {
            return null;
        }
        $category = $failure['category'] ?? null;
        $message = $this->optionalString($failure['message'] ?? null);

        return new DeliveryFailure(
            is_string($category) ? FailureCategory::fromValue($category) : FailureCategory::Other,
            $message === null ? null : $this->config->redact($message),
            $this->optionalString($failure['providerCode'] ?? null),
        );
    }

    private function time(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function unexpected(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse(
            $response,
            $this->config->redact($reason),
            $this->config->redact((string) $response->getBody()),
        );
    }

    /**
     * The first provider error messages, each prefixed with the field it belongs to.
     *
     * @param  array<mixed>  $errors
     */
    private function quote(array $errors): string
    {
        $messages = [];
        array_walk_recursive($errors, static function (mixed $value, int|string $key) use (&$messages): void {
            if (is_string($value) && trim($value) !== '') {
                $messages[] = is_string($key) && $key !== 'message' ? $key.': '.$value : $value;
            }
        });
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
     * The value with the credentials removed from every string in it.
     */
    private function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->config->redact($value);
        }
        if (is_array($value)) {
            return array_map($this->redactValue(...), $value);
        }

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Timestamp;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps B2Brouter API responses to transport results and exceptions. Unknown JSON fields are
 * ignored; every text taken from a response has the API key removed.
 *
 * @internal
 */
final class ResponseMapper
{
    /** B2Brouter states of an issued invoice that is still on its way. */
    private const PENDING_STATES = ['new', 'sending'];

    /**
     * B2Brouter states of an issued invoice that reached the receiver's access point. Business
     * responses (accepted, refused, paid) do not change that.
     */
    private const DELIVERED_STATES = ['sent', 'registered', 'read', 'accepted', 'refused', 'received', 'allegedly_paid', 'paid', 'closed'];

    /** The B2Brouter error code of a receiver that is not on the Peppol network. */
    private const NO_RECEIVER = 'PEPPOL_NO_RECEIVER';

    /** Bytes of a provider error message quoted in an exception message. */
    private const QUOTED_BYTES = 300;

    public function __construct(private readonly Redactor $redactor) {}

    /**
     * The result of an import, or the exception its response stands for.
     *
     * @param  string  $subject  the invoice, as named in messages, for example 'invoice number "INV-1"'
     *
     * @throws OutcomeUnknown when B2Brouter answered a 5xx status or a 201 answer cannot be read;
     *                        the invoice may have been imported
     * @throws TransportException when the response is not a successful import
     */
    public function sendResult(ResponseInterface $response, string $subject): SendResult
    {
        if ($response->getStatusCode() !== 201) {
            throw $this->sendFailure($response, $subject);
        }

        // A 201 means the import went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            $invoice = $this->invoice($response);
            $id = self::id($invoice['id'] ?? null) ?? throw $this->unexpected($response, 'The B2Brouter import response has no invoice id');
        } catch (UnexpectedResponse $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'B2Brouter answered the import of %s with HTTP 201, but the response cannot be read, so the invoice may or may not have been imported: %s %s',
                    $subject,
                    $exception->getMessage(),
                    self::checkBeforeResending($subject),
                ),
                $exception,
            );
        }

        return new SendResult($id, self::stateOf($invoice['state'] ?? null), failure: $this->failure($invoice));
    }

    /**
     * The delivery status of an issued invoice, or the exception its response stands for.
     *
     * @throws TransportException when the response is not an issued invoice
     */
    public function deliveryStatus(ResponseInterface $response, string $invoiceId): DeliveryStatus
    {
        $status = $response->getStatusCode();
        if ($status === 302) {
            // No body excerpt: a redirect body usually repeats the Location, which may carry a token.
            throw UnexpectedResponse::forResponse($response, sprintf('B2Brouter invoice "%s" is a received invoice, not a sent one, so it has no delivery status', $invoiceId), '');
        }
        if ($status !== 200) {
            throw $this->readFailure($response, sprintf('Querying the status of B2Brouter invoice "%s"', $invoiceId));
        }

        $invoice = $this->invoice($response);

        return new DeliveryStatus(
            $invoiceId,
            self::stateOf($invoice['state'] ?? null),
            $this->failure($invoice),
            Timestamp::parse($invoice['state_updated_at'] ?? null),
        );
    }

    /**
     * The received invoices listed by one page of the invoice list, in the order given.
     *
     * @return list<InboundReference>
     *
     * @throws TransportException when the response is not an invoice list
     */
    public function inboundReferences(ResponseInterface $response): array
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, 'Listing the received B2Brouter invoices');
        }

        $invoices = $this->body($response)['invoices'] ?? null;
        if (! is_array($invoices) || ! array_is_list($invoices)) {
            throw $this->unexpected($response, 'The B2Brouter invoice list response has no invoice list');
        }

        $references = [];
        foreach ($invoices as $invoice) {
            if (! is_array($invoice)) {
                throw $this->unexpected($response, 'The B2Brouter invoice list contains an entry that is not an object');
            }
            $id = self::id($invoice['id'] ?? null)
                ?? throw $this->unexpected($response, 'The B2Brouter invoice list contains an invoice without a positive integer id');
            $receivedAt = Timestamp::parse($invoice['created_at'] ?? null)
                ?? throw $this->unexpected($response, sprintf('The B2Brouter invoice "%s" has no valid created_at time (ISO 8601 with a time zone)', $id));
            $documentType = $invoice['document_type_code'] ?? null;

            // The list reports no Peppol participant identifiers.
            $references[] = new InboundReference(
                $id,
                null,
                null,
                is_string($documentType) && $documentType !== '' ? $documentType : null,
                $receivedAt,
            );
        }

        return $references;
    }

    /**
     * Checks that an original-document response carries the document, and returns its body.
     *
     * @throws TransportException when it does not
     */
    public function originalDocument(ResponseInterface $response, string $invoiceId): string
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->readFailure($response, sprintf('Fetching the original document of B2Brouter invoice "%s"', $invoiceId));
        }

        return (string) $response->getBody();
    }

    /**
     * Checks that an acknowledgement response reports success.
     *
     * @throws OutcomeUnknown when B2Brouter answered a 5xx status; acknowledging again is safe
     * @throws TransportException when the acknowledgement was refused
     */
    public function acknowledged(ResponseInterface $response, string $invoiceId): void
    {
        $status = $response->getStatusCode();
        if ($status === 200) {
            return;
        }
        $operation = sprintf('Acknowledging B2Brouter invoice "%s"', $invoiceId);
        if ($status >= 500 && $status <= 599) {
            throw new OutcomeUnknown(sprintf(
                '%s failed with a B2Brouter server error (HTTP %d), so it may or may not have been recorded. Acknowledging is idempotent, so it is safe to retry.',
                $operation,
                $status,
            ));
        }

        throw $this->readFailure($response, $operation);
    }

    /**
     * The delivery state of a B2Brouter issued-invoice state. A state this version does not know
     * is Unknown rather than an error, because the invoice was accepted and an exception would
     * invite a duplicate send.
     */
    public static function stateOf(mixed $state): DeliveryState
    {
        return match (true) {
            in_array($state, self::PENDING_STATES, true) => DeliveryState::Pending,
            in_array($state, self::DELIVERED_STATES, true) => DeliveryState::Delivered,
            $state === 'error', $state === 'invalid' => DeliveryState::Failed,
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
     * An UnexpectedResponse for the response, with the API key removed.
     */
    public function unexpected(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return $this->redactor->unexpected($response, $reason);
    }

    /**
     * The sentence that tells how to find out whether an invoice was imported.
     */
    public static function checkBeforeResending(string $subject): string
    {
        return sprintf(
            'Check whether the B2Brouter account holds %s before resending; a resend of an imported invoice is refused as a duplicate (HTTP 422, number taken).',
            $subject,
        );
    }

    private function sendFailure(ResponseInterface $response, string $subject): TransportException
    {
        $status = $response->getStatusCode();
        $operation = sprintf('Importing %s into B2Brouter', $subject);

        if ($status === 403) {
            return new AuthenticationFailed(sprintf(
                '%s failed: B2Brouter refused the request (HTTP 403). The API key belongs to no account group (no_account_group) or lacks the permission for this action.',
                $operation,
            ));
        }
        if ($status >= 500 && $status <= 599) {
            // The import may have run before the server error, or behind the failing gateway.
            return new OutcomeUnknown(sprintf(
                '%s failed with a B2Brouter server error (HTTP %d), which can come after the import, so the invoice may or may not have been imported. %s',
                $operation,
                $status,
                self::checkBeforeResending($subject),
            ));
        }
        if (! in_array($status, [400, 406, 409, 422], true)) {
            return $this->readFailure($response, $operation);
        }

        $error = $this->error($response);
        $errors = $error === null ? [] : ['error' => $this->redactor->redactValue($error)];
        if ($status === 422 && ($error['code'] ?? null) === 'parameter_taken' && ($error['param'] ?? null) === 'number') {
            return new DocumentRejected(
                sprintf(
                    'B2Brouter refused %s as a %s (HTTP 422, parameter_taken on number): an invoice with this number exists; check it before resending.',
                    $subject,
                    FailureCategory::Duplicate->value,
                ),
                $errors,
            );
        }

        return new DocumentRejected(
            sprintf('B2Brouter rejected %s (HTTP %d%s): %s', $subject, $status, self::codeAndParam($error), $this->quote($error)),
            $errors,
        );
    }

    /**
     * The mapping shared by every request that reads, and by refused acknowledgements.
     *
     * @param  string  $operation  what was attempted, phrased to start a sentence
     */
    private function readFailure(ResponseInterface $response, string $operation): TransportException
    {
        $status = $response->getStatusCode();
        if ($status >= 300 && $status <= 399) {
            // No body excerpt: a redirect body usually repeats the Location, which may carry a token.
            return UnexpectedResponse::forResponse($response, sprintf('%s failed: B2Brouter answered with a redirect, which is not followed', $operation), '');
        }

        return ReadFailure::of($response, 'B2Brouter', $operation, $this->redactor);
    }

    /**
     * The invoice object of an invoice response.
     *
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not JSON or has no invoice object
     */
    private function invoice(ResponseInterface $response): array
    {
        $invoice = $this->body($response)['invoice'] ?? null;
        if (! is_array($invoice) || array_is_list($invoice)) {
            throw $this->unexpected($response, 'The B2Brouter response has no invoice');
        }

        return $invoice;
    }

    /**
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not JSON
     */
    private function body(ResponseInterface $response): array
    {
        try {
            return JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw $this->sanitize($exception, (string) $response->getBody());
        }
    }

    /**
     * The error object of an error response, or null when there is none.
     *
     * @return array<mixed>|null
     */
    private function error(ResponseInterface $response): ?array
    {
        try {
            $error = JsonResponse::decode($response)['error'] ?? null;
        } catch (UnexpectedResponse) {
            return null;
        }

        return is_array($error) ? $error : null;
    }

    /**
     * The failure of an invoice in a failed state, or null for any other state.
     *
     * @param  array<mixed>  $invoice
     */
    private function failure(array $invoice): ?DeliveryFailure
    {
        $state = $invoice['state'] ?? null;
        if ($state !== 'error' && $state !== 'invalid') {
            return null;
        }
        $code = self::text($invoice['error_code'] ?? null);
        $message = self::text($invoice['error_message'] ?? null) ?? $this->listedErrors($invoice['errors'] ?? null);
        $category = match (true) {
            $state === 'invalid' => FailureCategory::Validation,
            $code === self::NO_RECEIVER => FailureCategory::RecipientNotFound,
            default => FailureCategory::Transport,
        };

        return new DeliveryFailure(
            $category,
            $message === null ? null : $this->redactor->redact($message),
            $code === null ? null : $this->redactor->redact($code),
        );
    }

    /**
     * The messages of an errors list joined with "; ", or null when it has none. An entry is either
     * a string or an object with a string "error"; anything else is ignored.
     */
    private function listedErrors(mixed $errors): ?string
    {
        if (! is_array($errors)) {
            return null;
        }
        $messages = [];
        foreach ($errors as $entry) {
            $text = self::text(is_array($entry) ? ($entry['error'] ?? null) : $entry);
            if ($text !== null) {
                $messages[] = $text;
            }
        }
        if ($messages === []) {
            return null;
        }
        $message = $this->redactor->redact(implode('; ', $messages));

        return strlen($message) > self::QUOTED_BYTES ? mb_strcut($message, 0, self::QUOTED_BYTES, 'UTF-8').'...' : $message;
    }

    /**
     * ", code, on param" for the error object, or an empty string without them.
     *
     * @param  array<mixed>|null  $error
     */
    private function codeAndParam(?array $error): string
    {
        $code = self::text($error['code'] ?? null);
        $param = self::text($error['param'] ?? null);
        $text = ($code === null ? '' : ', '.$code).($param === null ? '' : ' on '.$param);

        return $this->redactor->redact($text);
    }

    /**
     * The provider's error message in quotes, redacted and shortened, or a note that there is none.
     *
     * @param  array<mixed>|null  $error
     */
    private function quote(?array $error): string
    {
        $message = self::text($error['message'] ?? null);
        if ($message === null) {
            return 'no details given.';
        }
        $message = $this->redactor->redact($message);
        if (strlen($message) > self::QUOTED_BYTES) {
            $message = mb_strcut($message, 0, self::QUOTED_BYTES, 'UTF-8').'...';
        }

        return '"'.$message.'"';
    }

    /**
     * The id of a B2Brouter invoice, which is a positive integer, as a string; null otherwise.
     */
    private static function id(mixed $value): ?string
    {
        return is_int($value) && $value > 0 ? (string) $value : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}

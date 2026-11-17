<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
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

    private readonly B2BrouterErrors $errors;

    public function __construct(private readonly Redactor $redactor)
    {
        $this->errors = new B2BrouterErrors($redactor);
    }

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
            throw $this->errors->importFailure($response, $subject);
        }

        // A 201 means the import went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            $invoice = $this->errors->invoice($response);
            $id = B2BrouterErrors::id($invoice['id'] ?? null) ?? throw $this->unexpected($response, 'The B2Brouter import response has no invoice id');
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

        return new SendResult($id, self::stateOf($invoice['state'] ?? null), failure: $this->errors->failure($invoice));
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
            throw $this->errors->readFailure($response, sprintf('Querying the status of B2Brouter invoice "%s"', $invoiceId));
        }

        $invoice = $this->errors->invoice($response);

        return new DeliveryStatus(
            $invoiceId,
            self::stateOf($invoice['state'] ?? null),
            $this->errors->failure($invoice),
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
            throw $this->errors->readFailure($response, 'Listing the received B2Brouter invoices');
        }

        $invoices = $this->errors->body($response)['invoices'] ?? null;
        if (! is_array($invoices) || ! array_is_list($invoices)) {
            throw $this->unexpected($response, 'The B2Brouter invoice list response has no invoice list');
        }

        $references = [];
        foreach ($invoices as $invoice) {
            if (! is_array($invoice)) {
                throw $this->unexpected($response, 'The B2Brouter invoice list contains an entry that is not an object');
            }
            $id = B2BrouterErrors::id($invoice['id'] ?? null)
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
            throw $this->errors->readFailure($response, sprintf('Fetching the original document of B2Brouter invoice "%s"', $invoiceId));
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

        throw $this->errors->readFailure($response, $operation);
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
        return B2BrouterErrors::checkBeforeResending($subject);
    }
}

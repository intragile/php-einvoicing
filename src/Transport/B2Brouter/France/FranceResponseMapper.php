<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterErrors;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Timestamp;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps the B2Brouter API responses of the French flow to results and exceptions, and writes the
 * JSON request bodies with exact decimal amounts. Unknown JSON fields are ignored; every text
 * taken from a response has the API key removed. Response bodies arrive size-capped by the HTTP
 * client.
 *
 * @internal
 */
final class FranceResponseMapper
{
    /** The media type every document is imported as. */
    public const IMPORT_CONTENT_TYPE = 'application/octet-stream';

    /** Amounts must stay below 10^13. */
    private const AMOUNT_LIMIT = '10000000000000';

    private readonly B2BrouterErrors $errors;

    public function __construct(private readonly Redactor $redactor)
    {
        $this->errors = new B2BrouterErrors($redactor);
    }

    /**
     * The document as named in messages, for example 'invoice number "FA-1"'.
     */
    public static function subject(FrenchDocument $document): string
    {
        return $document->number === null
            ? 'the invoice (its invoice number cannot be read from the document)'
            : sprintf('invoice number "%s"', $document->number);
    }

    /**
     * The result of an import, or the exception its response stands for.
     *
     * @throws OutcomeUnknown when B2Brouter answered a 5xx status or a 201 answer cannot be read;
     *                        the invoice may have been imported
     * @throws UnsupportedDocument when B2Brouter does not accept the document (HTTP 406)
     * @throws TransportException when the response is not a successful import
     */
    public function sendResult(ResponseInterface $response, FrenchDocument $document): SendResult
    {
        $subject = self::subject($document);
        $status = $response->getStatusCode();
        if ($status === 406) {
            throw new UnsupportedDocument(sprintf(
                'B2Brouter did not accept %s as document type "%s" sent as %s (HTTP 406): %s',
                $subject,
                $document->type->value,
                self::IMPORT_CONTENT_TYPE,
                $this->errors->quote($this->errors->error($response)),
            ));
        }
        if ($status !== 201) {
            throw $this->sendFailure($response, $subject);
        }

        // A 201 means the import went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            $invoice = $this->errors->invoice($response);
            $id = B2BrouterErrors::id($invoice['id'] ?? null) ?? throw $this->redactor->unexpected($response, 'The B2Brouter import response has no invoice id');
        } catch (UnexpectedResponse $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'B2Brouter answered the import of %s with HTTP 201, but the response cannot be read, so the invoice may or may not have been imported: %s %s',
                    $subject,
                    $exception->getMessage(),
                    ResponseMapper::checkBeforeResending($subject),
                ),
                $exception,
            );
        }

        return new SendResult($id, ResponseMapper::stateOf($invoice['state'] ?? null), failure: $this->failure($invoice));
    }

    /**
     * The platform status of an invoice response.
     *
     * The invoice counts as received when its type is ReceivedInvoice. A response reached through
     * B2Brouter's redirect for a received invoice has no documented shape: it is read with the same
     * invoice reader and counts as received unless its type says IssuedInvoice.
     *
     * @param  bool  $redirected  whether the response is the target of a redirect
     *
     * @throws UnexpectedResponse when the body is not an invoice with a state, or the invoice is unknown
     * @throws TransportException when B2Brouter refused the request
     */
    public function platformStatus(ResponseInterface $response, string $invoiceId, bool $redirected): PlatformStatus
    {
        if ($response->getStatusCode() !== 200) {
            throw $this->errors->readFailure($response, sprintf('Querying the lifecycle status of B2Brouter invoice "%s"', $invoiceId));
        }

        return $this->statusOf($response, $invoiceId, $redirected);
    }

    /**
     * The platform status a buyer's response (mark_as) left a received invoice in.
     *
     * The answer counts as a received invoice unless its type says IssuedInvoice.
     *
     * @throws OutcomeUnknown when B2Brouter answered a 5xx status; the state may have changed
     * @throws DocumentRejected when B2Brouter refused the state change (HTTP 400, 422)
     * @throws UnexpectedResponse when the body is not an invoice with a state, the invoice is
     *                            unknown or B2Brouter answered with a redirect
     * @throws TransportException when B2Brouter refused the request
     */
    public function respondedStatus(ResponseInterface $response, string $invoiceId, BuyerResponse $answer): PlatformStatus
    {
        $status = $response->getStatusCode();
        $operation = sprintf('Answering B2Brouter invoice "%s" with status %s', $invoiceId, $answer->status()->value);
        if ($status >= 500 && $status <= 599) {
            throw new OutcomeUnknown(sprintf(
                '%s failed with a B2Brouter server error (HTTP %d), which can come after the state change, so the invoice may or may not have changed state. %s',
                $operation,
                $status,
                self::checkStateBeforeRepeating($invoiceId),
            ));
        }
        if ($status === 400 || $status === 422) {
            throw new DocumentRejected(
                sprintf('B2Brouter refused the answer to invoice "%s" with status %s (HTTP %d): %s', $invoiceId, $answer->status()->value, $status, $this->errors->quoteRefusal($response)),
                self::errorsOf($response, $this->redactor),
            );
        }
        if ($status !== 200) {
            throw $this->errors->readFailure($response, $operation);
        }

        return $this->statusOf($response, $invoiceId, true);
    }

    /**
     * The id of the payment a payments answer recorded, or null when a successful answer does not
     * name it.
     *
     * @throws OutcomeUnknown when B2Brouter answered a 5xx status; the payment may have been recorded
     * @throws DocumentRejected when B2Brouter refused the payment (HTTP 400, 422)
     * @throws TransportException when B2Brouter refused the request, for example an invoice
     *                            outside the account (HTTP 404)
     */
    public function recordedPayment(ResponseInterface $response, int $accountId, string $invoiceId): ?string
    {
        $status = $response->getStatusCode();
        $operation = sprintf('Recording a payment on B2Brouter invoice "%s"', $invoiceId);
        if ($status >= 500 && $status <= 599) {
            throw new OutcomeUnknown(sprintf(
                '%s failed with a B2Brouter server error (HTTP %d), which can come after the payment was recorded, so it may or may not have been recorded. %s',
                $operation,
                $status,
                self::checkPaymentsBeforeRepeating($accountId, $invoiceId),
            ));
        }
        if ($status === 400 || $status === 422) {
            throw new DocumentRejected(
                sprintf('B2Brouter refused the payment on invoice "%s" (HTTP %d): %s', $invoiceId, $status, $this->errors->quoteRefusal($response)),
                self::errorsOf($response, $this->redactor),
            );
        }
        if ($status !== 200 && $status !== 201) {
            throw $this->errors->readFailure($response, $operation);
        }

        // The payment is recorded, so a body that cannot be read only loses its id.
        try {
            $payment = JsonResponse::decode($response)['payment'] ?? null;
        } catch (UnexpectedResponse) {
            return null;
        }

        return is_array($payment) ? B2BrouterErrors::id($payment['id'] ?? null) : null;
    }

    /**
     * The sentence that tells how to find out whether a buyer's response changed the invoice state.
     */
    public static function checkStateBeforeRepeating(string $invoiceId): string
    {
        return sprintf(
            'Read the invoice state with lifecycle() for invoice "%s" before answering again; B2Brouter emits a lifecycle message for every state change.',
            $invoiceId,
        );
    }

    /**
     * The sentence that tells how to find out whether a payment was recorded.
     */
    public static function checkPaymentsBeforeRepeating(int $accountId, string $invoiceId): string
    {
        return sprintf(
            'A payment is not idempotent: list the invoice\'s payments with GET /accounts/%d/payments?invoice_id=%s before recording it again.',
            $accountId,
            $invoiceId,
        );
    }

    /**
     * The JSON number literal of an amount, with two decimals.
     *
     * The amount must be greater than zero, below 10^13 and have at most two decimals once its
     * trailing zeros are stripped; it is written from its decimal value, never through a float.
     *
     * @param  string  $what  the amount as named in messages, for example "The payment amount"
     *
     * @throws InvalidArgumentException when the amount is not a number or is out of range
     */
    public static function jsonAmount(BigDecimal|string|int $amount, string $what): string
    {
        try {
            $value = Decimal::of($amount, $what)->strippedOfTrailingZeros();
        } catch (MathException) {
            throw new InvalidArgumentException(sprintf('%s must be a decimal number.', $what));
        }
        if (! $value->isPositive() || $value->isGreaterThanOrEqualTo(self::AMOUNT_LIMIT)) {
            throw new InvalidArgumentException(sprintf('%s must be greater than 0 and below 10^13, %s given.', $what, (string) $value));
        }
        if ($value->getScale() > 2) {
            throw new InvalidArgumentException(sprintf('%s must have at most 2 decimal places, %s given.', $what, (string) $value));
        }

        return (string) $value->toScale(2);
    }

    /**
     * A JSON object from members whose values are JSON texts already, in the given order.
     *
     * @param  array<string, string>  $members
     */
    public static function jsonObject(array $members): string
    {
        $pairs = [];
        foreach ($members as $name => $json) {
            $pairs[] = self::json($name).':'.$json;
        }

        return '{'.implode(',', $pairs).'}';
    }

    /**
     * The JSON text of a scalar value.
     */
    public static function json(string|int|bool $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The platform status of a successful invoice response.
     *
     * @param  bool  $presumedReceived  whether an invoice without a type counts as received
     *
     * @throws UnexpectedResponse when the body is not an invoice with a state
     */
    private function statusOf(ResponseInterface $response, string $invoiceId, bool $presumedReceived): PlatformStatus
    {
        $invoice = $this->errors->invoice($response);
        $state = B2BrouterErrors::text($invoice['state'] ?? null)
            ?? throw $this->redactor->unexpected($response, sprintf('The B2Brouter response for invoice "%s" has no state', $invoiceId));
        $type = $invoice['type'] ?? null;
        $received = $type === 'ReceivedInvoice' || ($presumedReceived && $type !== 'IssuedInvoice');
        $reason = $invoice['refuse_reason_code'] ?? null;

        return new PlatformStatus(
            $invoiceId,
            $state,
            $received ? LifecycleMapping::forReceived($state) : LifecycleMapping::forIssued($state),
            new DeliveryStatus(
                $invoiceId,
                ResponseMapper::stateOf($state),
                $this->failure($invoice),
                Timestamp::parse($invoice['state_updated_at'] ?? null),
            ),
            is_string($reason) ? StatusReason::tryFrom($reason) : null,
            self::taxReportIds($invoice['tax_report_ids'] ?? null),
            $received,
        );
    }

    /**
     * The failure of an invoice in a failed state, or null for any other state. The reason is
     * the errors list, which B2Brouter fills with the rejection reasons, or the error message
     * when the list is empty.
     *
     * @param  array<mixed>  $invoice
     */
    public function failure(array $invoice): ?DeliveryFailure
    {
        return $this->errors->failure($invoice, errorsListFirst: true);
    }

    /**
     * The redacted error object of a refusal, keyed "error", or the errors list, keyed "errors".
     *
     * @return array<string, mixed>
     */
    private static function errorsOf(ResponseInterface $response, Redactor $redactor): array
    {
        try {
            $body = JsonResponse::decode($response);
        } catch (UnexpectedResponse) {
            return [];
        }
        $errors = [];
        foreach (['error', 'errors'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $errors[$key] = $redactor->redactValue($body[$key]);
            }
        }

        return $errors;
    }

    /**
     * The 1.0 import mapping, with a French permission message for HTTP 403 and, for a refused
     * import (HTTP 422), how to resolve the invoice B2Brouter kept in error state.
     */
    private function sendFailure(ResponseInterface $response, string $subject): TransportException
    {
        $status = $response->getStatusCode();
        if ($status === 403) {
            return new AuthenticationFailed(sprintf(
                'Importing %s into B2Brouter failed: B2Brouter refused the request (HTTP 403): %s The API key or the account lacks the permission for this action; check that the account is enabled for French e-invoicing.',
                $subject,
                $this->errors->quote($this->errors->error($response)),
            ));
        }

        $failure = $this->errors->importFailure($response, $subject);
        if ($status !== 422 || ! $failure instanceof DocumentRejected || B2BrouterErrors::isNumberTaken($this->errors->error($response))) {
            return $failure;
        }

        $errors = $failure->errors;
        $invoiceId = self::rejectedInvoiceId($response);
        if ($invoiceId !== null) {
            $errors['invoice_id'] = $invoiceId;
        }

        return new DocumentRejected($failure->getMessage().' '.self::correctionAdvice($invoiceId), $errors);
    }

    /**
     * How to resolve an invoice B2Brouter kept in error state after a refused import.
     */
    private static function correctionAdvice(?string $invoiceId): string
    {
        $invoice = $invoiceId === null
            ? 'If B2Brouter kept the invoice in error state, it'
            : sprintf('B2Brouter keeps it as B2Brouter invoice %s in error state; it', $invoiceId);

        return $invoice.' must be corrected with a PATCH request or deleted in B2Brouter before the document is imported again, because a re-import of the same number is refused as a duplicate (parameter_taken on number).';
    }

    /**
     * The tax report ids of an invoice: the positive integers of the list, as strings; anything
     * else is ignored.
     *
     * @return list<string>
     */
    private static function taxReportIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }
        $taxReportIds = [];
        foreach ($ids as $id) {
            $text = B2BrouterErrors::id($id);
            if ($text !== null) {
                $taxReportIds[] = $text;
            }
        }

        return $taxReportIds;
    }

    /**
     * The id of the invoice a refused import left behind, when the body names it: the id of an
     * invoice object or the invoice_id of the error object.
     */
    private static function rejectedInvoiceId(ResponseInterface $response): ?string
    {
        try {
            $body = JsonResponse::decode($response);
        } catch (UnexpectedResponse) {
            return null;
        }
        $invoice = $body['invoice'] ?? null;
        $error = $body['error'] ?? null;

        return (is_array($invoice) ? B2BrouterErrors::id($invoice['id'] ?? null) : null)
            ?? (is_array($error) ? B2BrouterErrors::id($error['invoice_id'] ?? null) : null);
    }
}

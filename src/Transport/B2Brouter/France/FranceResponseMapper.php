<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\SendResult;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps the B2Brouter API responses of the French flow to results and exceptions. Unknown JSON
 * fields are ignored; every text taken from a response has the API key removed. Response bodies
 * arrive size-capped by the HTTP client.
 *
 * @internal
 */
final class FranceResponseMapper
{
    /** The media type every document is imported as. */
    public const IMPORT_CONTENT_TYPE = 'application/octet-stream';

    /** The B2Brouter error code of a receiver that is not reachable. */
    private const NO_RECEIVER = 'PEPPOL_NO_RECEIVER';

    /** Bytes of a provider error message quoted in an exception message. */
    private const QUOTED_BYTES = 300;

    public function __construct(private readonly Redactor $redactor) {}

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
                $this->quote($this->error($response)),
            ));
        }
        if ($status !== 201) {
            throw $this->sendFailure($response, $subject);
        }

        // A 201 means the import went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            $invoice = $this->invoice($response);
            $id = self::id($invoice['id'] ?? null) ?? throw $this->redactor->unexpected($response, 'The B2Brouter import response has no invoice id');
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
     * The failure of an invoice in a failed state, or null for any other state. The reason is
     * the errors list, which B2Brouter fills with the rejection reasons, or the error message
     * when the list is empty.
     *
     * @param  array<mixed>  $invoice
     */
    public function failure(array $invoice): ?DeliveryFailure
    {
        $state = $invoice['state'] ?? null;
        if ($state !== 'error' && $state !== 'invalid') {
            return null;
        }
        $code = self::text($invoice['error_code'] ?? null);
        $message = $this->listedErrors($invoice['errors'] ?? null) ?? self::text($invoice['error_message'] ?? null);
        $category = match (true) {
            $state === 'invalid' => FailureCategory::Validation,
            $code === self::NO_RECEIVER => FailureCategory::RecipientNotFound,
            default => FailureCategory::Transport,
        };

        return new DeliveryFailure(
            $category,
            $message === null ? null : $this->shortened($this->redactor->redact($message)),
            $code === null ? null : $this->redactor->redact($code),
        );
    }

    private function sendFailure(ResponseInterface $response, string $subject): TransportException
    {
        $status = $response->getStatusCode();
        $operation = sprintf('Importing %s into B2Brouter', $subject);

        if ($status >= 500 && $status <= 599) {
            // The import may have run before the server error, or behind the failing gateway.
            return new OutcomeUnknown(sprintf(
                '%s failed with a B2Brouter server error (HTTP %d), which can come after the import, so the invoice may or may not have been imported. %s',
                $operation,
                $status,
                ResponseMapper::checkBeforeResending($subject),
            ));
        }
        if ($status === 403) {
            return new AuthenticationFailed(sprintf(
                '%s failed: B2Brouter refused the request (HTTP 403): %s The API key or the account lacks the permission for this action; check that the account is enabled for French e-invoicing.',
                $operation,
                $this->quote($this->error($response)),
            ));
        }
        if (! in_array($status, [400, 409, 422], true)) {
            return $this->readFailure($response, $operation);
        }

        $body = $this->json($response);
        $error = is_array($body['error'] ?? null) ? $body['error'] : null;
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

        $message = sprintf('B2Brouter rejected %s (HTTP %d%s): %s', $subject, $status, $this->codeAndParam($error), $this->quote($error));
        if ($status === 422) {
            $invoiceId = self::rejectedInvoiceId($body);
            if ($invoiceId !== null) {
                $errors['invoice_id'] = $invoiceId;
            }
            $message .= ' '.self::correctionAdvice($invoiceId);
        }

        return new DocumentRejected($message, $errors);
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
     * The id of the invoice a refused import left behind, when the body names it: the id of an
     * invoice object or the invoice_id of the error object.
     *
     * @param  array<mixed>  $body
     */
    private static function rejectedInvoiceId(array $body): ?string
    {
        $invoice = $body['invoice'] ?? null;
        $error = $body['error'] ?? null;

        return (is_array($invoice) ? self::id($invoice['id'] ?? null) : null)
            ?? (is_array($error) ? self::id($error['invoice_id'] ?? null) : null);
    }

    /**
     * The mapping of an answer that is not specific to an import.
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
        try {
            $body = JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw $this->redactor->sanitize($exception, (string) $response->getBody());
        }
        $invoice = $body['invoice'] ?? null;
        if (! is_array($invoice) || array_is_list($invoice)) {
            throw $this->redactor->unexpected($response, 'The B2Brouter response has no invoice');
        }

        return $invoice;
    }

    /**
     * The JSON body of an error answer, or an empty array when it is not JSON.
     *
     * @return array<mixed>
     */
    private function json(ResponseInterface $response): array
    {
        try {
            return JsonResponse::decode($response);
        } catch (UnexpectedResponse) {
            return [];
        }
    }

    /**
     * The error object of an error answer, or null when there is none.
     *
     * @return array<mixed>|null
     */
    private function error(ResponseInterface $response): ?array
    {
        $error = $this->json($response)['error'] ?? null;

        return is_array($error) ? $error : null;
    }

    /**
     * The raw (not yet redacted) messages of an errors list joined with "; ", or null when it has
     * none. An entry is either a string or an object with a string "error"; anything else is ignored.
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

        return $messages === [] ? null : implode('; ', $messages);
    }

    /**
     * ", code on param" for the error object, or an empty string without them.
     *
     * @param  array<mixed>|null  $error
     */
    private function codeAndParam(?array $error): string
    {
        $code = self::text($error['code'] ?? null);
        $param = self::text($error['param'] ?? null);

        return $this->redactor->redact(($code === null ? '' : ', '.$code).($param === null ? '' : ' on '.$param));
    }

    /**
     * The provider's error message in quotes, redacted and shortened, or a note that there is none.
     *
     * @param  array<mixed>|null  $error
     */
    private function quote(?array $error): string
    {
        $message = self::text($error['message'] ?? null);

        return $message === null ? 'no details given.' : '"'.$this->shortened($this->redactor->redact($message)).'"';
    }

    /**
     * The text cut to QUOTED_BYTES, so a long provider text stays a short message.
     */
    private function shortened(string $text): string
    {
        return strlen($text) > self::QUOTED_BYTES ? mb_strcut($text, 0, self::QUOTED_BYTES, 'UTF-8').'...' : $text;
    }

    /**
     * The id of a B2Brouter object, which is a positive integer, as a string; null otherwise.
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

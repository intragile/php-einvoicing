<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterErrors;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
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

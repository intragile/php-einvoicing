<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

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
use Psr\Http\Message\ResponseInterface;

/**
 * Reads B2Brouter response bodies and error answers for the B2Brouter response mappers: the
 * invoice object, the error object, the failure of an invoice, and the exceptions of refused
 * imports and reads. Every text taken from a response has the API key removed.
 *
 * @internal
 */
final class B2BrouterErrors
{
    /** The B2Brouter error code of a receiver that is not on the Peppol network. */
    private const NO_RECEIVER = 'PEPPOL_NO_RECEIVER';

    /** The sandbox reports an unregistered recipient only in the errors list, without an error code. */
    private const NO_RECEIVER_MESSAGE = '/not registered to receive/i';

    /** Bytes of a provider error message quoted in an exception message. */
    private const QUOTED_BYTES = 300;

    public function __construct(private readonly Redactor $redactor) {}

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

    /**
     * Whether the error object is B2Brouter's refusal of an invoice number that already exists.
     *
     * @param  array<mixed>|null  $error
     */
    public static function isNumberTaken(?array $error): bool
    {
        return ($error['code'] ?? null) === 'parameter_taken' && ($error['param'] ?? null) === 'number';
    }

    /**
     * The exception a refused import stands for: 403 is AuthenticationFailed, a 5xx status is
     * OutcomeUnknown, 400, 406, 409 and 422 are DocumentRejected (a taken number as a duplicate)
     * and any other status is mapped as a refused read.
     *
     * @param  string  $subject  the invoice, as named in messages, for example 'invoice number "INV-1"'
     */
    public function importFailure(ResponseInterface $response, string $subject): TransportException
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
        if ($status === 422 && self::isNumberTaken($error)) {
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
            sprintf('B2Brouter rejected %s (HTTP %d%s): %s', $subject, $status, $this->codeAndParam($error), $this->quote($error)),
            $errors,
        );
    }

    /**
     * The mapping shared by every request that reads, and by refused acknowledgements.
     *
     * @param  string  $operation  what was attempted, phrased to start a sentence
     */
    public function readFailure(ResponseInterface $response, string $operation): TransportException
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
    public function invoice(ResponseInterface $response): array
    {
        $invoice = $this->body($response)['invoice'] ?? null;
        if (! is_array($invoice) || array_is_list($invoice)) {
            throw $this->redactor->unexpected($response, 'The B2Brouter response has no invoice');
        }

        return $invoice;
    }

    /**
     * The decoded JSON body.
     *
     * @return array<mixed>
     *
     * @throws UnexpectedResponse when the body is not JSON
     */
    public function body(ResponseInterface $response): array
    {
        try {
            return JsonResponse::decode($response);
        } catch (UnexpectedResponse $exception) {
            throw $this->redactor->sanitize($exception, (string) $response->getBody());
        }
    }

    /**
     * The error object of an error response, or null when there is none.
     *
     * @return array<mixed>|null
     */
    public function error(ResponseInterface $response): ?array
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
     * The reason is the error message, or the errors list when there is no message; with
     * $errorsListFirst it is the errors list, or the error message when the list is empty. A
     * reason taken from the errors list is cut to a short message.
     *
     * @param  array<mixed>  $invoice
     */
    public function failure(array $invoice, bool $errorsListFirst = false): ?DeliveryFailure
    {
        $state = $invoice['state'] ?? null;
        if ($state !== 'error' && $state !== 'invalid') {
            return null;
        }
        $code = self::text($invoice['error_code'] ?? null);
        $errorMessage = self::text($invoice['error_message'] ?? null);
        $listed = $errorsListFirst || $errorMessage === null ? $this->listedErrors($invoice['errors'] ?? null) : null;
        $message = $errorsListFirst ? ($listed ?? $errorMessage) : ($errorMessage ?? $listed);
        $fromList = $message !== null && $message === $listed;
        $category = match (true) {
            $state === 'invalid' => FailureCategory::Validation,
            $code === self::NO_RECEIVER,
            $code === null && $message !== null && preg_match(self::NO_RECEIVER_MESSAGE, $message) === 1 => FailureCategory::RecipientNotFound,
            default => FailureCategory::Transport,
        };

        return new DeliveryFailure(
            $category,
            $message === null ? null : $this->shortened($this->redactor->redact($message), $fromList),
            $code === null ? null : $this->redactor->redact($code),
        );
    }

    /**
     * ", code on param" for the error object, or an empty string without them.
     *
     * @param  array<mixed>|null  $error
     */
    public function codeAndParam(?array $error): string
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
    public function quote(?array $error): string
    {
        $message = self::text($error['message'] ?? null);
        if ($message === null) {
            return 'no details given.';
        }

        return '"'.$this->shortened($this->redactor->redact($message), true).'"';
    }

    /**
     * The id of a B2Brouter object, which is a positive integer, as a string; null otherwise.
     */
    public static function id(mixed $value): ?string
    {
        return is_int($value) && $value > 0 ? (string) $value : null;
    }

    /**
     * The value when it is a string with more than whitespace, otherwise null.
     */
    public static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The text cut to QUOTED_BYTES when asked to, so a long provider text stays a short message.
     */
    private function shortened(string $text, bool $cut): string
    {
        return $cut && strlen($text) > self::QUOTED_BYTES ? mb_strcut($text, 0, self::QUOTED_BYTES, 'UTF-8').'...' : $text;
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
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Syntax\Verifactu\SubmissionWriter;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Submits VERI*FACTU records to the AEAT SOAP service and looks registered records up.
 *
 * Mutual TLS is the business of the PSR-18 client behind the HttpClient: it must present the
 * issuer's certificate. The endpoint comes from the configuration only.
 *
 * The answer body is read first, whatever the HTTP status, because AEAT returns SOAP faults with
 * HTTP 500. A Client fault with an authentication code is AuthenticationFailed; any other Client
 * fault refuses the submission as a whole and is DocumentRejected. A Server fault, an answer that
 * cannot be read and a request that got no answer leave the outcome of a submission unknown:
 * resending the very same bytes is safe, because AEAT answers a record it already holds with
 * RegistroDuplicado. A lookup only reads, so its failures are TransportUnavailable, except an
 * authentication fault.
 */
final class AeatClient
{
    /** @var array<string, string> */
    private const HEADERS = [
        'Content-Type' => 'text/xml; charset=utf-8',
        'SOAPAction' => '""',
        'Accept' => 'text/xml',
    ];

    private const RESEND_ADVICE = 'To settle it, resend the same bytes: AEAT answers a duplicate with RegistroDuplicado, or call lookup() for each record.';

    private readonly ResponseReader $reader;

    public function __construct(
        private readonly AeatConfig $config,
        private readonly HttpClient $http,
    ) {
        $this->reader = new ResponseReader;
    }

    /**
     * Writes the submission, validates it against SuministroLR.xsd and posts it.
     *
     * @throws InvalidRecord when the submission fails the schema; nothing is sent
     * @throws AuthenticationFailed when AEAT refuses the certificate or the caller's authorisation
     * @throws DocumentRejected when AEAT refuses the submission as a whole with a Client fault
     * @throws RateLimited when AEAT answers HTTP 429
     * @throws OutcomeUnknown when AEAT may or may not have registered the records
     */
    public function submit(Submission $submission): SubmissionResponse
    {
        $envelope = SoapEnvelope::wrap((new SubmissionWriter($this->config->schemas))->write($submission));
        $subject = sprintf(
            'The submission of %d %s of issuer %s',
            count($submission->records),
            count($submission->records) === 1 ? 'record' : 'records',
            $submission->header->issuer->value,
        );

        try {
            $response = $this->http->send('POST', $this->config->endpoint(), self::HEADERS, $envelope, $this->config->maxResponseBytes);
        } catch (OutcomeUnknown|TransportUnavailable $exception) {
            throw self::unknown($subject, 'got no answer', $exception);
        } catch (UnexpectedResponse $exception) {
            throw self::unknown($subject, sprintf('was answered with a body over the limit of %d bytes', $this->config->maxResponseBytes), $exception);
        }

        $status = $response->getStatusCode();
        try {
            $opened = SoapEnvelope::open((string) $response->getBody());
        } catch (UnsafeXml $exception) {
            throw $this->notSoap($response, $subject, sprintf('was answered with HTTP %d and a body carrying a DOCTYPE declaration, which is refused unread', $status), $exception, true);
        } catch (UnsupportedDocument $exception) {
            throw $this->notSoap($response, $subject, sprintf('was answered with HTTP %d and a body that is not a SOAP envelope', $status), $exception, true);
        }

        if ($opened instanceof SoapFault) {
            throw $this->submissionFault($opened, $subject);
        }

        try {
            return $this->reader->submission($opened);
        } catch (UnsupportedDocument $exception) {
            throw $this->notSoap(
                $response,
                $subject,
                sprintf('was answered with HTTP %d and a SOAP body that is not a readable submission response (%s)', $status, rtrim($exception->getMessage(), '.')),
                $exception,
                true,
            );
        }
    }

    /**
     * Asks AEAT for the record it holds for one invoice.
     *
     * @return RecordStatus|null the record, or null when AEAT holds none for the invoice
     *
     * @throws InvalidRecord when the query fails ConsultaLR.xsd; nothing is sent
     * @throws AuthenticationFailed when AEAT refuses the certificate or the caller's authorisation
     * @throws RateLimited when AEAT answers HTTP 429
     * @throws TransportUnavailable when the query fails in any other way; it may be repeated
     */
    public function lookup(LookupRequest $request): ?RecordStatus
    {
        $envelope = SoapEnvelope::wrap((new LookupWriter($this->config->schemas))->write($request));
        $subject = sprintf(
            'Looking up invoice %s of %s issued by %s',
            $request->invoice->number,
            $request->invoice->dateText(),
            $request->issuer->value,
        );

        try {
            $response = $this->http->send('POST', $this->config->endpoint(), self::HEADERS, $envelope, $this->config->maxResponseBytes);
        } catch (OutcomeUnknown|TransportUnavailable $exception) {
            throw new TransportUnavailable($subject.' failed without an answer from AEAT.', $exception);
        } catch (UnexpectedResponse $exception) {
            throw new TransportUnavailable(sprintf('%s failed: the answer exceeds the limit of %d bytes.', $subject, $this->config->maxResponseBytes), $exception);
        }

        $status = $response->getStatusCode();
        try {
            $opened = SoapEnvelope::open((string) $response->getBody());
        } catch (UnsafeXml $exception) {
            throw $this->notSoap($response, $subject, sprintf('failed: AEAT answered HTTP %d with a body carrying a DOCTYPE declaration, which is refused unread', $status), $exception, false);
        } catch (UnsupportedDocument $exception) {
            throw $this->notSoap($response, $subject, sprintf('failed: AEAT answered HTTP %d with a body that is not a SOAP envelope', $status), $exception, false);
        }

        if ($opened instanceof SoapFault) {
            if ($opened->isClient() && self::isAuthentication($opened)) {
                throw self::authenticationFault($opened, $subject);
            }

            throw new TransportUnavailable(sprintf('%s failed: AEAT answered with a %s fault: %s', $subject, $opened->faultCode, self::sentence($opened->faultString)));
        }

        return $this->readLookup($opened, $request, $subject, $status);
    }

    private function readLookup(DOMElement $payload, LookupRequest $request, string $subject, int $status): ?RecordStatus
    {
        try {
            return $this->reader->lookup($payload, $request->invoice);
        } catch (UnsupportedDocument $exception) {
            throw new TransportUnavailable(
                sprintf('%s failed: AEAT answered HTTP %d with a SOAP body that is not a readable query response (%s).', $subject, $status, rtrim($exception->getMessage(), '.')),
                $exception,
            );
        }
    }

    private function submissionFault(SoapFault $fault, string $subject): AuthenticationFailed|DocumentRejected|OutcomeUnknown
    {
        if (! $fault->isClient()) {
            return self::unknown($subject, sprintf('was answered with a %s fault (%s)', $fault->faultCode, rtrim($fault->faultString, '.')));
        }
        if (self::isAuthentication($fault)) {
            return self::authenticationFault($fault, $subject);
        }

        return new DocumentRejected(
            sprintf('%s was refused by AEAT: %s', $subject, self::sentence($fault->faultString)),
            ['errors' => [$fault->code === null ? $fault->faultString : $fault->code.': '.$fault->faultString]],
            null,
            $fault->code,
        );
    }

    /**
     * Maps an answer that cannot be read: 401 and 403 are AuthenticationFailed and 429 is
     * RateLimited; anything else leaves a submission's outcome unknown and makes a lookup
     * TransportUnavailable.
     *
     * @param  string  $what  what happened, phrased to follow the subject
     */
    private function notSoap(ResponseInterface $response, string $subject, string $what, Throwable $previous, bool $submission): AuthenticationFailed|RateLimited|OutcomeUnknown|TransportUnavailable
    {
        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            return new AuthenticationFailed(
                sprintf('%s was refused: AEAT did not accept the client certificate or its authorisation (HTTP %d).', $subject, $status),
                $previous,
            );
        }
        if ($status === 429) {
            return new RateLimited(
                sprintf('%s was refused: AEAT rate limit reached (HTTP 429).', $subject),
                ReadFailure::retryAfter($response),
                $previous,
            );
        }

        return $submission ? self::unknown($subject, $what, $previous) : new TransportUnavailable($subject.' '.$what.'.', $previous);
    }

    private static function authenticationFault(SoapFault $fault, string $subject): AuthenticationFailed
    {
        return new AuthenticationFailed(
            sprintf('%s was refused: AEAT did not accept the client certificate or its authorisation: %s', $subject, self::sentence($fault->faultString)),
            null,
            $fault->code,
        );
    }

    private static function isAuthentication(SoapFault $fault): bool
    {
        return $fault->code !== null && in_array($fault->code, AeatErrors::AUTHENTICATION_CODES, true);
    }

    /**
     * @param  string  $what  what happened, phrased to follow the subject
     */
    private static function unknown(string $subject, string $what, ?Throwable $previous = null): OutcomeUnknown
    {
        return new OutcomeUnknown(
            sprintf('%s %s, so AEAT may or may not have registered the records. %s', $subject, $what, self::RESEND_ADVICE),
            $previous,
        );
    }

    private static function sentence(string $text): string
    {
        $text = rtrim($text);

        return $text === '' || str_ends_with($text, '.') ? $text : $text.'.';
    }
}

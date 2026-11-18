<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\Syntax\Flux10\Flux10Writer;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\B2Brouter\SameOriginRedirect;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\SendResult;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends French invoices and Flux 10 e-reporting files through B2Brouter acting as the sender's
 * plateforme agréée.
 *
 * A document is imported into the configured account and sent in one request. Every document,
 * XML or Factur-X PDF, is uploaded as application/octet-stream with its B2Brouter document type
 * code. B2Brouter validates the document against the French rules before sending; a document
 * that fails them is refused with HTTP 422 and the invoice stays in B2Brouter in error state,
 * where it must be corrected or deleted before the same number can be imported again.
 *
 * Depositing an own Flux 10 file and B2Brouter's native e-reporting pipeline, which builds the
 * e-reports from the invoices and payments B2Brouter handles, are exclusive per account: an
 * account uses one or the other, never both.
 *
 * Requests are sent through an HttpClient whose PSR-18 client must not follow redirects.
 */
final class B2BrouterFrance
{
    /** The media type of JSON request bodies. */
    private const JSON = 'application/json';

    /** The document element of a Flux 10 e-reporting file, which has no namespace. */
    private const FLUX10_ROOT = 'Report';

    /** An idempotency key B2Brouter uses as given: 1 to 64 visible ASCII characters. */
    private const IDEMPOTENCY_KEY_PATTERN = '/\A[\x21-\x7E]{1,64}\z/';

    /** Payment dates are the dates in France. */
    private const TIME_ZONE = 'Europe/Paris';

    private readonly HttpClient $http;

    private readonly FranceResponseMapper $mapper;

    public function __construct(
        private readonly B2BrouterConfig $config,
        HttpClient $http,
    ) {
        $this->http = $http->withSecrets($config->secrets());
        $this->mapper = new FranceResponseMapper($config->redactor());
    }

    /**
     * Imports the document into the configured account and sends it.
     *
     * The result's document id is the B2Brouter invoice id. Its state is Pending while B2Brouter
     * sends, Delivered once sent and Failed with B2Brouter's errors when sending failed.
     *
     * @throws OutcomeUnknown when the request failed without an answer, B2Brouter answered a 5xx
     *                        status or a 201 answer cannot be read; the invoice may have been
     *                        imported, so look up its number before resending
     * @throws UnsupportedDocument when B2Brouter does not accept the document (HTTP 406)
     * @throws TransportException when B2Brouter refused the document or the request: a
     *                            DocumentRejected for a failed validation (HTTP 422) or a
     *                            duplicate number, AuthenticationFailed for a refused key or a
     *                            missing permission (HTTP 401, 403), RateLimited for HTTP 429
     */
    public function send(FrenchDocument $document): SendResult
    {
        $url = sprintf(
            '%s/accounts/%d/invoices/import?send_after_import=true&issued=true&transport_type_code_for_contact=peppol&document_type_code_for_contact=%s',
            $this->config->baseUrl,
            $this->config->accountId,
            rawurlencode($document->type->value),
        );

        try {
            $response = $this->http->send(
                'POST',
                $url,
                $this->config->headers() + ['Content-Type' => FranceResponseMapper::IMPORT_CONTENT_TYPE],
                $document->bytes,
            );
        } catch (OutcomeUnknown $exception) {
            $subject = FranceResponseMapper::subject($document);

            throw new OutcomeUnknown(
                sprintf(
                    'Importing %s into B2Brouter failed without an answer, so B2Brouter may or may not have imported it. %s',
                    $subject,
                    ResponseMapper::checkBeforeResending($subject),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->config->redactor()->sanitize($exception);
        }

        return $this->mapper->sendResult($response, $document);
    }

    /**
     * The state of an issued or received invoice at B2Brouter, with the French lifecycle status
     * it stands for.
     *
     * B2Brouter answers a received invoice with a 302 redirect, which is followed once when it
     * stays on the API host; the API key is sent to the target again.
     *
     * @throws UnexpectedResponse when the invoice is unknown, the body is not an invoice with a
     *                            state, or B2Brouter redirects to another host, more than once or
     *                            with a status other than 302
     * @throws TransportUnavailable when a request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function lifecycle(string $invoiceId): PlatformStatus
    {
        self::checkInvoiceId($invoiceId);

        $url = sprintf('%s/invoices/%s', $this->config->baseUrl, $invoiceId);
        $response = $this->get($url);
        $redirected = $response->getStatusCode() === 302;
        if ($redirected) {
            $response = $this->get(SameOriginRedirect::target($response, $url, $this->config->baseUrl));
            if (SameOriginRedirect::isRedirect($response)) {
                throw SameOriginRedirect::refused($response, sprintf('B2Brouter redirected invoice "%s" more than once', $invoiceId));
            }
        }

        // Any other redirect is refused by the mapper without quoting its body.
        return $this->mapper->platformStatus($response, $invoiceId, $redirected);
    }

    /**
     * Answers an invoice the account received; B2Brouter emits the lifecycle message for the
     * answer's status to the seller.
     *
     * The returned status is read from B2Brouter's answer. B2Brouter keeps one state for an
     * approval and a partial approval, so a partial approval reads back as Approved (205).
     *
     * @throws OutcomeUnknown when the request failed without an answer or B2Brouter answered a 5xx
     *                        status, a redirect or a 2xx status other than 200; read the state
     *                        with lifecycle() before answering again
     * @throws DocumentRejected when B2Brouter refused the answer (HTTP 400, 422), for example a
     *                          state change the invoice does not allow
     * @throws UnexpectedResponse when the invoice is unknown or the body is not an invoice with a state
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function respond(string $receivedInvoiceId, BuyerResponse $response): PlatformStatus
    {
        self::checkInvoiceId($receivedInvoiceId);

        try {
            $answer = $this->http->send(
                'POST',
                sprintf('%s/invoices/%s/mark_as', $this->config->baseUrl, $receivedInvoiceId),
                $this->config->headers() + ['Content-Type' => self::JSON],
                $response->toJson(),
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Answering B2Brouter invoice "%s" with status %s failed without an answer, so the invoice may or may not have changed state. %s',
                    $receivedInvoiceId,
                    $response->status()->value,
                    FranceResponseMapper::checkStateBeforeRepeating($receivedInvoiceId),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->config->redactor()->sanitize($exception);
        }

        return $this->mapper->respondedStatus($answer, $receivedInvoiceId, $response);
    }

    /**
     * Records a payment of an invoice the account issued, then reads the invoice.
     *
     * Without an amount the payment settles the amount outstanding when B2Brouter records it.
     * B2Brouter reports the payment to the DGFiP itself: Collected (212) for a domestic invoice
     * for services, an e-reporting payment record otherwise. Once the payments cover the invoice
     * total the invoice turns "paid", which reads back as Collected (212).
     *
     * @param  BigDecimal|string|int|null  $amount  the cash amount paid, taxes included, greater
     *                                              than zero with at most 2 decimals; null for
     *                                              the full amount outstanding
     * @param  DateTimeImmutable|null  $paidOn  when the payment was made, sent as its date in
     *                                          Paris; B2Brouter takes today without it
     *
     * @throws OutcomeUnknown when the request failed without an answer or B2Brouter answered a 5xx
     *                        status, a redirect or a 2xx status other than 200 and 201; a payment
     *                        is not idempotent, so list the invoice's payments before recording
     *                        it again
     * @throws DocumentRejected when B2Brouter refused the payment (HTTP 400, 422)
     * @throws UnexpectedResponse when reading the invoice after the payment was recorded failed;
     *                            the payment must not be recorded again
     * @throws TransportException when B2Brouter refused the request, for example for an invoice
     *                            outside the account
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits) or the
     *                                  amount is not accepted
     */
    public function recordPayment(string $issuedInvoiceId, BigDecimal|string|int|null $amount = null, ?DateTimeImmutable $paidOn = null): PlatformStatus
    {
        self::checkInvoiceId($issuedInvoiceId);
        // The id is digits; a JSON number has no leading zeros.
        $payment = ['invoice_id' => ltrim($issuedInvoiceId, '0') ?: '0'];
        if ($amount === null) {
            $payment['full'] = FranceResponseMapper::json(true);
        } else {
            $payment['amount'] = FranceResponseMapper::jsonAmount($amount, 'The payment amount');
        }
        if ($paidOn !== null) {
            $payment['date'] = FranceResponseMapper::json($paidOn->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('Y-m-d'));
        }

        try {
            $answer = $this->http->send(
                'POST',
                sprintf('%s/accounts/%d/payments', $this->config->baseUrl, $this->config->accountId),
                $this->config->headers() + ['Content-Type' => self::JSON],
                FranceResponseMapper::jsonObject(['payment' => FranceResponseMapper::jsonObject($payment)]),
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Recording a payment on B2Brouter invoice "%s" failed without an answer, so B2Brouter may or may not have recorded it. %s',
                    $issuedInvoiceId,
                    FranceResponseMapper::checkPaymentsBeforeRepeating($issuedInvoiceId),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->config->redactor()->sanitize($exception);
        }

        $paymentId = $this->mapper->recordedPayment($answer, $issuedInvoiceId);

        try {
            return $this->lifecycle($issuedInvoiceId);
        } catch (TransportException $exception) {
            throw new UnexpectedResponse(
                sprintf(
                    'B2Brouter recorded %s on invoice "%s", but reading the invoice afterwards failed; do not record it again, read its state with lifecycle(): %s',
                    $paymentId === null ? 'the payment' : 'payment '.$paymentId,
                    $issuedInvoiceId,
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * Deposits an own Flux 10 e-reporting file with the configured account, as a B2Brouter
     * ledger that B2Brouter sends to the DGFiP.
     *
     * Use this only for an account that does not use B2Brouter's native e-reporting pipeline:
     * the two are exclusive per account. B2Brouter replaces the file's ReportDocument/Id with the
     * ledger id and its Sender with its own platform matricule, so both may hold placeholders.
     *
     * The deposit is idempotent per key: a resend of the same file with the same key returns the
     * ledger already imported (alreadyExisted true) instead of creating another, and a resend of a
     * different file with the same key is refused. Without a key, the SHA-256 hash of the file in
     * hexadecimal is sent, so depositing the same bytes again is always safe. Read the state as
     * the DGFiP processes the file with eReportStatus() or the ledger.state_change webhook.
     *
     * @param  string|null  $idempotencyKey  1 to 64 visible ASCII characters; null for the hash of the file
     *
     * @throws UnsupportedDocument when the XML is not well-formed, its document element is not a
     *                             Report without namespace or B2Brouter does not accept the file's
     *                             content type (HTTP 406)
     * @throws UnsafeXml when the XML carries a DOCTYPE declaration or declares entities
     * @throws OutcomeUnknown when the request failed without an answer, B2Brouter answered a 5xx
     *                        status, a redirect or a 2xx status other than 200 and 201, or a
     *                        successful answer cannot be read; a resend of the same file with the
     *                        same key is safe
     * @throws DocumentRejected when B2Brouter refused the file (HTTP 400, 422), for example
     *                          for a schema error, an issuer SIREN other than the account's or an
     *                          account without a DGFiP tax report setting, or when the key was
     *                          used with a different file (HTTP 409)
     * @throws AuthenticationFailed when the account's B2Brouter plan lacks the ledger permission
     *                              (HTTP 403) or B2Brouter refused the key (HTTP 401)
     * @throws TransportException when B2Brouter refused the request otherwise, for example
     *                            RateLimited for HTTP 429
     * @throws InvalidArgumentException when the idempotency key is not 1 to 64 visible ASCII characters
     */
    public function depositEReport(string $flux10Xml, ?string $idempotencyKey = null): EReportDeposit
    {
        if ($idempotencyKey !== null && preg_match(self::IDEMPOTENCY_KEY_PATTERN, $idempotencyKey) !== 1) {
            throw new InvalidArgumentException('A B2Brouter Idempotency-Key consists of 1 to 64 visible ASCII characters.');
        }
        $root = SafeXml::load($flux10Xml)->documentElement;
        if (! $root instanceof DOMElement || $root->namespaceURI !== null || $root->localName !== self::FLUX10_ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element %s is not the Report element without namespace of a Flux 10 e-reporting file.',
                $root instanceof DOMElement ? sprintf('{%s}%s', $root->namespaceURI ?? '', $root->localName) : '(none)',
            ));
        }
        $key = $idempotencyKey ?? hash('sha256', $flux10Xml);

        try {
            $response = $this->http->send(
                'POST',
                sprintf('%s/accounts/%d/ledgers/import', $this->config->baseUrl, $this->config->accountId),
                $this->config->headers() + ['Content-Type' => FranceResponseMapper::IMPORT_CONTENT_TYPE, 'Idempotency-Key' => $key],
                $flux10Xml,
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Depositing the e-reporting file with B2Brouter failed without an answer, so B2Brouter may or may not have imported it. %s',
                    FranceResponseMapper::resendWithSameKey($key),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->config->redactor()->sanitize($exception);
        }

        return $this->mapper->deposit($response, $key);
    }

    /**
     * Writes the e-report as a Flux 10 file and deposits it with the configured account; see
     * depositEReport().
     *
     * @param  string|null  $idempotencyKey  1 to 64 visible ASCII characters; null for the hash of the written file
     *
     * @throws InvalidArgumentException when the writer refuses the e-report or the key is not accepted
     * @throws OutcomeUnknown when the deposit failed without an answer; a resend with the same key is safe
     * @throws TransportException when B2Brouter refused the file or the request
     */
    public function depositReport(EReport $report, ?string $idempotencyKey = null): EReportDeposit
    {
        return $this->depositEReport((new Flux10Writer)->write($report), $idempotencyKey);
    }

    /**
     * The state of a deposited e-reporting file. A state this version does not know reads as
     * LedgerState::Unknown; the result's alreadyExisted is always true.
     *
     * @throws UnexpectedResponse when the ledger is unknown, B2Brouter answered with a redirect or
     *                            the body is not a ledger with an id
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter ledger id (digits)
     */
    public function eReportStatus(string $ledgerId): EReportDeposit
    {
        self::checkId($ledgerId, 'ledger');

        return $this->mapper->ledgerStatus($this->get(sprintf('%s/ledgers/%s', $this->config->baseUrl, $ledgerId)), $ledgerId);
    }

    /**
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    private static function checkInvoiceId(string $invoiceId): void
    {
        self::checkId($invoiceId, 'invoice');
    }

    /**
     * @param  string  $kind  the object the id names, for example "invoice"
     *
     * @throws InvalidArgumentException when the id is not digits
     */
    private static function checkId(string $id, string $kind): void
    {
        if (preg_match('/^[0-9]{1,20}\z/', $id) !== 1) {
            throw new InvalidArgumentException(sprintf('A B2Brouter %s id consists of digits only.', $kind));
        }
    }

    /**
     * @throws TransportUnavailable when the request failed without an answer
     * @throws UnexpectedResponse when the response exceeds the size cap
     */
    private function get(string $url): ResponseInterface
    {
        try {
            return $this->http->send('GET', $url, $this->config->headers());
        } catch (UnexpectedResponse $exception) {
            throw $this->config->redactor()->sanitize($exception);
        }
    }
}

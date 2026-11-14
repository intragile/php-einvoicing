<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Sbdh\InboundXml;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Transport;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends and receives documents over Peppol through the B2Brouter API (https://api.b2brouter.net).
 *
 * A document is imported into the configured account and queued for sending in one request.
 * B2Brouter does not forward the imported XML as is: it renders the transmitted document from its
 * own invoice model in the format of the configured document type code, so the delivered bytes may
 * differ from the XML passed in. The document as delivered is available from B2Brouter as the
 * invoice's "legal" document.
 *
 * Received documents are the account's unacknowledged received invoices; one that still arrives
 * wrapped in an SBDH is unwrapped. The B2Brouter sandbox, used with keys that start with "test_",
 * simulates the Peppol network: nothing is sent to or received from a real access point.
 *
 * Requests are sent through an HttpClient whose PSR-18 client must not follow redirects; this
 * transport follows a redirect itself, only where B2Brouter uses one.
 */
final class B2BrouterTransport implements InboundSource, Transport
{
    /** Received invoices requested per list page, the B2Brouter maximum. */
    private const PAGE_SIZE = 100;

    /** List pages read by one pending() call. */
    private const MAX_PAGES = 10;

    /** Accepted types of an original document, which is XML; error answers stay JSON. */
    private const ACCEPT_DOCUMENT = 'application/xml, text/xml, application/json;q=0.5, */*;q=0.1';

    private readonly HttpClient $http;

    private readonly ResponseMapper $mapper;

    public function __construct(
        private readonly B2BrouterConfig $config,
        HttpClient $http,
    ) {
        $this->http = $http->withSecrets($config->secrets());
        $this->mapper = new ResponseMapper($config->redactor());
    }

    /**
     * Imports the document into the configured account and queues it for sending over Peppol.
     *
     * @throws UnsupportedDocument when no B2Brouter document type code is configured for the
     *                             document type; no request is made
     * @throws OutcomeUnknown when the request failed without an answer, B2Brouter answered a 5xx
     *                        status or a 201 answer cannot be read; the invoice may have been
     *                        imported, so look up its number before resending
     * @throws TransportException when B2Brouter refused the document or the request
     */
    public function send(OutboundDocument $document): SendResult
    {
        $documentType = $document->documentType->value;
        $code = $this->config->documentTypeCode($documentType) ?? throw new UnsupportedDocument(sprintf(
            'B2Brouter has no document type code configured for "%s"; add one to the document type codes of the B2Brouter configuration once B2Brouter lists it.',
            $documentType,
        ));

        $url = sprintf(
            '%s/accounts/%d/invoices/import?send_after_import=true&issued=true&transport_type_code_for_contact=peppol&document_type_code_for_contact=%s',
            $this->config->baseUrl,
            $this->config->accountId,
            rawurlencode($code),
        );
        $number = DocumentNumber::of($document->xml);
        $subject = $number === null
            ? 'the invoice (its invoice number cannot be read from the document)'
            : sprintf('invoice number "%s"', $number);

        try {
            $response = $this->http->send(
                'POST',
                $url,
                $this->config->headers() + ['Content-Type' => 'application/octet-stream'],
                $document->xml,
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Importing %s into B2Brouter failed without an answer, so B2Brouter may or may not have imported it. %s',
                    $subject,
                    ResponseMapper::checkBeforeResending($subject),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        return $this->mapper->sendResult($response, $subject);
    }

    /**
     * The delivery status of an invoice sent through this account.
     *
     * @throws UnexpectedResponse when the id is a received invoice or unknown
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function status(string $documentId): DeliveryStatus
    {
        self::assertInvoiceId($documentId);

        // B2Brouter answers a received invoice with a redirect, which is reported, never followed.
        return $this->mapper->deliveryStatus($this->get($this->invoiceUrl($documentId)), $documentId);
    }

    /**
     * The unacknowledged received invoices of the account, at most ten pages of 100; acknowledge
     * each processed invoice to reach the ones behind them. B2Brouter's list reports no Peppol
     * participant identifiers, so sender and receiver are null.
     *
     * @return list<InboundReference>
     *
     * @throws TransportUnavailable when a request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request or answered unexpectedly
     */
    public function pending(): array
    {
        $references = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = sprintf(
                '%s/accounts/%d/invoices?type=ReceivedInvoice&offset=%d&limit=%d',
                $this->config->baseUrl,
                $this->config->accountId,
                $page * self::PAGE_SIZE,
                self::PAGE_SIZE,
            );
            $listed = $this->mapper->inboundReferences($this->get($url));
            foreach ($listed as $reference) {
                // An invoice inserted ahead of the offset meanwhile shifts the list back, so one may
                // be listed twice. One acknowledged meanwhile shifts it forward, so one may be
                // skipped; the next poll lists it.
                $references[$reference->documentId] ??= $reference;
            }
            if (count($listed) < self::PAGE_SIZE) {
                break;
            }
        }

        return array_values($references);
    }

    /**
     * The received document exactly as B2Brouter stored it, with any SBDH envelope removed. The
     * XML is parsed without DTD processing or network access; the response size is capped by the
     * HTTP client.
     *
     * The original-document endpoint carries no invoice metadata, so the reference holds only the
     * id and the time of the fetch; pending() reports the time B2Brouter received the invoice.
     *
     * @throws UnexpectedResponse when the invoice is unknown, B2Brouter redirects to another
     *                            host, more than once or with a status other than 302, or the
     *                            response exceeds the size cap
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its envelope is incomplete
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function fetch(string $documentId): InboundDocument
    {
        self::assertInvoiceId($documentId);

        $url = $this->invoiceUrl($documentId).'/as/original';
        $response = $this->get($url, self::ACCEPT_DOCUMENT);
        if ($response->getStatusCode() === 302) {
            $response = $this->get($this->redirectTarget($response, $url), self::ACCEPT_DOCUMENT);
            if (self::isRedirect($response)) {
                throw self::refusedRedirect($response, sprintf('B2Brouter redirected the original document of invoice "%s" more than once', $documentId));
            }
        } elseif (self::isRedirect($response)) {
            // Only a 302 is followed; any other redirect is refused without quoting its body.
            throw self::refusedRedirect($response, sprintf('B2Brouter answered the original document of invoice "%s" with a redirect other than 302, so it was not followed', $documentId));
        }
        $xml = $this->mapper->originalDocument($response, $documentId);

        return InboundXml::document(
            new InboundReference($documentId, null, null, null, new DateTimeImmutable('now', new DateTimeZone('UTC'))),
            $xml,
        );
    }

    /**
     * Acknowledges the received invoice, which removes it from pending().
     *
     * Acknowledging is idempotent at B2Brouter, so after an OutcomeUnknown it is safe to call this
     * again with the same id.
     *
     * @throws OutcomeUnknown when the request failed without an answer or B2Brouter answered a 5xx
     *                        status; retrying is safe
     * @throws UnexpectedResponse when B2Brouter does not know the invoice or answered unexpectedly
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function acknowledge(string $documentId): void
    {
        self::assertInvoiceId($documentId);

        try {
            $response = $this->http->send('POST', $this->invoiceUrl($documentId).'/ack', $this->config->headers());
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Acknowledging B2Brouter invoice "%s" failed without an answer, so it may or may not have been recorded; acknowledging is idempotent, so it is safe to retry.',
                    $documentId,
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        $this->mapper->acknowledged($response, $documentId);
    }

    /**
     * @throws TransportUnavailable when the request failed without an answer
     * @throws UnexpectedResponse when the response exceeds the size cap
     */
    private function get(string $url, ?string $accept = null): ResponseInterface
    {
        $headers = $this->config->headers();
        if ($accept !== null) {
            $headers['Accept'] = $accept;
        }

        try {
            return $this->http->send('GET', $url, $headers);
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }
    }

    /**
     * The URL a redirect points to, when it stays on the https origin of the base URL; the API key
     * is sent to it again.
     *
     * @throws UnexpectedResponse when the redirect has no usable Location or leaves that origin;
     *                            neither the Location nor the body is quoted, as they may carry a token
     */
    private function redirectTarget(ResponseInterface $response, string $requestUrl): string
    {
        $location = trim($response->getHeaderLine('Location'));
        $base = parse_url($this->config->baseUrl);
        $origin = sprintf('https://%s%s', $base['host'] ?? '', isset($base['port']) ? ':'.$base['port'] : '');

        if (str_starts_with($location, '/') && ! str_starts_with($location, '//')) {
            $location = $origin.$location;
        } elseif ($location !== '' && ! str_contains($location, ':') && ! str_starts_with($location, '//')) {
            // A relative path, resolved against the directory of the request path.
            $path = (string) parse_url($requestUrl, PHP_URL_PATH);
            $location = $origin.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
        }

        $target = $location === '' ? false : parse_url($location);
        if (
            $target === false
            || strtolower($target['scheme'] ?? '') !== 'https'
            || strtolower($target['host'] ?? '') !== strtolower($base['host'] ?? '')
            || ($target['port'] ?? 443) !== ($base['port'] ?? 443)
            || isset($target['user'])
            || isset($target['pass'])
        ) {
            throw self::refusedRedirect($response, 'B2Brouter redirected the request away from its API host or without a usable Location, so the redirect was not followed');
        }

        return $location;
    }

    /**
     * An UnexpectedResponse for a redirect that is not followed. It keeps no body excerpt, because
     * a redirect body usually repeats the Location, which may carry a token.
     */
    private static function refusedRedirect(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse($response, $reason, '');
    }

    private static function isRedirect(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 300 && $response->getStatusCode() <= 399;
    }

    private function invoiceUrl(string $invoiceId): string
    {
        return sprintf('%s/invoices/%s', $this->config->baseUrl, $invoiceId);
    }

    private static function assertInvoiceId(string $invoiceId): void
    {
        if (preg_match('/^[0-9]{1,20}\z/', $invoiceId) !== 1) {
            throw new InvalidArgumentException('A B2Brouter invoice id consists of digits only.');
        }
    }
}

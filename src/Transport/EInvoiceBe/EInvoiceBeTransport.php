<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\EInvoiceBe;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\MultipartBody;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Sbdh\InboundXml;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Transport;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends and receives documents over Peppol through the e-invoice.be Peppol Access Point API
 * (https://api.e-invoice.be).
 *
 * Sending takes two requests: the UBL is uploaded as a draft, then the draft is sent with the
 * sender and receiver participant identifiers. e-invoice.be accepts Peppol BIS Billing 3.0 UBL
 * only, so XRechnung and CII documents are refused before any request. When it transmits the
 * document, e-invoice.be writes the final sender and receiver into the cbc:EndpointID elements of
 * the UBL; the rest of the document is sent unchanged.
 *
 * e-invoice.be has no acknowledgement for received documents: pending() lists the inbox newest
 * first, back to the configured look-back, and a document stays listed after it is processed, so
 * consumers de-duplicate on the document id. A received document is downloaded from a signed URL,
 * which is a credential: it is fetched without the API key and kept out of every message.
 *
 * Requests are sent through an HttpClient whose PSR-18 client must not follow redirects. The
 * company the API key belongs to decides whether a send reaches the Peppol network; a sandbox
 * company only simulates it.
 */
final class EInvoiceBeTransport implements InboundSource, Transport
{
    /** The Peppol document types e-invoice.be accepts. */
    public const ACCEPTED_DOCUMENT_TYPES = [PeppolBis3::INVOICE, PeppolBis3::CREDIT_NOTE];

    /** Inbox documents requested per page, the e-invoice.be maximum. */
    private const PAGE_SIZE = 100;

    /** Accepted types of a downloaded document, which is XML. */
    private const ACCEPT_DOCUMENT = 'application/xml, text/xml, */*;q=0.1';

    private readonly HttpClient $http;

    private readonly ResponseMapper $mapper;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $clock  the current time, which the inbound
     *                                                      look-back counts back from; the system
     *                                                      clock in UTC by default
     */
    public function __construct(
        private readonly EInvoiceBeConfig $config,
        HttpClient $http,
        ?Closure $clock = null,
    ) {
        $this->http = $http->withSecrets($config->secrets());
        $this->mapper = new ResponseMapper($config->redactor());
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Uploads the document as a draft and sends the draft over Peppol.
     *
     * The draft is sent with all four participant query parameters, taken from the document's
     * sender and receiver. A refused send leaves the draft in the account.
     *
     * @throws UnsupportedDocument when the document is not a Peppol BIS Billing 3.0 invoice or
     *                             credit note whose XML matches its document type; no request is made
     * @throws UnsafeXml when the document carries a DOCTYPE declaration; no request is made
     * @throws OutcomeUnknown when the upload or the send failed without an answer or with a 5xx
     *                        status; the message tells how to check before resending
     * @throws TransportException when e-invoice.be refused the document or the request
     */
    public function send(OutboundDocument $document): SendResult
    {
        $this->assertSendable($document);

        $number = DocumentNumber::of($document->xml);
        $subject = $number === null
            ? 'the invoice (its invoice number cannot be read from the document)'
            : sprintf('invoice number "%s"', $number);

        $draftId = $this->upload($document->xml, $subject);

        $url = sprintf(
            '%s/api/documents/%s/send?%s',
            $this->config->baseUrl,
            $draftId,
            http_build_query([
                'sender_peppol_scheme' => $document->sender->icd,
                'sender_peppol_id' => $document->sender->value,
                'receiver_peppol_scheme' => $document->receiver->icd,
                'receiver_peppol_id' => $document->receiver->value,
            ], '', '&', PHP_QUERY_RFC3986),
        );

        try {
            $response = $this->http->send('POST', $url, $this->config->headers());
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Sending draft "%s" of %s over e-invoice.be failed without an answer, so it may or may not have been sent: %s',
                    $draftId,
                    $subject,
                    ResponseMapper::checkDraftBeforeResending(),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        return $this->mapper->sendResult($response, $draftId, $subject);
    }

    /**
     * The delivery status of a document sent through this company. For a FAILED document the
     * timeline is read once more; e-invoice.be records no technical reason there, so the failure
     * only says that the transmission failed. When the timeline cannot be read, the state is still
     * Failed and the failure carries no message.
     *
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when e-invoice.be refused the request or answered unexpectedly
     * @throws InvalidArgumentException when the id is not an e-invoice.be document id
     */
    public function status(string $documentId): DeliveryStatus
    {
        self::assertDocumentId($documentId);

        $url = $this->documentUrl($documentId);
        $document = $this->mapper->document($this->get($url), $documentId);
        $state = ResponseMapper::stateOf($document['state'] ?? null);
        if ($state !== DeliveryState::Failed) {
            return new DeliveryStatus($documentId, $state);
        }

        try {
            [$failure, $changedAt] = $this->mapper->sendFailure($this->get($url.'/timeline'), $documentId)
                ?? [new DeliveryFailure(FailureCategory::Transport), null];
        } catch (TransportException) {
            // The state was read; a timeline that cannot be read must not hide it.
            [$failure, $changedAt] = [new DeliveryFailure(FailureCategory::Transport), null];
        }

        return new DeliveryStatus($documentId, $state, $failure, $changedAt);
    }

    /**
     * The documents received within the configured look-back, newest first, from at most the
     * configured number of inbox pages of 100. A document stays listed after it is processed
     * until it falls out of the look-back, so de-duplicate on the document id. The inbox reports
     * neither Peppol participant identifiers nor a Peppol document type, so sender, receiver and
     * document type are null; fetch() reports the participants.
     *
     * @return list<InboundReference>
     *
     * @throws TransportUnavailable when a request failed without an answer; it is safe to retry
     * @throws TransportException when e-invoice.be refused the request or answered unexpectedly
     */
    public function pending(): array
    {
        $oldest = ($this->clock)()->sub(new DateInterval(sprintf('P%dD', $this->config->inboundLookbackDays)));
        $references = [];
        for ($page = 1; $page <= $this->config->inboundMaxPages; $page++) {
            $url = sprintf(
                '%s/api/inbox/?page=%d&page_size=%d&sort_by=created_at&sort_order=desc',
                $this->config->baseUrl,
                $page,
                self::PAGE_SIZE,
            );
            [$listed, $hasNextPage] = $this->mapper->inboxPage($this->get($url));
            foreach ($listed as $reference) {
                if ($reference->receivedAt < $oldest) {
                    // The inbox is sorted newest first, so every document behind this one is older.
                    break 2;
                }
                // A document received meanwhile shifts the pages, so one may be listed twice.
                $references[$reference->documentId] ??= $reference;
            }
            if (! $hasNextPage) {
                break;
            }
        }

        return array_values($references);
    }

    /**
     * The received document exactly as e-invoice.be stored it, with any SBDH envelope removed.
     *
     * The document's UBL record names a signed download URL, which is fetched without the API key
     * and only over https. When the record carries a SHA-256 file hash, the downloaded bytes must
     * match it. The XML is parsed without DTD processing or network access; the response size is
     * capped by the HTTP client. The reference carries the participants of the record, when they
     * are ISO 6523 identifiers, and the time of the fetch; pending() reports the time e-invoice.be
     * received the document.
     *
     * @throws UnexpectedResponse when the document is unknown, its record has no https signed URL,
     *                            the download was refused or does not match the file hash, or a
     *                            response exceeds the size cap
     * @throws TransportUnavailable when a request failed without an answer; it is safe to retry
     * @throws TransportException when e-invoice.be refused the request
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its envelope is incomplete
     * @throws InvalidArgumentException when the id is not an e-invoice.be document id
     */
    public function fetch(string $documentId): InboundDocument
    {
        self::assertDocumentId($documentId);

        try {
            $response = $this->http->send('GET', $this->documentUrl($documentId).'/ubl', $this->config->headers());
        } catch (UnexpectedResponse $exception) {
            // The record carries the signed URL, so not even an excerpt of it is kept.
            throw new UnexpectedResponse($this->config->redactor()->redact($exception->getMessage()), $exception->status);
        }
        $record = $this->mapper->ublRecord($response, $documentId);

        $xml = $this->mapper->download($this->download($record['url'], $documentId), $documentId);
        ResponseMapper::assertDigest($xml, $record['sha256'], $documentId);

        return InboundXml::document(
            new InboundReference($documentId, $record['sender'], $record['receiver'], null, ($this->clock)()),
            $xml,
        );
    }

    /**
     * Checks the id and returns: e-invoice.be has no acknowledgement call; the document stays
     * listed until it falls out of the look-back window.
     *
     * @throws InvalidArgumentException when the id is not an e-invoice.be document id
     */
    public function acknowledge(string $documentId): void
    {
        self::assertDocumentId($documentId);
    }

    /**
     * @throws UnsupportedDocument when e-invoice.be cannot carry the document
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     */
    private function assertSendable(OutboundDocument $document): void
    {
        $documentType = $document->documentType->value;
        $root = SafeXml::load($document->xml)->documentElement
            ?? throw new UnsupportedDocument('The document has no root element.');
        $resolved = PeppolDocumentTypes::resolve(PeppolDocumentTypes::rootKey($root), PeppolDocumentTypes::specification($root));

        if (in_array($documentType, self::ACCEPTED_DOCUMENT_TYPES, true) && $resolved === $documentType) {
            return;
        }

        throw new UnsupportedDocument(sprintf(
            'e-invoice.be accepts only Peppol BIS Billing 3.0 UBL, of the document types "%s" and "%s". The document is declared as "%s" and its XML %s; e-invoice.be does not accept XRechnung or CII.',
            PeppolBis3::INVOICE,
            PeppolBis3::CREDIT_NOTE,
            $documentType,
            $resolved === null ? 'matches no Peppol document type' : sprintf('matches "%s"', $resolved),
        ));
    }

    /**
     * Uploads the XML as a draft and returns the draft id.
     *
     * @throws OutcomeUnknown when the upload failed without an answer or with a 5xx status
     * @throws TransportException when e-invoice.be refused the upload
     */
    private function upload(string $xml, string $subject): string
    {
        $body = MultipartBody::file('file', 'invoice.xml', 'application/xml', $xml);

        try {
            $response = $this->http->send(
                'POST',
                $this->config->baseUrl.'/api/documents/ubl',
                $this->config->headers() + ['Content-Type' => $body->contentType()],
                $body->body(),
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Uploading %s to e-invoice.be failed without an answer, so the draft may or may not exist. %s',
                    $subject,
                    ResponseMapper::searchDraftsFor($subject),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        return $this->mapper->createdDraft($response, $subject);
    }

    /**
     * Fetches a signed download URL without the API key, through a client that also redacts the
     * URL. Failures are reported without the client exception, whose text may quote the URL.
     *
     * @throws TransportUnavailable when the request failed without an answer
     * @throws UnexpectedResponse when the response exceeds the size cap
     */
    private function download(string $url, string $documentId): ResponseInterface
    {
        $withoutQuery = explode('#', explode('?', $url, 2)[0], 2)[0];
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $secrets = [$url, str_replace('/', '\/', $url), $withoutQuery, $query];

        try {
            return $this->http->withSecrets($secrets)->send('GET', $url, ['Accept' => self::ACCEPT_DOCUMENT]);
        } catch (TransportUnavailable $exception) {
            throw new TransportUnavailable(sprintf(
                'Downloading e-invoice.be document "%s" from its signed URL failed without an answer: %s. It is safe to retry.',
                $documentId,
                // The client names the URL without its query; that part is redacted here.
                rtrim($this->config->redactor()->with($secrets)->redact($exception->getMessage()), '.'),
            ));
        } catch (UnexpectedResponse $exception) {
            throw new UnexpectedResponse($this->config->redactor()->with($secrets)->redact($exception->getMessage()), $exception->status);
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
            throw $this->mapper->sanitize($exception);
        }
    }

    private function documentUrl(string $documentId): string
    {
        return sprintf('%s/api/documents/%s', $this->config->baseUrl, $documentId);
    }

    private static function assertDocumentId(string $documentId): void
    {
        if (! ResponseMapper::isDocumentId($documentId)) {
            throw new InvalidArgumentException('An e-invoice.be document id is "doc-" followed by 1 to 64 letters and digits.');
        }
    }
}

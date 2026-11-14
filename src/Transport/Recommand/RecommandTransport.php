<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
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
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends and receives documents over Peppol through the Recommand access point REST API
 * (https://app.recommand.eu). Recommand builds the SBDH envelope itself, so the raw business
 * document is sent together with its routing metadata. Received documents are taken from the inbox of the
 * configured company; one that still arrives wrapped in an SBDH is unwrapped.
 */
final class RecommandTransport implements InboundSource, Transport
{
    private readonly ResponseMapper $mapper;

    private readonly HttpClient $http;

    public function __construct(
        private readonly RecommandConfig $config,
        HttpClient $http,
    ) {
        $this->http = $config->redactingClient($http);
        $this->mapper = new ResponseMapper($config);
    }

    /**
     * Sends the document as raw XML to the receiver named in it.
     *
     * @throws OutcomeUnknown when the request failed without an answer, Recommand answered any 5xx
     *                        status except 503, or a 200 answer cannot be read; the document may
     *                        have been accepted, so check the document list before resending
     * @throws InvalidArgumentException when the document cannot be encoded as JSON (invalid UTF-8)
     */
    public function send(OutboundDocument $document): SendResult
    {
        try {
            $body = json_encode([
                'documentType' => 'xml',
                'document' => $document->xml,
                'recipient' => $document->receiver->toString(),
                'doctypeId' => $document->documentType->value,
                'processId' => $document->process->value,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The document cannot be sent: it is not valid UTF-8.', 0, $exception);
        }

        $url = sprintf('%s/api/v1/%s/send', $this->config->baseUrl, rawurlencode($this->config->companyId));

        try {
            $response = $this->http->send('POST', $url, $this->headers() + ['Content-Type' => 'application/json'], $body);
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Sending the document to %s through Recommand failed without an answer, so Recommand may or may not have accepted it. Check the document list before resending to avoid a duplicate.',
                    $document->receiver->toString(),
                ),
                $exception,
            );
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        return $this->mapper->sendResult($response, $document->receiver);
    }

    /**
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws InvalidArgumentException when the document id is empty or whitespace
     */
    public function status(string $documentId): DeliveryStatus
    {
        self::assertDocumentId($documentId);

        return $this->mapper->deliveryStatus($this->get($this->documentUrl($documentId)), $documentId);
    }

    /**
     * The unread incoming documents of the configured company. Recommand returns them all at
     * once, so acknowledge each processed document to keep the list short.
     *
     * @return list<InboundReference>
     *
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when Recommand refused the request or answered unexpectedly
     */
    public function pending(): array
    {
        $url = sprintf('%s/api/v1/inbox?companyId=%s', $this->config->baseUrl, rawurlencode($this->config->companyId));

        return $this->mapper->inboundReferences($this->get($url));
    }

    /**
     * The received document with any SBDH envelope removed and its metadata kept. The XML is
     * parsed without DTD processing or network access; the response size is capped by the HTTP
     * client (20 MB by default).
     *
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws UnexpectedResponse when the document is unknown, has no XML or the response exceeds the size cap
     * @throws TransportException when Recommand refused the request
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not well-formed XML or its envelope is incomplete
     * @throws InvalidArgumentException when the document id is empty or whitespace
     */
    public function fetch(string $documentId): InboundDocument
    {
        self::assertDocumentId($documentId);

        [$reference, $xml] = $this->mapper->inboundDocument($this->get($this->documentUrl($documentId)), $documentId);

        return InboundXml::document($reference, $xml);
    }

    /**
     * Marks the document as read, which removes it from the inbox.
     *
     * Marking as read is idempotent at Recommand, so after an OutcomeUnknown it is safe to call
     * this again with the same id.
     *
     * @throws OutcomeUnknown when the request failed without an answer; retrying is safe
     * @throws UnexpectedResponse when Recommand does not know the document or answered unexpectedly
     * @throws TransportException when Recommand refused the request
     * @throws InvalidArgumentException when the document id is empty or whitespace
     */
    public function acknowledge(string $documentId): void
    {
        self::assertDocumentId($documentId);

        try {
            $response = $this->http->send(
                'POST',
                $this->documentUrl($documentId).'/mark-as-read',
                $this->headers() + ['Content-Type' => 'application/json'],
                '{"read":true}',
            );
        } catch (OutcomeUnknown $exception) {
            throw new OutcomeUnknown(
                sprintf(
                    'Marking document "%s" as read at Recommand failed without an answer, so it may or may not have been recorded. Marking as read is idempotent, so it is safe to retry.',
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
    private function get(string $url): ResponseInterface
    {
        try {
            return $this->http->send('GET', $url, $this->headers());
        } catch (TransportUnavailable $exception) {
            throw new TransportUnavailable($this->config->redact($exception->getMessage()), $exception);
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }
    }

    private function documentUrl(string $documentId): string
    {
        return sprintf('%s/api/v1/documents/%s', $this->config->baseUrl, rawurlencode($documentId));
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => $this->config->authorization(),
            'Accept' => 'application/json',
        ];
    }

    private static function assertDocumentId(string $documentId): void
    {
        if (trim($documentId) === '') {
            throw new InvalidArgumentException('The document id must not be empty or whitespace.');
        }
    }
}

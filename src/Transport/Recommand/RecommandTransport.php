<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Recommand;

use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Transport\Transport;
use InvalidArgumentException;
use JsonException;

/**
 * Sends documents over Peppol through the Recommand access point REST API
 * (https://app.recommand.eu). Recommand builds the SBDH envelope itself, so the raw UBL document
 * is sent together with its routing metadata.
 */
final class RecommandTransport implements Transport
{
    private readonly ResponseMapper $mapper;

    public function __construct(
        private readonly RecommandConfig $config,
        private readonly HttpClient $http,
    ) {
        $this->mapper = new ResponseMapper($config);
    }

    /**
     * Sends the document as raw XML to the receiver named in it.
     *
     * @throws OutcomeUnknown when the request failed without an answer or timed out at the gateway;
     *                        the document may have been accepted, so check the document list before resending
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
     * @throws InvalidArgumentException when the document id is empty
     */
    public function status(string $documentId): DeliveryStatus
    {
        if ($documentId === '') {
            throw new InvalidArgumentException('The document id must not be empty.');
        }

        $url = sprintf('%s/api/v1/documents/%s', $this->config->baseUrl, rawurlencode($documentId));

        try {
            $response = $this->http->send('GET', $url, $this->headers());
        } catch (TransportUnavailable $exception) {
            throw new TransportUnavailable($this->config->redact($exception->getMessage()), $exception);
        } catch (UnexpectedResponse $exception) {
            throw $this->mapper->sanitize($exception);
        }

        return $this->mapper->deliveryStatus($response, $documentId);
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
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\SendResult;

/**
 * Sends French invoices through B2Brouter acting as the sender's plateforme agréée.
 *
 * A document is imported into the configured account and sent in one request. Every document,
 * XML or Factur-X PDF, is uploaded as application/octet-stream with its B2Brouter document type
 * code. B2Brouter validates the document against the French rules before sending; a document
 * that fails them is refused with HTTP 422 and the invoice stays in B2Brouter in error state,
 * where it must be corrected or deleted before the same number can be imported again.
 *
 * Requests are sent through an HttpClient whose PSR-18 client must not follow redirects.
 */
final class B2BrouterFrance
{
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
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterErrors;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Timestamp;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Issues Spanish invoices through B2Brouter acting as the VERI*FACTU invoicing system, and reads
 * back the tax reports B2Brouter sends to the AEAT.
 *
 * An invoice is imported into the configured account and sent to the customer by e-mail as a PDF
 * in one request. Sending makes B2Brouter generate the VERI*FACTU billing record, chain it to the
 * account's previous record and submit it; the outcome arrives asynchronously in the invoice's tax
 * report, which awaitRegistered() polls within a bound.
 *
 * The import takes no idempotency key: the refusal of a duplicate invoice number is the guard
 * against a double import. After an import that ended without an answer, look the invoice up with
 * findByNumber() before resending.
 *
 * Requests are sent through an HttpClient whose PSR-18 client must not follow redirects.
 */
final class B2BrouterVerifactu
{
    /** The query of the import: send right away, to the customer by e-mail, as a PDF. */
    private const IMPORT_QUERY = 'send_after_import=true&issued=true&transport_type_code_for_contact=email&document_type_code_for_contact=pdf.invoice';

    /** The media type every document is imported as. */
    private const IMPORT_CONTENT_TYPE = 'application/octet-stream';

    /** The UBL document elements the import accepts. */
    private const UBL_ROOTS = [PeppolDocumentTypes::UBL_INVOICE_ROOT, PeppolDocumentTypes::UBL_CREDIT_NOTE_ROOT];

    /** The size of the one page of the invoice list findByNumber() reads, B2Brouter's maximum. */
    private const LIST_LIMIT = 500;

    private readonly HttpClient $http;

    private readonly B2BrouterErrors $errors;

    private readonly VerifactuPolling $polling;

    public function __construct(
        private readonly B2BrouterConfig $config,
        HttpClient $http,
        ?VerifactuPolling $polling = null,
    ) {
        $this->http = $http->withSecrets($config->secrets());
        $this->errors = new B2BrouterErrors($config->redactor());
        $this->polling = $polling ?? new VerifactuPolling;
    }

    /**
     * Imports the invoice into the configured account and sends it, which makes B2Brouter
     * register it with the AEAT.
     *
     * An Invoice is written as UBL; a string is sent as given and must be a UBL Invoice or
     * CreditNote. The result is Pending unless B2Brouter put the invoice in error: the
     * registration outcome is read from its tax report (see awaitRegistered()).
     *
     * @throws UnsupportedDocument when the string is not a UBL Invoice or CreditNote; no request is made
     * @throws UnsafeXml when the string carries a DOCTYPE declaration or declares entities; no request is made
     * @throws InvalidArgumentException when the UBL writer refuses the invoice; no request is made
     * @throws OutcomeUnknown when the request failed without an answer, B2Brouter answered a 5xx
     *                        status or a 201 answer cannot be read; the invoice may have been
     *                        imported, so look it up with findByNumber() before resending
     * @throws DocumentRejected when B2Brouter refused the invoice (HTTP 400, 406, 409, 422), a
     *                          taken invoice number included
     * @throws TransportException when B2Brouter refused the request otherwise, for example
     *                            AuthenticationFailed for HTTP 401 and 403 or RateLimited for HTTP 429
     */
    public function issue(Invoice|string $ubl): IssuedInvoice
    {
        $xml = $ubl instanceof Invoice ? (new UblWriter)->write($ubl) : self::checkedUbl($ubl);
        $number = DocumentNumber::of($xml);
        $subject = $number === null
            ? 'the invoice (its invoice number cannot be read from the document)'
            : sprintf('invoice number "%s"', $number);

        try {
            $response = $this->http->send(
                'POST',
                sprintf('%s/accounts/%d/invoices/import?%s', $this->config->baseUrl, $this->config->accountId, self::IMPORT_QUERY),
                $this->config->headers() + ['Content-Type' => self::IMPORT_CONTENT_TYPE],
                $xml,
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
            throw $this->config->redactor()->sanitize($exception);
        }

        if ($response->getStatusCode() !== 201) {
            throw $this->errors->importFailure($response, $subject);
        }

        // A 201 means the import went through, so a body that cannot be read leaves the outcome
        // open rather than making it a failure that invites a resend.
        try {
            return $this->issuedInvoice($response);
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
    }

    /**
     * An issued invoice with the ids of its tax reports.
     *
     * @throws UnexpectedResponse when the invoice is unknown, is a received invoice (B2Brouter
     *                            redirects, which is not followed), or the body is not this
     *                            invoice with a state
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function invoice(string $invoiceId): IssuedInvoice
    {
        self::checkId($invoiceId, 'invoice');

        $response = $this->get(sprintf('%s/invoices/%s', $this->config->baseUrl, $invoiceId));
        if ($response->getStatusCode() !== 200) {
            throw $this->errors->readFailure($response, sprintf('Reading B2Brouter invoice "%s"', $invoiceId));
        }
        $invoice = $this->issuedInvoice($response);
        if ($invoice->invoiceId !== $invoiceId) {
            throw $this->config->redactor()->unexpected($response, sprintf('B2Brouter answered the request for invoice "%s" with invoice "%s"', $invoiceId, $invoice->invoiceId));
        }

        return $invoice;
    }

    /**
     * A tax report by its id, as listed in an invoice's taxReportIds.
     *
     * @throws UnexpectedResponse when the report is unknown, B2Brouter redirects or the body is
     *                            not JSON or another report
     * @throws UnsupportedDocument when the body is not a tax report with an id and a state, or
     *                             its qr or xml_base64 field is not Base64
     * @throws TransportUnavailable when the request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the id is not a B2Brouter tax report id (digits)
     */
    public function taxReport(string $taxReportId): TaxReport
    {
        self::checkId($taxReportId, 'tax report');

        $response = $this->get(sprintf('%s/tax_reports/%s', $this->config->baseUrl, $taxReportId));
        if ($response->getStatusCode() !== 200) {
            throw $this->errors->readFailure($response, sprintf('Reading B2Brouter tax report "%s"', $taxReportId));
        }
        $body = $this->config->redactor()->redactValue($this->errors->body($response));
        if (! is_array($body)) {
            throw $this->config->redactor()->unexpected($response, 'The B2Brouter tax report response is not an object');
        }
        $report = TaxReport::fromArray($body);
        if ($report->id !== $taxReportId) {
            throw $this->config->redactor()->unexpected($response, sprintf('B2Brouter answered the request for tax report "%s" with tax report "%s"', $taxReportId, $report->id));
        }

        return $report;
    }

    /**
     * Waits until the invoice's current tax report is final (see TaxReport::isFinal()), within the
     * polling bound.
     *
     * Each attempt makes one request: the invoice is read until it lists a tax report, then the
     * report with the highest id is read. Attempts are the configured wait apart. When the invoice
     * first lists a report on the last attempt, that report is read once right away, without a
     * further wait; otherwise no request is made after the last attempt.
     *
     * @return TaxReport|null the last report read, final or not; null only when the invoice listed
     *                        no tax report on any attempt
     *
     * @throws UnexpectedResponse when a response cannot be read
     * @throws UnsupportedDocument when a report body is not a tax report
     * @throws TransportUnavailable when a request failed without an answer; it is safe to call again
     * @throws TransportException when B2Brouter refused a request
     * @throws InvalidArgumentException when the id is not a B2Brouter invoice id (digits)
     */
    public function awaitRegistered(string $invoiceId): ?TaxReport
    {
        self::checkId($invoiceId, 'invoice');

        $reportId = null;
        $report = null;
        for ($attempt = 1; $attempt <= $this->polling->attempts; $attempt++) {
            if ($attempt > 1) {
                $this->polling->sleep($this->polling->waitMs);
            }
            if ($reportId === null) {
                $reportId = $this->invoice($invoiceId)->latestTaxReportId();
                if ($reportId === null || $attempt < $this->polling->attempts) {
                    continue;
                }
            }
            $report = $this->taxReport($reportId);
            if ($report->isFinal()) {
                return $report;
            }
        }

        return $report;
    }

    /**
     * The issued invoice with exactly this number, or null when the account holds none.
     *
     * Use it after an import that ended in OutcomeUnknown: a found invoice was imported and must
     * not be sent again. B2Brouter matches the number as a prefix, so one page of up to 500
     * issued invoices is read and filtered for the exact number; the invoice found is then read
     * in full, with its tax report ids.
     *
     * @throws UnexpectedResponse when more than one issued invoice has the number, when the page
     *                            cannot decide (none on it matches, and more invoices start with
     *                            the number than one page holds, or the page is full and the
     *                            answer gives no total), or a response cannot be read
     * @throws TransportUnavailable when a request failed without an answer; it is safe to retry
     * @throws TransportException when B2Brouter refused the request
     * @throws InvalidArgumentException when the number is blank
     */
    public function findByNumber(string $number): ?IssuedInvoice
    {
        if (trim($number) === '') {
            throw new InvalidArgumentException('The invoice number to look up must not be blank.');
        }

        $response = $this->get(sprintf(
            '%s/accounts/%d/invoices?type=IssuedInvoice&number=%s&offset=0&limit=%d',
            $this->config->baseUrl,
            $this->config->accountId,
            rawurlencode($number),
            self::LIST_LIMIT,
        ));
        $operation = sprintf('Looking up the issued B2Brouter invoice with number "%s"', $number);
        if ($response->getStatusCode() !== 200) {
            throw $this->errors->readFailure($response, $operation);
        }

        $body = $this->errors->body($response);
        $invoices = $body['invoices'] ?? null;
        if (! is_array($invoices) || ! array_is_list($invoices)) {
            throw $this->config->redactor()->unexpected($response, 'The B2Brouter invoice list response has no invoice list');
        }
        $matches = [];
        foreach ($invoices as $invoice) {
            if (! is_array($invoice)) {
                throw $this->config->redactor()->unexpected($response, 'The B2Brouter invoice list contains an entry that is not an object');
            }
            if (($invoice['number'] ?? null) === $number) {
                $matches[] = B2BrouterErrors::id($invoice['id'] ?? null)
                    ?? throw $this->config->redactor()->unexpected($response, 'The B2Brouter invoice list contains an invoice without a positive integer id');
            }
        }

        if (count($matches) > 1) {
            throw $this->config->redactor()->unexpected($response, sprintf(
                '%s found %d invoices (%s), so the invoice cannot be told apart',
                $operation,
                count($matches),
                implode(', ', $matches),
            ));
        }
        if ($matches === []) {
            $meta = $body['meta'] ?? null;
            $total = is_array($meta) ? ($meta['total_count'] ?? null) : ($body['total_count'] ?? null);
            if (is_int($total) && $total > count($invoices)) {
                throw $this->config->redactor()->unexpected($response, sprintf(
                    '%s cannot decide: %d invoices start with the number, more than one page of %d holds',
                    $operation,
                    $total,
                    self::LIST_LIMIT,
                ));
            }
            if (! is_int($total) && count($invoices) >= self::LIST_LIMIT) {
                throw $this->config->redactor()->unexpected($response, sprintf(
                    '%s cannot decide: the page of %d invoices is full and the answer gives no total, so more may start with the number',
                    $operation,
                    self::LIST_LIMIT,
                ));
            }

            return null;
        }

        return $this->invoice($matches[0]);
    }

    /**
     * The UBL text when its document element is a UBL Invoice or CreditNote.
     *
     * @throws UnsupportedDocument when it is not
     * @throws UnsafeXml when the XML carries a DOCTYPE declaration or declares entities
     */
    private static function checkedUbl(string $xml): string
    {
        $root = SafeXml::load($xml)->documentElement;
        $rootKey = $root instanceof DOMElement ? PeppolDocumentTypes::rootKey($root) : null;
        if ($rootKey === null || ! in_array($rootKey, self::UBL_ROOTS, true)) {
            throw new UnsupportedDocument(sprintf(
                'B2Brouter imports a Spanish invoice as UBL Invoice or CreditNote; the document element is %s.',
                $root instanceof DOMElement ? sprintf('{%s}%s', $root->namespaceURI ?? '', $root->localName) : '(none)',
            ));
        }

        return $xml;
    }

    /**
     * The issued invoice of an invoice response.
     *
     * @throws UnexpectedResponse when the body is not an invoice with an id, a state and a list
     *                            of positive integer tax report ids
     */
    private function issuedInvoice(ResponseInterface $response): IssuedInvoice
    {
        $redactor = $this->config->redactor();
        $invoice = $this->errors->invoice($response);
        $id = B2BrouterErrors::id($invoice['id'] ?? null)
            ?? throw $redactor->unexpected($response, 'The B2Brouter invoice has no positive integer id');
        $state = B2BrouterErrors::text($invoice['state'] ?? null)
            ?? throw $redactor->unexpected($response, sprintf('The B2Brouter invoice "%s" has no state', $id));
        $listed = $invoice['tax_report_ids'] ?? [];
        if (! is_array($listed) || ! array_is_list($listed)) {
            throw $redactor->unexpected($response, sprintf('The tax_report_ids of B2Brouter invoice "%s" is not a list', $id));
        }
        $taxReportIds = [];
        foreach ($listed as $reportId) {
            $taxReportIds[] = B2BrouterErrors::id($reportId)
                ?? throw $redactor->unexpected($response, sprintf('The tax_report_ids of B2Brouter invoice "%s" holds an id that is not a positive integer', $id));
        }
        $number = B2BrouterErrors::text($invoice['number'] ?? null);
        $failure = $this->errors->failure($invoice);

        return new IssuedInvoice(
            $id,
            $number === null ? null : $redactor->redact($number),
            $redactor->redact($state),
            $taxReportIds,
            new DeliveryStatus(
                $id,
                $failure === null ? DeliveryState::Pending : DeliveryState::Failed,
                $failure,
                Timestamp::parse($invoice['state_updated_at'] ?? null),
            ),
        );
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

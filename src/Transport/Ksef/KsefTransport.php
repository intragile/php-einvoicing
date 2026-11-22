<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\KsefTokens;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificates;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\CursorWindow;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\ExportResult;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InMemoryCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceMetadata;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\KsefCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\KsefExport;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\MetadataQuery;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchPackage;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchResult;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchSession;
use IntrAgile\EInvoicing\Transport\Ksef\Session\KsefInvoiceStatus;
use IntrAgile\EInvoicing\Transport\Ksef\Session\OnlineSession;
use IntrAgile\EInvoicing\Transport\Ksef\Session\Upo;
use IntrAgile\EInvoicing\Transport\SendResult;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use IntrAgile\EInvoicing\Validation\SchemaError;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Sends FA(3) invoices to KSeF and reads the context's inbox through the KSeF API 2.0.
 *
 * This is not a Transport: KSeF takes FA(3) files, which an OutboundDocument cannot carry.
 * Sending:
 * - send() sends one invoice in the online session of this instance, opened on the first send
 *   and replaced before it expires; close() closes it so that KSeF issues the receipts.
 * - sendBatch() sends several invoices in a batch session of their own.
 * - Both take an offline flag for invoices issued in offline mode; sendTechnicalCorrection()
 *   replaces an offline invoice KSeF rejected, in the online session.
 * - A raw FA(3) file is validated before anything is sent; an Invoice is written with Fa3Writer,
 *   which validates its own output.
 *
 * The document id of a sent invoice is "<session reference>/<invoice reference>". status() maps
 * the KSeF invoice status onto a DeliveryStatus; invoiceStatus() and awaitFinal() return the full
 * KSeF status with the KSeF number, and upo() the receipt (UPO) of an accepted invoice;
 * upoByKsefNumber() reads the receipt of any session's invoice, and isSameDocument() tells whether
 * the original a duplicate (status 440) names is the file that was resent.
 *
 * Receiving (InboundSource): pending() lists the invoices addressed to the context (Subject2) by
 * PermanentStorage date, in windows bounded by KSeF's high water mark. The window and the
 * invoices acknowledged in it are kept in the KsefCursorStore under
 * "<environment>:<context>:Subject2"; once every invoice of a window is acknowledged, the next
 * window starts at its high water mark. Without a persistent store a restarted process reads its
 * inbox from the start point again. The document id of a received invoice is its KSeF number.
 *
 * Every component shares one authenticator and one public-key registry, so the context
 * authenticates once and the Ministry of Finance keys are fetched once per instance. Polling is
 * bounded by the KsefConfig. The object never makes a network call on destruction.
 *
 * Repeats: a GET refused with 429 is repeated within a 60-second budget per HTTP request. Any
 * request, POST included, is repeated once with a renewed access token after KSeF refused the
 * authentication (HTTP 401, or code 21301/21304 in a 4xx answer), and the requests that carry a
 * key encrypted for the Ministry of Finance (opening an online or batch session, starting an
 * export, authenticating with a KSeF token) are repeated once with a fresh key list after error
 * 21470. Both repeats follow a refusal, so KSeF did not process the first request. Nothing else is
 * repeated: an invoice send that failed without an answer is never resent automatically.
 *
 * Exceptions other than TransportException: every method that talks to KSeF may authenticate on
 * the way. A certificate or private key the XAdES signer cannot use (unreadable, or not RSA of at
 * least 2048 bits or EC on P-256) and a Ministry of Finance key that cannot be loaded throw
 * InvalidArgumentException; an OpenSSL failure while signing throws RuntimeException, as does an
 * export that finds no usable temporary file. Both point to the configuration or the PHP
 * environment and are not worth repeating as such. Arguments are checked before any request and
 * refused with InvalidArgumentException.
 */
final class KsefTransport implements InboundSource
{
    /** Days before now at which the first inbox window starts when no start is given. */
    public const DEFAULT_INBOX_LOOKBACK_DAYS = 30;

    /** The form code of an inbox entry whose metadata does not name one. */
    private const DEFAULT_DOCUMENT_TYPE = 'FA (3)';

    /** A KSeF session or invoice reference number: letters, digits and hyphens. */
    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9-]{1,64}\z/';

    /**
     * Metadata pages one pending() call reads at most. KSeF allows 20 metadata queries per hour
     * for each context and IP address, so one call leaves room for a retry and fetch() lookups
     * within the hour; a longer result ends the window at its last record, as a truncated one
     * does.
     */
    private const MAX_INBOX_PAGES = 10;

    /** Days one inbox query spans at most; KSeF refuses date ranges over 100 days. */
    public const INBOX_CHUNK_DAYS = 90;

    /** Days after the date in a KSeF number searched for its PermanentStorage date. */
    private const LOOKUP_DAYS = 31;

    private ?Fa3Validator $validator = null;

    /** @var array<string, InvoiceMetadata> metadata of listed, not yet acknowledged invoices */
    private array $inbox = [];

    /**
     * @param  Closure(): DateTimeImmutable  $now
     */
    private function __construct(
        private readonly KsefConfig $config,
        private readonly OnlineSession $session,
        private readonly BatchSession $batch,
        private readonly MetadataQuery $query,
        private readonly KsefExport $exporter,
        private readonly KsefTokens $tokens,
        private readonly KsefCertificates $certificates,
        private readonly KsefCursorStore $cursor,
        private readonly Fa3Writer $writer,
        private readonly ?DateTimeImmutable $inboxStart,
        private readonly Closure $now,
    ) {}

    /**
     * @param  KsefCursorStore|null  $cursor  where the inbox window is kept; in memory when null
     * @param  DateTimeImmutable|null  $inboxStart  where the first inbox window starts when the
     *                                              store holds none; DEFAULT_INBOX_LOOKBACK_DAYS
     *                                              before now when null. An older start is read
     *                                              in chunks of INBOX_CHUNK_DAYS.
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time; the system clock when null
     */
    public static function create(
        KsefConfig $config,
        HttpClient $http,
        ?KsefCursorStore $cursor = null,
        ?DateTimeImmutable $inboxStart = null,
        ?Closure $now = null,
    ): self {
        $now ??= static fn (): DateTimeImmutable => new DateTimeImmutable;
        // The authentication endpoints and the key list take no access token.
        $plain = new KsefApi($config, $http);
        $keys = new PublicKeyRegistry($plain, $now);
        $api = $plain->withAuthenticator(new Authenticator($plain, $config, $keys, now: $now));

        return new self(
            $config,
            new OnlineSession($api, $keys, $config, $now),
            new BatchSession($api, $keys, $config),
            new MetadataQuery($api),
            new KsefExport($api, $keys, $config),
            new KsefTokens($api),
            new KsefCertificates($api, $config, $now),
            $cursor ?? new InMemoryCursorStore,
            new Fa3Writer($now),
            $inboxStart,
            $now,
        );
    }

    /**
     * Sends one FA(3) invoice in the online session, opening or replacing the session first when
     * needed. The result is Pending with the document id "<session>/<invoice>"; read the outcome
     * with status() or awaitFinal().
     *
     * A send that failed without an answer is never repeated automatically; only a send KSeF
     * refused without processing it is (once after HTTP 401 with a renewed access token, and the
     * session open once after 21470 with a fresh key list). After an OutcomeUnknown, sending the
     * same invoice again is safe: KSeF reports the second copy as a duplicate (Failed / Duplicate)
     * naming the KSeF number of the first. An OutcomeUnknown whose message names
     * /auth/token/refresh (or another /auth/ request) comes from renewing the access token before
     * the invoice was posted, or after KSeF refused the post with 401: the invoice was not
     * accepted, so sending it again is safe as well.
     *
     * When KSeF has ended the session on its side before its expiry, it refuses the send with
     * error 21180 (thrown as UnexpectedResponse): the invoice was not accepted and the local
     * session is dropped, so sending it again opens a new session.
     *
     * Offline mode: pass $offline for an invoice issued outside KSeF (offline24, unavailability
     * or failure mode). KSeF reports its own classification in KsefInvoiceStatus::$invoicingMode
     * and in the inbox metadata; it may report Offline for an invoice sent without the flag, for
     * example one whose issue date (P_1) lies before the day of the send. The flag and the
     * reported mode do not compute a sending deadline; see OfflineDeadlines.
     *
     * @param  string|Invoice  $invoice  an FA(3) file, or an invoice to write as one
     * @param  bool  $offline  true for an invoice issued in offline mode
     *
     * @throws DocumentRejected when the FA(3) file fails its schema or a fatal FA(3) rule; nothing is sent
     * @throws UnsafeXml|UnsupportedDocument when the file is not well-formed XML or not an FA(3) invoice
     * @throws InvalidArgumentException when the invoice cannot be written as FA(3)
     * @throws OutcomeUnknown when the send failed without an answer
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when opening the session or sending fails, or KSeF refuses it
     */
    public function send(string|Invoice $invoice, bool $offline = false): SendResult
    {
        $sent = $this->session->send($this->fa3($invoice, null), $offline);

        return new SendResult($sent->documentId(), DeliveryState::Pending);
    }

    /**
     * Sends the technical correction of an offline invoice KSeF rejected, in the online session
     * as send() does, and returns the correction's document id as Pending.
     *
     * The correction is sent in offline mode and names the rejected invoice by the SHA-256 digest
     * (Base64) of its exact bytes, which this method computes from the file: KSeF accepts a digest
     * of an invoice it has never seen, so a mistyped digest would not be refused. Pass the rejected
     * file byte for byte as it was sent; it must be well-formed XML, but its schema is not
     * validated, since KSeF rejected it. KSeF accepts one technical correction per invoice and
     * refuses a second one with error 21166, thrown as DocumentRejected.
     *
     * A caller that sent an Invoice object should keep the FA(3) bytes written for it (they are
     * not returned by send()); without them the rejected file cannot be passed here, and the
     * fallback is OnlineSession::send() with the stored digest as hashOfCorrectedInvoice.
     *
     * @param  string|Invoice  $corrected  the corrected FA(3) file, or an invoice to write as one
     * @param  string  $rejectedInvoiceXml  the FA(3) file KSeF rejected, as it was sent
     *
     * @throws InvalidArgumentException when the rejected file is empty, not well-formed XML, or
     *                                  identical to the corrected file, before anything is
     *                                  validated or sent; when the invoice cannot be written as FA(3)
     * @throws DocumentRejected when the corrected file fails its schema or a fatal FA(3) rule
     *                          (nothing is sent), or KSeF refuses a second technical correction
     *                          of the same invoice (21166)
     * @throws UnsafeXml|UnsupportedDocument when the corrected file is not well-formed XML or not an FA(3) invoice
     * @throws OutcomeUnknown when the send failed without an answer
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when opening the session or sending fails, or KSeF refuses it
     */
    public function sendTechnicalCorrection(string|Invoice $corrected, string $rejectedInvoiceXml): SendResult
    {
        if ($rejectedInvoiceXml === '') {
            throw new InvalidArgumentException('A technical correction needs the rejected FA(3) file it replaces; an empty file was given.');
        }
        // KSeF accepts a digest of anything, so a path, Base64 text or JSON passed by mistake
        // would be hashed and accepted as the rejected invoice's digest. The file is only
        // required to be XML; its schema is what KSeF rejected.
        try {
            SafeXml::load($rejectedInvoiceXml);
        } catch (UnsafeXml|UnsupportedDocument $exception) {
            throw new InvalidArgumentException('The rejected FA(3) file of a technical correction must be the XML file KSeF rejected, byte for byte; the given content is not well-formed XML.', 0, $exception);
        }
        // Checked before validation as well, so a rejected file passed twice is reported as such
        // rather than as failing validation.
        if (is_string($corrected)) {
            self::assertCorrects($corrected, $rejectedInvoiceXml);
        }
        $xml = $this->fa3($corrected, null);
        self::assertCorrects($xml, $rejectedInvoiceXml);

        $sent = $this->session->send($xml, true, OnlineSession::invoiceHash($rejectedInvoiceXml));

        return new SendResult($sent->documentId(), DeliveryState::Pending);
    }

    /**
     * Sends invoices in a batch session of their own and returns the session reference; read the
     * result with batchStatus() or awaitBatch(). Each invoice is validated or written as in send()
     * before anything is sent.
     *
     * @param  array<string, string|Invoice>  $invoices  FA(3) files or invoices by file name
     *                                                   (letters, digits, ".", "_", "-", ending in ".xml")
     * @param  bool  $offline  true when the invoices were issued in offline mode; the flag
     *                         applies to the whole batch, as in send()
     *
     * @throws DocumentRejected when an FA(3) file fails validation; nothing is sent
     * @throws UnsafeXml|UnsupportedDocument when a file is not well-formed XML or not an FA(3) invoice
     * @throws InvalidArgumentException when an invoice cannot be written, or the batch is empty,
     *                                  too large or has unusable file names
     * @throws LogicException when the zip extension is not loaded
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when opening, uploading or closing fails, or KSeF refuses it
     */
    public function sendBatch(array $invoices, bool $offline = false): string
    {
        $files = [];
        foreach ($invoices as $name => $invoice) {
            $files[$name] = $this->fa3($invoice, (string) $name);
        }

        return $this->batch->send(BatchPackage::build($files, $this->config->batchPartBytes), $offline);
    }

    /**
     * The state of a batch session and of every invoice in it, read once.
     *
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function batchStatus(string $sessionReference): BatchResult
    {
        return $this->batch->status(self::reference($sessionReference, 'batch session reference'));
    }

    /**
     * Reads the state of a batch session until it is final, within the configured polling bounds,
     * and returns the last state read.
     *
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function awaitBatch(string $sessionReference): BatchResult
    {
        return $this->batch->awaitFinal(self::reference($sessionReference, 'batch session reference'));
    }

    /**
     * The delivery state of a sent invoice, read once: Pending while KSeF processes it,
     * Delivered once it has a KSeF number, Failed with KSeF's reason otherwise.
     *
     * @throws InvalidArgumentException when the document id is not "<session>/<invoice>"; no request is made
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when the request fails or the answer cannot be read
     */
    public function status(string $documentId): DeliveryStatus
    {
        $status = $this->invoiceStatus($documentId);

        return new DeliveryStatus($documentId, $status->deliveryState(), $status->failure(), $status->acquisitionDate);
    }

    /**
     * The KSeF status of a sent invoice, read once, with its KSeF number once assigned.
     *
     * @throws InvalidArgumentException when the document id is not "<session>/<invoice>"; no request is made
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when the request fails or the answer cannot be read
     */
    public function invoiceStatus(string $documentId): KsefInvoiceStatus
    {
        [$session, $invoice] = self::documentId($documentId);

        return $this->session->status($session, $invoice);
    }

    /**
     * Reads the KSeF status of a sent invoice until it is final, within the configured polling
     * bounds, and returns the last status read, which is still pending when the attempts ran out.
     *
     * @throws InvalidArgumentException when the document id is not "<session>/<invoice>"; no request is made
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function awaitFinal(string $documentId): KsefInvoiceStatus
    {
        [$session, $invoice] = self::documentId($documentId);

        return $this->session->awaitFinal($session, $invoice);
    }

    /**
     * The receipt (UPO) of a sent invoice, fetched by the KSeF number its status reports. KSeF
     * may issue the receipt only after the session is closed; call close() first.
     *
     * @throws InvalidArgumentException when the document id is not "<session>/<invoice>"; no request is made
     * @throws LogicException when the invoice has no KSeF number: it is still being processed or
     *                        KSeF did not accept it; the receipt is not requested
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when a request fails or KSeF has no receipt
     * @throws UnsafeXml|UnsupportedDocument when the answer is not a readable UPO
     */
    public function upo(string $documentId): Upo
    {
        [$session, $invoice] = self::documentId($documentId);
        $status = $this->session->status($session, $invoice);
        if ($status->ksefNumber === null) {
            throw new LogicException(sprintf(
                'The KSeF invoice %s has no KSeF number (status %d: %s), so it has no receipt (UPO). %s',
                $documentId,
                $status->code,
                rtrim($status->description, '. '),
                $status->isFinal()
                    ? 'KSeF did not accept the invoice.'
                    : 'KSeF is still processing it; wait for the final status with awaitFinal().',
            ));
        }

        return $this->session->upo($session, $status->ksefNumber);
    }

    /**
     * The receipt (UPO) of an invoice by the reference of the session it was sent in and its KSeF
     * number, without reading its status first. It serves invoices of any session of the context,
     * such as the original that a duplicate (status 440) names.
     *
     * @throws InvalidArgumentException when the session reference or the KSeF number is malformed;
     *                                  no request is made
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when the request fails or KSeF has no receipt (error 21178)
     * @throws UnsafeXml|UnsupportedDocument when the answer is not a readable UPO
     */
    public function upoByKsefNumber(string $sessionReference, string $ksefNumber): Upo
    {
        $sessionReference = self::reference($sessionReference, 'session reference');
        if (! KsefNumber::isValid($ksefNumber)) {
            throw new InvalidArgumentException('The receipt (UPO) needs a valid KSeF number.');
        }

        return $this->session->upo($sessionReference, $ksefNumber);
    }

    /**
     * Whether the invoice a duplicate (status 440) names as the original is the given file, byte
     * for byte. KSeF reports a duplicate when the seller NIP, the invoice type and the invoice
     * number (Podmiot1/NIP, RodzajFaktury, P_2) match an accepted invoice, whatever the content,
     * so a 440 alone does not prove that a resend reached KSeF before. This reads the receipt
     * (UPO) of the original from its session and compares its document digest (SkrotDokumentu)
     * with the SHA-256 digest of the file, as OnlineSession::invoiceHash() computes it.
     *
     * True means the original is this exact file: the earlier send was accepted under the
     * original KSeF number. False means another invoice with the same number is in KSeF.
     *
     * @param  KsefInvoiceStatus  $duplicate  the status of the resend, as invoiceStatus() reads it
     * @param  string  $invoiceXml  the exact FA(3) bytes that were sent
     *
     * @throws LogicException when the status is not a duplicate naming the original KSeF number
     *                        and session; no request is made
     * @throws UnexpectedResponse when the receipt does not list the original invoice alone or has
     *                            no document digest
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when the request fails or KSeF has no receipt for the original
     * @throws UnsafeXml|UnsupportedDocument when the answer is not a readable UPO
     */
    public function isSameDocument(KsefInvoiceStatus $duplicate, string $invoiceXml): bool
    {
        $ksefNumber = $duplicate->originalKsefNumber;
        $session = $duplicate->originalSessionReferenceNumber;
        if ($duplicate->failure()?->category !== FailureCategory::Duplicate || $ksefNumber === null || $session === null) {
            throw new LogicException(sprintf(
                'Only a duplicate (status 440) that names the original KSeF number and session can be compared with a file; this status is %d: %s.',
                $duplicate->code,
                rtrim($duplicate->description, '. '),
            ));
        }

        $upo = $this->upoByKsefNumber($session, $ksefNumber);
        if ($upo->ksefNumbers !== [$ksefNumber] || $upo->documentHash === null) {
            throw new UnexpectedResponse(sprintf(
                'The receipt (UPO) of session %s does not list the KSeF invoice %s alone with its document digest (SkrotDokumentu).',
                $session,
                $ksefNumber,
            ));
        }
        $original = base64_decode($upo->documentHash, true);

        return $original !== false && hash_equals($original, hash('sha256', $invoiceXml, true));
    }

    /**
     * Closes the online session, if one is open, so that KSeF issues the receipts. The local
     * session is dropped even when the request fails, so the next send() opens a new one; it
     * also clears any other session state KSeF refuses to work with.
     *
     * @throws TransportException when KSeF refuses to close the session
     */
    public function close(): void
    {
        $this->session->close();
    }

    /**
     * The invoices addressed to the context that are not acknowledged yet, read from the current
     * inbox window. The first call without a stored window starts it at the configured inbox
     * start and saves it at once.
     *
     * Each query spans at most INBOX_CHUNK_DAYS (KSeF refuses ranges over 100 days): a window that
     * starts longer ago ends at that chunk's end, and the following windows catch up chunk by
     * chunk. The cursor moves only when every invoice of the window is acknowledged, so a single
     * invoice that is never acknowledged holds it at that window for good; resetInbox() is the
     * way out.
     *
     * Every call spends at least one metadata query, and one per further page (at most
     * MAX_INBOX_PAGES); KSeF allows 20 per hour for each context and IP address, and the
     * Ministry of Finance recommends polling each subject of an invoice no more often than every
     * 15 minutes in production. A result longer than the page limit ends the window at its last
     * record. When KSeF refuses a later page with 429, the window is saved ending at the last
     * record already listed and the RateLimited is thrown; the next call after the Retry-After
     * delay lists those invoices again, and once they are acknowledged the cursor moves past them.
     * fetch() spends one of KSeF's 64 invoice downloads per hour, plus a metadata query for an
     * invoice this instance did not list; for high volumes use export().
     *
     * @return list<InboundReference>
     *
     * @throws RateLimited when KSeF refuses a metadata query with 429 (a POST refused with 429 is
     *                     never repeated)
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when the query fails or its answer cannot be read
     */
    public function pending(): array
    {
        $key = $this->cursorKey();
        $stored = $this->cursor->window($key);
        if ($stored === null) {
            $stored = CursorWindow::start($this->defaultInboxStart());
            $this->cursor->save($key, $stored);
        }
        $window = $stored->next();

        $to = $window->from->modify(sprintf('+%d days', self::INBOX_CHUNK_DAYS));
        if ($to >= ($this->now)()) {
            $to = null;
        }
        $filters = new InvoiceQueryFilters(
            InvoiceQueryFilters::SUBJECT_BUYER,
            InvoiceQueryFilters::DATE_PERMANENT_STORAGE,
            $window->from,
            $to,
            restrictToHwm: true,
        );
        $previous = null;
        for ($offset = 0; $offset < self::MAX_INBOX_PAGES; $offset++) {
            try {
                $page = $this->query->page($filters, $offset, MetadataQuery::MAX_PAGE_SIZE);
            } catch (RateLimited $exception) {
                // Keep what the earlier pages listed: the window ends at their last record, so
                // that the cursor progresses once they are acknowledged instead of reading the
                // same pages again on every call.
                if ($previous !== null) {
                    $this->saveInterrupted($key, $window, $previous, $to);
                }

                throw $exception;
            }
            foreach ($page['invoices'] as $invoice) {
                $this->inbox[$invoice->ksefNumber] = $invoice;
            }
            // A truncated result, or one with more pages than KSeF should ever send for a filter
            // set, ends the window at its last record; the next window continues there, so no
            // unlisted invoice is skipped.
            $truncated = $page['isTruncated'] || ($page['hasMore'] && $offset === self::MAX_INBOX_PAGES - 1);
            $window = $window->withResults($page['invoices'], $page['hwm'], $truncated, $to);
            if (! $page['hasMore'] || $truncated) {
                break;
            }
            $previous = $page;
        }
        $this->cursor->save($key, $window->isComplete() ? $window->next() : $window);
        // Keep only the metadata of invoices still pending, so a long-running worker does not
        // accumulate it.
        $this->inbox = array_intersect_key($this->inbox, array_fill_keys($window->pending(), true));

        $references = [];
        foreach ($window->pending() as $ksefNumber) {
            $metadata = $this->inbox[$ksefNumber] ?? null;
            $references[] = new InboundReference(
                $ksefNumber,
                null,
                null,
                $metadata === null ? self::DEFAULT_DOCUMENT_TYPE : self::documentType($metadata),
                $metadata->permanentStorageDate ?? $window->from,
            );
        }

        return $references;
    }

    /**
     * Restarts the inbox at the given point: the stored window is replaced by an empty one
     * starting there. Invoices of the old window that were not acknowledged are dropped from the
     * cursor and are offered again only if they fall after the new start. Use it when a never
     * acknowledged invoice holds the cursor, or the stored window is older than KSeF's 100-day
     * query range allows.
     */
    public function resetInbox(DateTimeImmutable $from): void
    {
        $this->cursor->save($this->cursorKey(), CursorWindow::start($from));
    }

    /**
     * The invoice file with the given KSeF number, exactly as KSeF stores it. The reference
     * carries the PermanentStorage date from the metadata pending() read, or, for an invoice not
     * listed by this instance, from a metadata query by its KSeF number.
     *
     * KSeF allows 64 invoice downloads per hour for each context and IP address, and the lookup
     * of an unlisted invoice spends one of the 20 metadata queries per hour as well; pending()
     * with fetch() therefore handles about 64 invoices an hour. Use export() for high volumes.
     *
     * @throws InvalidArgumentException when the id is not a valid KSeF number; no request is made
     * @throws UnexpectedResponse when KSeF lists no invoice with that number addressed to the context
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when a request fails or KSeF refuses it
     */
    public function fetch(string $documentId): InboundDocument
    {
        if (! KsefNumber::isValid($documentId)) {
            throw new InvalidArgumentException('The invoice to fetch needs a valid KSeF number as its document id.');
        }
        $metadata = $this->inbox[$documentId] ?? $this->lookUp($documentId);
        $xml = $this->query->download($documentId);

        return new InboundDocument(
            new InboundReference($documentId, null, null, self::documentType($metadata), $metadata->permanentStorageDate),
            $xml,
        );
    }

    /**
     * Marks an invoice of the current inbox window as processed. Once every invoice of the window
     * is acknowledged, the stored cursor moves to the window's high water mark. An id outside the
     * current window is ignored.
     */
    public function acknowledge(string $documentId): void
    {
        $key = $this->cursorKey();
        $window = $this->cursor->window($key);
        unset($this->inbox[$documentId]);
        if ($window === null) {
            return;
        }
        $updated = $window->acknowledge($documentId);
        if ($updated->isComplete()) {
            $updated = $updated->next();
        }
        if ($updated !== $window) {
            $this->cursor->save($key, $updated);
        }
    }

    /**
     * Exports the invoices matching the filters as files, continuing while KSeF truncates the
     * package; see KsefExport::run(). The HttpClient given to create() must read at least
     * KsefExport::MAX_PART_BYTES (maxResponseBytes), as KSeF parts reach 50 MB.
     *
     * @throws LogicException when the zip extension is not loaded, or a part exceeds the
     *                        HttpClient's maxResponseBytes
     * @throws InvalidArgumentException|RuntimeException when an authentication on the way cannot
     *                                                   sign with the configured certificate or
     *                                                   OpenSSL fails; see the class description
     * @throws TransportException when an export fails, does not finish within the polling bounds,
     *                            or a part cannot be checked, decrypted or unzipped
     */
    public function export(InvoiceQueryFilters $filters): ExportResult
    {
        return $this->exporter->run($filters);
    }

    /**
     * The KSeF tokens of the context: generate, read, approve and revoke them.
     */
    public function tokens(): KsefTokens
    {
        return $this->tokens;
    }

    /**
     * The KSeF certificates of the subject: enrol, retrieve, list and revoke them. Enrolment needs
     * a configuration with CertificateCredentials.
     */
    public function certificates(): KsefCertificates
    {
        return $this->certificates;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'environment' => $this->config->environment,
            'context' => $this->config->context,
            'session' => $this->session,
            'cursorKey' => $this->cursorKey(),
        ];
    }

    /**
     * @throws LogicException Always: the transport holds credentials and tokens
     */
    public function __serialize(): never
    {
        throw new LogicException('The KSeF transport cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('The KSeF transport cannot be serialized.');
    }

    /**
     * The FA(3) file to send: an invoice written with Fa3Writer, or a raw file that passed
     * validation.
     *
     * @throws DocumentRejected|UnsafeXml|UnsupportedDocument|InvalidArgumentException
     */
    private function fa3(string|Invoice $invoice, ?string $name): string
    {
        if ($invoice instanceof Invoice) {
            return $this->writer->write($invoice);
        }

        $report = ($this->validator ??= Fa3Validator::create())->validate($invoice);
        $errors = array_map(
            static fn (SchemaError $error): string => sprintf('schema (line %d): %s', $error->line, rtrim(trim($error->message), '.')),
            $report->schemaErrors,
        );
        foreach ($report->fatal() as $finding) {
            $errors[] = $finding->ruleId.': '.rtrim($finding->message, '.');
        }
        if ($errors !== []) {
            throw new DocumentRejected(
                sprintf(
                    'The FA(3) invoice%s fails validation, so it was not sent to KSeF: %s',
                    $name === null ? '' : ' '.$name,
                    implode('; ', $errors),
                ),
                ['errors' => $errors],
            );
        }

        return $invoice;
    }

    /**
     * Refuses a technical correction whose file is byte for byte the rejected one: it would name
     * itself as the invoice it corrects.
     *
     * @throws InvalidArgumentException
     */
    private static function assertCorrects(string $correctedXml, string $rejectedInvoiceXml): void
    {
        if ($correctedXml === $rejectedInvoiceXml) {
            throw new InvalidArgumentException('The technical correction is identical to the rejected FA(3) file it should replace; pass the corrected file and the rejected file, in that order.');
        }
    }

    /**
     * The metadata of an invoice addressed to the context, queried by its KSeF number around the
     * date the number carries.
     *
     * @throws UnexpectedResponse|TransportException
     */
    private function lookUp(string $ksefNumber): InvoiceMetadata
    {
        $from = KsefNumber::parse($ksefNumber)->acceptedOn->modify('-1 day');
        $to = $from->modify(sprintf('+%d days', self::LOOKUP_DAYS));
        $filters = new InvoiceQueryFilters(
            InvoiceQueryFilters::SUBJECT_BUYER,
            InvoiceQueryFilters::DATE_PERMANENT_STORAGE,
            $from,
            $to < ($this->now)() ? $to : null,
            ksefNumber: $ksefNumber,
        );
        foreach ($this->query->page($filters, 0, MetadataQuery::MIN_PAGE_SIZE)['invoices'] as $invoice) {
            if ($invoice->ksefNumber === $ksefNumber) {
                return $invoice;
            }
        }

        throw new UnexpectedResponse(sprintf('KSeF lists no invoice with the KSeF number %s addressed to the context %s.', $ksefNumber, $this->config->context->value));
    }

    /**
     * Saves a window whose reading was interrupted after the given page, narrowed to the last
     * record listed as a truncated result is. When that record does not pass the window start the
     * stored window is left as it was.
     *
     * @param  array{invoices: list<InvoiceMetadata>, hwm: ?DateTimeImmutable, hasMore: bool, isTruncated: bool}  $page
     */
    private function saveInterrupted(string $key, CursorWindow $window, array $page, ?DateTimeImmutable $to): void
    {
        try {
            $narrowed = $window->withResults($page['invoices'], $page['hwm'], true, $to);
        } catch (UnexpectedResponse) {
            return;
        }
        $this->cursor->save($key, $narrowed);
        $this->inbox = array_intersect_key($this->inbox, array_fill_keys($narrowed->pending(), true));
    }

    private function defaultInboxStart(): DateTimeImmutable
    {
        return $this->inboxStart ?? ($this->now)()->modify(sprintf('-%d days', self::DEFAULT_INBOX_LOOKBACK_DAYS));
    }

    private function cursorKey(): string
    {
        return sprintf('%s:%s:%s', $this->config->environment->value, $this->config->context->value, InvoiceQueryFilters::SUBJECT_BUYER);
    }

    private static function documentType(InvoiceMetadata $metadata): string
    {
        $formCode = $metadata->raw['formCode'] ?? null;
        $systemCode = is_array($formCode) ? ($formCode['systemCode'] ?? null) : null;

        return is_string($systemCode) && trim($systemCode) !== '' ? trim($systemCode) : self::DEFAULT_DOCUMENT_TYPE;
    }

    /**
     * The session and invoice reference of a document id.
     *
     * @return array{string, string}
     *
     * @throws InvalidArgumentException
     */
    private static function documentId(string $documentId): array
    {
        $parts = explode('/', $documentId);
        if (
            count($parts) !== 2
            || preg_match(self::REFERENCE_PATTERN, $parts[0]) !== 1
            || preg_match(self::REFERENCE_PATTERN, $parts[1]) !== 1
        ) {
            throw new InvalidArgumentException('A KSeF document id is "<session reference>/<invoice reference>", as send() returns it.');
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function reference(string $reference, string $what): string
    {
        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new InvalidArgumentException(sprintf('The KSeF %s must be letters, digits and hyphens.', $what));
        }

        return $reference;
    }
}

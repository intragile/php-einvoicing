<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyUsage;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\RsaOaep;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use LogicException;

/**
 * An interactive (online) KSeF session for FA(3) invoices: sends invoices encrypted one by one,
 * reads their processing status and fetches their receipts (UPO).
 *
 * The session is opened lazily by the first send() and reused by later ones. It is replaced
 * (closed, then a new one opened with a fresh symmetric key) once less than a minute is left
 * before the validUntil KSeF gave when opening it, or once it holds 10,000 invoices. Closing a
 * session that is replaced is best effort: a failure is ignored, because KSeF closes the session
 * itself at validUntil. close() closes the current session; nothing is closed implicitly, and
 * the object never makes a network call on destruction. Long-running processes should call
 * close() when they are done, so that KSeF issues the receipts without waiting for validUntil.
 * When KSeF refuses a send because it has already ended the session (error 21180), the session
 * is forgotten, so the next send() opens a new one.
 *
 * status(), awaitFinal() and upo() take the references of any session, open or closed. Every
 * request uses the access token of the KsefApi, which must come from withAuthenticator().
 */
final class OnlineSession
{
    /** Invoices sent in one session before it is replaced. */
    public const MAX_INVOICES_PER_SESSION = 10_000;

    /** Seconds before validUntil from which the session is no longer used. */
    public const VALIDITY_MARGIN_SECONDS = 60;

    /** The KSeF error code of an operation the session's status does not allow, as a send into a closed session. */
    public const SESSION_CLOSED = 21180;

    /** The lifetime of an online session KSeF documents, assumed when the answer has no validUntil. */
    private const DEFAULT_LIFETIME = '+12 hours';

    private const OPEN_OPERATION = 'The KSeF request POST /sessions/online';

    private const SEND_OPERATION = 'The KSeF request POST /sessions/online/{referenceNumber}/invoices';

    private const STATUS_OPERATION = 'The KSeF request GET /sessions/{referenceNumber}/invoices/{invoiceReferenceNumber}';

    private const UPO_OPERATION = 'The KSeF request GET /sessions/{referenceNumber}/invoices/ksef/{ksefNumber}/upo';

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    private ?string $reference = null;

    private ?DateTimeImmutable $validUntil = null;

    private ?SymmetricKey $key = null;

    private int $sentInSession = 0;

    /**
     * @param  KsefApi  $api  a client made by withAuthenticator()
     * @param  PublicKeyRegistry  $keys  the Ministry of Finance key that encrypts the session key
     * @param  KsefConfig  $config  the bounds of awaitFinal()
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time; the system clock when null
     */
    public function __construct(
        private readonly KsefApi $api,
        private readonly PublicKeyRegistry $keys,
        private readonly KsefConfig $config,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * Encrypts an FA(3) invoice with the session key and sends it, opening or replacing the
     * session first when needed. The file is sent as given; it is not validated here.
     *
     * A send is never repeated automatically. When it fails without an answer (OutcomeUnknown),
     * sending the same file again is safe: KSeF answers a duplicate with status 440 and the
     * original KSeF number. When KSeF refuses it because it has already ended the session
     * (error 21180), the invoice was not accepted, the refusal is thrown and the session is
     * forgotten, so sending the invoice again opens a new session.
     *
     * @param  bool  $offlineMode  true for an invoice issued in offline mode
     * @param  string|null  $hashOfCorrectedInvoice  the Base64 SHA-256 digest of the invoice a
     *                                               technical correction replaces
     *
     * @throws TransportException when opening the session or sending fails, or KSeF refuses it
     */
    public function send(string $invoiceXml, bool $offlineMode = false, ?string $hashOfCorrectedInvoice = null): SentInvoice
    {
        [$reference, $key] = $this->openSession();

        $ciphertext = InvoiceCipher::encrypt($invoiceXml, $key);
        $invoiceHash = base64_encode(hash('sha256', $invoiceXml, true));
        $body = [
            'invoiceHash' => $invoiceHash,
            'invoiceSize' => strlen($invoiceXml),
            'encryptedInvoiceHash' => base64_encode(hash('sha256', $ciphertext, true)),
            'encryptedInvoiceSize' => strlen($ciphertext),
            'encryptedInvoiceContent' => base64_encode($ciphertext),
            'offlineMode' => $offlineMode,
        ];
        if ($hashOfCorrectedInvoice !== null) {
            $body['hashOfCorrectedInvoice'] = $hashOfCorrectedInvoice;
        }

        $path = '/sessions/online/'.rawurlencode($reference).'/invoices';
        try {
            $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, $body, $bearer));
        } catch (TransportException $exception) {
            if (KsefErrors::codeOf($exception) === self::SESSION_CLOSED) {
                // KSeF has ended the session on its side; there is nothing left to close.
                $this->forget();
            }

            throw $exception;
        }
        // Counted once KSeF has accepted the invoice into the session.
        $this->sentInSession++;

        return new SentInvoice($reference, KsefAnswer::string($answer, ['referenceNumber'], self::SEND_OPERATION), $invoiceHash);
    }

    /**
     * The current processing status of an invoice, read once.
     *
     * @throws TransportException when the request fails or the answer cannot be read
     */
    public function status(string $sessionRef, string $invoiceRef): KsefInvoiceStatus
    {
        $path = '/sessions/'.rawurlencode($sessionRef).'/invoices/'.rawurlencode($invoiceRef);
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', $path, null, $bearer));

        return KsefInvoiceStatus::fromAnswer($answer, self::STATUS_OPERATION);
    }

    /**
     * Reads the status until it is final, at most statusPollAttempts times with statusPollWaitMs
     * between reads, and returns the last status read, which is still pending when the attempts
     * ran out.
     *
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function awaitFinal(string $sessionRef, string $invoiceRef): KsefInvoiceStatus
    {
        for ($attempt = 1; ; $attempt++) {
            $status = $this->status($sessionRef, $invoiceRef);
            if ($status->isFinal() || $attempt >= $this->config->statusPollAttempts) {
                return $status;
            }
            $this->config->sleep($this->config->statusPollWaitMs);
        }
    }

    /**
     * The receipt (UPO) of an invoice by its KSeF number. KSeF may issue it only after the
     * session is closed; until then it answers with an error, thrown as a TransportException.
     *
     * @throws TransportException when the request fails or KSeF has no receipt
     * @throws UnsafeXml|UnsupportedDocument when the answer is not a readable UPO
     */
    public function upo(string $sessionRef, string $ksefNumber): Upo
    {
        $path = '/sessions/'.rawurlencode($sessionRef).'/invoices/ksef/'.rawurlencode($ksefNumber).'/upo';
        $response = $this->api->authorized(fn (string $bearer) => $this->api->raw('GET', $path, null, [
            'Accept' => 'application/xml',
            'X-Error-Format' => 'problem-details',
        ], $bearer));

        $xml = (string) $response->getBody();
        if (trim($xml) === '') {
            throw new UnexpectedResponse(sprintf('%s was answered with an empty body.', self::UPO_OPERATION), $response->getStatusCode());
        }

        return Upo::parse($xml);
    }

    /**
     * Closes the open session, if any; without one, no request is made. The session is
     * forgotten even when the request fails, so the next send() opens a new one. A session KSeF
     * ended on its own is forgotten by the send it refused, so close() makes no request for it.
     *
     * @throws TransportException when KSeF refuses to close the session
     */
    public function close(): void
    {
        $reference = $this->reference;
        if ($reference === null) {
            return;
        }
        $this->forget();

        $path = '/sessions/online/'.rawurlencode($reference).'/close';
        $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, null, $bearer));
    }

    /**
     * The reference number of the open session, or null when none is open.
     */
    public function referenceNumber(): ?string
    {
        return $this->reference;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'reference' => $this->reference,
            'validUntil' => $this->validUntil,
            'sentInSession' => $this->sentInSession,
        ];
    }

    /**
     * The usable open session, opened or replaced as needed.
     *
     * @return array{string, SymmetricKey}
     *
     * @throws TransportException
     */
    private function openSession(): array
    {
        if ($this->reference !== null && $this->key !== null && ! $this->mustReplace()) {
            return [$this->reference, $this->key];
        }
        if ($this->reference !== null) {
            try {
                $this->close();
            } catch (TransportException) {
                // KSeF closes the session itself at validUntil.
                $this->forget();
            }
        }

        $key = SymmetricKey::generate();
        $body = fn (string $certificatePem, string $keyId): array => [
            'formCode' => FormCode::fa3()->json(),
            'encryption' => [
                'encryptedSymmetricKey' => base64_encode(RsaOaep::encrypt($key->key, $certificatePem)),
                'initializationVector' => base64_encode($key->iv),
                'publicKeyId' => $keyId,
            ],
        ];
        $answer = $this->keys->using(
            PublicKeyUsage::SymmetricKeyEncryption,
            fn (string $certificatePem, string $keyId): array => $this->api->authorized(
                fn (string $bearer): array => $this->api->json('POST', '/sessions/online', $body($certificatePem, $keyId), $bearer),
            ),
        );

        $reference = KsefAnswer::string($answer, ['referenceNumber'], self::OPEN_OPERATION);
        $validUntil = is_string($answer['validUntil'] ?? null) ? KsefAnswer::parseDateTime($answer['validUntil']) : null;
        $this->reference = $reference;
        $this->validUntil = $validUntil ?? ($this->now)()->modify(self::DEFAULT_LIFETIME);
        $this->key = $key;
        $this->sentInSession = 0;

        return [$reference, $key];
    }

    private function mustReplace(): bool
    {
        if ($this->sentInSession >= self::MAX_INVOICES_PER_SESSION) {
            return true;
        }
        $validUntil = $this->validUntil ?? throw new LogicException('An open KSeF session has no validity.');

        return ($this->now)() >= $validUntil->modify(sprintf('-%d seconds', self::VALIDITY_MARGIN_SECONDS));
    }

    private function forget(): void
    {
        $this->reference = null;
        $this->validUntil = null;
        $this->key = null;
        $this->sentInSession = 0;
    }
}

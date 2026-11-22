<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Enrols, reads, lists and revokes the KSeF certificates of the authenticated subject through the
 * KSeF API 2.0.
 *
 * enroll() generates the key pair locally, builds the CSR from the subject KSeF reports, sends
 * the request, polls its status within the statusPoll bounds of the KsefConfig and retrieves the
 * issued certificate. The private key is returned once, in the EnrolledCertificate, and never
 * sent. KSeF accepts enrolment only after an authentication with a certificate (XAdES), so a
 * configuration with a KSeF token is refused before any request.
 *
 * Limits on TE (2026-11): 300 enrolment requests and 100 active certificates per subject; see
 * limits(). A certificate is valid for two years.
 */
final class KsefCertificates
{
    /** The status codes of CertificateEnrollmentStatusResponse. */
    private const STATUS_IN_PROGRESS = 100;

    private const STATUS_ISSUED = 200;

    private const STATUS_REJECTED = 400;

    private const STATUS_CANCELLED = 550;

    /** The certificate name pattern and length bounds of EnrollCertificateRequest. */
    private const NAME_PATTERN = '/\A[a-zA-Z0-9_\- ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]+\z/u';

    public const NAME_MIN_LENGTH = 5;

    public const NAME_MAX_LENGTH = 100;

    /** The certificate statuses of CertificateListItemStatus. */
    public const STATUSES = ['Active', 'Blocked', 'Revoked', 'Expired'];

    /** Serial numbers one POST /certificates/retrieve takes at most. */
    private const RETRIEVE_BATCH = 10;

    /** The largest page of POST /certificates/query. */
    private const QUERY_PAGE_SIZE = 50;

    /**
     * Query pages read at most: more than the 300 enrolments KSeF allows a subject, so a longer
     * result means an answer this client does not understand.
     */
    private const MAX_QUERY_PAGES = 10;

    /** A KSeF reference number: letters, digits and hyphens. */
    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9-]{1,64}\z/';

    private const STATUS_OPERATION = 'The KSeF request GET /certificates/enrollments/{referenceNumber}';

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param  KsefApi  $api  a client with an Authenticator; see KsefApi::withAuthenticator()
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time, for default certificate
     *                                                    names; the system clock when null
     */
    public function __construct(
        private readonly KsefApi $api,
        private readonly KsefConfig $config,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * The enrolment and certificate limits of the subject.
     *
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function limits(): CertificateLimits
    {
        $operation = 'The KSeF request GET /certificates/limits';
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', '/certificates/limits', null, $bearer));
        $canRequest = $answer['canRequest'] ?? null;
        if (! is_bool($canRequest)) {
            throw new UnexpectedResponse(sprintf('%s was answered without a boolean in "canRequest".', $operation));
        }

        return new CertificateLimits(
            $canRequest,
            KsefAnswer::int($answer, ['enrollment', 'remaining'], $operation),
            KsefAnswer::int($answer, ['enrollment', 'limit'], $operation),
            KsefAnswer::int($answer, ['certificate', 'remaining'], $operation),
            KsefAnswer::int($answer, ['certificate', 'limit'], $operation),
        );
    }

    /**
     * The subject a CSR must carry, read from the certificate the context authenticated with:
     * attribute name => value, or a list of values for givenName, in KSeF's order. Attributes
     * KSeF reports as null are left out.
     *
     * @return array<string, string|list<string>>
     *
     * @throws LogicException when the configuration authenticates with a KSeF token
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function enrollmentData(): array
    {
        $this->requireCertificateAuthentication();
        $operation = 'The KSeF request GET /certificates/enrollments/data';
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', '/certificates/enrollments/data', null, $bearer));

        $data = [];
        foreach ($answer as $attribute => $value) {
            if ($value === null) {
                continue;
            }
            $valid = is_string($attribute) && (is_string($value) || (is_array($value) && $value !== [] && array_is_list($value) && array_filter($value, 'is_string') === $value));
            if (! $valid) {
                throw new UnexpectedResponse(sprintf('%s was answered with an attribute that is neither a string nor a list of strings.', $operation));
            }
            /** @var string|list<string> $value */
            $data[$attribute] = $value;
        }
        if ($data === []) {
            throw new UnexpectedResponse(sprintf('%s was answered without any subject attribute.', $operation));
        }

        return $data;
    }

    /**
     * Enrols a new certificate for the authenticated subject and returns it with its private key.
     *
     * The key pair is generated locally (EC P-256 by default, or RSA-2048), the CSR carries
     * exactly the subject of enrollmentData(), and the status is read at once and then at most
     * statusPollAttempts times in all, statusPollWaitMs apart. The private key exists only in the
     * returned object: store it before doing anything else with the result. When reading the
     * issued certificate back fails, the exception, of the type the failure had and with it as the
     * previous one, names the issued serial number and says to revoke it.
     *
     * @param  ?string  $name  the certificate's own name: 5 to 100 letters (Polish ones included),
     *                         digits, spaces, hyphens and underscores; "<type> <UTC date and time>"
     *                         when null
     * @param  ?DateTimeImmutable  $validFrom  the start of validity; the moment of issue when null
     *
     * @throws InvalidArgumentException when the name is invalid, or the enrolment data cannot be
     *                                  put into a CSR
     * @throws LogicException when the configuration authenticates with a KSeF token
     * @throws DocumentRejected when KSeF rejects the request (status 400), with its description
     * @throws TransportUnavailable when the request is still being processed after the polling
     *                              bounds, or KSeF cancelled it (status 550); the message names the
     *                              enrolment reference number
     * @throws UnexpectedResponse when the request fails with another status, or an answer cannot be read
     * @throws RuntimeException when OpenSSL cannot generate the key or sign the CSR
     * @throws TransportException when a request fails or KSeF refuses it
     */
    public function enroll(
        KsefCertificateType $type,
        KeyAlgorithm $algorithm = KeyAlgorithm::Ec,
        ?string $name = null,
        ?DateTimeImmutable $validFrom = null,
    ): EnrolledCertificate {
        $this->requireCertificateAuthentication();
        $name = $name === null ? $this->defaultName($type) : self::checkedName($name);

        $csr = CsrBuilder::build($this->enrollmentData(), $algorithm);
        $body = ['certificateName' => $name, 'certificateType' => $type->value, 'csr' => base64_encode($csr['csrDer'])];
        if ($validFrom !== null) {
            $body['validFrom'] = self::isoDate($validFrom);
        }

        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', '/certificates/enrollments', $body, $bearer));
        $reference = KsefAnswer::string($answer, ['referenceNumber'], 'The KSeF request POST /certificates/enrollments');
        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new UnexpectedResponse('The KSeF request POST /certificates/enrollments was answered with a malformed reference number.');
        }

        $serial = $this->await($reference);

        try {
            return new EnrolledCertificate($this->retrieve($serial), $csr['privateKeyPem']);
        } catch (Throwable $exception) {
            throw self::orphaned($exception, $serial);
        }
    }

    /**
     * The certificate with the given serial number, without its status.
     *
     * @param  string  $serialNumber  16 hexadecimal digits, in either case
     *
     * @throws InvalidArgumentException when the serial number is malformed
     * @throws UnexpectedResponse when the answer lacks the certificate or it cannot be read
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function retrieve(string $serialNumber): KsefCertificate
    {
        $serial = KsefCertificate::serial($serialNumber);

        return $this->retrieveMany([$serial])[$serial];
    }

    /**
     * The certificates of the subject that match every given filter, active and historical ones
     * alike, each with its status and its certificate. Every page of the result is read; the
     * certificates themselves are then retrieved ten at a time.
     *
     * @param  ?string  $status  Active, Blocked, Revoked or Expired
     * @param  ?string  $name  a part of the certificate name
     * @param  ?string  $serialNumber  an exact serial number
     * @param  ?DateTimeImmutable  $expiresAfter  only certificates valid until after this moment
     * @return list<KsefCertificate>
     *
     * @throws InvalidArgumentException when the status or the serial number is invalid
     * @throws UnexpectedResponse when an answer cannot be read
     * @throws TransportException when a request fails or KSeF refuses it
     */
    public function query(
        ?KsefCertificateType $type = null,
        ?string $status = null,
        ?string $name = null,
        ?string $serialNumber = null,
        ?DateTimeImmutable $expiresAfter = null,
    ): array {
        if ($status !== null && ! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('A KSeF certificate status is one of %s, "%s" given.', implode(', ', self::STATUSES), $status));
        }
        $filters = array_filter([
            'certificateSerialNumber' => $serialNumber === null ? null : KsefCertificate::serial($serialNumber),
            'name' => $name,
            'type' => $type?->value,
            'status' => $status,
            'expiresAfter' => $expiresAfter === null ? null : self::isoDate($expiresAfter),
        ], static fn (?string $value): bool => $value !== null);

        $operation = 'The KSeF request POST /certificates/query';
        /** @var array<string, array{type: KsefCertificateType, name: string, status: string}> $listed */
        $listed = [];
        for ($page = 0; ; $page++) {
            if ($page >= self::MAX_QUERY_PAGES) {
                throw new UnexpectedResponse(sprintf('%s still reported more certificates after %d pages; narrow the filters.', $operation, self::MAX_QUERY_PAGES));
            }
            $path = sprintf('/certificates/query?pageSize=%d&pageOffset=%d', self::QUERY_PAGE_SIZE, $page);
            $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, $filters, $bearer));
            $items = $answer['certificates'] ?? null;
            $hasMore = $answer['hasMore'] ?? null;
            if (! is_array($items) || ! is_bool($hasMore)) {
                throw new UnexpectedResponse(sprintf('%s was answered without a "certificates" list and a boolean "hasMore".', $operation));
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new UnexpectedResponse(sprintf('%s was answered with a certificate entry that is not an object.', $operation));
                }
                $serial = self::answerSerial(KsefAnswer::string($item, ['certificateSerialNumber'], $operation), $operation);
                $listed[$serial] = [
                    'type' => self::answerType(KsefAnswer::string($item, ['type'], $operation), $operation),
                    'name' => KsefAnswer::string($item, ['name'], $operation),
                    'status' => KsefAnswer::string($item, ['status'], $operation),
                ];
            }
            if (! $hasMore) {
                break;
            }
        }

        $retrieved = [];
        foreach (array_chunk(array_keys($listed), self::RETRIEVE_BATCH) as $serials) {
            $retrieved += $this->retrieveMany($serials);
        }

        $certificates = [];
        foreach ($listed as $serial => $entry) {
            $certificate = $retrieved[$serial];
            $certificates[] = new KsefCertificate(
                $serial,
                $entry['type'],
                $certificate->certificatePem,
                $certificate->validFrom,
                $certificate->validTo,
                $entry['name'],
                $entry['status'],
            );
        }

        return $certificates;
    }

    /**
     * Revokes a certificate. A revoked Authentication certificate no longer authenticates, and a
     * link (KOD II) signed with a revoked Offline certificate no longer verifies.
     *
     * @param  string  $serialNumber  16 hexadecimal digits, in either case
     *
     * @throws InvalidArgumentException when the serial number is malformed
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function revoke(string $serialNumber, RevocationReason $reason = RevocationReason::Unspecified): void
    {
        $path = '/certificates/'.KsefCertificate::serial($serialNumber).'/revoke';
        $body = ['revocationReason' => $reason->value];
        $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, $body, $bearer));
    }

    /**
     * Polls the enrolment until KSeF issued the certificate and returns its serial number.
     *
     * @throws TransportException
     */
    private function await(string $reference): string
    {
        $path = '/certificates/enrollments/'.rawurlencode($reference);
        for ($attempt = 1; ; $attempt++) {
            $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', $path, null, $bearer));
            $code = KsefAnswer::int($answer, ['status', 'code'], self::STATUS_OPERATION);
            $status = is_array($answer['status'] ?? null) ? $answer['status'] : [];
            $description = is_string($status['description'] ?? null) ? $status['description'] : '';
            $details = is_array($status['details'] ?? null) ? array_values(array_filter($status['details'], 'is_string')) : [];

            if ($code === self::STATUS_ISSUED) {
                $serial = KsefAnswer::string($answer, ['certificateSerialNumber'], self::STATUS_OPERATION);

                return self::answerSerial($serial, self::STATUS_OPERATION);
            }
            if ($code === self::STATUS_REJECTED) {
                $lines = $details === [] ? [$description] : array_map(static fn (string $detail): string => $description.': '.$detail, $details);

                throw new DocumentRejected(
                    sprintf('KSeF rejected the certificate enrolment %s (status %d): %s', $reference, $code, implode('; ', $lines)),
                    ['errors' => $lines],
                );
            }
            if ($code === self::STATUS_CANCELLED) {
                throw new TransportUnavailable(sprintf('KSeF cancelled the certificate enrolment %s (status %d: %s); enrol again.', $reference, $code, $description));
            }
            if ($code !== self::STATUS_IN_PROGRESS) {
                throw new UnexpectedResponse(sprintf('The KSeF certificate enrolment %s failed with status %d: %s', $reference, $code, $description));
            }
            if ($attempt >= $this->config->statusPollAttempts) {
                throw new TransportUnavailable(sprintf(
                    'The KSeF certificate enrolment %s was still being processed after %d status reads; KSeF may still issue the certificate, but its private key is lost, so revoke it when it appears in query() and enrol again.',
                    $reference,
                    $attempt,
                ));
            }
            $this->config->sleep($this->config->statusPollWaitMs);
        }
    }

    /**
     * The failure of reading back a certificate KSeF has already issued, as an exception of the
     * same type, and with the same fields, that names the serial number and says the certificate
     * should be revoked: its private key existed only in this call and is gone. The original is
     * kept as the previous exception. An exception of a type not known here is returned unchanged.
     * The message quotes the original message, which never holds key material.
     */
    private static function orphaned(Throwable $exception, string $serial): Throwable
    {
        $message = sprintf(
            'KSeF issued the certificate %s, but it could not be read back with its private key: %s The private key is gone, so revoke the certificate %s and enrol again.',
            $serial,
            rtrim($exception->getMessage(), '.').'.',
            $serial,
        );

        return match ($exception::class) {
            TransportUnavailable::class => new TransportUnavailable($message, $exception),
            OutcomeUnknown::class => new OutcomeUnknown($message, $exception),
            AuthenticationFailed::class => new AuthenticationFailed($message, $exception),
            RateLimited::class => new RateLimited($message, $exception->retryAfterSeconds, $exception),
            DocumentRejected::class => new DocumentRejected($message, $exception->errors, $exception),
            UnexpectedResponse::class => new UnexpectedResponse($message, $exception->status, $exception->bodyExcerpt, $exception),
            InvalidArgumentException::class => new InvalidArgumentException($message, 0, $exception),
            RuntimeException::class => new RuntimeException($message, 0, $exception),
            default => $exception,
        };
    }

    /**
     * The certificates with the given serial numbers, by serial number.
     *
     * @param  non-empty-list<string>  $serials  upper-case serial numbers, at most RETRIEVE_BATCH
     * @return array<string, KsefCertificate>
     *
     * @throws TransportException
     */
    private function retrieveMany(array $serials): array
    {
        $operation = 'The KSeF request POST /certificates/retrieve';
        $body = ['certificateSerialNumbers' => $serials];
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', '/certificates/retrieve', $body, $bearer));
        $items = $answer['certificates'] ?? null;
        if (! is_array($items)) {
            throw new UnexpectedResponse(sprintf('%s was answered without a "certificates" list.', $operation));
        }

        $found = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new UnexpectedResponse(sprintf('%s was answered with a certificate entry that is not an object.', $operation));
            }
            $serial = self::answerSerial(KsefAnswer::string($item, ['certificateSerialNumber'], $operation), $operation);
            if (! in_array($serial, $serials, true)) {
                continue;
            }
            $found[$serial] = self::certificate(
                $serial,
                self::answerType(KsefAnswer::string($item, ['certificateType'], $operation), $operation),
                KsefAnswer::string($item, ['certificate'], $operation),
                KsefAnswer::string($item, ['certificateName'], $operation),
                $operation,
            );
        }
        foreach ($serials as $serial) {
            if (! isset($found[$serial])) {
                throw new UnexpectedResponse(sprintf('%s was answered without the certificate %s.', $operation, $serial));
            }
        }

        return $found;
    }

    /**
     * @throws UnexpectedResponse when the certificate is not Base64 DER X.509 or names another serial number
     */
    private static function certificate(string $serial, KsefCertificateType $type, string $base64Der, string $name, string $operation): KsefCertificate
    {
        $der = base64_decode($base64Der, true);
        $pem = $der === false || $der === ''
            ? ''
            : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
        $parsed = $pem === '' ? false : @openssl_x509_parse($pem);
        if (! is_array($parsed) || ! is_int($parsed['validFrom_time_t'] ?? null) || ! is_int($parsed['validTo_time_t'] ?? null)) {
            while (openssl_error_string() !== false) {
                // Drain OpenSSL's error queue.
            }

            throw new UnexpectedResponse(sprintf('%s was answered with a certificate %s that is not a Base64-encoded DER X.509 certificate.', $operation, $serial));
        }
        $x509Serial = is_string($parsed['serialNumberHex'] ?? null) ? str_pad(strtoupper($parsed['serialNumberHex']), 16, '0', STR_PAD_LEFT) : null;
        if ($x509Serial !== $serial) {
            throw new UnexpectedResponse(sprintf('%s was answered with a certificate whose serial number is not %s.', $operation, $serial));
        }

        return new KsefCertificate(
            $serial,
            $type,
            $pem,
            new DateTimeImmutable('@'.$parsed['validFrom_time_t']),
            new DateTimeImmutable('@'.$parsed['validTo_time_t']),
            $name,
        );
    }

    /**
     * @throws UnexpectedResponse
     */
    private static function answerSerial(string $serial, string $operation): string
    {
        if (preg_match(KsefCertificate::SERIAL_PATTERN, $serial) !== 1) {
            throw new UnexpectedResponse(sprintf('%s was answered with a certificate serial number that is not 16 upper-case hexadecimal digits.', $operation));
        }

        return $serial;
    }

    /**
     * @throws UnexpectedResponse
     */
    private static function answerType(string $type, string $operation): KsefCertificateType
    {
        return KsefCertificateType::tryFrom($type)
            ?? throw new UnexpectedResponse(sprintf('%s was answered with the unknown certificate type "%s".', $operation, $type));
    }

    /**
     * @throws LogicException
     */
    private function requireCertificateAuthentication(): void
    {
        if ($this->config->credentials instanceof TokenCredentials) {
            throw new LogicException('KSeF enrols certificates only after an authentication with a certificate (XAdES); configure CertificateCredentials instead of a KSeF token.');
        }
    }

    private function defaultName(KsefCertificateType $type): string
    {
        return $type->value.' '.($this->now)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d His');
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function checkedName(string $name): string
    {
        $length = mb_check_encoding($name, 'UTF-8') ? mb_strlen($name, 'UTF-8') : 0;
        if ($length < self::NAME_MIN_LENGTH || $length > self::NAME_MAX_LENGTH || preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'A KSeF certificate name has %d to %d letters (Polish ones included), digits, spaces, hyphens or underscores.',
                self::NAME_MIN_LENGTH,
                self::NAME_MAX_LENGTH,
            ));
        }

        return $name;
    }

    /**
     * An ISO 8601 date-time in UTC with milliseconds, for example 2026-11-14T09:48:26.870+00:00.
     */
    private static function isoDate(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.vP');
    }
}

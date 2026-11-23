<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Tests\Support\KsefLive;
use IntrAgile\EInvoicing\Tests\Support\KsefTap;
use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenPermission;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\EnrolledCertificate;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KeyAlgorithm;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificate;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificates;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificateType;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\CursorWindow;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InMemoryCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceMetadata;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\KsefCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\MetadataQuery;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use IntrAgile\EInvoicing\Transport\Ksef\KsefTransport;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\Latarnia;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\LatarniaMessage;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\OutageEvents;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\CertificateVerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\KsefQrCodes;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchPackage;
use IntrAgile\EInvoicing\Transport\Ksef\Session\InvoicingMode;
use IntrAgile\EInvoicing\Transport\Ksef\Session\OnlineSession;
use IntrAgile\EInvoicing\Transport\Ksef\Session\Upo;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\KsefTestData;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Proves the KSeF transport against the real KSeF TEST environment (TE) with synthetic data.
 *
 * Needs no secrets: every test generates fresh random, checksum-valid NIPs and self-signed
 * certificates in memory, which TE accepts in place of qualified ones. The base URL is hard-wired
 * to TE and the HTTP client refuses every other KSeF host. Every request has a 20 s timeout and
 * every polling loop is bounded. The tests skip during TE's daily maintenance window
 * (16:00-18:00 Europe/Warsaw). Output never contains a token, a key or a passphrase.
 *
 * Observed on TE (API 2.10.0 RC) on 2026-11-21; each test's docblock has its own findings:
 * - Authentication: AuthTokenRequest in the namespace http://ksef.mf.gov.pl/auth/token/2.1 is
 *   accepted. A seal reports xades.qualified-seal, a TINPL personal certificate (issuer DN with
 *   GN and SN) xades.qualified-certificate, a KSeF token token.ksef.
 * - An online session answers validUntil with seven fraction digits and an offset, 12 hours
 *   ahead (for example 2026-11-21T23:32:31.3685558+00:00).
 * - The UPO of an accepted invoice is available before the session is closed.
 * - A send into a closed online session is refused with HTTP 400, code 21180.
 * - Batch parts of about 1,490 bytes are accepted: TE enforces no minimum part size.
 * - Export partHash is the SHA-256 of the decrypted part; date ranges with six fraction digits
 *   are accepted by the export and the metadata query.
 * - The 429 answer is Problem Details without an errors list, with Retry-After in seconds.
 */
#[Group('ksef-live')]
final class KsefLiveTest extends TestCase
{
    /**
     * Reads of a buyer inbox before giving up. KSeF allows 20 metadata queries per hour for
     * each context and IP address, so the test polls at most ten times, 25 s apart.
     */
    private const INBOX_ATTEMPTS = 10;

    private const INBOX_WAIT_SECONDS = 25;

    /** Metadata queries the buyer test may spend, below the hourly quota of 20. */
    private const INBOX_QUERY_BUDGET = 15;

    /** Metadata queries all tests of a run may spend together; each context also has its own hourly quota of 20. */
    private const RUN_METADATA_BUDGET = 15;

    /** Reads of the invoice metadata before giving up on a technical correction's entry. */
    private const METADATA_ATTEMPTS = 5;

    private const METADATA_WAIT_SECONDS = 10;

    /** Reads of a QR verification page before giving up on the expected verdict. */
    private const QR_ATTEMPTS = 6;

    private const QR_WAIT_SECONDS = 5;

    /** The detail line of the TE QR page for a KOD II signature that does not verify. */
    private const SIGNATURE_INVALID = 'Podpis wystawcy nie jest prawidłowy';

    /** Metadata queries spent by the tests of this run that count them. */
    private static int $metadataQueries = 0;

    /** @var list<string> secrets a running test learned, kept out of its failure message */
    private array $secrets = [];

    /** @var array<string, KsefCertificates> certificates the running test enrolled and has not revoked, by serial number */
    private array $enrolled = [];

    protected function setUp(): void
    {
        KsefLive::skipDuringMaintenance();
    }

    /**
     * Observed: the self-signed seal (organizationIdentifier=VATPL-<NIP>) authenticates with the
     * AuthTokenRequest namespace http://ksef.mf.gov.pl/auth/token/2.1; GET /auth/sessions reports
     * the method xades.qualified-seal. The access token is valid 15 minutes, the refresh token
     * 7 days.
     */
    public function test_seal_authenticates(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip, 'Testowa Pieczęć Sp. z o.o.'));
        [$api, $authenticator] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($api, $authenticator): void {
            $tokens = $authenticator->authenticate();
            self::assertGreaterThan(new DateTimeImmutable, $tokens->accessValidUntil);
            KsefLive::report(sprintf('seal: access token valid until %s, refresh until %s', $tokens->accessValidUntil->format(DATE_ATOM), $tokens->refreshValidUntil->format(DATE_ATOM)));

            self::assertSame('xades.qualified-seal', self::authenticationMethod($api));
        });
    }

    /**
     * Observed: a self-signed personal certificate (C, givenName, surname,
     * serialNumber=TINPL-<NIP>, CN) authenticates in the context of its own NIP without any
     * /testdata/person call; the issuer DN sent with the short names GN and SN is accepted, and
     * GET /auth/sessions reports the method xades.qualified-certificate.
     */
    public function test_personal_certificate_authenticates(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::person($nip, 'Anna', 'Testowa'));
        [$api, $authenticator] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($api, $authenticator): void {
            $authenticator->authenticate();
            $method = self::authenticationMethod($api);
            KsefLive::report('person (TINPL): authentication method '.$method);
            self::assertSame('xades.qualified-certificate', $method);
        });
    }

    /**
     * Generates a KSeF token (rev2) after seal authentication, authenticates with it, generates a
     * second token while authenticated by the first (which then needs approval), approves it and
     * revokes both.
     *
     * Observed: POST /tokens/rev2 answers 202 with referenceNumber and token; POST
     * /auth/ksef-token answers 202 with referenceNumber and authenticationToken. A token generated
     * after seal authentication is Active at once. A token generated after KSeF-token
     * authentication is AwaitingApproval in GET /tokens/rev2/{ref} while the legacy
     * GET /tokens/{ref} reports it Active. Its approval by the token-authenticated context is
     * refused with 26005; the seal-authenticated context approves it (Active). After DELETE both
     * status reads report Revoked.
     */
    public function test_ksef_token_round_trip(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip));
        $sealed = KsefTransport::create($config, KsefLive::http($tap));
        [$sealedApi] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($nip, $tap, $sealed, $sealedApi): void {
            $generated = $sealed->tokens()->generate([TokenPermission::InvoiceRead, TokenPermission::InvoiceWrite], 'Synthetic live test token');
            $this->secrets[] = $generated->token();
            $generateAnswer = $tap->lastJson('POST', '#^/tokens/rev2$#');
            KsefLive::report('POST /tokens/rev2 answer members: '.implode(', ', array_keys($generateAnswer ?? [])).'; HTTP '.($tap->last('POST', '#^/tokens/rev2$#')['status'] ?? 0));

            $first = self::awaitTokenStatus($sealed, $sealedApi, $generated->referenceNumber, ['Active', 'AwaitingApproval', 'Failed']);
            KsefLive::report(sprintf('token after seal authentication: legacy status %s, rev2 status %s', $first['legacy'], $first['rev2']));
            if ($first['rev2'] === 'AwaitingApproval') {
                $sealed->tokens()->approve($generated->referenceNumber);
                $first = self::awaitTokenStatus($sealed, $sealedApi, $generated->referenceNumber, ['Active', 'Failed']);
            }
            self::assertSame('Active', $first['rev2']);

            // Authenticate with the token.
            $tokenTap = new KsefTap;
            $tokenConfig = KsefLive::config($nip, new TokenCredentials($generated->token()));
            $redactor = KsefLive::redactor($tokenConfig, $generated->token());
            $byToken = KsefTransport::create($tokenConfig, KsefLive::http($tokenTap));
            [$tokenApi] = KsefLive::api($tokenConfig, KsefLive::http($tokenTap));
            $method = self::authenticationMethod($tokenApi);
            KsefLive::report('KSeF token: authentication method '.$method, $redactor);
            $init = $tokenTap->lastJson('POST', '#^/auth/ksef-token$#');
            KsefLive::report('POST /auth/ksef-token answer members: '.implode(', ', array_keys($init ?? [])).'; HTTP '.($tokenTap->last('POST', '#^/auth/ksef-token$#')['status'] ?? 0));
            self::assertSame('token.ksef', $method);

            // A token generated after KSeF-token authentication needs approval.
            $second = $byToken->tokens()->generate([TokenPermission::InvoiceRead], 'Synthetic second live token');
            $this->secrets[] = $second->token();
            $redactor = KsefLive::redactor($tokenConfig, $generated->token(), $second->token());
            $secondStatus = self::awaitTokenStatus($byToken, $tokenApi, $second->referenceNumber, ['Active', 'AwaitingApproval', 'Failed']);
            KsefLive::report(sprintf('token generated after token authentication: legacy status %s, rev2 status %s', $secondStatus['legacy'], $secondStatus['rev2']));
            self::assertSame('AwaitingApproval', $secondStatus['rev2']);
            // Approval by the token-authenticated context is refused (26005); the seal approves.
            try {
                $byToken->tokens()->approve($second->referenceNumber);
                KsefLive::report('approve by the token-authenticated context: accepted');
            } catch (TransportException $exception) {
                KsefLive::report(sprintf('approve by the token-authenticated context: %s, code %s', $exception::class, KsefErrors::codeOf($exception) ?? '-'), $redactor);
            }
            if ($byToken->tokens()->status($second->referenceNumber) === 'AwaitingApproval') {
                $sealed->tokens()->approve($second->referenceNumber);
                $secondStatus = self::awaitTokenStatus($byToken, $tokenApi, $second->referenceNumber, ['Active', 'Failed']);
                KsefLive::report(sprintf('after approve: legacy status %s, rev2 status %s', $secondStatus['legacy'], $secondStatus['rev2']));
            }
            self::assertSame('Active', $secondStatus['rev2']);

            $sealed->tokens()->revoke($second->referenceNumber);
            $sealed->tokens()->revoke($generated->referenceNumber);
            $revoked = self::awaitTokenStatus($sealed, $sealedApi, $generated->referenceNumber, ['Revoked']);
            $secondRevoked = self::awaitTokenStatus($sealed, $sealedApi, $second->referenceNumber, ['Revoked']);
            KsefLive::report(sprintf('after revoke: first token legacy %s, rev2 %s; second token legacy %s, rev2 %s', $revoked['legacy'], $revoked['rev2'], $secondRevoked['legacy'], $secondRevoked['rev2']));
            self::assertContains($revoked['rev2'], ['Revoking', 'Revoked']);
            self::assertContains($secondRevoked['rev2'], ['Revoking', 'Revoked']);
        });
    }

    /**
     * Observed: POST /auth/token/refresh returns an access token valid another 15 minutes. A refresh
     * issued within the second of the authentication may return the identical token (same JWT), so
     * only its validity and its use are asserted.
     * After DELETE /auth/sessions/current the refresh is refused with HTTP 400, code 21301
     * ("Status uwierzytelnienia (425) nie pozwala na odświeżenie tokenu dostępowego"), which maps
     * to the AuthenticationFailed that Authenticator::renew() answers with a full authentication.
     */
    public function test_refresh_returns_a_valid_access_token(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip));
        [$api, $authenticator] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($config, $api, $authenticator, $tap): void {
            $tokens = $authenticator->authenticate();
            $refreshed = $authenticator->refresh($tokens);
            $redactor = KsefLive::redactor($config, $tokens->accessToken(), $tokens->refreshToken(), $refreshed->accessToken());
            array_push($this->secrets, $tokens->accessToken(), $tokens->refreshToken(), $refreshed->accessToken());
            self::assertGreaterThan(new DateTimeImmutable, $refreshed->accessValidUntil);
            self::assertGreaterThanOrEqual($tokens->accessValidUntil, $refreshed->accessValidUntil);
            KsefLive::report(sprintf(
                'refresh: access token valid until %s, %s the token of the authentication',
                $refreshed->accessValidUntil->format(DATE_ATOM),
                hash_equals($tokens->accessToken(), $refreshed->accessToken()) ? 'identical to' : 'different from',
            ));

            // The refreshed token works for an authorized call; a refusal throws.
            $api->json('GET', '/auth/sessions', null, $refreshed->accessToken());

            // End the authentication session, then try the refresh token again.
            $api->json('DELETE', '/auth/sessions/current', null, $refreshed->accessToken());
            try {
                $authenticator->refresh($refreshed);
                KsefLive::report('refresh after DELETE /auth/sessions/current: still accepted');
            } catch (TransportException $exception) {
                $answer = $tap->last('POST', '#^/auth/token/refresh$#');
                KsefLive::report(sprintf('refresh after DELETE /auth/sessions/current: %s, HTTP %d, code %s: %s', $exception::class, $answer['status'] ?? 0, KsefErrors::codeOf($exception) ?? '-', $exception->getMessage()), $redactor);
            }
        });
    }

    /**
     * Sends one invoice online, reads its KSeF number, closes the session, validates the UPO and
     * downloads the invoice unchanged.
     *
     * Observed: status 200 within a few seconds; the TE UPO validates with Upo::validate(Test);
     * GET /invoices/ksef/{n} returns the sent bytes.
     */
    public function test_online_send_gets_ksef_number_and_valid_upo(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));
        [$api] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $buyer, $tap, $transport, $api): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, $buyer));
            $sent = $transport->send($xml);
            $open = $tap->lastJson('POST', '#^/sessions/online$#');
            KsefLive::report('POST /sessions/online validUntil as sent: '.var_export($open['validUntil'] ?? null, true));

            $status = $transport->awaitFinal($sent->documentId);
            self::assertSame(200, $status->code, $status->description);
            self::assertNotNull($status->ksefNumber);
            self::assertTrue(KsefNumber::isValid($status->ksefNumber));
            self::assertSame(DeliveryState::Delivered, $transport->status($sent->documentId)->state);
            KsefLive::report('online: KSeF number '.$status->ksefNumber);

            $transport->close();
            $upo = self::awaitUpo($transport, $sent->documentId);
            $report = $upo->validate(KsefEnvironment::Test);
            self::assertTrue($report->isValid(), json_encode($report->schemaErrors, JSON_UNESCAPED_UNICODE) ?: '');
            self::assertSame($seller, $upo->contextNip);
            self::assertContains($status->ksefNumber, $upo->ksefNumbers);

            self::assertSame($xml, (new MetadataQuery($api))->download($status->ksefNumber));
        });
    }

    /**
     * Records whether the UPO of an invoice is available before its online session is closed
     * (P7) and asserts it is available after close. Then closes an OnlineSession's session
     * behind its back: the next send is refused with 21180 and the session forgotten, and the
     * send after it opens a new session and is accepted.
     *
     * Observed: the UPO is available before close. A send into a session KSeF has closed is
     * refused with HTTP 400, code 21180 "Status sesji nie pozwala na wykonanie operacji" (session
     * status 200), mapped to UnexpectedResponse.
     */
    public function test_upo_availability_before_close(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));
        [$api, , $keys] = KsefLive::api($config, KsefLive::http($tap));
        $session = new OnlineSession($api, $keys, $config);

        $this->live($config, function () use ($seller, $tap, $transport, $api, $session): void {
            $sent = $transport->send(KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip())));
            $status = $transport->awaitFinal($sent->documentId);
            self::assertSame(200, $status->code, $status->description);

            try {
                $transport->upo($sent->documentId);
                KsefLive::report('UPO before close: available');
            } catch (TransportException $exception) {
                $answer = $tap->last('GET', '#/upo$#');
                KsefLive::report(sprintf('UPO before close: %s, HTTP %d, code %s', $exception::class, $answer['status'] ?? 0, KsefErrors::codeOf($exception) ?? '-'));
            }

            $transport->close();
            self::assertContains($status->ksefNumber, self::awaitUpo($transport, $sent->documentId)->ksefNumbers);

            // KSeF ends the session of an OnlineSession on its own (here: closed through the API
            // behind its back).
            $first = $session->send(KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip())));
            self::assertSame(200, $session->awaitFinal($first->sessionReference, $first->invoiceReference)->code);
            $api->authorized(fn (string $bearer): array => $api->json('POST', '/sessions/online/'.$first->sessionReference.'/close', null, $bearer));

            $refused = null;
            try {
                $session->send(KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip())));
            } catch (TransportException $exception) {
                $refused = $exception;
            }
            self::assertNotNull($refused, 'A send into a session KSeF closed must be refused.');
            $answer = $tap->last('POST', '#^/sessions/online/[^/]+/invoices$#');
            KsefLive::report(sprintf('send to a closed session: %s, HTTP %d, code %s: %s', $refused::class, $answer['status'] ?? 0, KsefErrors::codeOf($refused) ?? '-', $refused->getMessage()));
            self::assertSame(OnlineSession::SESSION_CLOSED, KsefErrors::codeOf($refused));
            self::assertNull($session->referenceNumber());

            $again = $session->send(KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip())));
            self::assertNotSame($first->sessionReference, $again->sessionReference);
            $final = $session->awaitFinal($again->sessionReference, $again->invoiceReference);
            $session->close();
            KsefLive::report(sprintf('send after 21180: new session %s, status %d', $again->sessionReference, $final->code));
            self::assertSame(200, $final->code, $final->description);
        });
    }

    /**
     * Sends three invoices in one batch split into two encrypted parts; all three are accepted.
     *
     * Observed: a zip of about 3,000 bytes split into two parts of about 1,490 bytes is accepted
     * (no 21157), status 200 "Sesja wsadowa przetworzona pomyślnie"; GET /sessions/{ref}/invoices
     * answers 200 on the finished session.
     */
    public function test_batch_of_three_in_two_parts(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $files = [];
        for ($i = 1; $i <= 3; $i++) {
            $files[sprintf('invoice-%d.xml', $i)] = KsefLive::fa3(KsefLive::invoice($seller, $buyer));
        }
        $zipSize = BatchPackage::build($files, 100_000_000)->fileSize;
        $partBytes = intdiv($zipSize + 1, 2);
        $package = BatchPackage::build($files, $partBytes);
        self::assertCount(2, $package->parts);

        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller), $partBytes);
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($files, $zipSize, $partBytes, $transport, $tap): void {
            KsefLive::report(sprintf('batch: zip %d bytes, part size %d bytes, 2 parts', $zipSize, $partBytes));
            $reference = $transport->sendBatch($files);
            $result = $transport->awaitBatch($reference);
            KsefLive::report(sprintf('batch %s: status %d %s, %d invoices, %d successful, %d failed', $reference, $result->code, $result->description, $result->invoiceCount, $result->successful, $result->failed));
            self::assertSame(200, $result->code, $result->description.' '.implode('; ', $result->details));
            self::assertSame(3, $result->successful);
            self::assertCount(3, $result->invoices);
            foreach ($result->invoices as $invoice) {
                self::assertSame(200, $invoice->code);
            }
            $list = $tap->last('GET', '#^/sessions/[^/]+/invoices$#');
            KsefLive::report('GET /sessions/{ref}/invoices on the finished batch: HTTP '.($list['status'] ?? 0));
        });
    }

    /**
     * A raw FA(3) file with a buyer NIP that breaks the schema pattern, sent through an
     * OnlineSession so that the local validation of KsefTransport::send() is bypassed, ends
     * Failed / Validation.
     *
     * Observed: status 450 "Błąd weryfikacji semantyki dokumentu faktury" with the XSD message
     * (TNrNIP pattern) in details.
     */
    public function test_schema_invalid_invoice_fails_with_validation(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        [$api, , $keys] = KsefLive::api($config, KsefLive::http($tap));
        $session = new OnlineSession($api, $keys, $config);

        $this->live($config, function () use ($seller, $buyer, $session): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, $buyer));
            $broken = str_replace('<NIP>'.$buyer.'</NIP>', '<NIP>12AB</NIP>', $xml);
            self::assertNotSame($xml, $broken);

            $sent = $session->send($broken);
            try {
                $status = $session->awaitFinal($sent->sessionReference, $sent->invoiceReference);
            } finally {
                $session->close();
            }
            KsefLive::report(sprintf('schema-invalid invoice: status %d %s — %s', $status->code, $status->description, implode('; ', $status->details)));
            self::assertContains($status->code, [430, 450]);
            self::assertSame(DeliveryState::Failed, $status->deliveryState());
            self::assertSame(FailureCategory::Validation, $status->failure()?->category);
        });
    }

    /**
     * Sends the same FA(3) file twice: the second copy ends Failed / Duplicate naming the KSeF
     * number of the first (status 440). The original's UPO proves the copy is the same document.
     * A different file with the same seller, invoice type and number (P_2) is also answered 440,
     * and the UPO proves it is not the same document.
     *
     * Observed: status 440 "Duplikat faktury" with extensions.originalKsefNumber and
     * extensions.originalSessionReferenceNumber; the original's UPO can be read while its
     * session is still open.
     */
    public function test_resend_is_reported_as_duplicate(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $tap, $transport): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $first = $transport->awaitFinal($transport->send($xml)->documentId);
            self::assertSame(200, $first->code, $first->description);

            $second = $transport->send($xml);
            $duplicate = $transport->awaitFinal($second->documentId);
            $raw = $tap->lastJson('GET', '#^/sessions/[^/]+/invoices/[^/]+$#');
            KsefLive::report('duplicate status answer: '.json_encode($raw['status'] ?? null, JSON_UNESCAPED_UNICODE));
            self::assertSame(440, $duplicate->code);
            self::assertSame($first->ksefNumber, $duplicate->originalKsefNumber);
            $status = $transport->status($second->documentId);
            self::assertSame(DeliveryState::Failed, $status->state);
            self::assertSame(FailureCategory::Duplicate, $status->failure?->category);
            self::assertStringContainsString((string) $first->ksefNumber, (string) $status->failure->message);
            self::assertNotNull($duplicate->originalSessionReferenceNumber);
            self::assertTrue($transport->isSameDocument($duplicate, $xml));

            $other = str_replace('Materiał testowy', 'Materiał testowy B', $xml);
            self::assertNotSame($xml, $other);
            $otherDuplicate = $transport->awaitFinal($transport->send($other)->documentId);
            self::assertSame(440, $otherDuplicate->code);
            self::assertSame($first->ksefNumber, $otherDuplicate->originalKsefNumber);
            self::assertFalse($transport->isSameDocument($otherDuplicate, $other));
            $transport->close();
        });
    }

    /**
     * The buyer authenticates with its own seal, finds the invoice in pending() within bounded
     * polling and fetches the same bytes. The inbox starts 100 days back, so the first query is a
     * bounded 90-day chunk.
     *
     * Polls at most INBOX_ATTEMPTS times INBOX_WAIT_SECONDS apart and asserts that the whole
     * test spends at most INBOX_QUERY_BUDGET metadata queries, within KSeF's 20 per hour.
     *
     * After the acknowledgement the stored cursor moves to the window's high water mark, and a
     * fresh transport on the same cursor store no longer offers the invoice.
     *
     * Observed: the chunked queries are answered 200; the invoice was listed about two minutes
     * after acceptance, as the PermanentStorage high water mark lags behind (6 pending() calls
     * 25 s apart, 7 metadata queries in total).
     */
    public function test_buyer_sees_invoice_in_pending_and_fetches_it(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $sellerConfig = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $buyerConfig = KsefLive::config($buyer, SelfSignedCertificate::seal($buyer));
        $sellerTransport = KsefTransport::create($sellerConfig, KsefLive::http(new KsefTap));
        $buyerTap = new KsefTap;
        $store = new class implements KsefCursorStore
        {
            public ?CursorWindow $last = null;

            private InMemoryCursorStore $inner;

            public function __construct()
            {
                $this->inner = new InMemoryCursorStore;
            }

            public function window(string $key): ?CursorWindow
            {
                return $this->inner->window($key);
            }

            public function save(string $key, CursorWindow $window): void
            {
                $this->last = $window;
                $this->inner->save($key, $window);
            }
        };
        $buyerTransport = KsefTransport::create($buyerConfig, KsefLive::http($buyerTap), $store, new DateTimeImmutable('-100 days'));

        $this->live($buyerConfig, function () use ($seller, $buyer, $sellerTransport, $buyerTransport, $buyerTap, $buyerConfig, $store): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, $buyer));
            $status = $sellerTransport->awaitFinal($sellerTransport->send($xml)->documentId);
            self::assertSame(200, $status->code, $status->description);
            $sellerTransport->close();
            $ksefNumber = (string) $status->ksefNumber;

            $started = microtime(true);
            $queries = 0;
            $found = KsefLive::poll(function () use ($buyerTransport, $ksefNumber, &$queries): ?string {
                $queries++;
                foreach ($buyerTransport->pending() as $reference) {
                    if (KsefTransport::ksefNumberOf($reference->documentId) === $ksefNumber) {
                        return $reference->documentId;
                    }
                }

                return null;
            }, self::INBOX_ATTEMPTS, self::INBOX_WAIT_SECONDS);
            KsefLive::report(sprintf('buyer inbox: %s after %d pending() calls, %.0f s', $found === null ? 'not listed' : 'listed', $queries, microtime(true) - $started));
            $queries = $buyerTap->all('POST', '#^/invoices/query/metadata$#');
            KsefLive::report(sprintf('buyer inbox: metadata queries answered %s', implode(', ', array_unique(array_map(static fn (array $answer): string => (string) $answer['status'], $queries)))));
            self::assertNotNull($found);
            self::assertSame($ksefNumber, KsefTransport::ksefNumberOf($found));

            // The id from pending() is fetched without a metadata query.
            $before = count($buyerTap->all('POST', '#^/invoices/query/metadata$#'));
            $document = $buyerTransport->fetch($found);
            self::assertSame($xml, $document->xml);
            self::assertCount($before, $buyerTap->all('POST', '#^/invoices/query/metadata$#'));
            $listed = $store->last;
            self::assertNotNull($listed);
            self::assertNotNull($listed->hwm);
            self::assertArrayHasKey($ksefNumber, $listed->ids);
            $buyerTransport->acknowledge($found);
            self::assertSame([], array_filter($buyerTransport->pending(), static fn ($r): bool => $r->documentId === $found));

            // The cursor moved past the acknowledged window and survives in the store.
            $moved = $store->last;
            self::assertNotNull($moved);
            self::assertArrayNotHasKey($ksefNumber, $moved->ids);
            self::assertGreaterThanOrEqual($listed->hwm, $moved->from);
            self::assertGreaterThan($listed->from, $moved->from);
            KsefLive::report(sprintf('buyer inbox: cursor moved from %s to %s', $listed->from->format(DATE_RFC3339_EXTENDED), $moved->from->format(DATE_RFC3339_EXTENDED)));
            $freshTap = new KsefTap;
            $fresh = KsefTransport::create($buyerConfig, KsefLive::http($freshTap), $store);
            self::assertSame([], array_filter($fresh->pending(), static fn ($r): bool => KsefTransport::ksefNumberOf($r->documentId) === $ksefNumber));
            $fresh->close();
            $spent = count($buyerTap->all('POST', '#^/invoices/query/metadata$#')) + count($freshTap->all('POST', '#^/invoices/query/metadata$#'));
            KsefLive::report(sprintf('buyer inbox: %d metadata queries in total', $spent));
            self::assertLessThanOrEqual(self::INBOX_QUERY_BUDGET, $spent);
        });
    }

    /**
     * Exports the seller's invoices of the last hour and finds the sent one, byte for byte.
     *
     * Observed: one export finds the invoice; partHash matches the decrypted part; status 420
     * was not seen.
     */
    public function test_export_returns_sent_invoice(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $tap, $transport): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $status = $transport->awaitFinal($transport->send($xml)->documentId);
            self::assertSame(200, $status->code, $status->description);
            $transport->close();
            $ksefNumber = (string) $status->ksefNumber;

            $filters = new InvoiceQueryFilters(InvoiceQueryFilters::SUBJECT_SELLER, InvoiceQueryFilters::DATE_PERMANENT_STORAGE, new DateTimeImmutable('-1 hour'));
            KsefLive::report('export dateRange.from as sent: '.$filters->from->format('Y-m-d\\TH:i:s.uP'));
            $rounds = 0;
            $result = KsefLive::poll(function () use ($transport, $filters, $ksefNumber, &$rounds) {
                $rounds++;
                $result = $transport->export($filters);

                return isset($result->invoices[$ksefNumber]) ? $result : null;
            }, 5, 10);
            $start = $tap->lastJson('GET', '#^/invoices/exports/[^/]+$#');
            KsefLive::report(sprintf('export: %s after %d export(s); last status %s', $result === null ? 'not found' : 'found', $rounds, json_encode($start['status'] ?? null, JSON_UNESCAPED_UNICODE)));
            self::assertNotNull($result);
            self::assertSame($xml, $result->invoices[$ksefNumber]);
        });
    }

    /**
     * A corrective invoice (KOR) naming the KSeF number of an invoice sent in the same run is
     * accepted.
     */
    public function test_correction_references_ksef_number(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http(new KsefTap));

        $this->live($config, function () use ($seller, $buyer, $transport): void {
            $original = KsefLive::invoice($seller, $buyer);
            $status = $transport->awaitFinal($transport->send(KsefLive::fa3($original))->documentId);
            self::assertSame(200, $status->code, $status->description);

            $correction = KsefLive::fa3(KsefLive::correction($seller, $buyer, $original->number, $original->issueDate, (string) $status->ksefNumber));
            self::assertStringContainsString('<NrKSeFFaKorygowanej>'.$status->ksefNumber.'</NrKSeFFaKorygowanej>', $correction);
            $corrected = $transport->awaitFinal($transport->send($correction)->documentId);
            $transport->close();
            KsefLive::report(sprintf('correction: status %d, KSeF number %s', $corrected->code, $corrected->ksefNumber ?? '-'));
            self::assertSame(200, $corrected->code, $corrected->description.' '.implode('; ', $corrected->details));
        });
    }

    /**
     * The first key list the registry reads carries a wrong publicKeyId for the symmetric key:
     * KSeF answers 21470, the registry fetches the list again and the session opens.
     *
     * Observed: the refusal is HTTP 400 Problem Details with errors[0].code 21470, the same
     * shape as the offline fixture.
     */
    public function test_stale_public_key_id_recovers(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $corrupted = 0;
        $tap = new KsefTap(static function (RequestInterface $request, ResponseInterface $response) use (&$corrupted): ResponseInterface {
            if ($corrupted > 0 || $request->getMethod() !== 'GET' || ! str_ends_with($request->getUri()->getPath(), '/security/public-key-certificates')) {
                return $response;
            }
            $keys = json_decode((string) $response->getBody(), true);
            if (! is_array($keys)) {
                return $response;
            }
            foreach ($keys as $index => $key) {
                if (is_array($key) && in_array('SymmetricKeyEncryption', (array) ($key['usage'] ?? []), true)) {
                    $key['publicKeyId'] = base64_encode(hash('sha256', 'stale key '.$index, true));
                    $keys[$index] = $key;
                    $corrupted++;
                }
            }

            return new Response($response->getStatusCode(), ['Content-Type' => 'application/json'], json_encode($keys, JSON_THROW_ON_ERROR));
        });
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $tap, $transport, &$corrupted): void {
            $sent = $transport->send(KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip())));
            self::assertGreaterThan(0, $corrupted);
            $fetches = $tap->all('GET', '#^/security/public-key-certificates$#');
            $opens = $tap->all('POST', '#^/sessions/online$#');
            $refusal = json_decode($opens[0]['body'] ?? '', true);
            KsefLive::report(sprintf(
                'stale key id: %d key list fetches, session opens answered %s, first refusal code %s: %s',
                count($fetches),
                implode(', ', array_map(static fn (array $answer): string => (string) $answer['status'], $opens)),
                is_array($refusal) ? (KsefErrors::code($refusal) ?? '-') : '-',
                substr($opens[0]['body'] ?? '', 0, 400),
            ));
            self::assertCount(2, $fetches);
            self::assertSame([400, 201], array_map(static fn (array $answer): int => $answer['status'], $opens));
            self::assertIsArray($refusal);
            self::assertSame(21470, KsefErrors::code($refusal));
            $status = $transport->awaitFinal($sent->documentId);
            $transport->close();
            self::assertSame(200, $status->code, $status->description);
        });
    }

    /**
     * A low invoiceMetadata limit set through /testdata/rate-limits on a fresh context makes the
     * second metadata query fail with RateLimited (HTTP 429); the limits are reset afterwards.
     *
     * Observed: HTTP 429 at the second query, Retry-After 3600, Problem Details with detail
     * "Przekroczono limit 1 żądania na godzinę..." and no errors list (the offline fixture shape).
     */
    public function test_low_rate_limit_yields_rate_limited(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip));
        $http = KsefLive::http($tap);
        $testData = new KsefTestData($config, $http);
        $transport = KsefTransport::create($config, $http);

        $this->live($config, function () use ($testData, $transport, $tap): void {
            $testData->setRateLimits(['invoiceMetadata' => ['perSecond' => 1, 'perMinute' => 1, 'perHour' => 1]]);
            try {
                $limited = null;
                for ($attempt = 1; $attempt <= 3 && $limited === null; $attempt++) {
                    try {
                        $transport->pending();
                    } catch (RateLimited $exception) {
                        $limited = $exception;
                    }
                }
                self::assertInstanceOf(RateLimited::class, $limited);
                $answer = $tap->last('POST', '#^/invoices/query/metadata$#');
                KsefLive::report(sprintf('rate limit: HTTP %d after %d queries, Retry-After %s s, body %s', $answer['status'] ?? 0, $attempt - 1, var_export($limited->retryAfterSeconds, true), substr($answer['body'] ?? '', 0, 300)));
            } finally {
                $testData->resetRateLimits();
            }
        });
    }

    /**
     * Enrols an Offline certificate with an EC P-256 key and one with an RSA-2048 key for a fresh
     * seal and checks what KSeF issued; both are revoked at the end.
     *
     * Observed on 2026-11-22: issued in 3-4 s each by "TEST CCK KSeF", CN with the suffix
     * " (offline)", key usage Non Repudiation, valid two years; both listed Active by the query.
     */
    public function test_offline_certificate_enrolment_ec_and_rsa(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip, 'Testowa Pieczęć '.substr($nip, -4)));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($transport, $tap): void {
            $certificates = $transport->certificates();
            $limits = $certificates->limits();
            KsefLive::report(sprintf('certificate limits: canRequest %s, enrolments %d/%d, certificates %d/%d', var_export($limits->canRequest, true), $limits->enrollmentRemaining, $limits->enrollmentLimit, $limits->certificateRemaining, $limits->certificateLimit));
            self::assertTrue($limits->canRequest);

            $issued = [];
            foreach ([KeyAlgorithm::Ec, KeyAlgorithm::Rsa] as $algorithm) {
                $started = microtime(true);
                $enrolled = $this->enrol($certificates, KsefCertificateType::Offline, $algorithm);
                $certificate = $enrolled->certificate;
                $fields = self::certificateFields($certificate->certificatePem);
                KsefLive::report(sprintf(
                    'enrolled Offline %s in %.1f s: serial %s, CN "%s", issuer CN "%s", key usage %s, valid %s to %s',
                    $algorithm->name,
                    microtime(true) - $started,
                    $certificate->serialNumber,
                    $fields['subject'],
                    $fields['issuer'],
                    $fields['keyUsage'],
                    $certificate->validFrom->format(DATE_ATOM),
                    $certificate->validTo->format(DATE_ATOM),
                ));
                self::assertSame(KsefCertificateType::Offline, $certificate->type);
                self::assertMatchesRegularExpression(KsefCertificate::SERIAL_PATTERN, $certificate->serialNumber);
                self::assertStringEndsWith(' (offline)', $fields['subject']);
                self::assertSame('TEST CCK KSeF', $fields['issuer']);
                self::assertStringContainsString('Non Repudiation', $fields['keyUsage']);
                self::assertSame($algorithm === KeyAlgorithm::Ec ? OPENSSL_KEYTYPE_EC : OPENSSL_KEYTYPE_RSA, $fields['keyType']);
                self::assertTrue(openssl_x509_check_private_key($certificate->certificatePem, $enrolled->credentials()->privateKey()));
                $issued[] = $certificate->serialNumber;
            }

            $listed = [];
            foreach ($certificates->query(KsefCertificateType::Offline) as $certificate) {
                $listed[$certificate->serialNumber] = $certificate->status;
            }
            KsefLive::report('certificate query (Offline): '.json_encode($listed));
            foreach ($issued as $serial) {
                self::assertSame('Active', $listed[$serial] ?? null);
            }
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * KOD II signed with an Offline certificate verifies on the TE QR page, for an EC P-256 key
     * (through KsefQrCodes::offline()) and an RSA-2048 key (through
     * CertificateVerificationLink::build()); the same link with a tampered signature does not.
     *
     * Observed on 2026-11-22: the page answers HTTP 200 text/html in every case;
     * "Weryfikacja prawidłowa" for the signed links, "Weryfikacja nieprawidłowa" / "Podpis
     * wystawcy nie jest prawidłowy" for the tampered ones.
     */
    public function test_kod2_ec_and_rsa_verify_on_qr_test(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $transport, $tap): void {
            $certificates = $transport->certificates();
            $ec = $this->enrol($certificates, KsefCertificateType::Offline, KeyAlgorithm::Ec);
            $rsa = $this->enrol($certificates, KsefCertificateType::Offline, KeyAlgorithm::Rsa);
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $context = ContextIdentifier::nip($seller);

            [$kod1, $kod2] = KsefQrCodes::offline(KsefEnvironment::Test, $context, $xml, $ec->credentials());
            self::assertSame(KsefQrCodes::OFFLINE_LABEL, $kod1->label);
            self::assertSame(KsefQrCodes::CERTIFICATE_LABEL, $kod2->label);
            self::assertStringStartsWith(sprintf('%s/certificate/Nip/%s/%s/%s/', KsefLive::QR_BASE_URL, $seller, $seller, $ec->certificate->serialNumber), $kod2->url);
            $rsaLink = CertificateVerificationLink::build(KsefEnvironment::Test, $context, $seller, $xml, $rsa->credentials());

            foreach (['EC' => $kod2->url, 'RSA' => $rsaLink] as $kind => $link) {
                $signature = substr($link, (int) strrpos($link, '/') + 1);
                $attempts = 0;
                $text = self::awaitQrVerdict($tap, $link, KsefLive::KOD2_VALID, $attempts);
                KsefLive::report(sprintf('KOD II %s (signature %d characters): %s after %d page read(s)', $kind, strlen($signature), self::verdict($text), $attempts));
                self::assertStringContainsString(KsefLive::KOD2_VALID, $text);
                self::assertStringNotContainsString(KsefLive::KOD2_INVALID, $text);

                $tampered = KsefLive::qrPage($tap, self::tampered($link));
                KsefLive::report(sprintf('KOD II %s, tampered signature: %s', $kind, self::verdict($tampered)));
                self::assertStringContainsString(KsefLive::KOD2_INVALID, $tampered);
                self::assertStringContainsString(self::SIGNATURE_INVALID, $tampered);
                self::assertStringNotContainsString(KsefLive::KOD2_VALID, $tampered);
            }
            self::assertSame(86, strlen(substr($kod2->url, (int) strrpos($kod2->url, '/') + 1)));
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * A KOD II link that verifies stops verifying once its Offline certificate is revoked.
     *
     * Observed on 2026-11-22: right after the revocation the page reports
     * "Weryfikacja nieprawidłowa" with "Certyfikat został unieważniony".
     */
    public function test_kod2_fails_after_revocation(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $transport, $tap): void {
            $ec = $this->enrol($transport->certificates(), KsefCertificateType::Offline, KeyAlgorithm::Ec);
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $link = KsefQrCodes::offline(KsefEnvironment::Test, ContextIdentifier::nip($seller), $xml, $ec->credentials())[1]->url;

            $attempts = 0;
            $before = self::awaitQrVerdict($tap, $link, KsefLive::KOD2_VALID, $attempts);
            KsefLive::report(sprintf('KOD II before revocation: %s after %d page read(s)', self::verdict($before), $attempts));
            self::assertStringContainsString(KsefLive::KOD2_VALID, $before);

            $this->revoke($ec->certificate->serialNumber);
            $attempts = 0;
            $after = self::awaitQrVerdict($tap, $link, KsefLive::KOD2_INVALID, $attempts);
            KsefLive::report(sprintf('KOD II after revocation: %s after %d page read(s); revoked phrase %s', self::verdict($after), $attempts, str_contains($after, KsefLive::KOD2_REVOKED) ? 'present' : 'absent'));
            self::assertStringContainsString(KsefLive::KOD2_INVALID, $after);
            self::assertStringContainsString(KsefLive::KOD2_REVOKED, $after);
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * An invoice sent with the offline flag is accepted and reported Offline; its KOD I page says
     * "not found" before the send and "found" (mode Offline) after it.
     *
     * Observed on 2026-11-22: status 200 with invoicingMode Offline; the KOD I page lists the
     * KSeF number and "Tryb wystawienia faktury Offline" seconds after acceptance.
     */
    public function test_offline_send_reports_offline_mode_and_kod1_found(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $transport, $tap): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $unsent = KsefQrCodes::online(KsefEnvironment::Test, $xml, null);
            self::assertSame(KsefQrCodes::OFFLINE_LABEL, $unsent->label);
            $before = KsefLive::qrPage($tap, $unsent->url);
            KsefLive::report('KOD I before the send: '.self::verdict($before));
            self::assertStringContainsString(KsefLive::KOD1_NOT_FOUND, $before);

            try {
                $sent = $transport->send($xml, offline: true);
                $status = $transport->awaitFinal($sent->documentId);
            } finally {
                $transport->close();
            }
            KsefLive::report(sprintf('offline send: status %d, KSeF number %s, invoicingMode %s', $status->code, $status->ksefNumber ?? '-', $status->invoicingMode->value ?? '-'));
            self::assertSame(200, $status->code, $status->description);
            self::assertSame(InvoicingMode::Offline, $status->invoicingMode);
            $ksefNumber = (string) $status->ksefNumber;

            $sentCode = KsefQrCodes::online(KsefEnvironment::Test, $xml, $ksefNumber);
            self::assertSame($ksefNumber, $sentCode->label);
            self::assertSame($unsent->url, $sentCode->url);
            $attempts = 0;
            $after = self::awaitQrVerdict($tap, $sentCode->url, KsefLive::KOD1_FOUND, $attempts);
            KsefLive::report(sprintf('KOD I after the send: %s after %d page read(s)', self::verdict($after), $attempts));
            self::assertStringContainsString(KsefLive::KOD1_FOUND, $after);
            self::assertStringNotContainsString(KsefLive::KOD1_NOT_FOUND, $after);
            self::assertStringContainsString($ksefNumber, $after);
            self::assertStringContainsString('Tryb wystawienia faktury Offline', $after);
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * An offline invoice without its mandatory P_2 is rejected with 450; its technical correction
     * is accepted, reported Offline, and listed in the metadata with hashOfCorrectedInvoice, the
     * SHA-256 of the rejected file.
     *
     * Observed on 2026-11-22: 450 "Błąd weryfikacji semantyki dokumentu faktury" with the XSD
     * message naming P_2; the correction 200 with invoicingMode Offline.
     */
    public function test_technical_correction_of_rejected_offline_invoice(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));
        [$api, , $keys] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $config, $transport, $tap, $api, $keys): void {
            [$fixed, $broken] = self::rejectedOfflineInvoice($seller, new OnlineSession($api, $keys, $config));

            try {
                $correction = $transport->sendTechnicalCorrection($fixed, $broken);
                $status = $transport->awaitFinal($correction->documentId);
            } finally {
                $transport->close();
            }
            KsefLive::report(sprintf('technical correction: status %d, KSeF number %s, invoicingMode %s', $status->code, $status->ksefNumber ?? '-', $status->invoicingMode->value ?? '-'));
            self::assertSame(200, $status->code, $status->description.' '.implode('; ', $status->details));
            self::assertSame(InvoicingMode::Offline, $status->invoicingMode);
            $ksefNumber = (string) $status->ksefNumber;

            $query = new MetadataQuery($api);
            $filters = new InvoiceQueryFilters(InvoiceQueryFilters::SUBJECT_SELLER, InvoiceQueryFilters::DATE_INVOICING, new DateTimeImmutable('-1 hour'), new DateTimeImmutable('+5 minutes'));
            $metadata = KsefLive::poll(static function () use ($query, $filters, $ksefNumber): ?InvoiceMetadata {
                foreach ($query->page($filters, 0, 10)['invoices'] as $entry) {
                    if ($entry->ksefNumber === $ksefNumber) {
                        return $entry;
                    }
                }

                return null;
            }, self::METADATA_ATTEMPTS, self::METADATA_WAIT_SECONDS);
            $spent = self::spendMetadataQueries($tap);
            KsefLive::report(sprintf('technical correction metadata: %s after %d metadata quer(ies); invoicingMode %s, hashOfCorrectedInvoice %s', $metadata === null ? 'not listed' : 'listed', $spent, $metadata->invoicingMode->value ?? '-', $metadata->hashOfCorrectedInvoice ?? '-'));
            self::assertNotNull($metadata);
            self::assertSame(base64_encode(hash('sha256', $broken, true)), $metadata->hashOfCorrectedInvoice);
            self::assertSame(InvoicingMode::Offline, $metadata->invoicingMode);
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * KSeF accepts one technical correction per rejected invoice: a second one, with different
     * bytes, is refused with HTTP 400, code 21166, thrown as DocumentRejected.
     *
     * Observed on 2026-11-22: "21166: Korekta techniczna faktury jest niedostępna. — Faktura
     * posiada już przetworzoną prawidłowo korektę techniczną".
     */
    public function test_second_technical_correction_is_refused_21166(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));
        [$api, , $keys] = KsefLive::api($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $config, $transport, $tap, $api, $keys): void {
            [$fixed, $broken] = self::rejectedOfflineInvoice($seller, new OnlineSession($api, $keys, $config));

            $first = $transport->awaitFinal($transport->sendTechnicalCorrection($fixed, $broken)->documentId);
            self::assertSame(200, $first->code, $first->description.' '.implode('; ', $first->details));

            $again = str_replace('<P_1M>Testowo</P_1M>', '<P_1M>Testowo Dolne</P_1M>', $fixed);
            self::assertNotSame($fixed, $again);
            $refused = null;
            try {
                $transport->sendTechnicalCorrection($again, $broken);
            } catch (TransportException $exception) {
                $refused = $exception;
            } finally {
                $transport->close();
            }
            self::assertNotNull($refused, 'A second technical correction of the same invoice must be refused.');
            $answer = $tap->last('POST', '#^/sessions/online/[^/]+/invoices$#');
            KsefLive::report(sprintf('second technical correction: %s, HTTP %d, codes %s: %s', $refused::class, $answer['status'] ?? 0, implode(', ', KsefErrors::codesOf($refused)), $refused->getMessage()));
            self::assertInstanceOf(DocumentRejected::class, $refused);
            self::assertTrue(KsefErrors::hasCode($refused, KsefErrors::TECHNICAL_CORRECTION_UNAVAILABLE));
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * A KSeF certificate of type Authentication with an EC P-256 key, enrolled after seal
     * authentication, authenticates in the same context through the XAdES signer's ECDSA path.
     *
     * Observed on 2026-11-22: the certificate's CN carries the suffix " (uwierzytelnienie)" and
     * the key usage Digital Signature; GET /auth/sessions reports the method
     * xades.ksef-certificate.
     */
    public function test_authentication_certificate_logs_in(): void
    {
        $nip = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($nip, SelfSignedCertificate::seal($nip, 'Testowa Pieczęć '.substr($nip, -4)));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($nip, $transport, $tap): void {
            $enrolled = $this->enrol($transport->certificates(), KsefCertificateType::Authentication, KeyAlgorithm::Ec);
            $fields = self::certificateFields($enrolled->certificate->certificatePem);
            KsefLive::report(sprintf('enrolled Authentication EC: serial %s, CN "%s", key usage %s', $enrolled->certificate->serialNumber, $fields['subject'], $fields['keyUsage']));
            self::assertSame(KsefCertificateType::Authentication, $enrolled->certificate->type);

            $ksefTap = new KsefTap;
            $ksefConfig = KsefLive::config($nip, $enrolled->credentials());
            [$api, $authenticator] = KsefLive::api($ksefConfig, KsefLive::http($ksefTap));
            $tokens = $authenticator->authenticate();
            array_push($this->secrets, $tokens->accessToken(), $tokens->refreshToken());
            self::assertGreaterThan(new DateTimeImmutable, $tokens->accessValidUntil);
            $method = self::authenticationMethod($api);
            KsefLive::report('KSeF Authentication certificate (EC): authentication method '.$method);
            self::assertSame('xades.ksef-certificate', $method);
            self::reportCalls($this->name(), $tap, $ksefTap);
        });
    }

    /**
     * An invoice sent without the offline flag whose issue date (P_1) is yesterday is classified
     * Offline by KSeF.
     *
     * Observed on 2026-11-22: status 200 with invoicingMode Offline.
     */
    public function test_online_send_dated_yesterday_is_classified_offline(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $tap = new KsefTap;
        $config = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $transport = KsefTransport::create($config, KsefLive::http($tap));

        $this->live($config, function () use ($seller, $transport, $tap): void {
            $xml = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
            $today = KsefLive::today();
            $dated = str_replace('<P_1>'.$today->format('Y-m-d').'</P_1>', '<P_1>'.$today->modify('-1 day')->format('Y-m-d').'</P_1>', $xml);
            self::assertNotSame($xml, $dated);

            try {
                $status = $transport->awaitFinal($transport->send($dated)->documentId);
            } finally {
                $transport->close();
            }
            KsefLive::report(sprintf('online send dated yesterday: status %d, invoicingMode %s', $status->code, $status->invoicingMode->value ?? '-'));
            self::assertSame(200, $status->code, $status->description);
            self::assertSame(InvoicingMode::Offline, $status->invoicingMode);
            self::reportCalls($this->name(), $tap);
        });
    }

    /**
     * Latarnia TE answers /status and /messages in the documented shape. TE serves stale sample
     * events, so only the shape is asserted.
     *
     * Observed on 2026-11-22: status TOTAL_FAILURE with 2 messages; /messages lists 3 (a total
     * failure and a failure start, a maintenance announcement), the January 2026 samples.
     */
    public function test_latarnia_te_status_and_messages_parse(): void
    {
        $tap = new KsefTap;
        $latarnia = Latarnia::forEnvironment(KsefEnvironment::Test, KsefLive::http($tap));

        $status = $latarnia->status();
        $messages = $latarnia->messages();
        $outages = OutageEvents::fold($messages);
        KsefLive::report(sprintf(
            'Latarnia TE: status %s with %d message(s); /messages %d message(s) (%s); %d outage(s) folded',
            $status->availability->value,
            count($status->messages),
            count($messages),
            implode(', ', array_map(static fn (LatarniaMessage $message): string => $message->category->name.'/'.$message->type->value, $messages)),
            count($outages),
        ));
        // The parse itself is the shape check: an unknown status, category or type, a missing
        // field or an end message without an end time throws.
        foreach ([...$status->messages, ...$messages] as $message) {
            self::assertNotSame('', $message->id);
            self::assertNotSame('', $message->title);
            self::assertTrue($message->end === null || $message->end >= $message->start, 'A Latarnia event ends after it starts.');
        }
        self::assertSame(2, $tap->countFor(KsefLive::LATARNIA_HOST));
        self::assertSame(0, $tap->count());
        self::reportCalls($this->name(), $tap);
    }

    /**
     * Runs a live body with every failure message redacted of the configuration's credentials
     * and of the secrets the body added to $secrets on the way (generated tokens, the private
     * keys of enrolled certificates). Every certificate the body enrolled through enrol() and
     * did not revoke is revoked afterwards, whether the body failed or not; a failed revocation
     * fails a body that passed.
     *
     * @param  \Closure(): void  $body
     */
    private function live(KsefConfig $config, \Closure $body): void
    {
        $this->secrets = [];
        $this->enrolled = [];
        $failure = null;
        try {
            $body();
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        $revocationFailures = $this->revokeEnrolled();
        try {
            $redactor = KsefLive::redactor($config, ...$this->secrets);
            foreach ($revocationFailures as $line) {
                KsefLive::report($line, $redactor);
            }
            if ($failure === null && $revocationFailures !== []) {
                $failure = new \RuntimeException(implode("\n", $revocationFailures));
            }
            if ($failure !== null) {
                LiveTransport::failRedacted($redactor, static function () use ($failure): never {
                    throw $failure;
                });
            }
        } finally {
            $this->secrets = [];
        }
    }

    /**
     * The authentication method code of the current authentication session, read from
     * GET /auth/sessions.
     */
    private static function authenticationMethod(KsefApi $api): string
    {
        $answer = $api->authorized(fn (string $bearer): array => $api->json('GET', '/auth/sessions', null, $bearer));
        $items = is_array($answer['items'] ?? null) ? $answer['items'] : [];
        foreach ($items as $item) {
            if (is_array($item) && ($item['isCurrent'] ?? false) === true) {
                $info = $item['authenticationMethodInfo'] ?? null;

                if (is_array($info) && is_string($info['code'] ?? null)) {
                    return $info['code'];
                }
                $method = $item['authenticationMethod'] ?? '';

                return is_string($method) ? $method : '';
            }
        }

        return '';
    }

    /**
     * The token's status through KsefTokens::status() (rev2) and the legacy GET /tokens/{ref},
     * read until the status is one of the wanted ones, at most 15 times every 2 s.
     *
     * @param  list<string>  $wanted
     * @return array{legacy: string, rev2: string}
     */
    private static function awaitTokenStatus(KsefTransport $transport, KsefApi $api, string $reference, array $wanted): array
    {
        $last = ['legacy' => '', 'rev2' => ''];
        KsefLive::poll(function () use ($transport, $api, $reference, $wanted, &$last): ?bool {
            $legacy = $api->authorized(fn (string $bearer): array => $api->json('GET', '/tokens/'.$reference, null, $bearer));
            $last = ['legacy' => is_string($legacy['status'] ?? null) ? $legacy['status'] : '', 'rev2' => $transport->tokens()->status($reference)];

            return in_array($last['rev2'], $wanted, true) ? true : null;
        }, 15, 2);

        return $last;
    }

    /**
     * The UPO of a sent invoice, read at most 10 times every 3 s after close.
     */
    private static function awaitUpo(KsefTransport $transport, string $documentId): Upo
    {
        $failure = null;
        $upo = KsefLive::poll(function () use ($transport, $documentId, &$failure) {
            try {
                return $transport->upo($documentId);
            } catch (TransportException $exception) {
                $failure = $exception;

                return null;
            }
        }, 10, 3);
        if ($upo === null) {
            throw $failure ?? new \RuntimeException('No UPO.');
        }

        return $upo;
    }

    /**
     * Enrols a certificate for the running test, remembers it for revocation at the end of live()
     * and keeps its private key out of every failure message.
     */
    private function enrol(KsefCertificates $certificates, KsefCertificateType $type, KeyAlgorithm $algorithm): EnrolledCertificate
    {
        $name = sprintf('Live %s %s %s', $type->value, $algorithm->name, strtoupper(bin2hex(random_bytes(3))));
        $enrolled = $certificates->enroll($type, $algorithm, $name);
        $this->enrolled[$enrolled->certificate->serialNumber] = $certificates;
        $this->secrets[] = $enrolled->privateKeyPem();

        return $enrolled;
    }

    /**
     * Revokes a certificate the running test enrolled.
     */
    private function revoke(string $serial): void
    {
        $certificates = $this->enrolled[$serial] ?? throw new \LogicException('The certificate '.$serial.' was not enrolled by this test.');
        $certificates->revoke($serial);
        unset($this->enrolled[$serial]);
    }

    /**
     * Revokes every certificate the running test enrolled and has not revoked yet; returns one
     * line for each revocation that failed.
     *
     * @return list<string>
     */
    private function revokeEnrolled(): array
    {
        $failures = [];
        foreach (array_keys($this->enrolled) as $serial) {
            try {
                $this->revoke($serial);
                KsefLive::report('revoked certificate '.$serial);
            } catch (Throwable $exception) {
                $failures[] = sprintf('revoking certificate %s failed: %s: %s', $serial, $exception::class, $exception->getMessage());
            }
        }
        $this->enrolled = [];

        return $failures;
    }

    /**
     * Metadata queries the tap has seen, added to the run's count, which must stay within
     * RUN_METADATA_BUDGET; returns the tap's count.
     */
    private static function spendMetadataQueries(KsefTap $tap): int
    {
        $spent = count($tap->all('POST', '#^/invoices/query/metadata$#'));
        self::$metadataQueries += $spent;
        KsefLive::report(sprintf('metadata queries: %d in this test, %d in the run so far', $spent, self::$metadataQueries));
        self::assertLessThanOrEqual(self::RUN_METADATA_BUDGET, self::$metadataQueries);

        return $spent;
    }

    /**
     * Writes the number of requests the taps sent to each TE host.
     */
    private static function reportCalls(string $test, KsefTap ...$taps): void
    {
        $count = static fn (string $host): int => array_sum(array_map(static fn (KsefTap $tap): int => $tap->countFor($host), $taps));
        KsefLive::report(sprintf('%s: requests to the TE API %d, QR pages %d, Latarnia %d', $test, $count(KsefLive::HOST), $count(KsefLive::QR_HOST), $count(KsefLive::LATARNIA_HOST)));
    }

    /**
     * An FA(3) file and the same file without its mandatory P_2, the latter sent in offline mode
     * through the session and rejected with 450; returns both files.
     *
     * @return array{string, string} the corrected file, then the rejected one
     */
    private static function rejectedOfflineInvoice(string $seller, OnlineSession $session): array
    {
        $fixed = KsefLive::fa3(KsefLive::invoice($seller, SelfSignedCertificate::randomNip()));
        $broken = preg_replace('#<P_2>[^<]*</P_2>#', '', $fixed, 1);
        self::assertIsString($broken);
        self::assertNotSame($fixed, $broken);

        $sent = $session->send($broken, offlineMode: true);
        try {
            $rejected = $session->awaitFinal($sent->sessionReference, $sent->invoiceReference);
        } finally {
            $session->close();
        }
        KsefLive::report(sprintf('offline invoice without P_2: status %d %s — %s', $rejected->code, $rejected->description, implode('; ', $rejected->details)));
        self::assertSame(450, $rejected->code);
        self::assertStringContainsString('P_2', implode(' ', $rejected->details));

        return [$fixed, $broken];
    }

    /**
     * The text of the QR page, read until it contains the phrase, at most QR_ATTEMPTS times
     * QR_WAIT_SECONDS apart; the last text is returned either way.
     */
    private static function awaitQrVerdict(KsefTap $tap, string $url, string $phrase, int &$attempts): string
    {
        $text = '';
        KsefLive::poll(function () use ($tap, $url, $phrase, &$text, &$attempts): ?bool {
            $attempts++;
            $text = KsefLive::qrPage($tap, $url);

            return str_contains($text, $phrase) ? true : null;
        }, self::QR_ATTEMPTS, self::QR_WAIT_SECONDS);

        return $text;
    }

    /**
     * The verdict phrases found in a QR page text, for the report.
     */
    private static function verdict(string $text): string
    {
        $found = array_filter(
            [KsefLive::KOD2_VALID, KsefLive::KOD2_INVALID, KsefLive::KOD2_REVOKED, self::SIGNATURE_INVALID, KsefLive::KOD1_FOUND, KsefLive::KOD1_NOT_FOUND],
            static fn (string $phrase): bool => str_contains($text, $phrase),
        );

        return $found === [] ? 'no known verdict' : '"'.implode('", "', $found).'"';
    }

    /**
     * The link with its signature segment's bytes reversed: well-formed, but not a valid signature.
     */
    private static function tampered(string $link): string
    {
        $cut = (int) strrpos($link, '/');
        $signature = base64_decode(strtr(substr($link, $cut + 1), '-_', '+/'), true);
        self::assertIsString($signature);

        return substr($link, 0, $cut + 1).rtrim(strtr(base64_encode(strrev($signature)), '+/', '-_'), '=');
    }

    /**
     * The subject and issuer common names, the key usage and the key type of a certificate.
     *
     * @return array{subject: string, issuer: string, keyUsage: string, keyType: int}
     */
    private static function certificateFields(string $pem): array
    {
        $details = openssl_x509_parse($pem);
        self::assertIsArray($details);
        $publicKey = openssl_pkey_get_public($pem);
        self::assertNotFalse($publicKey);
        $key = openssl_pkey_get_details($publicKey);
        self::assertIsArray($key);
        $name = static function (mixed $dn): string {
            $cn = is_array($dn) ? ($dn['CN'] ?? '') : '';

            return is_string($cn) ? $cn : '';
        };
        $extensions = is_array($details['extensions'] ?? null) ? $details['extensions'] : [];
        $keyUsage = $extensions['keyUsage'] ?? '';
        $keyType = $key['type'] ?? null;

        return [
            'subject' => $name($details['subject'] ?? null),
            'issuer' => $name($details['issuer'] ?? null),
            'keyUsage' => is_string($keyUsage) ? $keyUsage : '',
            'keyType' => is_int($keyType) ? $keyType : -1,
        ];
    }
}

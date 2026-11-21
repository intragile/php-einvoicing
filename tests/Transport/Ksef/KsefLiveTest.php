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
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenPermission;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\MetadataQuery;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use IntrAgile\EInvoicing\Transport\Ksef\KsefTransport;
use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchPackage;
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
    /** Status reads of a buyer inbox before giving up. */
    private const INBOX_ATTEMPTS = 40;

    private const INBOX_WAIT_SECONDS = 6;

    /** @var list<string> secrets a running test learned, kept out of its failure message */
    private array $secrets = [];

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
     * Observed: POST /auth/token/refresh returns a new access token valid another 15 minutes.
     * After DELETE /auth/sessions/current the refresh is refused with HTTP 400, code 21301
     * ("Status uwierzytelnienia (425) nie pozwala na odświeżenie tokenu dostępowego"), which maps
     * to the AuthenticationFailed that Authenticator::renew() answers with a full authentication.
     */
    public function test_refresh_returns_new_access_token(): void
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
            self::assertNotSame(hash('sha256', $tokens->accessToken()), hash('sha256', $refreshed->accessToken()));
            self::assertGreaterThan(new DateTimeImmutable, $refreshed->accessValidUntil);
            KsefLive::report('refresh: new access token valid until '.$refreshed->accessValidUntil->format(DATE_ATOM));

            // The refreshed token works.
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
     * number of the first (status 440).
     *
     * Observed: status 440 "Duplikat faktury" with extensions.originalKsefNumber and
     * extensions.originalSessionReferenceNumber.
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
            $transport->close();
        });
    }

    /**
     * The buyer authenticates with its own seal, finds the invoice in pending() within bounded
     * polling and fetches the same bytes. The inbox starts 100 days back, so the first query is a
     * bounded 90-day chunk.
     *
     * Observed: the chunked queries are answered 200; the invoice was listed about two minutes
     * after acceptance (19 pending() calls 6 s apart), as the PermanentStorage high water mark
     * lags behind.
     */
    public function test_buyer_sees_invoice_in_pending_and_fetches_it(): void
    {
        $seller = SelfSignedCertificate::randomNip();
        $buyer = SelfSignedCertificate::randomNip();
        $sellerConfig = KsefLive::config($seller, SelfSignedCertificate::seal($seller));
        $buyerConfig = KsefLive::config($buyer, SelfSignedCertificate::seal($buyer));
        $sellerTransport = KsefTransport::create($sellerConfig, KsefLive::http(new KsefTap));
        $buyerTap = new KsefTap;
        $buyerTransport = KsefTransport::create($buyerConfig, KsefLive::http($buyerTap), inboxStart: new DateTimeImmutable('-100 days'));

        $this->live($buyerConfig, function () use ($seller, $buyer, $sellerTransport, $buyerTransport, $buyerTap): void {
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
                    if ($reference->documentId === $ksefNumber) {
                        return $reference->documentId;
                    }
                }

                return null;
            }, self::INBOX_ATTEMPTS, self::INBOX_WAIT_SECONDS);
            KsefLive::report(sprintf('buyer inbox: %s after %d pending() calls, %.0f s', $found === null ? 'not listed' : 'listed', $queries, microtime(true) - $started));
            $queries = $buyerTap->all('POST', '#^/invoices/query/metadata$#');
            KsefLive::report(sprintf('buyer inbox: metadata queries answered %s', implode(', ', array_unique(array_map(static fn (array $answer): string => (string) $answer['status'], $queries)))));
            self::assertSame($ksefNumber, $found);

            $document = $buyerTransport->fetch($ksefNumber);
            self::assertSame($xml, $document->xml);
            $buyerTransport->acknowledge($ksefNumber);
            self::assertSame([], array_filter($buyerTransport->pending(), static fn ($r): bool => $r->documentId === $ksefNumber));
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
     * Runs a live body with every failure message redacted of the configuration's credentials
     * and of the secrets the body added to $secrets on the way (generated tokens).
     *
     * @param  \Closure(): void  $body
     */
    private function live(KsefConfig $config, \Closure $body): void
    {
        $this->secrets = [];
        try {
            $body();
        } catch (Throwable $failure) {
            LiveTransport::failRedacted(KsefLive::redactor($config, ...$this->secrets), static function () use ($failure): never {
                throw $failure;
            });
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
}

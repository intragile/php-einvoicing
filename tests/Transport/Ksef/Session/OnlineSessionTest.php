<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use IntrAgile\EInvoicing\Transport\Ksef\Session\FormCode;
use IntrAgile\EInvoicing\Transport\Ksef\Session\OnlineSession;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;

final class OnlineSessionTest extends TestCase
{
    use SessionFixtures;

    public function test_form_code_of_fa3(): void
    {
        self::assertSame(['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'], FormCode::fa3()->json());
    }

    public function test_open_sends_fa3_form_code_and_encrypted_key(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);
        $session = $this->session($recorder);

        self::assertNull($session->referenceNumber());
        $session->send(self::INVOICE_XML);

        self::assertSame(self::SESSION_REF, $session->referenceNumber());
        $open = self::requestsTo($recorder, 'POST', '/sessions/online');
        self::assertCount(1, $open);
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $open[0]->getHeaderLine('Authorization'));
        $body = self::body($open[0]);
        self::assertSame(['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'], $body['formCode']);
        $key = self::sessionKey($open[0]);
        self::assertSame(32, strlen($key->key()));
        // The session holds the key without exposing it to var_export() or an array cast.
        foreach ([var_export($session, true), var_export((array) $session, true)] as $dump) {
            self::assertStringNotContainsString(var_export($key->key(), true), $dump);
        }
        self::assertSame(16, strlen($key->iv));
        self::assertIsArray($body['encryption']);
        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), $body['encryption']['publicKeyId']);
    }

    public function test_send_body_hashes_and_sizes(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $sent = $this->session($recorder)->send(self::INVOICE_XML);

        $send = self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices');
        self::assertCount(1, $send);
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $send[0]->getHeaderLine('Authorization'));
        $body = self::body($send[0]);
        self::assertIsString($body['encryptedInvoiceContent']);
        $ciphertext = base64_decode($body['encryptedInvoiceContent'], true);
        self::assertIsString($ciphertext);
        self::assertSame(base64_encode(hash('sha256', self::INVOICE_XML, true)), $body['invoiceHash']);
        self::assertSame(strlen(self::INVOICE_XML), $body['invoiceSize']);
        self::assertSame(base64_encode(hash('sha256', $ciphertext, true)), $body['encryptedInvoiceHash']);
        self::assertSame(strlen($ciphertext), $body['encryptedInvoiceSize']);
        self::assertFalse($body['offlineMode']);
        self::assertArrayNotHasKey('hashOfCorrectedInvoice', $body);

        $key = self::sessionKey(self::requestsTo($recorder, 'POST', '/sessions/online')[0]);
        self::assertSame(self::INVOICE_XML, InvoiceCipher::decrypt($ciphertext, $key));

        self::assertSame(self::SESSION_REF, $sent->sessionReference);
        self::assertSame(self::INVOICE_REF, $sent->invoiceReference);
        self::assertSame(base64_encode(hash('sha256', self::INVOICE_XML, true)), $sent->invoiceHash);
        self::assertSame(self::SESSION_REF.'/'.self::INVOICE_REF, $sent->documentId());
    }

    public function test_send_passes_offline_mode_and_corrected_hash(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);
        $corrected = base64_encode(hash('sha256', 'the corrected invoice', true));

        $this->session($recorder)->send(self::INVOICE_XML, true, $corrected);

        $body = self::body(self::requestsTo($recorder, 'POST', '/invoices')[0]);
        self::assertTrue($body['offlineMode']);
        self::assertSame($corrected, $body['hashOfCorrectedInvoice']);
    }

    public function test_session_is_reused_then_reopened_after_valid_until(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses('2026-11-21T10:10:00.0000000+00:00'),
            self::sendResponse(),
            self::sendResponse(self::SECOND_INVOICE_REF),
            new Response(204),
            self::openResponse(self::SECOND_SESSION_REF),
            self::sendResponse(),
        ]);
        $session = $this->session($recorder);

        $first = $session->send(self::INVOICE_XML);
        $this->advanceClock('+5 minutes');
        $second = $session->send(self::INVOICE_XML);
        // Less than 60 seconds before validUntil: the session is replaced.
        $this->advanceClock('+4 minutes 1 second');
        $third = $session->send(self::INVOICE_XML);

        self::assertSame(self::SESSION_REF, $first->sessionReference);
        self::assertSame(self::SESSION_REF, $second->sessionReference);
        self::assertSame(self::SECOND_INVOICE_REF, $second->invoiceReference);
        self::assertSame(self::SECOND_SESSION_REF, $third->sessionReference);
        self::assertSame(self::SECOND_SESSION_REF, $session->referenceNumber());
        self::assertSame([
            'GET /security/public-key-certificates',
            'POST /auth/challenge',
            'POST /auth/ksef-token',
            'GET /auth/'.self::AUTH_REFERENCE,
            'POST /auth/token/redeem',
            'POST /sessions/online',
            'POST /sessions/online/'.self::SESSION_REF.'/invoices',
            'POST /sessions/online/'.self::SESSION_REF.'/invoices',
            'POST /sessions/online/'.self::SESSION_REF.'/close',
            'POST /sessions/online',
            'POST /sessions/online/'.self::SECOND_SESSION_REF.'/invoices',
        ], self::paths($recorder));

        // Each session has its own symmetric key.
        $opens = self::requestsTo($recorder, 'POST', '/sessions/online');
        self::assertNotSame(self::sessionKey($opens[0])->key(), self::sessionKey($opens[1])->key());
        $lastSend = self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SECOND_SESSION_REF.'/invoices')[0];
        $content = self::body($lastSend)['encryptedInvoiceContent'];
        self::assertIsString($content);
        self::assertSame(self::INVOICE_XML, InvoiceCipher::decrypt((string) base64_decode($content, true), self::sessionKey($opens[1])));
    }

    public function test_reopens_after_10000_invoices(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::sendResponse(),
            new Response(204),
            self::openResponse(self::SECOND_SESSION_REF),
            self::sendResponse(),
        ]);
        $session = $this->session($recorder);
        $session->send(self::INVOICE_XML);
        self::assertSame(10_000, OnlineSession::MAX_INVOICES_PER_SESSION);

        // The counter after 10,000 sends, without making them.
        (new ReflectionProperty(OnlineSession::class, 'sentInSession'))->setValue($session, OnlineSession::MAX_INVOICES_PER_SESSION);
        $sent = $session->send(self::INVOICE_XML);

        self::assertSame(self::SECOND_SESSION_REF, $sent->sessionReference);
        self::assertCount(1, self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/close'));
        self::assertCount(2, self::requestsTo($recorder, 'POST', '/sessions/online'));
    }

    public function test_send_into_a_session_ksef_closed_forgets_it_and_the_next_send_opens_a_new_one(): void
    {
        // The shape TE answers a send into a session it has already closed with.
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::sendResponse(),
            self::fixture(400, 'session-closed-21180.json'),
            self::openResponse(self::SECOND_SESSION_REF),
            self::sendResponse(self::SECOND_INVOICE_REF),
        ]);
        $session = $this->session($recorder);
        $session->send(self::INVOICE_XML);

        $refusal = self::capture(fn () => $session->send(self::INVOICE_XML));

        self::assertInstanceOf(UnexpectedResponse::class, $refusal);
        self::assertSame(21180, KsefErrors::codeOf($refusal));
        self::assertNull($session->referenceNumber());
        // Not resent automatically: exactly one send went to the closed session.
        self::assertCount(2, self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices'));

        $sent = $session->send(self::INVOICE_XML);

        self::assertSame(self::SECOND_SESSION_REF, $sent->sessionReference);
        self::assertSame(self::SECOND_INVOICE_REF, $sent->invoiceReference);
        // No close request for the session KSeF already closed.
        self::assertSame([], self::requestsTo($recorder, 'POST', '/close'));
        self::assertCount(2, self::requestsTo($recorder, 'POST', '/sessions/online'));
    }

    public function test_session_closed_as_a_later_error_code_also_forgets_the_session(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::sendResponse(),
            self::json(400, ['errors' => [
                ['code' => 21999, 'description' => 'Inny błąd.'],
                ['code' => 21180, 'description' => 'Status sesji nie pozwala na wykonanie operacji.'],
            ]]),
        ]);
        $session = $this->session($recorder);
        $session->send(self::INVOICE_XML);

        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $session->send(self::INVOICE_XML)));
        self::assertNull($session->referenceNumber());
    }

    public function test_other_send_refusals_keep_the_session(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::sendResponse(),
            self::fixture(400, 'unknown-key-id-21470.json'),
            self::sendResponse(self::SECOND_INVOICE_REF),
        ]);
        $session = $this->session($recorder);
        $session->send(self::INVOICE_XML);

        self::capture(fn () => $session->send(self::INVOICE_XML));

        self::assertSame(self::SESSION_REF, $session->referenceNumber());
        self::assertSame(self::SESSION_REF, $session->send(self::INVOICE_XML)->sessionReference);
    }

    public function test_close_without_open_makes_no_request(): void
    {
        $recorder = new RecordingHttpClient;
        $session = $this->session($recorder);

        $session->close();

        self::assertSame([], $recorder->requests());
        self::assertNull($session->referenceNumber());
    }

    public function test_close_closes_the_open_session_once(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse(), new Response(204)]);
        $session = $this->session($recorder);
        $session->send(self::INVOICE_XML);

        $session->close();
        $session->close();

        self::assertNull($session->referenceNumber());
        $close = self::requestsTo($recorder, 'POST', '/close');
        self::assertCount(1, $close);
        self::assertSame('/v2/sessions/online/'.self::SESSION_REF.'/close', $close[0]->getUri()->getPath());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $close[0]->getHeaderLine('Authorization'));
    }

    public function test_open_recovers_from_unknown_key_id(): void
    {
        // The first open is refused with 21470; a fresh key list and a repeated open follow.
        $responses = [...array_slice(self::openingResponses(), 0, 5), self::fixture(400, 'unknown-key-id-21470.json'), self::keysResponse(), self::openResponse(), self::sendResponse()];
        $recorder = new RecordingHttpClient(...$responses);

        $sent = $this->session($recorder)->send(self::INVOICE_XML);

        self::assertSame(self::SESSION_REF, $sent->sessionReference);
        self::assertCount(2, self::requestsTo($recorder, 'POST', '/sessions/online'));
        self::assertCount(2, self::requestsTo($recorder, 'GET', '/security/public-key-certificates'));
    }

    public function test_status_reads_the_invoice_status(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse(['code' => 200, 'description' => 'Sukces'], [
                'ksefNumber' => self::KSEF_NUMBER,
                'acquisitionDate' => '2026-11-21T10:00:03.0154302+00:00',
            ]),
        ]);

        $status = $this->session($recorder)->status(self::SESSION_REF, self::INVOICE_REF);

        self::assertSame(200, $status->code);
        self::assertSame(DeliveryState::Delivered, $status->deliveryState());
        self::assertSame(self::KSEF_NUMBER, $status->ksefNumber);
        self::assertSame('2026-11-21T10:00:03.015430+00:00', $status->acquisitionDate?->format('Y-m-d\TH:i:s.uP'));
        $get = self::requestsTo($recorder, 'GET', '/invoices/'.self::INVOICE_REF);
        self::assertCount(1, $get);
        self::assertSame('/v2/sessions/'.self::SESSION_REF.'/invoices/'.self::INVOICE_REF, $get[0]->getUri()->getPath());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $get[0]->getHeaderLine('Authorization'));
    }

    public function test_duplicate_maps_to_duplicate_with_original_number(): void
    {
        // The SessionInvoiceStatusResponse example of the KSeF OpenAPI description.
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse([
                'code' => 440,
                'description' => 'Duplikat faktury',
                'details' => ['Duplikat faktury. Faktura o numerze KSeF: 5265877635-20250626-010080DD2B5E-26 została już prawidłowo przesłana do systemu w sesji: 20250626-SO-2F14610000-242991F8C9-B4'],
                'extensions' => [
                    'originalSessionReferenceNumber' => '20250626-SO-2F14610000-242991F8C9-B4',
                    'originalKsefNumber' => '5265877635-20250626-010080DD2B5E-26',
                ],
            ]),
        ]);

        $status = $this->session($recorder)->status(self::SESSION_REF, self::INVOICE_REF);

        self::assertTrue($status->isFinal());
        self::assertSame(DeliveryState::Failed, $status->deliveryState());
        self::assertSame('5265877635-20250626-010080DD2B5E-26', $status->originalKsefNumber);
        self::assertNull($status->ksefNumber);
        $failure = $status->failure();
        self::assertNotNull($failure);
        self::assertSame(FailureCategory::Duplicate, $failure->category);
        self::assertSame('440', $failure->providerCode);
        self::assertStringContainsString('Duplikat faktury', (string) $failure->message);
        self::assertStringContainsString('5265877635-20250626-010080DD2B5E-26', (string) $failure->message);
    }

    public function test_await_final_is_bounded(): void
    {
        $pending = self::invoiceStatusResponse(['code' => 150, 'description' => 'Trwa przetwarzanie']);
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), $pending, $pending, $pending]);

        $status = $this->session($recorder, $this->sessionConfig(3, 1500))->awaitFinal(self::SESSION_REF, self::INVOICE_REF);

        self::assertSame(150, $status->code);
        self::assertSame(DeliveryState::Pending, $status->deliveryState());
        self::assertFalse($status->isFinal());
        self::assertCount(3, self::requestsTo($recorder, 'GET', '/invoices/'.self::INVOICE_REF));
        self::assertSame([1500, 1500], $this->sleeps);
    }

    public function test_await_final_stops_at_a_final_status(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse(['code' => 100, 'description' => 'Faktura przyjęta do dalszego przetwarzania']),
            self::invoiceStatusResponse(['code' => 200, 'description' => 'Sukces'], ['ksefNumber' => self::KSEF_NUMBER]),
        ]);

        $status = $this->session($recorder, $this->sessionConfig(5, 2000))->awaitFinal(self::SESSION_REF, self::INVOICE_REF);

        self::assertSame(200, $status->code);
        self::assertSame(self::KSEF_NUMBER, $status->ksefNumber);
        self::assertSame([2000], $this->sleeps);
    }

    public function test_upo_is_fetched_as_xml(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/../../../fixtures/ksef/upo-te.xml');
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), new Response(200, ['Content-Type' => 'application/xml'], $xml)]);

        $upo = $this->session($recorder)->upo('20261120-SO-4F70173000-DBF8CA6741-F1', '4425138491-20261121-00F89C000000-00');

        self::assertSame($xml, $upo->xml);
        self::assertSame(['4425138491-20261121-00F89C000000-00'], $upo->ksefNumbers);
        $get = self::requestsTo($recorder, 'GET', '/upo');
        self::assertCount(1, $get);
        self::assertSame('/v2/sessions/20261120-SO-4F70173000-DBF8CA6741-F1/invoices/ksef/4425138491-20261121-00F89C000000-00/upo', $get[0]->getUri()->getPath());
        self::assertSame('application/xml', $get[0]->getHeaderLine('Accept'));
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $get[0]->getHeaderLine('Authorization'));
    }

    /**
     * The session key of a recorded open request, decrypted with the in-memory Ministry key.
     */
    private static function sessionKey(RequestInterface $open): SymmetricKey
    {
        $encryption = self::body($open)['encryption'] ?? null;
        self::assertIsArray($encryption);
        self::assertIsString($encryption['encryptedSymmetricKey']);
        self::assertIsString($encryption['initializationVector']);
        $key = self::oaepDecrypt((string) base64_decode($encryption['encryptedSymmetricKey'], true), self::mfKey(1));

        return new SymmetricKey($key, (string) base64_decode($encryption['initializationVector'], true));
    }
}

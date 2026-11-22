<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Certificates;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\AuthTokenRequest;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\XadesSigner;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\CsrBuilder;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KeyAlgorithm;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificate;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificateType;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\RevocationReason;
use IntrAgile\EInvoicing\Transport\Ksef\KsefTransport;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class KsefCertificatesTest extends TestCase
{
    use CertificateFixtures;

    private const SECOND_SERIAL = '017FAF7A608F7F32';

    public function test_limits_and_enrollment_data(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::json(200, ['canRequest' => true, 'enrollment' => ['remaining' => 298, 'limit' => 300], 'certificate' => ['remaining' => 97, 'limit' => 100]]),
            self::enrollmentDataResponse(['commonName' => 'Jan Kowalski', 'countryName' => 'PL', 'givenName' => 'Jan', 'surname' => 'Kowalski', 'serialNumber' => 'TINPL-7767012903', 'uniqueIdentifier' => null, 'organizationName' => null, 'organizationIdentifier' => null]),
        ]);
        $certificates = $this->certificates($client);

        $limits = $certificates->limits();
        self::assertTrue($limits->canRequest);
        self::assertSame([298, 300, 97, 100], [$limits->enrollmentRemaining, $limits->enrollmentLimit, $limits->certificateRemaining, $limits->certificateLimit]);

        // Null attributes are dropped, the order is kept.
        self::assertSame(
            ['commonName' => 'Jan Kowalski', 'countryName' => 'PL', 'givenName' => 'Jan', 'surname' => 'Kowalski', 'serialNumber' => 'TINPL-7767012903'],
            $certificates->enrollmentData(),
        );
        self::assertSame(['GET /certificates/limits', 'GET /certificates/enrollments/data'], array_slice(self::lines($client->requests()), 4));
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $client->requests()[4]->getHeaderLine('Authorization'));
    }

    public function test_enroll_polls_until_issued_then_retrieves(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::enrollmentStatus(100, 'Wniosek przyjęty do realizacji'),
            self::issuedStatus(),
            self::retrieveIssued(),
        ]);
        $certificates = $this->certificates($client);

        $enrolled = $certificates->enroll(KsefCertificateType::Offline, KeyAlgorithm::Ec, 'KOD II seal', new DateTimeImmutable('2026-11-23T08:00:00.25+01:00'));

        self::assertSame([
            'GET /certificates/enrollments/data',
            'POST /certificates/enrollments',
            'GET /certificates/enrollments/'.self::ENROLLMENT_REFERENCE,
            'GET /certificates/enrollments/'.self::ENROLLMENT_REFERENCE,
            'POST /certificates/retrieve',
        ], array_slice(self::lines($client->requests()), 4));
        self::assertSame([2000], $this->sleeps);

        $body = self::body($client->requests()[5]);
        self::assertSame(['certificateName', 'certificateType', 'csr', 'validFrom'], array_keys($body));
        self::assertSame('KOD II seal', $body['certificateName']);
        self::assertSame('Offline', $body['certificateType']);
        self::assertSame('2026-11-23T07:00:00.250+00:00', $body['validFrom']);
        self::assertIsString($body['csr']);
        $csrDer = base64_decode($body['csr'], true);
        self::assertIsString($csrDer);
        self::assertSame(self::SEAL_DATA, openssl_csr_get_subject(self::csrPem($csrDer), false));
        self::assertSame(['certificateSerialNumbers' => [self::SERIAL]], self::body($client->requests()[8]));

        $certificate = $enrolled->certificate;
        self::assertSame(self::SERIAL, $certificate->serialNumber);
        self::assertSame(KsefCertificateType::Offline, $certificate->type);
        self::assertSame('KOD II seal', $certificate->name);
        self::assertNull($certificate->status);
        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', $certificate->certificatePem);
        $parsed = openssl_x509_parse($certificate->certificatePem);
        self::assertIsArray($parsed);
        self::assertSame(self::SERIAL, $parsed['serialNumberHex']);
        self::assertIsInt($parsed['validFrom_time_t']);
        self::assertIsInt($parsed['validTo_time_t']);
        self::assertEquals(new DateTimeImmutable('@'.$parsed['validFrom_time_t']), $certificate->validFrom);
        self::assertEquals(new DateTimeImmutable('@'.$parsed['validTo_time_t']), $certificate->validTo);

        // The key never left the client, and the credentials pair it with the certificate.
        foreach ($client->requests() as $request) {
            self::assertStringNotContainsString('PRIVATE KEY', (string) $request->getBody());
        }
        $credentials = $enrolled->credentials();
        self::assertSame($certificate->certificatePem, $credentials->certificatePem);
        self::assertSame($enrolled->privateKeyPem(), $credentials->privateKeyPem());
        self::assertTrue(openssl_x509_check_private_key($certificate->certificatePem, $enrolled->privateKeyPem()));
    }

    public function test_authentication_certificate_signs_a_xades_login(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::issuedStatus(),
            self::retrieveIssued(self::SERIAL, 'Logowanie EC', 'Authentication'),
        ]);

        $enrolled = $this->certificates($client)->enroll(KsefCertificateType::Authentication);

        self::assertSame(KsefCertificateType::Authentication, $enrolled->certificate->type);
        self::assertSame([], $this->sleeps);
        $body = self::body($client->requests()[5]);
        self::assertSame(['certificateName', 'certificateType', 'csr'], array_keys($body));
        self::assertSame('Authentication', $body['certificateType']);
        // Without a name, one is made from the type and the time, within KSeF's pattern.
        self::assertSame('Authentication 2026-11-21 100000', $body['certificateName']);

        $signed = (new XadesSigner($this->now()))->sign(AuthTokenRequest::xml(self::CHALLENGE, ContextIdentifier::nip('7767012903')), $enrolled->credentials());
        self::assertStringContainsString('ecdsa-sha256', $signed);
    }

    public function test_enroll_rsa_sends_a_2048_bit_key(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::issuedStatus(),
            self::retrieveIssued(),
        ]);

        $enrolled = $this->certificates($client)->enroll(KsefCertificateType::Offline, KeyAlgorithm::Rsa, 'KOD II rsa');

        $key = openssl_pkey_get_private($enrolled->privateKeyPem());
        self::assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertSame([OPENSSL_KEYTYPE_RSA, 2048], [$details['type'], $details['bits']]);
    }

    public function test_enroll_rejected_400_throws_document_rejected(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::enrollmentStatus(400, 'Wniosek odrzucony', null, ['Osiągnięto dopuszczalny limit posiadanych certyfikatów.']),
        ]);

        $exception = self::capture(fn () => $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString(self::ENROLLMENT_REFERENCE, $exception->getMessage());
        self::assertStringContainsString('Wniosek odrzucony', $exception->getMessage());
        self::assertStringContainsString('Osiągnięto dopuszczalny limit posiadanych certyfikatów.', $exception->getMessage());
        self::assertSame(['Wniosek odrzucony: Osiągnięto dopuszczalny limit posiadanych certyfikatów.'], $exception->errors['errors'] ?? null);
        self::assertStringNotContainsString('PRIVATE KEY', $exception->getMessage().json_encode($exception->errors));
        self::assertCount(7, $client->requests());
    }

    public function test_enroll_poll_exhaustion(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::enrollmentStatus(100, 'Wniosek przyjęty do realizacji'),
            self::enrollmentStatus(100, 'Wniosek przyjęty do realizacji'),
            self::enrollmentStatus(100, 'Wniosek przyjęty do realizacji'),
        ]);
        $certificates = $this->certificates($client, $this->certificateConfig(statusPollAttempts: 3));

        $exception = self::capture(fn () => $certificates->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringContainsString(self::ENROLLMENT_REFERENCE, $exception->getMessage());
        self::assertStringContainsString('3 status reads', $exception->getMessage());
        self::assertSame([2000, 2000], $this->sleeps);
        self::assertCount(9, $client->requests());
    }

    public function test_enroll_cancelled_or_failed_status(): void
    {
        foreach ([[550, 'Operacja została anulowana przez system', TransportUnavailable::class], [500, 'Nieznany błąd', UnexpectedResponse::class]] as [$code, $description, $class]) {
            $client = new ScriptedHttpClient(...[
                ...self::xadesAuthResponses(),
                self::enrollmentDataResponse(),
                self::enrollResponse(),
                self::enrollmentStatus($code, $description),
            ]);

            $exception = self::capture(fn () => $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

            self::assertInstanceOf($class, $exception);
            self::assertStringContainsString((string) $code, $exception->getMessage());
            self::assertStringContainsString($description, $exception->getMessage());
        }
    }

    public function test_failure_while_polling_names_the_enrolment_to_revoke(): void
    {
        $cases = [
            'status read fails' => [new Response(503), TransportUnavailable::class],
            'issued without a serial' => [self::enrollmentStatus(200, 'Wniosek obsłużony (certyfikat wygenerowany)'), UnexpectedResponse::class],
            'unknown error status' => [self::enrollmentStatus(500, 'Nieznany błąd'), UnexpectedResponse::class],
        ];
        foreach ($cases as $label => [$status, $class]) {
            $client = new ScriptedHttpClient(...[
                ...self::xadesAuthResponses(),
                self::enrollmentDataResponse(),
                self::enrollResponse(),
                $status,
            ]);

            $exception = self::capture(fn () => $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

            self::assertSame($class, $exception::class, $label);
            self::assertStringContainsString(self::ENROLLMENT_REFERENCE, $exception->getMessage(), $label);
            self::assertStringContainsString('revoke it when it appears in query()', $exception->getMessage(), $label);
            self::assertStringNotContainsString('-----BEGIN', $exception->getMessage(), $label);
        }
    }

    public function test_failure_after_issue_names_the_serial_to_revoke(): void
    {
        $cases = [
            'retrieve fails without an answer' => [new Response(503), OutcomeUnknown::class],
            'certificate with another serial' => [self::retrieveResponse([[self::issue(self::csrFor(), self::SECOND_SERIAL), self::SERIAL, 'KOD II seal', 'Offline']]), UnexpectedResponse::class],
            'certificate for another key' => [self::retrieveResponse([[self::issue(self::csrFor(), self::SERIAL), self::SERIAL, 'KOD II seal', 'Offline']]), InvalidArgumentException::class],
        ];
        foreach ($cases as $label => [$retrieve, $class]) {
            $client = new ScriptedHttpClient(...[
                ...self::xadesAuthResponses(),
                self::enrollmentDataResponse(),
                self::enrollResponse(),
                self::issuedStatus(),
                $retrieve,
            ]);

            $exception = self::capture(fn () => $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

            self::assertSame($class, $exception::class, $label);
            self::assertStringContainsString('KSeF issued the certificate '.self::SERIAL, $exception->getMessage(), $label);
            self::assertStringContainsString('revoke the certificate '.self::SERIAL, $exception->getMessage(), $label);
            self::assertStringNotContainsString('PRIVATE KEY', $exception->getMessage(), $label);
            self::assertStringNotContainsString('-----BEGIN', $exception->getMessage(), $label);
            $previous = $exception->getPrevious();
            self::assertNotNull($previous, $label);
            self::assertSame($class, $previous::class, $label);
            self::assertStringContainsString($previous->getMessage(), $exception->getMessage(), $label);
        }
    }

    public function test_token_credentials_refused_before_request(): void
    {
        $client = new ScriptedHttpClient;
        $certificates = $this->certificates($client, $this->certificateConfig(new TokenCredentials(self::KSEF_TOKEN)));

        foreach ([
            static fn () => $certificates->enroll(KsefCertificateType::Offline),
            static fn () => $certificates->enrollmentData(),
        ] as $call) {
            $exception = self::capture($call);
            self::assertInstanceOf(LogicException::class, $exception);
            self::assertStringContainsString('certificate', $exception->getMessage());
            self::assertStringNotContainsString(self::KSEF_TOKEN, $exception->getMessage());
        }
        self::assertSame([], $client->requests());
    }

    public function test_enroll_arguments_checked_before_request(): void
    {
        $client = new ScriptedHttpClient;
        $certificates = $this->certificates($client);

        foreach (['Abc', str_repeat('a', 101), 'Name/with/slashes', 'Ünïcode name'] as $name) {
            $exception = self::capture(static fn () => $certificates->enroll(KsefCertificateType::Offline, name: $name));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $name);
        }
        self::assertSame([], $client->requests());
    }

    public function test_enrolled_certificate_is_redacted_and_not_serializable(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::issuedStatus(),
            self::retrieveIssued(),
        ]);
        $enrolled = $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal');
        $key = $enrolled->privateKeyPem();
        $keyBody = trim((string) preg_replace('/-----[^-]+-----/', '', $key));
        self::assertNotSame('', $keyBody);

        ob_start();
        var_dump($enrolled);
        $dumped = (string) ob_get_clean();
        $outputs = [
            'var_dump' => $dumped,
            'print_r' => print_r($enrolled, true),
            'var_export' => var_export($enrolled, true),
            'json_encode' => (string) json_encode($enrolled),
            'array cast' => print_r((array) $enrolled, true),
            'debugInfo' => print_r($enrolled->__debugInfo(), true),
        ];
        foreach ($outputs as $label => $output) {
            self::assertStringNotContainsString($keyBody, $output, $label);
            self::assertStringNotContainsString(substr($keyBody, 0, 40), $output, $label);
        }
        self::assertStringContainsString('[redacted]', $outputs['print_r']);
        self::assertStringContainsString(self::SERIAL, $outputs['print_r']);

        foreach ([static fn () => serialize($enrolled), static fn () => serialize([$enrolled])] as $call) {
            self::assertInstanceOf(LogicException::class, self::capture($call));
        }
        $exception = self::capture(static fn () => unserialize('O:68:"IntrAgile\EInvoicing\Transport\Ksef\Certificates\EnrolledCertificate":0:{}'));
        self::assertInstanceOf(LogicException::class, $exception);
    }

    public function test_retrieve_and_query(): void
    {
        $offline = self::issue(self::csrFor(), self::SERIAL);
        $auth = self::issue(self::csrFor(), self::SECOND_SERIAL);
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::retrieveResponse([[$offline, self::SERIAL, 'KOD II seal', 'Offline']]),
            self::json(200, ['certificates' => [self::listItem(self::SERIAL, 'KOD II seal', 'Offline', 'Revoked'), self::listItem(self::SECOND_SERIAL, 'Logowanie', 'Authentication')], 'hasMore' => false]),
            self::retrieveResponse([[$auth, self::SECOND_SERIAL, 'Logowanie', 'Authentication'], [$offline, self::SERIAL, 'KOD II seal', 'Offline']]),
        ]);
        $certificates = $this->certificates($client);

        $retrieved = $certificates->retrieve(self::SERIAL);
        self::assertSame(self::SERIAL, $retrieved->serialNumber);
        self::assertSame($offline, $retrieved->certificatePem);

        $found = $certificates->query();
        self::assertSame([self::SERIAL, self::SECOND_SERIAL], array_map(static fn (KsefCertificate $c): string => $c->serialNumber, $found));
        self::assertSame(['Revoked', 'Active'], array_map(static fn (KsefCertificate $c): ?string => $c->status, $found));
        self::assertSame([KsefCertificateType::Offline, KsefCertificateType::Authentication], array_map(static fn (KsefCertificate $c): KsefCertificateType => $c->type, $found));
        self::assertSame($auth, $found[1]->certificatePem);
        self::assertSame(['POST /certificates/retrieve', 'POST /certificates/query?pageSize=50&pageOffset=0', 'POST /certificates/retrieve'], array_slice(self::lines($client->requests()), 4));
        self::assertSame([], self::body($client->requests()[5]));
        self::assertSame(['certificateSerialNumbers' => [self::SERIAL, self::SECOND_SERIAL]], self::body($client->requests()[6]));
    }

    public function test_query_reads_every_page(): void
    {
        $items = [];
        for ($i = 0; $i < 12; $i++) {
            $items[] = sprintf('01%014X', $i + 1);
        }
        // Each certificate carries its own serial number, which the client checks.
        $csr = self::csrFor();
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::json(200, ['certificates' => array_map(static fn (string $s): array => self::listItem($s, 'Seal '.$s), array_slice($items, 0, 7)), 'hasMore' => true]),
            self::json(200, ['certificates' => array_map(static fn (string $s): array => self::listItem($s, 'Seal '.$s), array_slice($items, 7)), 'hasMore' => false]),
            self::retrieveFor($csr, array_slice($items, 0, 10)),
            self::retrieveFor($csr, array_slice($items, 10)),
        ]);

        $found = $this->certificates($client)->query(KsefCertificateType::Offline);

        self::assertCount(12, $found);
        self::assertSame([
            'POST /certificates/query?pageSize=50&pageOffset=0',
            'POST /certificates/query?pageSize=50&pageOffset=1',
            'POST /certificates/retrieve',
            'POST /certificates/retrieve',
        ], array_slice(self::lines($client->requests()), 4));
        self::assertSame(['certificateSerialNumbers' => array_slice($items, 0, 10)], self::body($client->requests()[6]));
        self::assertSame(['certificateSerialNumbers' => array_slice($items, 10)], self::body($client->requests()[7]));
    }

    public function test_query_and_revoke_bodies(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::json(200, ['certificates' => [], 'hasMore' => false]),
            new Response(204),
            new Response(204),
        ]);
        $certificates = $this->certificates($client);

        self::assertSame([], $certificates->query(KsefCertificateType::Offline, 'Active', 'KOD', self::SERIAL, new DateTimeImmutable('2027-01-01T00:00:00+02:00')));
        $certificates->revoke(self::SERIAL, RevocationReason::KeyCompromise);
        $certificates->revoke(self::SECOND_SERIAL);

        self::assertSame([
            'POST /certificates/query?pageSize=50&pageOffset=0',
            'POST /certificates/'.self::SERIAL.'/revoke',
            'POST /certificates/'.self::SECOND_SERIAL.'/revoke',
        ], array_slice(self::lines($client->requests()), 4));
        self::assertSame([
            'certificateSerialNumber' => self::SERIAL,
            'name' => 'KOD',
            'type' => 'Offline',
            'status' => 'Active',
            'expiresAfter' => '2026-12-31T22:00:00.000+00:00',
        ], self::body($client->requests()[4]));
        self::assertSame(['revocationReason' => 'KeyCompromise'], self::body($client->requests()[5]));
        self::assertSame(['revocationReason' => 'Unspecified'], self::body($client->requests()[6]));
        self::assertSame(['Unspecified', 'Superseded', 'KeyCompromise'], array_map(static fn (RevocationReason $r): string => $r->value, RevocationReason::cases()));
        self::assertSame(['Authentication', 'Offline'], array_map(static fn (KsefCertificateType $t): string => $t->value, KsefCertificateType::cases()));

        $exception = self::capture(static fn () => $certificates->query(status: 'Suspended'));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function test_serial_is_uppercase_16_hex(): void
    {
        $pem = self::issue(self::csrFor(), self::SERIAL);
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            new Response(204),
            self::retrieveResponse([[$pem, self::SERIAL, 'KOD II seal', 'Offline']]),
        ]);
        $certificates = $this->certificates($client);

        // A lower-case serial is sent, and kept, in the upper case KSeF uses.
        $certificates->revoke(strtolower(self::SERIAL));
        self::assertSame('POST /certificates/'.self::SERIAL.'/revoke', self::lines($client->requests())[4]);
        $retrieved = $certificates->retrieve(strtolower(self::SERIAL));
        self::assertSame(['certificateSerialNumbers' => [self::SERIAL]], self::body($client->requests()[5]));
        self::assertSame(self::SERIAL, $retrieved->serialNumber);

        $certificate = new KsefCertificate(strtolower(self::SERIAL), KsefCertificateType::Offline, $pem, new DateTimeImmutable('2026-11-22'), new DateTimeImmutable('2028-11-22'));
        self::assertSame(self::SERIAL, $certificate->serialNumber);

        foreach (['0148BCFE8559370', '0148BCFE8559370F0', '0148BCFE8559370G', ' 0148BCFE8559370F', '../revoke/x'] as $serial) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(static fn () => $certificates->revoke($serial)), $serial);
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(static fn () => $certificates->retrieve($serial)), $serial);
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(static fn () => new KsefCertificate($serial, KsefCertificateType::Offline, $pem, new DateTimeImmutable, new DateTimeImmutable)), $serial);
        }
        self::assertCount(6, $client->requests());
    }

    public function test_malformed_answer_serial_is_unexpected(): void
    {
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::enrollmentDataResponse(),
            self::enrollResponse(),
            self::issuedStatus('0148bcfe8559370f'),
        ]);

        $exception = self::capture(fn () => $this->certificates($client)->enroll(KsefCertificateType::Offline, name: 'KOD II seal'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);

        // A certificate whose own serial number is not the one asked for.
        $client = new ScriptedHttpClient(...[
            ...self::xadesAuthResponses(),
            self::retrieveResponse([[self::issue(self::csrFor(), self::SECOND_SERIAL), self::SERIAL, 'KOD II seal', 'Offline']]),
        ]);
        $exception = self::capture(fn () => $this->certificates($client)->retrieve(self::SERIAL));
        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('serial number is not '.self::SERIAL, $exception->getMessage());
    }

    public function test_transport_exposes_certificates(): void
    {
        $factory = new Psr17Factory;
        $transport = KsefTransport::create($this->certificateConfig(), new HttpClient(new ScriptedHttpClient, $factory, $factory));

        self::assertSame($transport->certificates(), $transport->certificates());
    }

    /**
     * The answer of POST /certificates/retrieve with a certificate of its own for each serial
     * number.
     *
     * @param  list<string>  $serials
     */
    private static function retrieveFor(string $csr, array $serials): Response
    {
        $items = [];
        foreach ($serials as $serial) {
            $items[] = [self::issue($csr, $serial), $serial, 'Seal '.$serial, 'Offline'];
        }

        return self::retrieveResponse($items);
    }

    /**
     * A DER CSR for the seal data, for certificates the tests issue up front.
     */
    private static function csrFor(): string
    {
        return CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Ec)['csrDer'];
    }
}

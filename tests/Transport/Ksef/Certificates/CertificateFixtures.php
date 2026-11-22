<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Certificates;

use Closure;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Credentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificates;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Canned KSeF certificate answers (GET /certificates/limits, GET /certificates/enrollments/data,
 * POST /certificates/enrollments, GET /certificates/enrollments/{referenceNumber},
 * POST /certificates/retrieve and POST /certificates/query), shaped after the OpenAPI schemas and
 * the answers KSeF TE gave on 2026-11-22. The issued certificate is signed in memory, by a
 * self-signed stand-in for "CN=TEST CCK KSeF", from the CSR the client actually sent.
 */
trait CertificateFixtures
{
    use AuthFixtures;

    private const BASE = 'https://api-test.ksef.mf.gov.pl/v2';

    private const ENROLLMENT_REFERENCE = '20261122-CE-00E99F6000-DBF4708FE9-BD';

    private const SERIAL = '0148BCFE8559370F';

    /** The seal data KSeF TE returned for the authenticating context, in its order. */
    private const SEAL_DATA = [
        'commonName' => 'Testowa Firma 2903',
        'countryName' => 'PL',
        'organizationName' => 'Testowa Firma 2903',
        'organizationIdentifier' => 'VATPL-7767012903',
    ];

    private static ?CertificateCredentials $seal = null;

    private static ?CertificateCredentials $issuer = null;

    private static function seal(): CertificateCredentials
    {
        return self::$seal ??= SelfSignedCertificate::seal('7767012903', 'Testowa Firma 2903');
    }

    private static function issuer(): CertificateCredentials
    {
        return self::$issuer ??= SelfSignedCertificate::seal('5265877635', 'TEST CCK KSeF');
    }

    private function certificateConfig(?Credentials $credentials = null, int $statusPollAttempts = 30): KsefConfig
    {
        return new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip('7767012903'),
            $credentials ?? self::seal(),
            statusPollAttempts: $statusPollAttempts,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    private function certificates(ScriptedHttpClient $client, ?KsefConfig $config = null): KsefCertificates
    {
        $config ??= $this->certificateConfig();
        $factory = new Psr17Factory;
        $plain = new KsefApi($config, new HttpClient($client, $factory, $factory));
        $api = $plain->withAuthenticator(new Authenticator($plain, $config, new PublicKeyRegistry($plain, $this->now()), now: $this->now()));

        return new KsefCertificates($api, $config, $this->now());
    }

    /**
     * The answers of a XAdES authentication: challenge, signed request, status and redeem.
     *
     * @return list<Response>
     */
    private static function xadesAuthResponses(): array
    {
        return [self::challengeResponse(), self::initResponse(), self::successStatus(), self::redeemResponse()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function enrollmentDataResponse(array $data = self::SEAL_DATA): Response
    {
        return self::json(200, $data);
    }

    private static function enrollResponse(): Response
    {
        return self::json(202, ['referenceNumber' => self::ENROLLMENT_REFERENCE, 'timestamp' => '2026-11-22T00:15:56.9183714+00:00']);
    }

    /**
     * @param  list<string>|null  $details
     */
    private static function enrollmentStatus(int $code, string $description, ?string $serial = null, ?array $details = null): Response
    {
        $status = ['code' => $code, 'description' => $description];
        if ($details !== null) {
            $status['details'] = $details;
        }
        $data = ['requestDate' => '2026-11-22T00:15:56.9183714+00:00', 'status' => $status];
        if ($serial !== null) {
            $data['certificateSerialNumber'] = $serial;
        }

        return self::json(200, $data);
    }

    private static function issuedStatus(string $serial = self::SERIAL): Response
    {
        return self::enrollmentStatus(200, 'Wniosek obsłużony (certyfikat wygenerowany)', $serial);
    }

    /**
     * The answer of POST /certificates/retrieve: a certificate issued for the CSR of the recorded
     * POST /certificates/enrollments request.
     *
     * @return Closure(list<RequestInterface>): Response
     */
    private static function retrieveIssued(string $serial = self::SERIAL, string $name = 'KOD II seal', string $type = 'Offline'): Closure
    {
        return static function (array $requests) use ($serial, $name, $type): Response {
            $enrollment = null;
            foreach ($requests as $request) {
                if ($request instanceof RequestInterface && $request->getMethod() === 'POST' && str_ends_with((string) $request->getUri(), '/certificates/enrollments')) {
                    $enrollment = $request;
                }
            }
            self::assertNotNull($enrollment, 'The certificate was retrieved before a CSR was sent.');
            $csr = self::body($enrollment)['csr'] ?? null;
            self::assertIsString($csr);
            $der = base64_decode($csr, true);
            self::assertIsString($der);

            return self::retrieveResponse([[self::issue($der, $serial), $serial, $name, $type]]);
        };
    }

    /**
     * @param  list<array{string, string, string, string}>  $items  certificate PEM, serial, name, type
     */
    private static function retrieveResponse(array $items): Response
    {
        $certificates = [];
        foreach ($items as [$pem, $serial, $name, $type]) {
            $certificates[] = [
                'certificate' => base64_encode(self::der($pem)),
                'certificateName' => $name,
                'certificateSerialNumber' => $serial,
                'certificateType' => $type,
            ];
        }

        return self::json(200, ['certificates' => $certificates]);
    }

    /**
     * A certificate signed by the stand-in issuer for a DER-encoded CSR, valid for two years.
     */
    private static function issue(string $csrDer, string $serial = self::SERIAL): string
    {
        $issuer = self::issuer();
        $certificate = openssl_csr_sign(self::csrPem($csrDer), $issuer->certificatePem, $issuer->privateKey(), 730, ['digest_alg' => 'sha256', 'config' => self::opensslConfig()], (int) hexdec($serial));
        self::assertNotFalse($certificate, 'The test issuer could not sign the CSR.');
        self::assertTrue(openssl_x509_export($certificate, $pem));
        self::assertIsString($pem);

        return $pem;
    }

    private static function csrPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE REQUEST-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE REQUEST-----\n";
    }

    private static function opensslConfig(): string
    {
        return dirname(__DIR__, 4).'/resources/poland/ksef-testing/openssl.cnf';
    }

    /**
     * One CertificateListItem of POST /certificates/query.
     *
     * @return array<string, mixed>
     */
    private static function listItem(string $serial, string $name, string $type = 'Offline', string $status = 'Active'): array
    {
        return [
            'certificateSerialNumber' => $serial,
            'name' => $name,
            'type' => $type,
            'commonName' => 'Testowa Firma 2903 (offline)',
            'status' => $status,
            'subjectIdentifier' => ['type' => 'Nip', 'value' => '7767012903'],
            'validFrom' => '2026-11-22T00:15:56+00:00',
            'validTo' => '2028-11-22T00:15:56+00:00',
            'lastUseDate' => null,
            'requestDate' => '2026-11-22T00:15:56+00:00',
        ];
    }

    /**
     * The request line of each recorded request, its path relative to the API base.
     *
     * @param  list<RequestInterface>  $requests
     * @return list<string>
     */
    private static function lines(array $requests): array
    {
        return array_map(static function (RequestInterface $request): string {
            $uri = (string) $request->getUri();

            return $request->getMethod().' '.(str_starts_with($uri, self::BASE) ? substr($uri, strlen(self::BASE)) : $uri);
        }, $requests);
    }
}

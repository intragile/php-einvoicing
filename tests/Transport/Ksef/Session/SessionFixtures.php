<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Session\OnlineSession;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * An online session over canned KSeF answers. The session and the authenticator share one
 * public-key registry, so the key list is fetched once, before the first authentication.
 */
trait SessionFixtures
{
    use AuthFixtures;

    private const SESSION_REF = '20250625-SO-2C3E6C8000-B675CF5D68-07';

    private const SECOND_SESSION_REF = '20250625-SO-2C3E6C8000-B675CF5D68-08';

    private const INVOICE_REF = '20250625-EE-319D7EE000-B67F415CDC-2C';

    private const SECOND_INVOICE_REF = '20250625-EE-319D7EE000-B67F415CDC-2D';

    private const KSEF_NUMBER = '5265877635-20250626-010080DD2B5E-26';

    private const INVOICE_XML = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Faktura xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/"><Naglowek><KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza></Naglowek></Faktura>'."\n";

    private function sessionConfig(int $statusPollAttempts = 30, int $statusPollWaitMs = 2000): KsefConfig
    {
        return new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip('5265877635'),
            new TokenCredentials(self::KSEF_TOKEN),
            statusPollAttempts: $statusPollAttempts,
            statusPollWaitMs: $statusPollWaitMs,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    private function session(RecordingHttpClient $recorder, ?KsefConfig $config = null): OnlineSession
    {
        $config ??= $this->sessionConfig();
        $api = $this->plainApi($recorder, $config);
        $keys = new PublicKeyRegistry($api, $this->now());
        $authenticator = new Authenticator($api, $config, $keys, now: $this->now());

        return new OnlineSession($api->withAuthenticator($authenticator), $keys, $config, $this->now());
    }

    /**
     * The answers of the first send() up to the open session: the key list, a KSeF-token
     * authentication and the opened session.
     *
     * @return list<Response>
     */
    private static function openingResponses(string $validUntil = '2026-11-21T22:00:00.0000000+00:00'): array
    {
        return [
            self::keysResponse(),
            self::challengeResponse(),
            self::initResponse(),
            self::successStatus(),
            self::redeemResponse(),
            self::openResponse(self::SESSION_REF, $validUntil),
        ];
    }

    private static function openResponse(string $reference = self::SESSION_REF, string $validUntil = '2026-11-21T22:00:00.0000000+00:00'): Response
    {
        return self::json(201, ['referenceNumber' => $reference, 'validUntil' => $validUntil]);
    }

    private static function sendResponse(string $reference = self::INVOICE_REF): Response
    {
        return self::json(202, ['referenceNumber' => $reference]);
    }

    /**
     * A SessionInvoiceStatusResponse.
     *
     * @param  array<string, mixed>  $status
     * @param  array<string, mixed>  $extra
     */
    private static function invoiceStatusResponse(array $status, array $extra = []): Response
    {
        return self::json(200, [
            'ordinalNumber' => 1,
            'referenceNumber' => self::INVOICE_REF,
            'invoiceHash' => base64_encode(hash('sha256', self::INVOICE_XML, true)),
            'invoicingDate' => '2026-11-21T10:00:01.0154302+00:00',
            'status' => $status,
        ] + $extra);
    }

    /**
     * The requests whose URL path ends with the given suffix, as "METHOD /path" for readability.
     *
     * @return list<string>
     */
    private static function paths(RecordingHttpClient $recorder): array
    {
        return array_map(
            static fn (RequestInterface $request): string => $request->getMethod().' '.substr($request->getUri()->getPath(), strlen('/v2')),
            $recorder->requests(),
        );
    }

    /**
     * @return list<RequestInterface>
     */
    private static function requestsTo(RecordingHttpClient $recorder, string $method, string $pathSuffix): array
    {
        return array_values(array_filter(
            $recorder->requests(),
            static fn (RequestInterface $request): bool => $request->getMethod() === $method && str_ends_with($request->getUri()->getPath(), $pathSuffix),
        ));
    }
}

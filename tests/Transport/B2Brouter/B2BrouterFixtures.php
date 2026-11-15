<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterTransport;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Shared helpers of the B2Brouter driver tests.
 */
trait B2BrouterFixtures
{
    use CapturesThrowable;

    private const KEY = 'test_FAKEKEY000000';

    private const ACCOUNT = 4711;

    private const BASE = 'https://api.b2brouter.net';

    /**
     * The transport over a plain HTTP client built without secrets: the transport must add the
     * API key to the redacted values itself.
     *
     * @param  array<string, string>  $documentTypeCodes
     */
    private static function transport(RecordingHttpClient $recorder, array $documentTypeCodes = [], string $baseUrl = self::BASE): B2BrouterTransport
    {
        $factory = new Psr17Factory;

        return new B2BrouterTransport(
            new B2BrouterConfig(self::KEY, self::ACCOUNT, $baseUrl, $documentTypeCodes),
            new HttpClient($recorder, $factory, $factory),
        );
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private static function fixture(int $status, string $name, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, self::read($name));
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function json(int $status, array $data): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decoded(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode(self::read($name), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function read(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../../fixtures/b2brouter/'.$name);
        self::assertIsString($contents);

        return $contents;
    }

    private static function networkException(string $message): NetworkExceptionInterface
    {
        return new class(new Request('POST', self::BASE), $message) extends RuntimeException implements NetworkExceptionInterface
        {
            public function __construct(private readonly RequestInterface $request, string $message)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }
}

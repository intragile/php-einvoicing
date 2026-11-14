<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeTransport;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

/**
 * Shared helpers of the e-invoice.be driver tests.
 */
trait EInvoiceBeFixtures
{
    private const KEY = 'test_FAKEKEY000000';

    private const BASE = 'https://api.e-invoice.be';

    /** The time the test clock reports. */
    private const NOW = '2026-11-14T12:00:00Z';

    /**
     * The transport over a plain HTTP client built without secrets: the transport must add the
     * API key to the redacted values itself.
     */
    private static function transport(
        RecordingHttpClient $recorder,
        int $lookbackDays = 7,
        int $maxPages = 10,
        string $now = self::NOW,
    ): EInvoiceBeTransport {
        $factory = new Psr17Factory;

        return new EInvoiceBeTransport(
            new EInvoiceBeConfig(self::KEY, self::BASE, $lookbackDays, $maxPages),
            new HttpClient($recorder, $factory, $factory),
            static fn (): DateTimeImmutable => new DateTimeImmutable($now),
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
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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
        $contents = file_get_contents(__DIR__.'/../../fixtures/einvoicebe/'.$name);
        self::assertIsString($contents);

        return $contents;
    }

    private static function capture(callable $action): Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }
        self::fail('No exception was thrown.');
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

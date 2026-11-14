<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 decorator for tests that counts the requests it forwards.
 */
final class RequestCounter implements ClientInterface
{
    private int $count = 0;

    public function __construct(private readonly ClientInterface $client) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->count++;

        return $this->client->sendRequest($request);
    }

    public function count(): int
    {
        return $this->count;
    }
}

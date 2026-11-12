<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client for tests: answers from a queue of responses and client exceptions, in order,
 * and records every request. A request beyond the queue fails the test.
 */
final class RecordingHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue;

    /** @var list<RequestInterface> */
    private array $requests = [];

    public function __construct(ResponseInterface|ClientExceptionInterface ...$queue)
    {
        $this->queue = array_values($queue);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            Assert::fail(sprintf(
                'Unexpected HTTP request %s %s: no response is queued.',
                $request->getMethod(),
                (string) $request->getUri(),
            ));
        }
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}

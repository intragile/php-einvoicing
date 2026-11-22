<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Certificates;

use Closure;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client for tests that answers from a queue, in order, and records every request. An
 * entry is a response, or a closure that builds the response from the requests recorded so far,
 * the current one last; that lets a canned answer depend on what the client sent, such as a
 * certificate issued for the CSR of an earlier request. A request beyond the queue fails the test.
 */
final class ScriptedHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|Closure(list<RequestInterface>): ResponseInterface> */
    private array $queue;

    /** @var list<RequestInterface> */
    private array $requests = [];

    /**
     * @param  ResponseInterface|Closure(list<RequestInterface>): ResponseInterface  ...$queue
     */
    public function __construct(ResponseInterface|Closure ...$queue)
    {
        $this->queue = array_values($queue);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            Assert::fail(sprintf('Unexpected HTTP request %s %s: no response is queued.', $request->getMethod(), (string) $request->getUri()));
        }

        return $next instanceof Closure ? $next($this->requests) : $next;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}

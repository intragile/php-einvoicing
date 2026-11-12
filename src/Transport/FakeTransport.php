<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use InvalidArgumentException;
use LogicException;

/**
 * An in-memory transport and inbound source for tests of code that sends or receives documents.
 *
 * Every send is recorded, including those answered with a queued exception. Sends are answered
 * from the queue filled by willReturn(), in order, then with delivered results whose ids are
 * fake-1, fake-2 and so on.
 */
final class FakeTransport implements InboundSource, Transport
{
    /** @var list<OutboundDocument> */
    private array $sent = [];

    /** @var list<SendResult|TransportException> */
    private array $outcomes = [];

    /** @var array<string, SendResult> */
    private array $results = [];

    private int $sequence = 0;

    /** @var array<string, InboundDocument> */
    private array $inbox = [];

    /** @var list<string> */
    private array $acknowledged = [];

    public function send(OutboundDocument $document): SendResult
    {
        $this->sent[] = $document;
        $outcome = array_shift($this->outcomes)
            ?? new SendResult('fake-'.++$this->sequence, DeliveryState::Delivered);
        if ($outcome instanceof TransportException) {
            throw $outcome;
        }
        $this->results[$outcome->documentId] = $outcome;

        return $outcome;
    }

    /**
     * The state of the send result with this id; Unknown for a document never sent.
     */
    public function status(string $documentId): DeliveryStatus
    {
        $result = $this->results[$documentId] ?? null;

        return $result === null
            ? new DeliveryStatus($documentId, DeliveryState::Unknown)
            : new DeliveryStatus($documentId, $result->state, $result->failure);
    }

    /**
     * Queues the answers to the next sends: a result is returned, an exception thrown.
     */
    public function willReturn(SendResult|TransportException ...$outcomes): void
    {
        array_push($this->outcomes, ...array_values($outcomes));
    }

    /**
     * The documents passed to send(), in order.
     *
     * @return list<OutboundDocument>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * @param  callable(OutboundDocument): bool  $match
     *
     * @throws LogicException when no sent document matches
     */
    public function assertSent(callable $match): void
    {
        foreach ($this->sent as $document) {
            if ($match($document)) {
                return;
            }
        }

        if ($this->sent === []) {
            throw new LogicException('No sent document matched: no document was sent.');
        }
        throw new LogicException(sprintf(
            "No sent document matched; %d document%s sent:\n%s",
            count($this->sent),
            count($this->sent) === 1 ? ' was' : 's were',
            implode("\n", array_map(
                static fn (OutboundDocument $d): string => sprintf('- %s -> %s (%s)', $d->sender->toString(), $d->receiver->toString(), $d->documentType->value),
                $this->sent,
            )),
        ));
    }

    /**
     * Adds a document to the inbound source, pending until acknowledged.
     */
    public function receive(InboundDocument $document): void
    {
        $this->inbox[$document->reference->documentId] = $document;
    }

    public function pending(): array
    {
        return array_values(array_map(
            static fn (InboundDocument $document): InboundReference => $document->reference,
            array_filter(
                $this->inbox,
                fn (InboundDocument $document): bool => ! in_array($document->reference->documentId, $this->acknowledged, true),
            ),
        ));
    }

    /**
     * @throws InvalidArgumentException when no document with this id was received
     */
    public function fetch(string $documentId): InboundDocument
    {
        return $this->inbox[$documentId]
            ?? throw new InvalidArgumentException(sprintf('No inbound document with id "%s" was received.', $documentId));
    }

    /**
     * @throws InvalidArgumentException when no document with this id was received
     */
    public function acknowledge(string $documentId): void
    {
        $this->fetch($documentId);
        if (! in_array($documentId, $this->acknowledged, true)) {
            $this->acknowledged[] = $documentId;
        }
    }

    /**
     * The ids passed to acknowledge(), in order, each once.
     *
     * @return list<string>
     */
    public function acknowledged(): array
    {
        return $this->acknowledged;
    }
}

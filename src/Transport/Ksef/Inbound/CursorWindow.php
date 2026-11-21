<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;

/**
 * One window of incremental inbox reading: the invoices with a PermanentStorage date from $from
 * up to the high water mark $hwm, and whether each of them has been acknowledged.
 *
 * KSeF guarantees that no invoice with a PermanentStorage date at or before the high water mark
 * appears later, so once every invoice of a window is acknowledged the next window can start at
 * its high water mark. Until then next() returns the same window, so an unacknowledged invoice
 * is offered again after a restart. Invoices stored exactly at a window boundary may be offered
 * in two adjacent windows: consumers deduplicate by KSeF number.
 *
 * The window is immutable; every change returns a new one to save in a KsefCursorStore.
 */
final readonly class CursorWindow
{
    /**
     * @param  DateTimeImmutable|null  $hwm  the end of the window; null until a query has reported it
     * @param  array<string, bool>  $ids  the KSeF numbers of the window, true once acknowledged
     */
    public function __construct(
        public DateTimeImmutable $from,
        public ?DateTimeImmutable $hwm = null,
        public array $ids = [],
    ) {}

    /**
     * An empty window starting at the given point, before its first query.
     */
    public static function start(DateTimeImmutable $from): self
    {
        return new self($from);
    }

    /**
     * The window with the invoices of a query from $from: new KSeF numbers are added as not yet
     * acknowledged, known ones keep their state. The end becomes the reported high water mark;
     * a null mark keeps the current end. When the result was truncated (more than KSeF returns
     * for one filter set), the end narrows to the latest PermanentStorage date returned, so that
     * the next window continues where this result stopped. When the query was bounded by an end
     * date ($queryEnd), the window ends no later than that date, so that a window older than
     * KSeF's query range moves forward in chunks.
     *
     * @param  list<InvoiceMetadata>  $invoices
     * @param  DateTimeImmutable|null  $queryEnd  the "to" of the query, or null when it was open-ended
     *
     * @throws UnexpectedResponse when a truncated result does not pass the start of the window:
     *                            more invoices share that PermanentStorage date than KSeF returns
     *                            for one filter set, so the window could never advance
     */
    public function withResults(array $invoices, ?DateTimeImmutable $hwm, bool $isTruncated, ?DateTimeImmutable $queryEnd = null): self
    {
        $ids = $this->ids;
        $last = null;
        foreach ($invoices as $invoice) {
            $ids[$invoice->ksefNumber] ??= false;
            if ($last === null || $invoice->permanentStorageDate > $last) {
                $last = $invoice->permanentStorageDate;
            }
        }

        $end = $hwm ?? $this->hwm;
        if ($end !== null && $queryEnd !== null && $queryEnd < $end) {
            $end = $queryEnd;
        }
        if ($isTruncated && $last !== null) {
            if ($last <= $this->from) {
                throw new UnexpectedResponse(sprintf(
                    'More than 10,000 KSeF invoices share the PermanentStorage date %s; KSeF cannot page past it, so the inbox window cannot advance.',
                    $this->from->format('Y-m-d\\TH:i:s.uP'),
                ));
            }
            $end = $last;
        }

        return new self($this->from, $end, $ids);
    }

    /**
     * The window with the invoice marked as acknowledged; an id outside the window is ignored.
     */
    public function acknowledge(string $id): self
    {
        if (! array_key_exists($id, $this->ids) || $this->ids[$id]) {
            return $this;
        }
        $ids = $this->ids;
        $ids[$id] = true;

        return new self($this->from, $this->hwm, $ids);
    }

    /**
     * True when the end of the window is known and every invoice in it is acknowledged.
     */
    public function isComplete(): bool
    {
        return $this->hwm !== null && ! in_array(false, $this->ids, true);
    }

    /**
     * The KSeF numbers not yet acknowledged, in the order they were added.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        return array_map(strval(...), array_keys(array_filter($this->ids, static fn (bool $acknowledged): bool => ! $acknowledged)));
    }

    /**
     * The following window, starting at this window's high water mark with no invoices, once this
     * window is complete; this same window while it is not. A high water mark before the window
     * start (a start set ahead of KSeF's mark) keeps the start, so the cursor never moves back.
     */
    public function next(): self
    {
        if (! $this->isComplete() || $this->hwm === null) {
            return $this;
        }

        return new self(max($this->hwm, $this->from));
    }
}

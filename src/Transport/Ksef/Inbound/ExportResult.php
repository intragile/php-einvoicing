<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;

/**
 * The outcome of a KSeF invoice export, over all of its rounds.
 */
final readonly class ExportResult
{
    /**
     * @param  array<string, string>  $invoices  the invoice files by KSeF number (the file name
     *                                           without ".xml"), each invoice once
     * @param  array<mixed>|null  $metadata  the _metadata.json content: its "invoices" list
     *                                       merged over all rounds without repeated KSeF
     *                                       numbers; null when no package carried the file
     * @param  DateTimeImmutable|null  $hwm  where the next export continues: the high water mark
     *                                       (permanentStorageHwmDate) when the export is
     *                                       complete, the date of the last exported invoice when
     *                                       it is still truncated, the requested start when
     *                                       KSeF had nothing beyond its high water mark; null
     *                                       when KSeF reported no such date
     * @param  int  $rounds  the exports made, at most KsefExport::MAX_ROUNDS
     * @param  bool  $isTruncated  true when the last round was still truncated: run the
     *                             export again from $hwm for the rest
     */
    public function __construct(
        public array $invoices,
        public ?array $metadata,
        public ?DateTimeImmutable $hwm,
        public int $rounds,
        public bool $isTruncated = false,
    ) {}
}

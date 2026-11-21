<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;

/**
 * One invoice of a KSeF metadata query or of an export's _metadata.json (InvoiceMetadata schema).
 * The fields an inbox needs are typed; the whole entry stays available in $raw.
 */
final readonly class InvoiceMetadata
{
    /**
     * @param  string|null  $invoiceHash  the SHA-256 digest of the invoice file, Base64
     * @param  array<mixed>  $raw  the entry as KSeF sent it
     */
    public function __construct(
        public string $ksefNumber,
        public string $invoiceNumber,
        public string $sellerNip,
        public DateTimeImmutable $permanentStorageDate,
        public ?string $invoiceHash = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<mixed>  $entry
     *
     * @throws UnexpectedResponse when a required field is missing or mistyped
     */
    public static function fromAnswer(array $entry, string $operation): self
    {
        $hash = $entry['invoiceHash'] ?? null;

        return new self(
            KsefAnswer::string($entry, ['ksefNumber'], $operation),
            KsefAnswer::string($entry, ['invoiceNumber'], $operation),
            KsefAnswer::string($entry, ['seller', 'nip'], $operation),
            KsefAnswer::dateTime($entry, ['permanentStorageDate'], $operation),
            is_string($hash) && $hash !== '' ? $hash : null,
            $entry,
        );
    }
}

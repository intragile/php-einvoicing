<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\FailureCategory;

/**
 * The processing state of a KSeF batch session (SessionStatusResponse) with the status of each
 * invoice in it (SessionInvoicesResponse).
 *
 * Batch session status codes:
 * - 100 (session opened) and 150 (processing): pending;
 * - 200 (processed): final and successful, although single invoices may still have failed;
 * - 445 (no valid invoice): final failure, Validation;
 * - 405 (package verification), 415 (key decryption), 430 (decompression) and 435 (part
 *   decryption): final failure, Transport;
 * - 420 (invoice limit exceeded), 440 (cancelled: upload time exceeded or nothing sent),
 *   500 (unknown error) and any code this version does not know: final failure, Other.
 */
final readonly class BatchResult
{
    private const PENDING_CODES = [100, 150];

    private const PROCESSED = 200;

    /**
     * @param  int  $code  the KSeF session status code
     * @param  string  $description  KSeF's description of the status (in Polish)
     * @param  int  $invoiceCount  the invoices KSeF accepted into the session
     * @param  int  $successful  the invoices processed successfully
     * @param  int  $failed  the invoices processed with an error
     * @param  array<string, KsefInvoiceStatus>  $invoices  the status of each invoice by its file
     *                                                      name in the package (by its reference
     *                                                      number when KSeF gives no file name)
     * @param  string|null  $upoReference  the reference number of the first page of the
     *                                     session receipt (UPO), once KSeF has issued it
     * @param  list<string>  $details  KSeF's additional details of the status
     */
    public function __construct(
        public int $code,
        public string $description,
        public int $invoiceCount,
        public int $successful,
        public int $failed,
        public array $invoices,
        public ?string $upoReference,
        public array $details = [],
    ) {}

    /**
     * True once KSeF has finished processing the session, successfully or not.
     */
    public function isFinal(): bool
    {
        return ! in_array($this->code, self::PENDING_CODES, true);
    }

    /**
     * True when KSeF processed the session (status 200). Single invoices may still have failed;
     * see $failed and $invoices.
     */
    public function isSuccessful(): bool
    {
        return $this->code === self::PROCESSED;
    }

    /**
     * Why the session as a whole failed, or null while it is pending or once it is processed.
     */
    public function failure(): ?DeliveryFailure
    {
        if (! $this->isFinal() || $this->isSuccessful()) {
            return null;
        }
        $category = match ($this->code) {
            445 => FailureCategory::Validation,
            405, 415, 430, 435 => FailureCategory::Transport,
            default => FailureCategory::Other,
        };
        $message = $this->description;
        if ($this->details !== []) {
            $message .= ': '.implode('; ', $this->details);
        }

        return new DeliveryFailure($category, $message, (string) $this->code);
    }
}

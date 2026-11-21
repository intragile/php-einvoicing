<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;

/**
 * The processing status of one invoice sent in a KSeF session (SessionInvoiceStatusResponse).
 *
 * Status codes map to delivery states as follows:
 * - 100 (accepted for processing) and 150 (processing): Pending;
 * - 200 (success, KSeF number assigned): Delivered;
 * - 440 (duplicate): Failed / Duplicate, the original KSeF number in the message;
 * - 430 (invoice file verification) and 450 (semantic verification): Failed / Validation;
 * - 410 (permission scope) and 415 (attachments not allowed): Failed / DocumentNotSupported;
 * - 405 (cancelled by a session error), 435 (decryption error) and 550 (cancelled by the
 *   system): Failed / Transport;
 * - 500 (unknown error) and any code this version does not know: Failed / Other.
 */
final readonly class KsefInvoiceStatus
{
    private const PENDING_CODES = [100, 150];

    private const DELIVERED = 200;

    private const DUPLICATE = 440;

    /**
     * @param  int  $code  the KSeF invoice status code
     * @param  string  $description  KSeF's description of the status (in Polish)
     * @param  list<string>  $details  KSeF's additional details
     * @param  string|null  $ksefNumber  the KSeF number, once assigned (status 200)
     * @param  DateTimeImmutable|null  $acquisitionDate  when the KSeF number was assigned
     * @param  string|null  $originalKsefNumber  for a duplicate (status 440), the KSeF number of
     *                                           the invoice sent before
     */
    public function __construct(
        public int $code,
        public string $description,
        public array $details = [],
        public ?string $ksefNumber = null,
        public ?DateTimeImmutable $acquisitionDate = null,
        public ?string $originalKsefNumber = null,
    ) {}

    /**
     * Reads a decoded SessionInvoiceStatusResponse.
     *
     * @param  array<mixed>  $answer
     * @param  string  $operation  the request that was answered, phrased to start a sentence
     *
     * @throws UnexpectedResponse when the status code or description is missing
     */
    public static function fromAnswer(array $answer, string $operation): self
    {
        $status = is_array($answer['status'] ?? null) ? $answer['status'] : [];
        $details = is_array($status['details'] ?? null)
            ? array_values(array_filter($status['details'], static fn (mixed $detail): bool => is_string($detail) && trim($detail) !== ''))
            : [];
        $extensions = is_array($status['extensions'] ?? null) ? $status['extensions'] : [];
        $acquisition = $answer['acquisitionDate'] ?? null;

        return new self(
            KsefAnswer::int($answer, ['status', 'code'], $operation),
            KsefAnswer::string($answer, ['status', 'description'], $operation),
            array_map(trim(...), $details),
            self::optionalString($answer['ksefNumber'] ?? null),
            is_string($acquisition) ? KsefAnswer::parseDateTime($acquisition) : null,
            self::optionalString($extensions['originalKsefNumber'] ?? null),
        );
    }

    public function deliveryState(): DeliveryState
    {
        return match (true) {
            in_array($this->code, self::PENDING_CODES, true) => DeliveryState::Pending,
            $this->code === self::DELIVERED => DeliveryState::Delivered,
            default => DeliveryState::Failed,
        };
    }

    /**
     * Why the invoice failed, or null while it is pending or once it is delivered. The message
     * joins the description and the details; for a duplicate it names the original KSeF number.
     */
    public function failure(): ?DeliveryFailure
    {
        if ($this->deliveryState() !== DeliveryState::Failed) {
            return null;
        }
        $category = match ($this->code) {
            self::DUPLICATE => FailureCategory::Duplicate,
            430, 450 => FailureCategory::Validation,
            410, 415 => FailureCategory::DocumentNotSupported,
            405, 435, 550 => FailureCategory::Transport,
            default => FailureCategory::Other,
        };
        $message = $this->description;
        if ($this->details !== []) {
            $message .= ': '.implode('; ', $this->details);
        }
        if ($this->originalKsefNumber !== null && ! str_contains($message, $this->originalKsefNumber)) {
            $message .= sprintf(' (original KSeF number: %s)', $this->originalKsefNumber);
        }

        return new DeliveryFailure($category, $message, (string) $this->code);
    }

    /**
     * True once KSeF has finished processing the invoice, successfully or not.
     */
    public function isFinal(): bool
    {
        return $this->deliveryState() !== DeliveryState::Pending;
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

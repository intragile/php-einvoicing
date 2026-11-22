<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

use DateTimeImmutable;
use Exception;
use IntrAgile\EInvoicing\Poland\Offline\OutageCategory;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * One message published by Latarnia about a planned maintenance or a failure of KSeF.
 *
 * A maintenance is announced by a single message carrying its whole window. A failure has a
 * start message and, once it is over, an end message with the same event id; Latarnia then also
 * copies the end time onto the start message. A null end means the event has not ended, so an end
 * message must carry one.
 */
final readonly class LatarniaMessage
{
    private const CATEGORIES = [
        'MAINTENANCE' => OutageCategory::Maintenance,
        'FAILURE' => OutageCategory::Failure,
        'TOTAL_FAILURE' => OutageCategory::TotalFailure,
    ];

    /** An ISO 8601 date and time with an explicit offset or "Z". */
    private const DATE_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,7})?)?(?:Z|[+-]\d{2}:\d{2})$/i';

    /**
     * @throws InvalidArgumentException when an end message carries no end time
     */
    public function __construct(
        /** The message identifier shown to users, for example a reference number. */
        public string $id,
        /** The event the message belongs to; a failure's start and end messages share it. */
        public int $eventId,
        public OutageCategory $category,
        public LatarniaMessageType $type,
        public string $title,
        public string $text,
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        /** The revision of the message; a higher version replaces a lower one. */
        public int $version,
        public DateTimeImmutable $published,
    ) {
        if ($type === LatarniaMessageType::FailureEnd && $end === null) {
            // No end is guessed: a later end than the real one would move a legal deadline later.
            throw new InvalidArgumentException(sprintf('The Latarnia FAILURE_END message "%s" carries no end time.', $id));
        }
    }

    /**
     * The message decoded from its JSON object. The end may be null or left out.
     *
     * @param  array<mixed>  $data
     *
     * @throws UnexpectedValueException when a field is missing, has the wrong type or an unknown
     *                                  value, or an end message carries no end time
     */
    public static function fromArray(array $data): self
    {
        $category = self::string($data, 'category');
        $type = self::string($data, 'type');

        try {
            return new self(
                self::string($data, 'id'),
                self::int($data, 'eventId'),
                self::CATEGORIES[$category] ?? throw new UnexpectedValueException(sprintf('Unknown Latarnia message category "%s".', $category)),
                LatarniaMessageType::tryFrom($type) ?? throw new UnexpectedValueException(sprintf('Unknown Latarnia message type "%s".', $type)),
                self::string($data, 'title'),
                self::string($data, 'text'),
                self::dateTime($data, 'start'),
                ($data['end'] ?? null) === null ? null : self::dateTime($data, 'end'),
                self::int($data, 'version'),
                self::dateTime($data, 'published'),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws UnexpectedValueException
     */
    private static function string(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf('The Latarnia message field "%s" is missing or not a string.', $field));
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws UnexpectedValueException
     */
    private static function int(array $data, string $field): int
    {
        $value = $data[$field] ?? null;
        if (! is_int($value)) {
            throw new UnexpectedValueException(sprintf('The Latarnia message field "%s" is missing or not an integer.', $field));
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws UnexpectedValueException
     */
    private static function dateTime(array $data, string $field): DateTimeImmutable
    {
        $value = self::string($data, $field);
        if (preg_match(self::DATE_TIME, $value) !== 1) {
            throw new UnexpectedValueException(sprintf('The Latarnia message field "%s" is not a date and time with an offset: "%s".', $field, $value));
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new UnexpectedValueException(sprintf('The Latarnia message field "%s" is not a valid date and time: "%s".', $field, $value), 0, $exception);
        }
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The generation timestamp of a VERI*FACTU record, carried together with its exact XML text.
 */
final readonly class RecordTimestamp
{
    private const PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})\z/';

    private function __construct(
        public string $text,
        public DateTimeImmutable $value,
    ) {}

    /** Writes the instant in the given zone as `Y-m-d\TH:i:sP`; fractional seconds are dropped. */
    public static function of(DateTimeImmutable $at, DateTimeZone $zone): self
    {
        $text = $at->setTimezone($zone)->format('Y-m-d\TH:i:sP');

        $value = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $text);
        assert($value !== false);

        return new self($text, $value);
    }

    public static function fromLiteral(string $text): self
    {
        $text = trim($text);

        if (preg_match(self::PATTERN, $text) !== 1) {
            throw new InvalidRecord(sprintf('The timestamp "%s" does not match the AEAT timestamp pattern.', $text));
        }

        // Parse with at most microsecond precision; the kept text is untouched.
        $parsable = (string) preg_replace('/(\.\d{6})\d+/', '$1', $text);
        $value = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $parsable)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $parsable);
        $errors = DateTimeImmutable::getLastErrors();

        if ($value === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidRecord(sprintf('The timestamp "%s" is not a valid date and time.', $text));
        }

        return new self($text, $value);
    }
}

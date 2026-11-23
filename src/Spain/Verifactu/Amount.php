<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * A monetary amount of a VERI*FACTU record, carried together with its exact XML text.
 *
 * The text is what enters the fingerprint, so it is never reformatted after construction.
 * {@see self::of()} produces the canonical form (scale 2, half-up); {@see self::fromLiteral()}
 * keeps a foreign record's text verbatim.
 */
final readonly class Amount
{
    private const LITERAL_PATTERN = '/\A(\+|-)?\d{1,12}(\.\d{0,2})?\z/';

    private function __construct(
        public string $text,
        public BigDecimal $value,
    ) {}

    public static function of(BigDecimal|string|int $amount): self
    {
        try {
            $value = $amount instanceof BigDecimal ? $amount : BigDecimal::of($amount);
        } catch (MathException $e) {
            throw new InvalidRecord('The amount is not a number.', 0, $e);
        }

        $rounded = $value->toScale(2, RoundingMode::HalfUp);
        // A value that rounds to zero is written without a sign.
        $text = $rounded->isZero() ? '0.00' : (string) $rounded;

        if (preg_match(self::LITERAL_PATTERN, $text) !== 1) {
            throw new InvalidRecord(sprintf('The amount "%s" exceeds twelve integer digits.', $text));
        }

        return new self($text, $rounded);
    }

    public static function fromLiteral(string $text): self
    {
        $text = trim($text);

        if (preg_match(self::LITERAL_PATTERN, $text) !== 1) {
            throw new InvalidRecord(sprintf('The amount "%s" does not match the AEAT amount pattern.', $text));
        }

        return new self($text, BigDecimal::of(rtrim($text, '.')));
    }

    public function negated(): self
    {
        return self::of($this->value->negated());
    }

    public function isEqualTo(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    public function abs(): BigDecimal
    {
        return $this->value->abs();
    }
}

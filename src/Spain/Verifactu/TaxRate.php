<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * A tax rate percentage of a VERI*FACTU record, carried together with its exact XML text.
 */
final readonly class TaxRate
{
    private const PATTERN = '/\A\d{1,3}(\.\d{0,2})?\z/';

    private function __construct(
        public string $text,
        public BigDecimal $value,
    ) {}

    public static function of(BigDecimal|string|int $rate): self
    {
        try {
            $value = $rate instanceof BigDecimal ? $rate : BigDecimal::of($rate);
        } catch (MathException $e) {
            throw new InvalidRecord('The tax rate is not a number.', 0, $e);
        }

        $rounded = $value->toScale(2, RoundingMode::HalfUp);
        $text = (string) $rounded;

        if (preg_match(self::PATTERN, $text) !== 1) {
            throw new InvalidRecord(sprintf('The tax rate "%s" is outside 0 to 999.99.', $text));
        }

        return new self($text, $rounded);
    }

    public static function fromLiteral(string $text): self
    {
        $text = trim($text);

        if (preg_match(self::PATTERN, $text) !== 1) {
            throw new InvalidRecord(sprintf('The tax rate "%s" does not match the AEAT rate pattern.', $text));
        }

        return new self($text, BigDecimal::of(rtrim($text, '.')));
    }

    public function isEqualTo(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use InvalidArgumentException;

/**
 * An exact decimal amount in a currency.
 */
final readonly class Money
{
    public BigDecimal $amount;

    public function __construct(BigDecimal|string|int $amount, public CurrencyCode $currency)
    {
        $this->amount = BigDecimal::of($amount);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    /**
     * Rounds half up to the given scale; EN 16931 amounts use two decimals (BR-DEC rules).
     */
    public function rounded(int $scale = 2): self
    {
        return new self($this->amount->toScale($scale, RoundingMode::HALF_UP), $this->currency);
    }

    public function isEqualTo(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount->isEqualTo($other->amount);
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function __toString(): string
    {
        return $this->amount.' '.$this->currency->value;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Cannot combine amounts in %s and %s.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }
}

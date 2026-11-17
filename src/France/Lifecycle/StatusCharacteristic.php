<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * A data item attached to a status, such as the amount collected at one VAT rate (MEN) or a
 * corrected invoice value.
 */
final readonly class StatusCharacteristic
{
    /** Type of the data item (MDT-207). */
    public CharacteristicType $type;

    /** Amount (MDT-215). */
    public ?BigDecimal $amount;

    /** Currency of the amount. */
    public ?CurrencyCode $currency;

    /** Percentage (MDT-224), such as the VAT rate of a collected amount. */
    public ?BigDecimal $percent;

    /** Date (MDT-219). */
    public ?DateTimeImmutable $date;

    /** Whether the value changed (MDT-209). */
    public ?bool $changed;

    /** Identifier of the data item (MDT-206), such as a business term id. */
    public ?string $id;

    /** Name of the data item (MDT-211). */
    public ?string $name;

    /** Location of the data item in the invoice XML (MDT-213). */
    public ?string $location;

    /**
     * @param  BigDecimal|numeric-string|int|null  $amount
     * @param  BigDecimal|numeric-string|int|null  $percent
     */
    public function __construct(
        CharacteristicType $type,
        BigDecimal|string|int|float|null $amount = null,
        ?CurrencyCode $currency = null,
        BigDecimal|string|int|float|null $percent = null,
        ?DateTimeImmutable $date = null,
        ?bool $changed = null,
        ?string $id = null,
        ?string $name = null,
        ?string $location = null,
    ) {
        $this->type = $type;
        $this->amount = Decimal::ofNullable($amount, 'MDT-215');
        $this->currency = $currency;
        $this->percent = Decimal::ofNullable($percent, 'MDT-224');
        $this->date = $date;
        $this->changed = $changed;
        $this->id = $id;
        $this->name = $name;
        $this->location = $location;
    }

    /**
     * The amount collected at one VAT rate, reported with status 212 (Encaissée).
     */
    public static function collected(CollectedAmount $amount, CurrencyCode $currency): self
    {
        return new self(CharacteristicType::MEN, $amount->amount, $currency, $amount->vatRate, changed: false);
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * One line of the order an advance invoice refers to (ZamowienieWiersz).
 */
final readonly class OrderLine
{
    /** Quantity (P_8BZ). */
    public ?BigDecimal $quantity;

    /** Unit net price (P_9AZ). */
    public ?BigDecimal $unitNetPrice;

    /** Net amount (P_11NettoZ). */
    public ?BigDecimal $netAmount;

    /** VAT amount (P_11VatZ). */
    public ?BigDecimal $vatAmount;

    /** VAT rate of a one-stop-shop supply in percent (P_12Z_XII). */
    public ?BigDecimal $ossRate;

    /** Excise duty amount (KwotaAkcyzyZ). */
    public ?BigDecimal $excise;

    /**
     * @param  BigDecimal|numeric-string|int|null  $quantity
     * @param  BigDecimal|numeric-string|int|null  $unitNetPrice
     * @param  BigDecimal|numeric-string|int|null  $netAmount
     * @param  BigDecimal|numeric-string|int|null  $vatAmount
     * @param  BigDecimal|numeric-string|int|null  $ossRate
     * @param  BigDecimal|numeric-string|int|null  $excise
     *
     * @throws InvalidArgumentException when the procedure is an import procedure (I_42, I_63),
     *                                  which an order line cannot carry
     */
    public function __construct(
        /** Universal unique line identifier (UU_IDZ). */
        public ?string $uuid = null,
        /** Name of the goods or service (P_7Z). */
        public ?string $name = null,
        /** Internal product index (IndeksZ). */
        public ?string $index = null,
        /** Global Trade Item Number (GTINZ). */
        public ?string $gtin = null,
        /** PKWiU classification (PKWiUZ). */
        public ?string $pkwiu = null,
        /** Combined Nomenclature code (CNZ). */
        public ?string $cn = null,
        /** PKOB classification (PKOBZ). */
        public ?string $pkob = null,
        /** Unit of measure as free text (P_8AZ). */
        public ?string $unitLabel = null,
        BigDecimal|string|int|float|null $quantity = null,
        BigDecimal|string|int|float|null $unitNetPrice = null,
        BigDecimal|string|int|float|null $netAmount = null,
        BigDecimal|string|int|float|null $vatAmount = null,
        /** VAT rate code (P_12Z). */
        public ?VatRateCode $rateCode = null,
        BigDecimal|string|int|float|null $ossRate = null,
        /** Goods listed in annex 15 of the VAT Act (P_12Z_Zal_15). */
        public bool $annex15 = false,
        /** GTU goods and services group (GTUZ). */
        public ?Gtu $gtu = null,
        /** Procedure marking (ProceduraZ). */
        public ?Procedure $procedure = null,
        BigDecimal|string|int|float|null $excise = null,
        /** Row describing the state before a correction (StanPrzedZ). */
        public bool $stateBefore = false,
    ) {
        if ($procedure !== null && ! $procedure->allowedInOrder()) {
            throw new InvalidArgumentException(sprintf(
                'The procedure %s is not allowed on an order line (ProceduraZ).',
                $procedure->value,
            ));
        }
        $this->quantity = Decimal::ofNullable($quantity, 'P_8BZ');
        $this->unitNetPrice = Decimal::ofNullable($unitNetPrice, 'P_9AZ');
        $this->netAmount = Decimal::ofNullable($netAmount, 'P_11NettoZ');
        $this->vatAmount = Decimal::ofNullable($vatAmount, 'P_11VatZ');
        $this->ossRate = Decimal::ofNullable($ossRate, 'P_12Z_XII');
        $this->excise = Decimal::ofNullable($excise, 'KwotaAkcyzyZ');
    }
}

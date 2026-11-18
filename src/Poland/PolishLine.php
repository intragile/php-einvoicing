<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * Polish FA(3) line extension (FaWiersz data the EN 16931 line does not carry), attached to an
 * invoice line through its extension bag.
 *
 * The omission markers (unitOmitted, quantityOmitted, unitPriceOmitted, rateCodeOmitted) record
 * that a row read from FA(3) had no P_8A, P_8B, P_9A or P_12, so writing the read invoice back
 * keeps those optional elements absent. Invoices built in code leave them false and get the
 * elements written.
 */
final readonly class PolishLine
{
    /** Unit price with VAT (P_9B). */
    public ?BigDecimal $grossUnitPrice;

    /** Line amount with VAT (P_11A). */
    public ?BigDecimal $grossAmount;

    /** Line VAT amount (P_11Vat). */
    public ?BigDecimal $vatAmount;

    /**
     * VAT rate of a supply under the one-stop-shop procedure (OSS) in percent (P_12_XII). The line
     * then feeds the OSS summary (P_13_5, P_14_5) and is written without a rate code (P_12); its VAT
     * category is S at this rate.
     */
    public ?BigDecimal $ossRate;

    /** Excise duty amount (KwotaAkcyzy). */
    public ?BigDecimal $excise;

    /** Exchange rate of the line (KursWaluty). */
    public ?BigDecimal $exchangeRate;

    /**
     * @param  BigDecimal|numeric-string|int|null  $grossUnitPrice
     * @param  BigDecimal|numeric-string|int|null  $grossAmount
     * @param  BigDecimal|numeric-string|int|null  $vatAmount
     * @param  BigDecimal|numeric-string|int|null  $ossRate
     * @param  BigDecimal|numeric-string|int|null  $excise
     * @param  BigDecimal|numeric-string|int|null  $exchangeRate
     *
     * @throws InvalidArgumentException when a margin line has a VAT rate code, a bucket line is
     *                                  flagged as a state-before row, or the rate code omission
     *                                  marker is set on a margin line
     */
    public function __construct(
        /** VAT rate code (P_12); overrides the code derived from the line VAT category and rate. */
        public ?VatRateCode $rateCode = null,
        /** Unit of measure as free text (P_8A); overrides the unit code (BT-130). */
        public ?string $unitLabel = null,
        /** Supply date of the line (P_6A). */
        public ?DateTimeImmutable $supplyDate = null,
        BigDecimal|string|int|float|null $grossUnitPrice = null,
        BigDecimal|string|int|float|null $grossAmount = null,
        BigDecimal|string|int|float|null $vatAmount = null,
        BigDecimal|string|int|float|null $ossRate = null,
        /** Goods listed in annex 15 of the VAT Act (P_12_Zal_15). */
        public bool $annex15 = false,
        BigDecimal|string|int|float|null $excise = null,
        /** GTU goods and services group (GTU). */
        public ?Gtu $gtu = null,
        /** Procedure marking (Procedura). */
        public ?Procedure $procedure = null,
        BigDecimal|string|int|float|null $exchangeRate = null,
        /**
         * Row describing the state before a correction (StanPrzed). The line holds it in storno
         * form, quantity and net amount negated, so the invoice totals give the difference; the
         * writer restores the sign.
         */
        public bool $stateBefore = false,
        /** Line under a margin scheme, which carries no VAT rate (P_12). */
        public bool $margin = false,
        /**
         * Synthetic line standing for one P_13 bucket of an invoice without FaWiersz rows (advance
         * invoices, period-discount corrections); never written as FaWiersz.
         */
        public bool $bucketLine = false,
        /** PKWiU classification (PKWiU). */
        public ?string $pkwiu = null,
        /** Combined Nomenclature code (CN). */
        public ?string $cn = null,
        /** PKOB classification (PKOB). */
        public ?string $pkob = null,
        /**
         * The source row had no unit (P_8A). Set by the FA(3) reader so that the row is written back
         * without it; the writer leaves P_8A out only while the line still has unit C62 and no unit
         * label, the values read from the missing element.
         */
        public bool $unitOmitted = false,
        /**
         * The source row had no quantity (P_8B). Set by the FA(3) reader; the writer leaves P_8B out
         * only while the quantity is still 1 (-1 for a state-before row held in storno form).
         */
        public bool $quantityOmitted = false,
        /**
         * The source row had no net unit price (P_9A). Set by the FA(3) reader; the writer leaves
         * P_9A out only while the price still equals the one read from its absence: the net amount
         * (P_11, else P_11A) divided by the quantity.
         */
        public bool $unitPriceOmitted = false,
        /**
         * The source row had no VAT rate code (P_12) and is not a margin scheme row. Set by the
         * FA(3) reader; the writer leaves P_12 out. The rate code override, else the line VAT
         * category and rate, still decide the summary bucket (P_13_x) the line feeds.
         */
        public bool $rateCodeOmitted = false,
    ) {
        if ($margin && $rateCode !== null) {
            throw new InvalidArgumentException(sprintf(
                'A margin scheme line carries no VAT rate code (P_12), got %s.',
                $rateCode->value,
            ));
        }
        if ($rateCodeOmitted && $margin) {
            throw new InvalidArgumentException('A margin scheme line carries no VAT rate code (P_12) already; it takes no rate code omission marker.');
        }
        if ($bucketLine && $stateBefore) {
            throw new InvalidArgumentException('A bucket line is not a state-before row (StanPrzed).');
        }
        $this->grossUnitPrice = Decimal::ofNullable($grossUnitPrice, 'P_9B');
        $this->grossAmount = Decimal::ofNullable($grossAmount, 'P_11A');
        $this->vatAmount = Decimal::ofNullable($vatAmount, 'P_11Vat');
        $this->ossRate = Decimal::ofNullable($ossRate, 'P_12_XII');
        $this->excise = Decimal::ofNullable($excise, 'KwotaAkcyzy');
        $this->exchangeRate = Decimal::ofNullable($exchangeRate, 'KursWaluty');
    }
}

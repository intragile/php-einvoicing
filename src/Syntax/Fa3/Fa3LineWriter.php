<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;

/**
 * Writes one invoice line as an FA(3) FaWiersz and resolves the VAT rate code (P_12) and the
 * summary bucket (P_13_x) of a line.
 *
 * Line numbers (NrWierszaFa) run 1 to n in output order; the line identifier (BT-126) goes to
 * UU_ID when it differs from that number. The unit (P_8A) is the Polish unit label, else the
 * UN/ECE code of BT-130; a one-stop-shop line (PolishLine::$ossRate without a rate code override)
 * is written with P_12_XII and without P_12, a line with both with both elements; with a label, a BT-130 other than C62 goes to a line description
 * (DodatkowyOpis) under its fixed key. P_10 is the sum of the line allowances (BT-136). P_8A, P_8B and P_9A are
 * left out for a row read without them (the PolishLine omission markers) as long as the line still
 * holds the values read from their absence; P_12 is left out for a row read without it.
 *
 * A state-before row of a correction (PolishLine::$stateBefore) is held in storno form, so the
 * invoice totals give the difference; it is written with StanPrzed = 1 and the sign of its
 * quantity and amounts (P_8B, P_10, P_11, P_11A, P_11Vat, KwotaAkcyzy) restored. Unit prices are
 * written as held.
 *
 * @internal
 */
final class Fa3LineWriter
{
    /**
     * The P_12 code of the line: the Polish override, else the code of its VAT category and rate.
     * A margin scheme line and a one-stop-shop line (an OSS rate without a rate code override)
     * have none.
     *
     * @throws InvalidArgumentException when the category and rate have no FA(3) code
     */
    public static function rateCode(InvoiceLine $line, ?CountryCode $buyerCountry): ?VatRateCode
    {
        $polish = $line->extensions()->get(PolishLine::class);
        if ($polish?->margin === true || self::isOss($line)) {
            return null;
        }

        return $polish->rateCode ?? VatRateCode::fromModel($line->vat->category, $line->vat->rate, $buyerCountry);
    }

    /**
     * The summary bucket the line feeds: the margin bucket for a margin scheme line, the
     * one-stop-shop bucket (P_13_5) for a line with an OSS rate and no rate code override, else
     * the bucket of its rate code.
     *
     * @throws InvalidArgumentException when the line has no FA(3) code or its code has no bucket
     */
    public static function bucket(InvoiceLine $line, ?CountryCode $buyerCountry): VatBucket
    {
        $polish = $line->extensions()->get(PolishLine::class);
        if ($polish?->margin === true) {
            return VatBucket::Margin;
        }
        if (self::isOss($line)) {
            return VatBucket::Oss;
        }
        $code = self::rateCode($line, $buyerCountry) ?? VatRateCode::fromModel($line->vat->category, $line->vat->rate, $buyerCountry);
        $bucket = $code->bucket();
        if ($bucket === null) {
            throw new InvalidArgumentException(sprintf(
                'rate code "%s" has no P_13 summary bucket in FA(3): the brochure ties no rate code to the taxi lump-sum summary (P_13_4), so the row cannot be written',
                $code->value,
            ));
        }

        return $bucket;
    }

    /**
     * Whether the line is a one-stop-shop line: an OSS rate (P_12_XII) without a rate code
     * override. A line with both is written with both elements in the bucket of its rate code.
     */
    public static function isOss(InvoiceLine $line): bool
    {
        $polish = $line->extensions()->get(PolishLine::class);

        return $polish?->ossRate !== null && $polish->rateCode === null && ! $polish->margin;
    }

    /**
     * The unit code (BT-130) of a line whose P_8A is the Polish unit label, when that code is not
     * C62. It is written as a line description so the code is not lost; null otherwise.
     */
    public static function unitCodeBesideLabel(InvoiceLine $line): ?string
    {
        $label = $line->extensions()->get(PolishLine::class)?->unitLabel;

        return $label === null || $line->unit === UnitCode::C62 ? null : $line->unit->value;
    }

    /**
     * Whether the line is written as a FaWiersz; synthetic bucket lines are not.
     */
    public static function isWritten(InvoiceLine $line): bool
    {
        return $line->extensions()->get(PolishLine::class)?->bucketLine !== true;
    }

    public static function write(Fa3Output $output, DOMElement $fa, InvoiceLine $line, int $position, ?CountryCode $buyerCountry): void
    {
        $polish = $line->extensions()->get(PolishLine::class);
        $before = $polish?->stateBefore === true;
        $sign = static fn (?BigDecimal $value): ?BigDecimal => $before ? $value?->negated() : $value;
        $item = $line->item;
        $row = $output->add($fa, 'FaWiersz');

        $output->text($row, 'NrWierszaFa', (string) $position);
        $output->text($row, 'UU_ID', $line->id === (string) $position ? null : $line->id);
        $output->date($row, 'P_6A', $polish?->supplyDate);
        $output->text($row, 'P_7', $item->name === '' ? null : $item->name);
        $output->text($row, 'Indeks', $item->sellersIdentifier);
        $output->text($row, 'GTIN', $item->standardIdentifier?->scheme === IdentifierScheme::C_0160->value ? $item->standardIdentifier->value : null);
        $output->text($row, 'PKWiU', $polish?->pkwiu);
        $output->text($row, 'CN', $polish?->cn);
        $output->text($row, 'PKOB', $polish?->pkob);
        $quantity = $sign($line->quantity) ?? $line->quantity;
        $omitQuantity = $polish?->quantityOmitted === true && $quantity->isEqualTo(1);
        $omitUnit = $polish?->unitOmitted === true && $polish->unitLabel === null && $line->unit === UnitCode::C62;
        $omitPrice = $polish?->unitPriceOmitted === true && $line->price->netPrice->isEqualTo(
            Fa3LineMapper::unitPrice($sign($line->netAmount) ?? $sign($polish->grossAmount) ?? BigDecimal::zero(), $omitQuantity ? BigDecimal::one() : $quantity),
        );
        $output->text($row, 'P_8A', $omitUnit ? null : $polish->unitLabel ?? $line->unit->value);
        $output->decimal($row, 'P_8B', $omitQuantity ? null : $quantity);
        $output->decimal($row, 'P_9A', $omitPrice ? null : $line->price->netPrice);
        $output->decimal($row, 'P_9B', $polish?->grossUnitPrice);
        $output->decimal($row, 'P_10', $sign(self::allowanceTotal($line)));
        $output->decimal($row, 'P_11', $sign($line->netAmount));
        $output->decimal($row, 'P_11A', $sign($polish?->grossAmount));
        $output->decimal($row, 'P_11Vat', $sign($polish?->vatAmount));
        $output->text($row, 'P_12', $polish?->rateCodeOmitted === true ? null : self::rateCode($line, $buyerCountry)?->value);
        $output->decimal($row, 'P_12_XII', $polish?->ossRate);
        if ($polish?->annex15 === true) {
            $output->marker($row, 'P_12_Zal_15');
        }
        $output->decimal($row, 'KwotaAkcyzy', $sign($polish?->excise));
        $output->text($row, 'GTU', $polish?->gtu?->value);
        $output->text($row, 'Procedura', $polish?->procedure?->value);
        $output->decimal($row, 'KursWaluty', $polish?->exchangeRate);
        if ($before) {
            $output->marker($row, 'StanPrzed');
        }
    }

    /**
     * Sum of the line allowances (BT-136), or null for a line without allowances.
     */
    public static function allowanceTotal(InvoiceLine $line): ?BigDecimal
    {
        if ($line->allowances === []) {
            return null;
        }
        $total = BigDecimal::zero();
        foreach ($line->allowances as $allowance) {
            $total = $total->plus($allowance->amount);
        }

        return $total;
    }

    private function __construct() {}
}

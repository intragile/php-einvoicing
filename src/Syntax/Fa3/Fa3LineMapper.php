<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Poland\Gtu;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\Procedure;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;

/**
 * Reads FA(3) FaWiersz rows into invoice lines and builds the synthetic bucket lines of an
 * invoice without rows; the inverse of Fa3LineWriter.
 *
 * - The line identifier (BT-126) is UU_ID, else NrWierszaFa. The quantity is P_8B, else 1; the
 *   net price is P_9A, else P_11 (else P_11A) divided by the quantity; the item name is P_7, else
 *   empty.
 * - P_8A is the unit code (BT-130) when it is a UN/ECE Recommendation 20 code; any other text is
 *   unit C62 with the text as the Polish unit label, unless a line description under the BT-130
 *   key gives the unit code beside the label.
 * - P_12 gives the VAT category and rate; the code is kept as the Polish rate code when the
 *   category and rate would derive another code. A row without P_12 is a margin scheme line
 *   when the invoice declares a margin scheme. Otherwise it is marked as written without P_12
 *   (PolishLine::$rateCodeOmitted) and takes the category and rate of the default code of the
 *   invoice's only summary bucket; with several buckets the row's bucket is unknown, so it is
 *   category O without a rate and no code is guessed. A row with an OSS rate (P_12_XII) and
 *   without P_12 is a one-stop-shop line: category S at that rate.
 * - P_10 is one line allowance with that amount; its reason is the fixed-key description of
 *   the row, else "P_10".
 * - A row without P_8A, P_8B or P_9A is marked with the matching PolishLine omission marker, so
 *   the writer leaves the element out again.
 * - A state-before row (StanPrzed) is held in storno form: P_8B, P_10, P_11, P_11A, P_11Vat and
 *   KwotaAkcyzy are negated; unit prices are kept as written.
 *
 * @internal
 */
final class Fa3LineMapper
{
    public function __construct(private readonly Fa3Document $d) {}

    /**
     * @param  array<string, string>  $terms  EN 16931 line terms carried as fixed-key descriptions
     *                                        of this row, keyed by business term (BT-127, BT-154,
     *                                        BT-128, BT-132, BT-133, BT-156, BT-159, BT-130, and BT-139,
     *                                        BT-137, BT-138, BT-140 of the allowance); values are
     *                                        already checked
     * @param  ?VatBucket  $onlyBucket  the summary bucket when the invoice writes exactly one
     */
    public function line(DOMElement $row, array $terms, ?CountryCode $buyerCountry, bool $marginInvoice, ?VatBucket $onlyBucket = null): InvoiceLine
    {
        $d = $this->d;
        $before = $d->flag('fa:StanPrzed', $row);
        $sign = static fn (?BigDecimal $value): ?BigDecimal => $before ? $value?->negated() : $value;

        $writtenQuantity = $d->decimal('fa:P_8B', $row);
        $quantity = $writtenQuantity ?? BigDecimal::one();
        $net = $d->decimal('fa:P_11', $row);
        $gross = $d->decimal('fa:P_11A', $row);
        $price = $d->decimal('fa:P_9A', $row) ?? self::unitPrice($net ?? $gross ?? BigDecimal::zero(), $quantity);
        $allowance = $d->decimal('fa:P_10', $row);

        [$unit, $unitLabel] = $this->unit($row);
        if ($unitLabel !== null && isset($terms['BT-130'])) {
            $unit = UnitCode::from($terms['BT-130']);
        }
        $codeElement = $d->one('fa:P_12', $row);
        $code = $d->code(VatRateCode::class, $codeElement);
        $margin = false;
        $omitted = false;
        $ossRate = $d->decimal('fa:P_12_XII', $row);
        if ($code !== null) {
            [$vat, $override] = self::vat($code, $buyerCountry);
        } elseif ($codeElement === null && $ossRate !== null) {
            $vat = new LineVat(VatCategory::StandardRate, $ossRate);
            $override = null;
        } else {
            $margin = $codeElement === null && $marginInvoice;
            $omitted = $codeElement === null && ! $margin;
            $default = $omitted && $onlyBucket !== null ? self::defaultCode($onlyBucket) : null;
            [$vat, $override] = $default === null
                ? [new LineVat(VatCategory::NotSubjectToVat), null]
                : self::vat($default, $buyerCountry);
        }

        $position = $d->token('fa:NrWierszaFa', $row);
        $gtin = $d->text('fa:GTIN', $row);
        $origin = isset($terms['BT-159']) ? CountryCode::tryFrom($terms['BT-159']) : null;
        $line = new InvoiceLine(
            id: $d->text('fa:UU_ID', $row) ?? $position ?? '',
            quantity: $sign($quantity) ?? $quantity,
            unit: $unit,
            price: new Price($price),
            item: new Item(
                name: $d->text('fa:P_7', $row) ?? '',
                description: $terms['BT-154'] ?? null,
                sellersIdentifier: $d->text('fa:Indeks', $row),
                buyersIdentifier: $terms['BT-156'] ?? null,
                standardIdentifier: $gtin === null ? null : new Identifier($gtin, IdentifierScheme::C_0160),
                originCountry: $origin,
            ),
            vat: $vat,
            netAmount: $sign($net),
            note: $terms['BT-127'] ?? null,
            objectIdentifier: isset($terms['BT-128']) ? new Identifier($terms['BT-128']) : null,
            orderLineReference: $terms['BT-132'] ?? null,
            buyerAccountingReference: $terms['BT-133'] ?? null,
            allowances: $allowance === null ? [] : [new LineAllowance(
                amount: $sign($allowance) ?? $allowance,
                baseAmount: isset($terms['BT-137']) ? BigDecimal::of($terms['BT-137']) : null,
                percentage: isset($terms['BT-138']) ? BigDecimal::of($terms['BT-138']) : null,
                reason: $terms['BT-139'] ?? Fa3Format::ALLOWANCE_REASON,
                reasonCode: isset($terms['BT-140']) ? AllowanceReasonCode::tryFrom($terms['BT-140']) : null,
            )],
        );

        $polish = new PolishLine(
            rateCode: $override,
            unitLabel: $unitLabel,
            supplyDate: $d->date('fa:P_6A', $row),
            grossUnitPrice: $d->decimal('fa:P_9B', $row),
            grossAmount: $sign($gross),
            vatAmount: $sign($d->decimal('fa:P_11Vat', $row)),
            ossRate: $ossRate,
            annex15: $d->flag('fa:P_12_Zal_15', $row),
            excise: $sign($d->decimal('fa:KwotaAkcyzy', $row)),
            gtu: $d->code(Gtu::class, $d->one('fa:GTU', $row)),
            procedure: $d->code(Procedure::class, $d->one('fa:Procedura', $row)),
            exchangeRate: $d->decimal('fa:KursWaluty', $row),
            stateBefore: $before,
            margin: $margin,
            pkwiu: $d->text('fa:PKWiU', $row),
            cn: $d->text('fa:CN', $row),
            pkob: $d->text('fa:PKOB', $row),
            unitOmitted: ! $d->has('fa:P_8A', $row),
            quantityOmitted: $writtenQuantity === null,
            unitPriceOmitted: ! $d->has('fa:P_9A', $row),
            rateCodeOmitted: $omitted,
        );

        return $polish == new PolishLine ? $line : $line->withExtension($polish);
    }

    /**
     * The synthetic line standing for one P_13 bucket of an invoice without FaWiersz rows: item
     * name = the bucket's element name, quantity 1, net amount and price = the bucket's net.
     */
    public static function bucketLine(string $id, VatBucket $bucket, BigDecimal $net, ?CountryCode $buyerCountry): InvoiceLine
    {
        $code = self::defaultCode($bucket);
        [$vat, $override] = $code === null
            ? [new LineVat(self::codelessCategory($bucket)), null]
            : self::vat($code, $buyerCountry);

        return (new InvoiceLine(
            id: $id,
            quantity: BigDecimal::one(),
            unit: UnitCode::C62,
            price: new Price($net),
            item: new Item($bucket->netElement()),
            vat: $vat,
            netAmount: $net,
        ))->withExtension(new PolishLine(rateCode: $override, bucketLine: true, margin: $bucket === VatBucket::Margin));
    }

    /**
     * The line VAT of a rate code and the code itself when the category and rate would derive
     * another code for this buyer country.
     *
     * @return array{LineVat, ?VatRateCode}
     */
    public static function vat(VatRateCode $code, ?CountryCode $buyerCountry): array
    {
        [$category, $rate] = $code->toModel();
        try {
            $derived = VatRateCode::fromModel($category, $rate, $buyerCountry);
        } catch (InvalidArgumentException) {
            $derived = null;
        }

        return [new LineVat($category, $rate), $derived === $code ? null : $code];
    }

    /**
     * The rate code a bucket stands for when no row tells: the basic rates 23, 8 and 5, else the
     * one code of the bucket; null for the taxi lump sum, one-stop-shop and margin buckets.
     */
    public static function defaultCode(VatBucket $bucket): ?VatRateCode
    {
        return match ($bucket) {
            VatBucket::Basic => VatRateCode::Rate23,
            VatBucket::Reduced1 => VatRateCode::Rate8,
            VatBucket::Reduced2 => VatRateCode::Rate5,
            VatBucket::ZeroDomestic => VatRateCode::ZeroDomestic,
            VatBucket::ZeroIntraCommunity => VatRateCode::ZeroIntraCommunity,
            VatBucket::ZeroExport => VatRateCode::ZeroExport,
            VatBucket::Exempt => VatRateCode::Exempt,
            VatBucket::OutsideTerritory => VatRateCode::OutsideTerritory,
            VatBucket::EuServicesArticle100 => VatRateCode::EuServicesArticle100,
            VatBucket::ReverseCharge => VatRateCode::ReverseCharge,
            VatBucket::TaxiLumpSum, VatBucket::Oss, VatBucket::Margin => null,
        };
    }

    /**
     * The VAT category of a bucket without a rate code: standard rate for the taxi lump sum and
     * one-stop-shop buckets, not subject to VAT for the margin bucket.
     */
    public static function codelessCategory(VatBucket $bucket): VatCategory
    {
        return $bucket === VatBucket::Margin ? VatCategory::NotSubjectToVat : VatCategory::StandardRate;
    }

    /**
     * @return array{UnitCode, ?string}
     */
    private function unit(DOMElement $row): array
    {
        $label = $this->d->text('fa:P_8A', $row);
        if ($label === null) {
            return [UnitCode::C62, null];
        }
        $code = UnitCode::tryFrom($label);

        return $code === null ? [UnitCode::C62, $label] : [$code, null];
    }

    /**
     * The net unit price of a row without P_9A: the amount divided by the quantity, exactly at a
     * scale of at least 2 when possible, else rounded half up to 8 decimals.
     */
    public static function unitPrice(BigDecimal $amount, BigDecimal $quantity): BigDecimal
    {
        if ($quantity->isZero()) {
            return $amount;
        }
        try {
            return $amount->dividedBy($quantity, max($amount->getScale(), 2), RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            return $amount->dividedBy($quantity, 8, RoundingMode::HalfUp);
        }
    }
}

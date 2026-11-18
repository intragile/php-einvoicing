<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use InvalidArgumentException;

/**
 * FA(3) line VAT rate code (P_12, XSD type TStawkaPodatku).
 */
enum VatRateCode: string
{
    case Rate23 = '23';
    case Rate22 = '22';
    case Rate8 = '8';
    case Rate7 = '7';
    case Rate5 = '5';
    case Rate4 = '4';
    case Rate3 = '3';
    /** 0 % on a domestic supply, excluding intra-Community supply and export. */
    case ZeroDomestic = '0 KR';
    /** 0 % on an intra-Community supply of goods. */
    case ZeroIntraCommunity = '0 WDT';
    /** 0 % on the export of goods. */
    case ZeroExport = '0 EX';
    /** Exempt from VAT. */
    case Exempt = 'zw';
    /** Domestic reverse charge. */
    case ReverseCharge = 'oo';
    /** Not taxable in Poland: supplies outside the country other than np II. */
    case OutsideTerritory = 'np I';
    /** Not taxable in Poland: services under art. 100(1)(4) of the VAT Act (EU B2B services). */
    case EuServicesArticle100 = 'np II';

    /**
     * The invoice-level summary bucket the code feeds, or null for 4 % and 3 % (no bucket assigned).
     */
    public function bucket(): ?VatBucket
    {
        return match ($this) {
            self::Rate23, self::Rate22 => VatBucket::Basic,
            self::Rate8, self::Rate7 => VatBucket::Reduced1,
            self::Rate5 => VatBucket::Reduced2,
            self::Rate4, self::Rate3 => null,
            self::ZeroDomestic => VatBucket::ZeroDomestic,
            self::ZeroIntraCommunity => VatBucket::ZeroIntraCommunity,
            self::ZeroExport => VatBucket::ZeroExport,
            self::Exempt => VatBucket::Exempt,
            self::OutsideTerritory => VatBucket::OutsideTerritory,
            self::EuServicesArticle100 => VatBucket::EuServicesArticle100,
            self::ReverseCharge => VatBucket::ReverseCharge,
        };
    }

    /**
     * The default code for a line's VAT category and rate.
     *
     * Reverse charge maps to "oo" when the buyer is Polish or the buyer country is unknown,
     * otherwise to "np II".
     *
     * @throws InvalidArgumentException When the category or rate has no FA(3) code with a summary bucket.
     */
    public static function fromModel(VatCategory $category, ?BigDecimal $rate, ?CountryCode $buyerCountry): self
    {
        $code = match ($category) {
            VatCategory::StandardRate => self::standardRate($rate),
            VatCategory::ZeroRated => self::ZeroDomestic,
            VatCategory::IntraCommunitySupply => self::ZeroIntraCommunity,
            VatCategory::ExportOutsideEu => self::ZeroExport,
            VatCategory::Exempt => self::Exempt,
            VatCategory::ReverseCharge => $buyerCountry === null || $buyerCountry === CountryCode::PL
                ? self::ReverseCharge
                : self::EuServicesArticle100,
            VatCategory::NotSubjectToVat => self::OutsideTerritory,
            VatCategory::CanaryIslands, VatCategory::CeutaAndMelilla, VatCategory::TransferredVat => null,
        };

        if ($code === null) {
            throw new InvalidArgumentException(sprintf(
                'VAT category %s with %s has no FA(3) P_12 rate code; set the Polish line rate code explicitly.',
                $category->value,
                $rate === null ? 'no rate' : 'rate '.$rate->__toString(),
            ));
        }

        return $code;
    }

    /**
     * The model VAT category and rate for this code. Zero and exempt codes yield rate 0, "np I" yields no rate.
     *
     * @return array{VatCategory, ?BigDecimal}
     */
    public function toModel(): array
    {
        return match ($this) {
            self::Rate23, self::Rate22, self::Rate8, self::Rate7, self::Rate5, self::Rate4, self::Rate3 => [VatCategory::StandardRate, BigDecimal::of($this->value)],
            self::ZeroDomestic => [VatCategory::ZeroRated, BigDecimal::zero()],
            self::ZeroIntraCommunity => [VatCategory::IntraCommunitySupply, BigDecimal::zero()],
            self::ZeroExport => [VatCategory::ExportOutsideEu, BigDecimal::zero()],
            self::Exempt => [VatCategory::Exempt, BigDecimal::zero()],
            self::ReverseCharge, self::EuServicesArticle100 => [VatCategory::ReverseCharge, BigDecimal::zero()],
            self::OutsideTerritory => [VatCategory::NotSubjectToVat, null],
        };
    }

    /**
     * Standard-rate codes that have a summary bucket (4 % and 3 % have none and are refused).
     */
    private static function standardRate(?BigDecimal $rate): ?self
    {
        if ($rate === null) {
            return null;
        }

        foreach ([self::Rate23, self::Rate22, self::Rate8, self::Rate7, self::Rate5] as $code) {
            if ($rate->isEqualTo($code->value)) {
                return $code;
            }
        }

        return null;
    }
}

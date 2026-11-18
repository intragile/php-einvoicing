<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * The FA(3) invoice-level VAT summary buckets: each case is the suffix of a P_13_x net amount element.
 *
 * 1: basic rate (23 % or 22 %); 2: first reduced rate (8 % or 7 %); 3: second reduced rate (5 %);
 * 4: taxi lump sum; 5: OSS special procedure; 6_1: domestic 0 %; 6_2: intra-Community supply 0 %;
 * 6_3: export 0 %; 7: exempt; 8: supplies outside the country; 9: services under art. 100(1)(4);
 * 10: domestic reverse charge; 11: margin schemes.
 */
enum VatBucket: string
{
    case Basic = '1';
    case Reduced1 = '2';
    case Reduced2 = '3';
    case TaxiLumpSum = '4';
    case Oss = '5';
    case ZeroDomestic = '6_1';
    case ZeroIntraCommunity = '6_2';
    case ZeroExport = '6_3';
    case Exempt = '7';
    case OutsideTerritory = '8';
    case EuServicesArticle100 = '9';
    case ReverseCharge = '10';
    case Margin = '11';

    /**
     * Name of the net amount element (P_13_x).
     */
    public function netElement(): string
    {
        return 'P_13_'.$this->value;
    }

    /**
     * Name of the VAT amount element (P_14_x), or null when the bucket carries no tax amount.
     */
    public function vatElement(): ?string
    {
        return match ($this) {
            self::Basic, self::Reduced1, self::Reduced2, self::TaxiLumpSum, self::Oss => 'P_14_'.$this->value,
            default => null,
        };
    }

    /**
     * Name of the VAT-in-PLN element (P_14_xW) used on invoices in a foreign currency, or null.
     */
    public function plnVatElement(): ?string
    {
        return match ($this) {
            self::Basic, self::Reduced1, self::Reduced2, self::TaxiLumpSum => 'P_14_'.$this->value.'W',
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;

/**
 * FA(3) payment form (FormaPlatnosci, XSD type TFormaPlatnosci).
 */
enum PaymentForm: int
{
    case Cash = 1;
    case Card = 2;
    case Voucher = 3;
    case Cheque = 4;
    case Credit = 5;
    case Transfer = 6;
    case Mobile = 7;

    /**
     * The form for a payment means code (BT-81), or null when no form fits and the payment is
     * written as "other" with a description.
     */
    public static function fromMeansCode(PaymentMeansCode $code): ?self
    {
        return match ($code) {
            PaymentMeansCode::C_10 => self::Cash,
            PaymentMeansCode::C_48, PaymentMeansCode::C_54, PaymentMeansCode::C_55 => self::Card,
            PaymentMeansCode::C_20 => self::Cheque,
            PaymentMeansCode::C_30, PaymentMeansCode::C_31, PaymentMeansCode::C_42, PaymentMeansCode::C_58 => self::Transfer,
            default => null,
        };
    }

    /**
     * The payment means code (BT-81) a reader assigns to this form; forms without an
     * equivalent map to 1 (instrument not defined).
     */
    public function meansCode(): PaymentMeansCode
    {
        return match ($this) {
            self::Cash => PaymentMeansCode::C_10,
            self::Card => PaymentMeansCode::C_48,
            self::Cheque => PaymentMeansCode::C_20,
            self::Transfer => PaymentMeansCode::C_30,
            self::Voucher, self::Credit, self::Mobile => PaymentMeansCode::C_1,
        };
    }
}

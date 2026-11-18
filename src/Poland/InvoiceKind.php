<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;

/**
 * FA(3) invoice kind (RodzajFaktury, XSD type TRodzajFaktury).
 */
enum InvoiceKind: string
{
    /** Basic VAT invoice. */
    case Vat = 'VAT';
    /** Corrective invoice. */
    case Kor = 'KOR';
    /** Advance payment invoice. */
    case Zal = 'ZAL';
    /** Settlement invoice closing earlier advance payment invoices. */
    case Roz = 'ROZ';
    /** Simplified invoice (art. 106e(5)(3) of the VAT Act). */
    case Upr = 'UPR';
    /** Correction of an advance payment invoice. */
    case KorZal = 'KOR_ZAL';
    /** Correction of a settlement invoice. */
    case KorRoz = 'KOR_ROZ';

    public function isCorrection(): bool
    {
        return match ($this) {
            self::Kor, self::KorZal, self::KorRoz => true,
            default => false,
        };
    }

    /**
     * The kind implied by an invoice type code (BT-3), or null when the code needs an explicit kind.
     */
    public static function fromTypeCode(InvoiceTypeCode $code): ?self
    {
        return match ($code) {
            InvoiceTypeCode::C_380 => self::Vat,
            InvoiceTypeCode::C_381, InvoiceTypeCode::C_384 => self::Kor,
            InvoiceTypeCode::C_386 => self::Zal,
            default => null,
        };
    }

    /**
     * The invoice type code (BT-3) a reader assigns to this kind. Corrections map to 384
     * (corrected invoice), since FA(3) carries signed differences rather than a credit note.
     */
    public function typeCode(): InvoiceTypeCode
    {
        return match ($this) {
            self::Vat, self::Roz, self::Upr => InvoiceTypeCode::C_380,
            self::Kor, self::KorZal, self::KorRoz => InvoiceTypeCode::C_384,
            self::Zal => InvoiceTypeCode::C_386,
        };
    }
}

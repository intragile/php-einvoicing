<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * ClaveTipoFacturaType: the type of invoice.
 */
enum InvoiceType: string
{
    case F1 = 'F1';
    case F2 = 'F2';
    case F3 = 'F3';
    case R1 = 'R1';
    case R2 = 'R2';
    case R3 = 'R3';
    case R4 = 'R4';
    case R5 = 'R5';

    /**
     * True for the corrective invoice types R1 to R5.
     */
    public function isCorrective(): bool
    {
        return match ($this) {
            self::R1, self::R2, self::R3, self::R4, self::R5 => true,
            self::F1, self::F2, self::F3 => false,
        };
    }
}

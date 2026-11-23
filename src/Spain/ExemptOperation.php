<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * OperacionExentaType: the legal ground of an exempt operation.
 */
enum ExemptOperation: string
{
    case E1 = 'E1';
    case E2 = 'E2';
    case E3 = 'E3';
    case E4 = 'E4';
    case E5 = 'E5';
    case E6 = 'E6';
    case E7 = 'E7';
    case E8 = 'E8';

    /**
     * E7 and E8 exist for IGIC only; E1 to E6 apply to IVA, IPSI and IGIC. No exemption applies to
     * other taxes.
     */
    public function allowedFor(TaxType $tax): bool
    {
        return match ($tax) {
            TaxType::Igic => true,
            TaxType::Iva, TaxType::Ipsi => $this !== self::E7 && $this !== self::E8,
            TaxType::Other => false,
        };
    }
}

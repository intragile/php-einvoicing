<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * IdOperacionesTrascendenciaTributariaType: the tax regime or operation of significance key.
 */
enum RegimeKey: string
{
    case Key01 = '01';
    case Key02 = '02';
    case Key03 = '03';
    case Key04 = '04';
    case Key05 = '05';
    case Key06 = '06';
    case Key07 = '07';
    case Key08 = '08';
    case Key09 = '09';
    case Key10 = '10';
    case Key11 = '11';
    case Key14 = '14';
    case Key15 = '15';
    case Key17 = '17';
    case Key18 = '18';
    case Key19 = '19';
    case Key20 = '20';
    case Key21 = '21';

    /**
     * Whether the key is admitted for the tax (AEAT validation list L8A for IVA, L8B for IGIC;
     * IPSI admits 01, 08, 11, 18, 19 and 20; other taxes admit none).
     */
    public function allowedFor(TaxType $tax): bool
    {
        return match ($tax) {
            TaxType::Iva => $this !== self::Key21,
            TaxType::Igic => true,
            TaxType::Ipsi => in_array($this, [self::Key01, self::Key08, self::Key11, self::Key18, self::Key19, self::Key20], true),
            TaxType::Other => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) procedure marking of a line (Procedura, XSD type TOznaczenieProcedury).
 */
enum Procedure: string
{
    /** Intra-Community distance sale of goods from another member state, or OSS electronic services. */
    case WSTO_EE = 'WSTO_EE';
    /** Supply of goods by an electronic interface facilitating the supply (deemed supplier). */
    case IED = 'IED';
    /** Supply of goods by the second taxpayer in a triangular transaction. */
    case TT_D = 'TT_D';
    /** Import under customs procedure 42. */
    case I_42 = 'I_42';
    /** Import under customs procedure 63. */
    case I_63 = 'I_63';
    /** Transfer of a single-purpose voucher by a taxpayer acting in its own name. */
    case B_SPV = 'B_SPV';
    /** Supply of goods or services the single-purpose voucher relates to. */
    case B_SPV_DOSTAWA = 'B_SPV_DOSTAWA';
    /** Brokerage or other service on the transfer of a multi-purpose voucher. */
    case B_MPV_PROWIZJA = 'B_MPV_PROWIZJA';

    /**
     * Whether the marking is allowed on an order line (ZamowienieWiersz, XSD type TOznaczenieProceduryZ),
     * which excludes the import procedures.
     */
    public function allowedInOrder(): bool
    {
        return match ($this) {
            self::I_42, self::I_63 => false,
            default => true,
        };
    }
}

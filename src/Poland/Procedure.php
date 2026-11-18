<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) procedure marking of a line (Procedura, XSD type TOznaczenieProcedury).
 */
enum Procedure: string
{
    /**
     * Intra-Community distance sale of goods located in Poland when their dispatch or transport starts,
     * and telecommunications, broadcasting and electronic services (art. 28k of the VAT Act) to
     * non-taxable persons established in another member state.
     */
    case WSTO_EE = 'WSTO_EE';
    /**
     * Supply of goods under art. 7a(1) and (2) of the VAT Act by the taxpayer facilitating it, who does
     * not use the OSS or IOSS special procedures, with Poland as the place of supply.
     */
    case IED = 'IED';
    /**
     * Supply of goods outside the country by the second taxpayer in a simplified triangular transaction
     * (chapter XII section 8 of the VAT Act).
     */
    case TT_D = 'TT_D';
    /** Intra-Community supply of goods following their import under customs procedure 42. */
    case I_42 = 'I_42';
    /** Intra-Community supply of goods following their import under customs procedure 63. */
    case I_63 = 'I_63';
    /** Transfer of a single-purpose voucher by a taxpayer acting in its own name, taxed under art. 8a(1). */
    case B_SPV = 'B_SPV';
    /**
     * Supply of the goods or services a single-purpose voucher relates to, to the taxpayer who issued
     * the voucher (art. 8a(4) of the VAT Act).
     */
    case B_SPV_DOSTAWA = 'B_SPV_DOSTAWA';
    /** Brokerage or other service on the transfer of a multi-purpose voucher, taxed under art. 8b(2). */
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

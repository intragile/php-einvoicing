<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * ImpuestoType: the tax a breakdown line is subject to.
 */
enum TaxType: string
{
    /** IVA, value added tax. */
    case Iva = '01';

    /** IPSI, the Ceuta and Melilla tax. */
    case Ipsi = '02';

    /** IGIC, the Canary Islands general indirect tax. */
    case Igic = '03';

    /** Other taxes. */
    case Other = '05';
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * The kinds of message other than EN 16931 invoices that a validator checks: the French reform
 * lifecycle messages and e-reporting transmissions, the Polish FA(3) structured invoice, the KSeF
 * official receipt (UPO) and the Spanish VERI*FACTU billing records.
 */
enum MessageType: string
{
    /** A CDAR (Cross Domain Acknowledgement And Response) invoice lifecycle message. */
    case Cdar = 'cdar';

    /** A Flux 10 e-reporting transmission of transactions or payments. */
    case Flux10 = 'flux10';

    /** A Polish FA(3) structured invoice (KSeF). */
    case Fa3 = 'fa3';

    /** A KSeF official receipt (UPO, Urzędowe Poświadczenie Odbioru). */
    case Upo = 'upo';

    /** A Spanish VERI*FACTU billing record (RegistroAlta, RegistroAnulacion) or a submission of them. */
    case Verifactu = 'verifactu';
}

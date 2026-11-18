<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation;

/**
 * The kinds of message other than EN 16931 invoices that a validator checks: the French reform
 * lifecycle messages and e-reporting transmissions, and the Polish FA(3) structured invoice.
 */
enum MessageType: string
{
    /** A CDAR (Cross Domain Acknowledgement And Response) invoice lifecycle message. */
    case Cdar = 'cdar';

    /** A Flux 10 e-reporting transmission of transactions or payments. */
    case Flux10 = 'flux10';

    /** A Polish FA(3) structured invoice (KSeF). */
    case Fa3 = 'fa3';
}

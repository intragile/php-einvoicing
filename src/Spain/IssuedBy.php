<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * TercerosODestinatarioType: who issued an invoice other than the taxpayer.
 */
enum IssuedBy: string
{
    case Recipient = 'D';
    case ThirdParty = 'T';
}

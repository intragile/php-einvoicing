<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * GeneradoPorType: who generated the billing record.
 */
enum GeneratedBy: string
{
    case Issuer = 'E';
    case Recipient = 'D';
    case ThirdParty = 'T';
}

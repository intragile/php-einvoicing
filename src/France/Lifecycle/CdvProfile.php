<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Guideline identifier of a lifecycle message (compte rendu de vie, CDV).
 */
enum CdvProfile: string
{
    case Invoice = 'urn.cpro.gouv.fr:1p0:CDV:invoice';
    case EinvoicingF2 = 'urn.cpro.gouv.fr:1p0:CDV:einvoicingF2';
}

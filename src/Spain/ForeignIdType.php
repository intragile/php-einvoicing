<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * PersonaFisicaJuridicaIDTypeType: the kind of identifier of a party without a Spanish NIF.
 */
enum ForeignIdType: string
{
    /** VAT identification number of another EU member state. */
    case VatNumber = '02';
    case Passport = '03';
    case OfficialDocument = '04';
    case ResidenceCertificate = '05';
    case OtherDocument = '06';
    case NotRegistered = '07';
}

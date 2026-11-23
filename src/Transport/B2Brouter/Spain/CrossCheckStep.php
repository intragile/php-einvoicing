<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * The steps of a cross-check of a B2Brouter VERI*FACTU record, in the order they run.
 */
enum CrossCheckStep: int
{
    /** The record XML is present, safe, valid against SuministroInformacion.xsd and readable. */
    case Schema = 1;

    /** The Huella recomputes from the record's hash fields and equals the report's fingerprint. */
    case Fingerprint = 2;

    /** RegistroAnterior names the previous report's record and its fingerprint. */
    case Chain = 3;

    /** The record carries what our own engine maps the invoice to. */
    case Semantics = 4;

    /** The QR image holds the verification URL of the record. */
    case Qr = 5;
}

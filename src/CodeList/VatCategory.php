<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-17), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * VAT category codes (UNTDID 5305 subset, BT-95, BT-102, BT-118, BT-151).
 */
enum VatCategory: string
{
    case ReverseCharge = 'AE';
    case CanaryIslands = 'L';
    case CeutaAndMelilla = 'M';
    case Exempt = 'E';
    case StandardRate = 'S';
    case ZeroRated = 'Z';
    case ExportOutsideEu = 'G';
    case NotSubjectToVat = 'O';
    case IntraCommunitySupply = 'K';
    case TransferredVat = 'B';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

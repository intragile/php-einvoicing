<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-19), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * Allowance reason codes (UNTDID 5189 subset, BT-98 and BT-140).
 */
enum AllowanceReasonCode: string
{
    case C_41 = '41';
    case C_42 = '42';
    case C_60 = '60';
    case C_62 = '62';
    case C_63 = '63';
    case C_64 = '64';
    case C_65 = '65';
    case C_66 = '66';
    case C_67 = '67';
    case C_68 = '68';
    case C_70 = '70';
    case C_71 = '71';
    case C_88 = '88';
    case C_95 = '95';
    case C_100 = '100';
    case C_102 = '102';
    case C_103 = '103';
    case C_104 = '104';
    case C_105 = '105';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

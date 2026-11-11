<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-01), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * Invoice and credit note type codes (UNTDID 1001 subset, BT-3).
 */
enum InvoiceTypeCode: string
{
    case C_71 = '71';
    case C_80 = '80';
    case C_81 = '81';
    case C_82 = '82';
    case C_83 = '83';
    case C_84 = '84';
    case C_102 = '102';
    case C_130 = '130';
    case C_202 = '202';
    case C_203 = '203';
    case C_204 = '204';
    case C_211 = '211';
    case C_218 = '218';
    case C_219 = '219';
    case C_261 = '261';
    case C_262 = '262';
    case C_295 = '295';
    case C_296 = '296';
    case C_308 = '308';
    case C_325 = '325';
    case C_326 = '326';
    case C_331 = '331';
    case C_380 = '380';
    case C_381 = '381';
    case C_382 = '382';
    case C_383 = '383';
    case C_384 = '384';
    case C_385 = '385';
    case C_386 = '386';
    case C_387 = '387';
    case C_388 = '388';
    case C_389 = '389';
    case C_390 = '390';
    case C_393 = '393';
    case C_394 = '394';
    case C_395 = '395';
    case C_396 = '396';
    case C_420 = '420';
    case C_456 = '456';
    case C_457 = '457';
    case C_458 = '458';
    case C_471 = '471';
    case C_472 = '472';
    case C_473 = '473';
    case C_500 = '500';
    case C_501 = '501';
    case C_502 = '502';
    case C_503 = '503';
    case C_527 = '527';
    case C_532 = '532';
    case C_553 = '553';
    case C_575 = '575';
    case C_623 = '623';
    case C_633 = '633';
    case C_751 = '751';
    case C_780 = '780';
    case C_817 = '817';
    case C_870 = '870';
    case C_875 = '875';
    case C_876 = '876';
    case C_877 = '877';
    case C_935 = '935';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }

    /**
     * Whether the code is one of the credit note type codes of BR-CL-01 (a UBL CreditNote).
     */
    public function isCreditNote(): bool
    {
        return match ($this) {
            self::C_81, self::C_83, self::C_261, self::C_262, self::C_296, self::C_308, self::C_381, self::C_396, self::C_420, self::C_458, self::C_502, self::C_503, self::C_532 => true,
            default => false,
        };
    }
}

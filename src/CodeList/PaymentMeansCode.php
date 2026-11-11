<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-16), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * Payment means type codes (UNTDID 4461, BT-81).
 */
enum PaymentMeansCode: string
{
    case C_1 = '1';
    case C_2 = '2';
    case C_3 = '3';
    case C_4 = '4';
    case C_5 = '5';
    case C_6 = '6';
    case C_7 = '7';
    case C_8 = '8';
    case C_9 = '9';
    case C_10 = '10';
    case C_11 = '11';
    case C_12 = '12';
    case C_13 = '13';
    case C_14 = '14';
    case C_15 = '15';
    case C_16 = '16';
    case C_17 = '17';
    case C_18 = '18';
    case C_19 = '19';
    case C_20 = '20';
    case C_21 = '21';
    case C_22 = '22';
    case C_23 = '23';
    case C_24 = '24';
    case C_25 = '25';
    case C_26 = '26';
    case C_27 = '27';
    case C_28 = '28';
    case C_29 = '29';
    case C_30 = '30';
    case C_31 = '31';
    case C_32 = '32';
    case C_33 = '33';
    case C_34 = '34';
    case C_35 = '35';
    case C_36 = '36';
    case C_37 = '37';
    case C_38 = '38';
    case C_39 = '39';
    case C_40 = '40';
    case C_41 = '41';
    case C_42 = '42';
    case C_43 = '43';
    case C_44 = '44';
    case C_45 = '45';
    case C_46 = '46';
    case C_47 = '47';
    case C_48 = '48';
    case C_49 = '49';
    case C_50 = '50';
    case C_51 = '51';
    case C_52 = '52';
    case C_53 = '53';
    case C_54 = '54';
    case C_55 = '55';
    case C_56 = '56';
    case C_57 = '57';
    case C_58 = '58';
    case C_59 = '59';
    case C_60 = '60';
    case C_61 = '61';
    case C_62 = '62';
    case C_63 = '63';
    case C_64 = '64';
    case C_65 = '65';
    case C_66 = '66';
    case C_67 = '67';
    case C_68 = '68';
    case C_69 = '69';
    case C_70 = '70';
    case C_74 = '74';
    case C_75 = '75';
    case C_76 = '76';
    case C_77 = '77';
    case C_78 = '78';
    case C_91 = '91';
    case C_92 = '92';
    case C_93 = '93';
    case C_94 = '94';
    case C_95 = '95';
    case C_96 = '96';
    case C_97 = '97';
    case C_98 = '98';
    case ZZZ = 'ZZZ';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

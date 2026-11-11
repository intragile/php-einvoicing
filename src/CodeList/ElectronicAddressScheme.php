<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-25), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * Electronic address scheme identifiers (CEF EAS, BT-34 and BT-49).
 */
enum ElectronicAddressScheme: string
{
    case C_0002 = '0002';
    case C_0007 = '0007';
    case C_0009 = '0009';
    case C_0037 = '0037';
    case C_0060 = '0060';
    case C_0088 = '0088';
    case C_0096 = '0096';
    case C_0097 = '0097';
    case C_0106 = '0106';
    case C_0130 = '0130';
    case C_0135 = '0135';
    case C_0142 = '0142';
    case C_0147 = '0147';
    case C_0151 = '0151';
    case C_0154 = '0154';
    case C_0158 = '0158';
    case C_0170 = '0170';
    case C_0177 = '0177';
    case C_0183 = '0183';
    case C_0184 = '0184';
    case C_0188 = '0188';
    case C_0190 = '0190';
    case C_0191 = '0191';
    case C_0192 = '0192';
    case C_0193 = '0193';
    case C_0194 = '0194';
    case C_0195 = '0195';
    case C_0196 = '0196';
    case C_0198 = '0198';
    case C_0199 = '0199';
    case C_0200 = '0200';
    case C_0201 = '0201';
    case C_0202 = '0202';
    case C_0203 = '0203';
    case C_0204 = '0204';
    case C_0205 = '0205';
    case C_0208 = '0208';
    case C_0209 = '0209';
    case C_0210 = '0210';
    case C_0211 = '0211';
    case C_0212 = '0212';
    case C_0213 = '0213';
    case C_0215 = '0215';
    case C_0216 = '0216';
    case C_0217 = '0217';
    case C_0218 = '0218';
    case C_0221 = '0221';
    case C_0225 = '0225';
    case C_0230 = '0230';
    case C_0235 = '0235';
    case C_0240 = '0240';
    case C_0244 = '0244';
    case C_0242 = '0242';
    case C_0245 = '0245';
    case C_0246 = '0246';
    case C_0248 = '0248';
    case C_9910 = '9910';
    case C_9913 = '9913';
    case C_9914 = '9914';
    case C_9915 = '9915';
    case C_9918 = '9918';
    case C_9919 = '9919';
    case C_9920 = '9920';
    case C_9922 = '9922';
    case C_9923 = '9923';
    case C_9924 = '9924';
    case C_9925 = '9925';
    case C_9926 = '9926';
    case C_9927 = '9927';
    case C_9928 = '9928';
    case C_9929 = '9929';
    case C_9930 = '9930';
    case C_9931 = '9931';
    case C_9932 = '9932';
    case C_9933 = '9933';
    case C_9934 = '9934';
    case C_9935 = '9935';
    case C_9936 = '9936';
    case C_9937 = '9937';
    case C_9938 = '9938';
    case C_9939 = '9939';
    case C_9940 = '9940';
    case C_9941 = '9941';
    case C_9942 = '9942';
    case C_9943 = '9943';
    case C_9944 = '9944';
    case C_9945 = '9945';
    case C_9946 = '9946';
    case C_9947 = '9947';
    case C_9948 = '9948';
    case C_9949 = '9949';
    case C_9950 = '9950';
    case C_9951 = '9951';
    case C_9952 = '9952';
    case C_9953 = '9953';
    case C_9957 = '9957';
    case C_9959 = '9959';
    case C_9960 = '9960';
    case AN = 'AN';
    case AQ = 'AQ';
    case AS = 'AS';
    case AU = 'AU';
    case EM = 'EM';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

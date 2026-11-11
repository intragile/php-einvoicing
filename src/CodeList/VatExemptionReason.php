<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-22), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * VAT exemption reason codes (CEF VATEX, BT-121).
 */
enum VatExemptionReason: string
{
    case VATEX_EU_79_C = 'VATEX-EU-79-C';
    case VATEX_EU_132 = 'VATEX-EU-132';
    case VATEX_EU_132_1A = 'VATEX-EU-132-1A';
    case VATEX_EU_132_1B = 'VATEX-EU-132-1B';
    case VATEX_EU_132_1C = 'VATEX-EU-132-1C';
    case VATEX_EU_132_1D = 'VATEX-EU-132-1D';
    case VATEX_EU_132_1E = 'VATEX-EU-132-1E';
    case VATEX_EU_132_1F = 'VATEX-EU-132-1F';
    case VATEX_EU_132_1G = 'VATEX-EU-132-1G';
    case VATEX_EU_132_1H = 'VATEX-EU-132-1H';
    case VATEX_EU_132_1I = 'VATEX-EU-132-1I';
    case VATEX_EU_132_1J = 'VATEX-EU-132-1J';
    case VATEX_EU_132_1K = 'VATEX-EU-132-1K';
    case VATEX_EU_132_1L = 'VATEX-EU-132-1L';
    case VATEX_EU_132_1M = 'VATEX-EU-132-1M';
    case VATEX_EU_132_1N = 'VATEX-EU-132-1N';
    case VATEX_EU_132_1O = 'VATEX-EU-132-1O';
    case VATEX_EU_132_1P = 'VATEX-EU-132-1P';
    case VATEX_EU_132_1Q = 'VATEX-EU-132-1Q';
    case VATEX_EU_135_1 = 'VATEX-EU-135-1';
    case VATEX_EU_143 = 'VATEX-EU-143';
    case VATEX_EU_143_1A = 'VATEX-EU-143-1A';
    case VATEX_EU_143_1B = 'VATEX-EU-143-1B';
    case VATEX_EU_143_1C = 'VATEX-EU-143-1C';
    case VATEX_EU_143_1D = 'VATEX-EU-143-1D';
    case VATEX_EU_143_1E = 'VATEX-EU-143-1E';
    case VATEX_EU_143_1F = 'VATEX-EU-143-1F';
    case VATEX_EU_143_1FA = 'VATEX-EU-143-1FA';
    case VATEX_EU_143_1G = 'VATEX-EU-143-1G';
    case VATEX_EU_143_1H = 'VATEX-EU-143-1H';
    case VATEX_EU_143_1I = 'VATEX-EU-143-1I';
    case VATEX_EU_143_1J = 'VATEX-EU-143-1J';
    case VATEX_EU_143_1K = 'VATEX-EU-143-1K';
    case VATEX_EU_143_1L = 'VATEX-EU-143-1L';
    case VATEX_EU_144 = 'VATEX-EU-144';
    case VATEX_EU_146_1E = 'VATEX-EU-146-1E';
    case VATEX_EU_159 = 'VATEX-EU-159';
    case VATEX_EU_309 = 'VATEX-EU-309';
    case VATEX_EU_148 = 'VATEX-EU-148';
    case VATEX_EU_148_A = 'VATEX-EU-148-A';
    case VATEX_EU_148_B = 'VATEX-EU-148-B';
    case VATEX_EU_148_C = 'VATEX-EU-148-C';
    case VATEX_EU_148_D = 'VATEX-EU-148-D';
    case VATEX_EU_148_E = 'VATEX-EU-148-E';
    case VATEX_EU_148_F = 'VATEX-EU-148-F';
    case VATEX_EU_148_G = 'VATEX-EU-148-G';
    case VATEX_EU_151 = 'VATEX-EU-151';
    case VATEX_EU_151_1A = 'VATEX-EU-151-1A';
    case VATEX_EU_151_1AA = 'VATEX-EU-151-1AA';
    case VATEX_EU_151_1B = 'VATEX-EU-151-1B';
    case VATEX_EU_151_1C = 'VATEX-EU-151-1C';
    case VATEX_EU_151_1D = 'VATEX-EU-151-1D';
    case VATEX_EU_151_1E = 'VATEX-EU-151-1E';
    case VATEX_EU_G = 'VATEX-EU-G';
    case VATEX_EU_O = 'VATEX-EU-O';
    case VATEX_EU_IC = 'VATEX-EU-IC';
    case VATEX_EU_AE = 'VATEX-EU-AE';
    case VATEX_EU_D = 'VATEX-EU-D';
    case VATEX_EU_F = 'VATEX-EU-F';
    case VATEX_EU_I = 'VATEX-EU-I';
    case VATEX_EU_J = 'VATEX-EU-J';
    case VATEX_FR_FRANCHISE = 'VATEX-FR-FRANCHISE';
    case VATEX_FR_CNWVAT = 'VATEX-FR-CNWVAT';
    case VATEX_EU_153 = 'VATEX-EU-153';
    case VATEX_FR_CGI261_1 = 'VATEX-FR-CGI261-1';
    case VATEX_FR_CGI261_2 = 'VATEX-FR-CGI261-2';
    case VATEX_FR_CGI261_3 = 'VATEX-FR-CGI261-3';
    case VATEX_FR_CGI261_4 = 'VATEX-FR-CGI261-4';
    case VATEX_FR_CGI261_5 = 'VATEX-FR-CGI261-5';
    case VATEX_FR_CGI261_7 = 'VATEX-FR-CGI261-7';
    case VATEX_FR_CGI261_8 = 'VATEX-FR-CGI261-8';
    case VATEX_FR_CGI261A = 'VATEX-FR-CGI261A';
    case VATEX_FR_CGI261B = 'VATEX-FR-CGI261B';
    case VATEX_FR_CGI261C_1 = 'VATEX-FR-CGI261C-1';
    case VATEX_FR_CGI261C_2 = 'VATEX-FR-CGI261C-2';
    case VATEX_FR_CGI261C_3 = 'VATEX-FR-CGI261C-3';
    case VATEX_FR_CGI261D_1 = 'VATEX-FR-CGI261D-1';
    case VATEX_FR_CGI261D_1BIS = 'VATEX-FR-CGI261D-1BIS';
    case VATEX_FR_CGI261D_2 = 'VATEX-FR-CGI261D-2';
    case VATEX_FR_CGI261D_3 = 'VATEX-FR-CGI261D-3';
    case VATEX_FR_CGI261D_4 = 'VATEX-FR-CGI261D-4';
    case VATEX_FR_CGI261E_1 = 'VATEX-FR-CGI261E-1';
    case VATEX_FR_CGI261E_2 = 'VATEX-FR-CGI261E-2';
    case VATEX_FR_CGI277A = 'VATEX-FR-CGI277A';
    case VATEX_FR_CGI275 = 'VATEX-FR-CGI275';
    case VATEX_FR_298SEXDECIESA = 'VATEX-FR-298SEXDECIESA';
    case VATEX_FR_CGI295 = 'VATEX-FR-CGI295';
    case VATEX_FR_AE = 'VATEX-FR-AE';
    case VATEX_ES_N1 = 'VATEX-ES-N1';
    case VATEX_ES_N2 = 'VATEX-ES-N2';
    case VATEX_ES_IVA20 = 'VATEX-ES-IVA20';
    case VATEX_ES_IVA21 = 'VATEX-ES-IVA21';
    case VATEX_ES_IVA22 = 'VATEX-ES-IVA22';
    case VATEX_ES_IVA23_24 = 'VATEX-ES-IVA23/24';
    case VATEX_ES_IVA25 = 'VATEX-ES-IVA25';
    case VATEX_ES_IVA140BIS = 'VATEX-ES-IVA140BIS';
    case VATEX_ES_OTROS = 'VATEX-ES-OTROS';
    case VATEX_ES_IGIC1TR = 'VATEX-ES-IGIC1TR';
    case VATEX_ES_IGIC11 = 'VATEX-ES-IGIC11';
    case VATEX_ES_IGIC12 = 'VATEX-ES-IGIC12';
    case VATEX_ES_IGIC13 = 'VATEX-ES-IGIC13';
    case VATEX_ES_IGIC25 = 'VATEX-ES-IGIC25';
    case VATEX_ES_IGIC47 = 'VATEX-ES-IGIC47';
    case VATEX_ES_IGIC90 = 'VATEX-ES-IGIC90';
    case VATEX_ES_IPSI7 = 'VATEX-ES-IPSI7';
    case VATEX_ES_IPSI8 = 'VATEX-ES-IPSI8';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

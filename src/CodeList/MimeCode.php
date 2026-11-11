<?php

declare(strict_types=1);

/*
 * Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit.
 *
 * Source (BR-CL-24), regenerate with bin/generate-codelists.php:
 *   tests/fixtures/official/cen-1.3.17/ubl/schematron/codelist/EN16931-UBL-codes.sch
 *   tests/fixtures/official/cen-1.3.17/cii/schematron/codelist/EN16931-CII-codes.sch
 */

namespace IntrAgile\EInvoicing\CodeList;

/**
 * Attachment MIME codes (BT-125).
 */
enum MimeCode: string
{
    case application_pdf = 'application/pdf';
    case image_png = 'image/png';
    case image_jpeg = 'image/jpeg';
    case text_csv = 'text/csv';
    case application_vnd_openxmlformats_officedocument_spreadsheetml_sheet = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case application_vnd_oasis_opendocument_spreadsheet = 'application/vnd.oasis.opendocument.spreadsheet';

    public static function isValid(string $code): bool
    {
        return self::tryFrom($code) !== null;
    }
}

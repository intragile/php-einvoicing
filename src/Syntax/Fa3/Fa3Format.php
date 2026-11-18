<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

/**
 * The identifiers of the Polish structured invoice FA(3), schema version 1-0E: the namespaces
 * and the values of the KodFormularza and WariantFormularza header elements.
 */
final class Fa3Format
{
    /** Target namespace of the FA(3) schema. */
    public const NS = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** Namespace of the shared Ministry of Finance type definitions (prefix "etd"). */
    public const ETD_NS = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/';

    /** The kodSystemowy attribute of KodFormularza. */
    public const SYSTEM_CODE = 'FA (3)';

    /** The wersjaSchemy attribute of KodFormularza. */
    public const SCHEMA_VERSION = '1-0E';

    /** The text of KodFormularza. */
    public const FORM_CODE = 'FA';

    /** The text of WariantFormularza. */
    public const FORM_VARIANT = '3';

    /** Local name of the document element. */
    public const ROOT = 'Faktura';

    /** The schema instance namespace (prefix "xsi"). */
    public const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Member state codes FA(3) accepts as the prefix of an EU VAT number (XSD type TKodyKrajowUE).
     * Greece is EL and Northern Ireland XI, as in VAT numbers.
     */
    public const EU_VAT_PREFIXES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'EL', 'HR', 'HU', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'XI',
    ];

    /**
     * Fixed keys of the additional descriptions (DodatkowyOpis/Klucz) that carry EN 16931 text
     * terms FA(3) has no element for, per business term.
     */
    public const DESCRIPTION_KEYS = [
        'BT-10' => 'Referencja nabywcy (BT-10)',
        'BT-11' => 'Projekt (BT-11)',
        'BT-14' => 'Zamówienie sprzedaży (BT-14)',
        'BT-15' => 'Przyjęcie dostawy (BT-15)',
        'BT-17' => 'Przetarg lub partia (BT-17)',
        'BT-19' => 'Konto księgowe nabywcy (BT-19)',
        'BT-20' => 'Warunki płatności (BT-20)',
        'BT-22' => 'Uwagi (BT-22)',
        'BT-83' => 'Tytuł płatności (BT-83)',
        'BT-127' => 'Uwagi do pozycji (BT-127)',
        'BT-154' => 'Opis (BT-154)',
    ];

    private function __construct() {}
}

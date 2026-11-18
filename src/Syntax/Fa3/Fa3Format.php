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

    private function __construct() {}
}

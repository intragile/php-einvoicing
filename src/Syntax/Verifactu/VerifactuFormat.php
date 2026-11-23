<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

/**
 * The identifiers of the AEAT VERI*FACTU record and submission schemas, release tikeV1.0.
 */
final class VerifactuFormat
{
    /** Target namespace of SuministroInformacion.xsd: the record types and the submission header. */
    public const SF_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /** Target namespace of SuministroLR.xsd: the RegFactuSistemaFacturacion submission. */
    public const LR_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    /** Prefix of SF_NS, as in the AEAT examples. */
    public const SF_PREFIX = 'sum1';

    /** Prefix of LR_NS, as in the AEAT examples. */
    public const LR_PREFIX = 'sum';

    /** The text of IDVersion. */
    public const ID_VERSION = '1.0';

    /** The text of TipoHuella: SHA-256. */
    public const HASH_TYPE = '01';

    /** The schema release the writers follow. */
    public const RELEASE = 'tikeV1.0';

    private function __construct() {}
}

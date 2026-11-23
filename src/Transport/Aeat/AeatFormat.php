<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * The identifiers of the AEAT VERI*FACTU web service wire format: SOAP 1.1 and the namespaces of
 * the response and query schemas.
 */
final class AeatFormat
{
    /** SOAP 1.1 envelope namespace. */
    public const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    /** Prefix of SOAP_NS, as in the AEAT examples. */
    public const SOAP_PREFIX = 'soapenv';

    /** Target namespace of RespuestaSuministro.xsd: the response to a submission. */
    public const RESPONSE_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';

    /** Target namespace of ConsultaLR.xsd: the record query. */
    public const QUERY_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';

    /** Target namespace of RespuestaConsultaLR.xsd: the response to a record query. */
    public const QUERY_RESPONSE_NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd';

    /** Prefix of QUERY_NS, as in the AEAT examples. */
    public const QUERY_PREFIX = 'con';

    /** Content type of a SOAP 1.1 request. */
    public const CONTENT_TYPE = 'text/xml; charset=utf-8';

    private function __construct() {}
}

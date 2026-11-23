<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * EstadoRegistro of a record returned by a record query (RespuestaConsultaLR.xsd).
 */
enum LookupState: string
{
    case Correcto = 'Correcto';
    case AceptadoConErrores = 'AceptadoConErrores';
    /** The registered invoice has been cancelled. */
    case Anulado = 'Anulado';
}

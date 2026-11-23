<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * EstadoRegistro of a submission response line (RespuestaSuministro.xsd).
 */
enum RecordState: string
{
    case Correcto = 'Correcto';
    /** Registered, with an error the issuer must remedy; the line carries its code. */
    case AceptadoConErrores = 'AceptadoConErrores';
    case Incorrecto = 'Incorrecto';
}

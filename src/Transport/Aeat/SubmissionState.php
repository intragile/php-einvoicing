<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * EstadoEnvio of a submission response (RespuestaSuministro.xsd): the state of the submission as
 * a whole.
 */
enum SubmissionState: string
{
    /** The header and every record are correct. */
    case Correcto = 'Correcto';
    /** At least one record is incorrect; see the response lines. */
    case ParcialmenteCorrecto = 'ParcialmenteCorrecto';
    /** Every record is incorrect, or the submission was refused as a whole. */
    case Incorrecto = 'Incorrecto';
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

/**
 * EstadoRegistroDuplicado (SuministroInformacion.xsd): the state of the record AEAT already holds
 * when it rejects a submitted record as a duplicate.
 */
enum DuplicateState: string
{
    case Correcta = 'Correcta';
    case AceptadaConErrores = 'AceptadaConErrores';
    case Anulada = 'Anulada';
}

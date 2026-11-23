<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * CalificacionOperacionType: the qualification of a taxed or non-subject operation.
 */
enum OperationQualification: string
{
    /** Subject and not exempt, without reverse charge. */
    case S1 = 'S1';

    /** Subject and not exempt, with reverse charge. */
    case S2 = 'S2';

    /** Not subject, articles 7 and 14 and others. */
    case N1 = 'N1';

    /** Not subject, by localisation rules. */
    case N2 = 'N2';
}

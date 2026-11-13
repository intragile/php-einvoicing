<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

/**
 * PDF/A-3 relationship of an embedded file to the PDF document (the /AFRelationship key).
 */
enum AfRelationship: string
{
    /** The file holds the data the visual representation is built from. */
    case Data = 'Data';

    /** The file is an equivalent representation of the document's content. */
    case Alternative = 'Alternative';

    /** The file is the original source the document was created from. */
    case Source = 'Source';
}

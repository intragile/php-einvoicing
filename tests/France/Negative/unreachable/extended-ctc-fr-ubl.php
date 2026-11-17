<?php

declare(strict_types=1);

// Rules of fr-extended-ctc-fr-ubl no document can make fire: rule id => reason.

return [
    'BR-FREXT-24' => 'The assert needs an invoice line without cbc:LineExtensionAmount, which the UBL 2.1 schema requires on every line '
        .'(removing it leaves the document XSD-invalid); only an XSD-invalid document reaches it.',
    'BR-FREXT-54-1' => 'The assert needs a cac:AdditionalItemProperty with neither cbc:Name nor cbc:NameCode, but the UBL 2.1 schema requires cbc:Name '
        .'in that element (a property without it is XSD-invalid); only an XSD-invalid document reaches it.',
    'BR-FREXT-12' => 'Cannot fire: both sides of the comparison select the same GROUP lines (same two predicates), one counting the lines and the other '
        .'their cbc:LineExtensionAmount children, and the schema allows exactly one such child per line, so the counts are always equal.',
];

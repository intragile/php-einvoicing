<?php

declare(strict_types=1);

// Rules of fr-extended-ctc-fr-cii no document can make fire: rule id => reason.

return [
    'BR-EXT-CL-07' => 'Cannot fire: the rule context is ram:ReferenceTypeCode and its test starts with not(self::ram:ContractReferencedDocument). '
        .'The context node is the ReferenceTypeCode element itself, never a ContractReferencedDocument, so that disjunct is always true '
        .'and the assert cannot fail on any document (a ContractReferencedDocument with an invalid code such as XXX passes).',
];

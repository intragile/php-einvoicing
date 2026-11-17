<?php

declare(strict_types=1);

// Rules of fr-flux2-cii no document can make fire: rule id => reason.

$lineReference = 'The rule context is an ram:AdditionalReferencedDocument directly below ram:IncludedSupplyChainTradeLineItem, '
    .'which the CII D22B schema does not allow there (line object references sit in ram:SpecifiedLineTradeSettlement); '
    .'only an XSD-invalid document reaches it.';

return [
    'BR-FR-30_BT-128' => 'The rule counts ram:AdditionalReferencedDocument children of ram:IncludedSupplyChainTradeLineItem, '
        .'which the CII D22B schema does not allow there (line object references sit in ram:SpecifiedLineTradeSettlement); '
        .'only an XSD-invalid document reaches it.',
    'BR-FR-30_AFL' => $lineReference,
    'BR-FR-30_AVV' => $lineReference,
    'BR-FR-DEC-01_BT-111' => 'Its rule has the same context as the rule of BR-FR-DEC-01_BT-110 (every header ram:TaxTotalAmount) '
        .'and follows it in the pattern BR-FR-DEC-01; a Schematron node is handled by the first matching rule of a pattern, '
        .'so this assert is never evaluated.',
];

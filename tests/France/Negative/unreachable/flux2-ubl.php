<?php

declare(strict_types=1);

// Rules of fr-flux2-ubl no document can make fire: rule id => reason.

$itemUnderInvoice = 'The rule context is a cac:Item (or one of its children) directly below the Invoice or CreditNote root, '
    .'which the UBL 2.1 schema does not allow (items sit in cac:InvoiceLine and cac:CreditNoteLine); '
    .'only an XSD-invalid document reaches it.';
$singleId = 'The rule counts cbc:ID children of one %s, but the UBL 2.1 schema allows a single cbc:ID there; '
    .'a count above 1 needs an XSD-invalid document.';
$singleLocationId = 'The rule needs two cbc:ID children in one cac:DeliveryLocation, but the UBL 2.1 schema (LocationType) allows a single cbc:ID; '
    .'only an XSD-invalid document reaches it.';

return [
    'BR-FR-27_BG-32' => $itemUnderInvoice,
    'BR-FR-27_BT-160' => $itemUnderInvoice,
    'BR-FR-27_EXT-FR-FE-159' => $itemUnderInvoice,
    'BR-FR-29_BT-18' => sprintf($singleId, 'cac:AdditionalDocumentReference'),
    'BR-FR-30_BT-128' => sprintf($singleId, 'cac:DocumentReference'),
    'BR-FR-09_EXT-FR-FE-146' => 'The rule context is cac:InvoiceLine/cac:Delivery/cac:DeliveryLocation, but its siret variable reads '
        .'cac:DeliveryLocation/cbc:ID below that context, a nested cac:DeliveryLocation the UBL 2.1 schema does not allow; '
        .'the variable is always empty, so the assert (not($siret) or ...) is always true for a schema-valid document.',
    'BR-FR-CO-10_BT-71-2' => $singleLocationId,
    'BR-FR-CO-10_EXT-FR-FE-146-2' => $singleLocationId,
    'BR-FR-16_BT-103' => 'Its rule has the same context as the rule of BR-FR-16_BT-96 (every cac:AllowanceCharge/cac:TaxCategory) '
        .'and follows it in the pattern BR-FR-16; a Schematron node is handled by the first matching rule of a pattern, '
        .'so this assert is never evaluated.',
    'BR-FR-DEC-01_BT-111' => 'Its rule has the same context as the rule of BR-FR-DEC-01_BT-110 (every cac:TaxTotal/cbc:TaxAmount) '
        .'and follows it in the pattern BR-FR-DEC-01; a Schematron node is handled by the first matching rule of a pattern, '
        .'so this assert is never evaluated.',
    'BR-FR-MV-10_EXT-FR-FE-184' => 'The assert is not(../cbc:TaxInclusiveLineExtensionAmount) or ..., but cbc:TaxInclusiveLineExtensionAmount '
        .'is not an element of the UBL 2.1 InvoiceLine or CreditNoteLine; it is always absent in a schema-valid document, so the assert holds.',
];

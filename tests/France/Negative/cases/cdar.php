<?php

declare(strict_types=1);

// Recipes for fr-cdar; the format is documented on NegativeCorpusTest.

$message = '/rsm:CrossDomainAcknowledgementAndResponse';
$context = "{$message}/rsm:ExchangedDocumentContext";
$exchanged = "{$message}/rsm:ExchangedDocument";
$sender = "{$exchanged}/ram:SenderTradeParty";
$issuer = "{$exchanged}/ram:IssuerTradeParty";
$recipient = "{$exchanged}/ram:RecipientTradeParty";
$acknowledgement = "{$message}/rsm:AcknowledgementDocument";
$reference = "{$acknowledgement}/ram:ReferenceReferencedDocument";
$referenceIssuer = "{$reference}/ram:IssuerTradeParty";
$referenceRecipient = "{$reference}/ram:RecipientTradeParty";
$status = "{$reference}/ram:SpecifiedDocumentStatus";
$referenceCode = "{$reference}/ram:ProcessConditionCode";
$statusCode = "{$status}/ram:ProcessConditionCode";
$reason = "{$status}/ram:ReasonCode";

$recipe = static fn (string $rule, array $edits): array => [
    'set' => 'cdar',
    'rule' => $rule,
    'base' => 'fixture:cdar-base',
    'edits' => $edits,
];
// A reason code the status list (CL-09) does not allow for one process condition code: the invoice
// status and the status of the reason block both carry that code.
$reasonFor = static fn (string $rule, string $code, string $reasonCode, array $extra = []): array => $recipe($rule, [
    [$referenceCode, 'set', $code],
    [$statusCode, 'set', $code],
    [$reason, 'set', $reasonCode],
    ...$extra,
]);

return [
    $recipe('BR-FR-04_MDT-91', [["{$reference}/ram:TypeCode", 'set', '999']]),
    $recipe('BR-FR-CDV-02_MDT-3', [["{$context}/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID", 'set', 'urn:example:other']]),
    $recipe('BR-FR-CDV-03_MDT-4', [["{$exchanged}/ram:ID", 'remove']]),
    $recipe('BR-FR-CDV-07_MDT-38_yes', [["{$issuer}/ram:GlobalID", 'remove']]),
    $recipe('BR-FR-CDV-08_MDT-73', [
        ["{$recipient}/ram:RoleCode", 'set', 'BY'],
        ["{$recipient}/ram:URIUniversalCommunication", 'remove'],
    ]),
    $recipe('BR-FR-CDV-09_MDT-77', [["{$acknowledgement}/ram:TypeCode", 'set', '99']]),
    $recipe('BR-FR-CDV-11_MDG-35', [["{$reference}/ram:FormattedIssueDateTime", 'remove']]),
    $recipe('BR-FR-CDV-12_MDT-105', [[$referenceCode, 'remove']]),
    $recipe('BR-FR-CDV-13_MDT-129', [["{$referenceIssuer}/ram:GlobalID", 'remove']]),
    $recipe('BR-FR-CDV-13_MDT-129-2', [["{$referenceIssuer}/ram:GlobalID/@schemeID", 'set', '0009']]),
    $recipe('BR-FR-CDV-14_MDT-207', [[$referenceCode, 'set', '212']]),
    $recipe('BR-FR-CDV-15_MDT-113', [[$reason, 'remove']]),
    $recipe('BR-FR-CDV-16_MDT-124-2', [["{$status}/ram:SequenceNumeric", 'remove']]),

    $recipe('BR-FR-CDV-CL-01_MDT-2', [["{$context}/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID", 'set', 'UNKNOWN_FRAMEWORK']]),
    $recipe('BR-FR-CDV-CL-02_MDT-21_305', [
        ["{$acknowledgement}/ram:TypeCode", 'set', '305'],
        ["{$sender}/ram:RoleCode", 'set', 'BY'],
    ]),
    $recipe('BR-FR-CDV-CL-02_MDT-21_23', [["{$sender}/ram:RoleCode", 'set', 'XX']]),
    $recipe('BR-FR-CDV-CL-03_MDT-40_305', [
        ["{$acknowledgement}/ram:TypeCode", 'set', '305'],
        ["{$issuer}/ram:RoleCode", 'set', 'BY'],
    ]),
    $recipe('BR-FR-CDV-CL-03_MDT-40_23', [["{$issuer}/ram:RoleCode", 'set', 'XX']]),
    $recipe('BR-FR-CDV-CL-04_MDT-59', [["{$recipient}/ram:RoleCode", 'set', 'XX']]),
    $recipe('BR-FR-CDV-CL-05_MDT-88_305', [
        ["{$acknowledgement}/ram:TypeCode", 'set', '305'],
        ["{$reference}/ram:StatusCode", 'set', '46'],
    ]),
    $recipe('BR-FR-CDV-CL-05_MDT-88_23', [["{$reference}/ram:StatusCode", 'set', '99']]),
    $recipe('BR-FR-CDV-CL-06_MDT-105', [[$referenceCode, 'set', '999']]),
    $recipe('BR-FR-CDV-CL-06_MDT-115', [[$statusCode, 'set', '999']]),
    $recipe('BR-FR-CDV-CL-07_MDT-132', [["{$referenceIssuer}/ram:RoleCode", 'set', 'BY']]),
    $recipe('BR-FR-CDV-CL-08_MDT-158', [["{$referenceRecipient}/ram:RoleCode", 'set', 'XX']]),
    $recipe('BR-FR-CDV-CL-09_MDT-113', [[$reason, 'set', 'UNKNOWN_REASON']]),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_200', '200', 'TX_TVA_ERR'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_213', '213', 'TX_TVA_ERR'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_210', '210', 'DEST_INC'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_210B2G', '210', 'DEST_INC', [
        ["{$sender}/ram:RoleCode", 'before', '<ram:GlobalID schemeID="0238">9999</ram:GlobalID>'],
    ]),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_207', '207', 'JUSTIF_ABS'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_206', '206', 'TX_TVA_ERR'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_208', '208', 'TX_TVA_ERR'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_221', '221', 'TX_TVA_ERR'),
    $reasonFor('BR-FR-CDV-CL-09_MDT-113_501', '501', 'TX_TVA_ERR'),
    $recipe('BR-FR-CDV-CL-10_MDT-121', [["{$status}/ram:RequestedActionCode", 'set', 'XXX']]),
    $recipe('BR-FR-CDV-CL-11_MDT-207', [["{$status}/ram:SpecifiedDocumentCharacteristic/ram:TypeCode", 'set', 'XXX']]),
];

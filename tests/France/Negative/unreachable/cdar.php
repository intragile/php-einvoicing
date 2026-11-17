<?php

declare(strict_types=1);

// Rules of fr-cdar no document can make fire: rule id => reason.

$presence = static fn (string $context): string => "The rule context is {$context} and the test is \".\", the context node itself. "
    .'A node that exists in the document always has a true effective boolean value, even with empty text, '
    .'so the assert cannot fail (an empty value was tried and does not fire; removing the element removes the context).';

return [
    'BR-FR-CDV-01_MDG-3' => $presence('ram:GuidelineSpecifiedDocumentContextParameter'),
    'BR-FR-CDV-04_MDG-4' => $presence('rsm:ExchangedDocument/ram:IssueDateTime'),
    'BR-FR-CDV-05_MDG-9' => $presence('rsm:ExchangedDocument/ram:SenderTradeParty'),
    'BR-FR-CDV-06_MDT-21' => $presence('rsm:ExchangedDocument/ram:SenderTradeParty/ram:RoleCode'),
    'BR-FR-CDV-10_MDT-87' => $presence('ram:ReferenceReferencedDocument/ram:IssuerAssignedID'),
    'BR-FR-CDV-07_MDT-38_no' => 'Dead rule: it has the same context as the rule of BR-FR-CDV-07_MDT-38_yes (rsm:ExchangedDocument/ram:IssuerTradeParty) '
        .'and follows it in the pattern BR-FR-CDV-07, so under first-matching-rule semantics it is never evaluated. '
        .'It also cannot fail: count() of a boolean expression is always 1, so "count(...) ge 0" is always true.',
];

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cdar;

use DOMElement;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\StatusCharacteristic;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\France\Lifecycle\StatusNote;

/**
 * Writes a French lifecycle message (CDV) as a UN/CEFACT CrossDomainAcknowledgementAndResponse
 * D22B, the syntax of the French invoice lifecycle (flux 6).
 *
 * The document has one AcknowledgementDocument with one ReferenceReferencedDocument, the invoice
 * the status is about. Elements appear in the order of the D22B schema sequences, with the
 * prefixes of the FNFE examples (rsm, ram, udt, qdt).
 *
 * Timestamps (MDT-8, MDT-78, MDT-95 and the status reference date) are written in format 204,
 * which has no time zone, in French local time. The invoice issue date (MDT-100) and
 * characteristic dates (MDT-219) are dates, written in format 102 as given. Amounts and
 * percentages keep the scale they were given with.
 */
final class CdarWriter
{
    public function write(LifecycleMessage $message): string
    {
        $output = new CdarOutput;

        $this->context($output, $message);
        $this->exchangedDocument($output, $message);
        $this->acknowledgement($output, $message);

        return $output->xml();
    }

    private function context(CdarOutput $output, LifecycleMessage $message): void
    {
        $context = $output->add($output->root, 'rsm:ExchangedDocumentContext');
        if ($message->test) {
            $output->indicator($context, 'ram:TestIndicator', true);
        }
        if ($message->scope !== null) {
            $output->text($output->add($context, 'ram:BusinessProcessSpecifiedDocumentContextParameter'), 'ram:ID', $message->scope->value);
        }
        $output->text($output->add($context, 'ram:GuidelineSpecifiedDocumentContextParameter'), 'ram:ID', $message->profile->value);
    }

    private function exchangedDocument(CdarOutput $output, LifecycleMessage $message): void
    {
        $document = $output->add($output->root, 'rsm:ExchangedDocument');
        $output->text($document, 'ram:ID', $message->id);
        $output->text($document, 'ram:Name', $message->name);
        $output->timestamp($document, 'ram:IssueDateTime', $message->issuedAt);
        $this->party($output, $document, 'ram:SenderTradeParty', $message->sender);
        $this->party($output, $document, 'ram:IssuerTradeParty', $message->issuer);
        foreach ($message->recipients as $recipient) {
            $this->party($output, $document, 'ram:RecipientTradeParty', $recipient);
        }
    }

    private function acknowledgement(CdarOutput $output, LifecycleMessage $message): void
    {
        $acknowledgement = $output->add($output->root, 'rsm:AcknowledgementDocument');
        $output->indicator($acknowledgement, 'ram:MultipleReferencesIndicator', false);
        $output->text($acknowledgement, 'ram:TypeCode', $message->acknowledgementType()->value);
        $output->timestamp($acknowledgement, 'ram:IssueDateTime', $message->statusAt);
        $this->referencedDocument($output, $acknowledgement, $message);
    }

    private function referencedDocument(CdarOutput $output, DOMElement $parent, LifecycleMessage $message): void
    {
        $invoice = $message->invoice;
        $reference = $output->add($parent, 'ram:ReferenceReferencedDocument');
        $output->text($reference, 'ram:IssuerAssignedID', $invoice->number);
        $output->text($reference, 'ram:StatusCode', $message->statusCode());
        $output->text($reference, 'ram:TypeCode', $invoice->type->value);
        $output->timestamp($reference, 'ram:ReceiptDateTime', $invoice->receivedAt);
        $output->text($reference, 'ram:ReferenceTypeCode', $invoice->referenceTypeCode);
        $output->date($reference, 'ram:FormattedIssueDateTime', $invoice->issueDate, 'qdt:DateTimeString');
        $output->text($reference, 'ram:ProcessConditionCode', $message->status->value);
        $output->text($reference, 'ram:ProcessCondition', $message->statusLabel);
        $this->invoiceIssuer($output, $reference, $invoice);
        foreach ($message->invoiceRecipients as $recipient) {
            $this->party($output, $reference, 'ram:RecipientTradeParty', $recipient);
        }
        foreach ($message->details as $detail) {
            $this->status($output, $reference, $detail);
        }
    }

    private function invoiceIssuer(CdarOutput $output, DOMElement $parent, InvoiceReference $invoice): void
    {
        if ($invoice->issuer !== null) {
            $output->identifier($output->add($parent, 'ram:IssuerTradeParty'), 'ram:GlobalID', $invoice->issuer);
        }
    }

    private function party(CdarOutput $output, DOMElement $parent, string $name, LifecycleParty $party): void
    {
        $element = $output->add($parent, $name);
        $output->identifier($element, 'ram:GlobalID', $party->globalId);
        $output->text($element, 'ram:Name', $party->name);
        $output->text($element, 'ram:RoleCode', $party->role->value);
        if ($party->uri !== null) {
            $output->identifier($output->add($element, 'ram:URIUniversalCommunication'), 'ram:URIID', $party->uri);
        }
    }

    private function status(CdarOutput $output, DOMElement $parent, StatusDetail $detail): void
    {
        $status = $output->add($parent, 'ram:SpecifiedDocumentStatus');
        $output->timestamp($status, 'ram:ReferenceDateTime', $detail->referenceDate);
        $output->text($status, 'ram:ReasonCode', $detail->reason?->value);
        $output->text($status, 'ram:Reason', $detail->reasonText);
        $output->text($status, 'ram:ProcessConditionCode', $detail->status?->value);
        $output->text($status, 'ram:RequestedActionCode', $detail->action?->value);
        $output->text($status, 'ram:RequestedAction', $detail->actionText);
        $output->text($status, 'ram:SequenceNumeric', (string) $detail->sequence);
        foreach ($detail->notes as $note) {
            $this->note($output, $status, $note);
        }
        foreach ($detail->characteristics as $characteristic) {
            $this->characteristic($output, $status, $characteristic);
        }
    }

    private function note(CdarOutput $output, DOMElement $parent, StatusNote $note): void
    {
        $element = $output->add($parent, 'ram:IncludedNote');
        $output->text($element, 'ram:ContentCode', $note->contentCode);
        $output->text($element, 'ram:Content', $note->content);
        $output->text($element, 'ram:SubjectCode', $note->subject?->value);
    }

    private function characteristic(CdarOutput $output, DOMElement $parent, StatusCharacteristic $characteristic): void
    {
        $element = $output->add($parent, 'ram:SpecifiedDocumentCharacteristic');
        $output->text($element, 'ram:ID', $characteristic->id);
        $output->text($element, 'ram:TypeCode', $characteristic->type->value);
        if ($characteristic->changed !== null) {
            $output->indicator($element, 'ram:ValueChangedIndicator', $characteristic->changed, 'udt:IndicatorString');
        }
        $output->text($element, 'ram:Name', $characteristic->name);
        $output->text($element, 'ram:Location', $characteristic->location);
        $output->decimal($element, 'ram:ValueAmount', $characteristic->amount, ['currencyID' => $characteristic->currency?->value]);
        $output->date($element, 'ram:ValueDateTime', $characteristic->date);
        $output->decimal($element, 'ram:ValuePercent', $characteristic->percent);
    }
}

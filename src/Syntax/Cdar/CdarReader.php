<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cdar;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\Lifecycle\AcknowledgementType;
use IntrAgile\EInvoicing\France\Lifecycle\CdvProfile;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RegulatoryScope;
use IntrAgile\EInvoicing\France\Lifecycle\RequestedAction;
use IntrAgile\EInvoicing\France\Lifecycle\StatusCharacteristic;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\France\Lifecycle\StatusNote;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use InvalidArgumentException;

/**
 * Reads a French lifecycle message (CDV), a UN/CEFACT CrossDomainAcknowledgementAndResponse
 * D22B, into the lifecycle model; the inverse of CdarWriter.
 *
 * The model holds one invoice per message: a document with more than one AcknowledgementDocument
 * or ReferenceReferencedDocument is unsupported, and so is a status the public portal emits for
 * another flow (250, 251, 300, 301, 400, 401, 500, 601).
 *
 * As in the invoice readers, elements that are not mapped are listed in
 * LifecycleReadResult::$ignored, and a code its list rejects leaves the term empty and is reported
 * as "(invalid code '...')"; a status detail, note or data item that lacks a term the model
 * requires is dropped and reported. Only data the message cannot do without makes a document
 * unsupported. Timestamps (format 204) are returned in French local time.
 */
final class CdarReader
{
    /**
     * Statuses the public portal emits for flows other than the invoice lifecycle, with the flow.
     */
    private const OTHER_FLOWS = [
        '250' => 'flow F1',
        '251' => 'flow F1',
        '300' => 'flow F10',
        '301' => 'flow F10',
        '400' => 'flow F13',
        '401' => 'flow F13',
        '500' => 'another flow',
        '601' => 'the CDV flow',
    ];

    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when it is not a readable French lifecycle message
     */
    public function read(string $xml): LifecycleReadResult
    {
        $d = new CdarDocument(SafeXml::load($xml));
        $root = $d->root;

        $context = $this->required($d, 'rsm:ExchangedDocumentContext', $root, 'MDT-1');
        $document = $this->required($d, 'rsm:ExchangedDocument', $root, 'MDT-4');
        $acknowledgement = $this->single($d, 'rsm:AcknowledgementDocument', $root, 'MDT-74');
        $reference = $this->single($d, 'ram:ReferenceReferencedDocument', $acknowledgement, 'MDT-87');

        $recipients = array_map(
            fn (DOMElement $party): LifecycleParty => $this->party($d, $party),
            $d->all('ram:RecipientTradeParty', $document),
        );
        if ($recipients === []) {
            throw new UnsupportedDocument(sprintf('Missing required recipient (MDT-57) in %s.', $d->path($document)));
        }
        $this->multipleReferences($d, $acknowledgement);
        $status = $this->status($d, $reference);
        $statusCode = $d->text('ram:StatusCode', $reference);
        $acknowledgementType = $d->requiredCode(AcknowledgementType::class, $d->one('ram:TypeCode', $acknowledgement), null, 'MDT-77', 'ram:TypeCode', $acknowledgement);

        try {
            $message = new LifecycleMessage(
                id: $d->requiredText('ram:ID', $document, 'MDT-4'),
                issuedAt: $d->requiredTimestamp('ram:IssueDateTime/udt:DateTimeString', $document, 'MDT-8'),
                sender: $this->party($d, $this->required($d, 'ram:SenderTradeParty', $document, 'MDT-21')),
                issuer: $this->party($d, $this->required($d, 'ram:IssuerTradeParty', $document, 'MDT-40')),
                recipients: $recipients,
                status: $status,
                invoice: $this->invoice($d, $reference),
                statusAt: $d->requiredTimestamp('ram:IssueDateTime/udt:DateTimeString', $acknowledgement, 'MDT-78'),
                profile: $d->requiredCode(CdvProfile::class, $d->one('ram:GuidelineSpecifiedDocumentContextParameter/ram:ID', $context), null, 'MDT-3', 'ram:GuidelineSpecifiedDocumentContextParameter/ram:ID', $context),
                scope: $d->code(RegulatoryScope::class, $d->one('ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID', $context)),
                name: $d->text('ram:Name', $document),
                statusLabel: $d->text('ram:ProcessCondition', $reference),
                // A value equal to the status's own is left to the default, as the factories do.
                statusCode: $statusCode === $status->statusCode() ? null : $statusCode,
                acknowledgementType: $acknowledgementType === $status->acknowledgementType() ? null : $acknowledgementType,
                details: $this->details($d, $reference),
                invoiceRecipients: array_map(
                    fn (DOMElement $party): LifecycleParty => $this->party($d, $party),
                    $d->all('ram:RecipientTradeParty', $reference),
                ),
                test: $this->indicator($d, 'ram:TestIndicator/udt:Indicator', $context) ?? false,
            );
        } catch (InvalidArgumentException $e) {
            throw new UnsupportedDocument($e->getMessage(), 0, $e);
        }

        return new LifecycleReadResult(message: $message, ignored: $d->ignored());
    }

    private function required(CdarDocument $d, string $expression, DOMElement $context, string $term): DOMElement
    {
        $element = $d->one($expression, $context) ?? throw new UnsupportedDocument(sprintf(
            'Missing required %s (%s) in %s.',
            $term,
            $expression,
            $d->path($context),
        ));
        $d->use($element);

        return $element;
    }

    /**
     * The one occurrence of an element the model holds exactly once.
     */
    private function single(CdarDocument $d, string $expression, DOMElement $context, string $term): DOMElement
    {
        $elements = $d->all($expression, $context);
        if (count($elements) > 1) {
            throw new UnsupportedDocument(sprintf(
                'Unsupported document: %d %s elements in %s; a lifecycle message reports on exactly one invoice.',
                count($elements),
                $expression,
                $d->path($context),
            ));
        }

        return $this->required($d, $expression, $context, $term);
    }

    /**
     * MDT-74 is false for the one referenced document the model holds; true is reported.
     */
    private function multipleReferences(CdarDocument $d, DOMElement $acknowledgement): void
    {
        $element = $d->one('ram:MultipleReferencesIndicator/udt:Indicator', $acknowledgement);
        if ($element !== null && $this->indicator($d, 'ram:MultipleReferencesIndicator/udt:Indicator', $acknowledgement) === true) {
            $d->reject($element, 'a message with multiple references is not supported; read as false');
        }
    }

    private function status(CdarDocument $d, DOMElement $reference): LifecycleStatus
    {
        $element = $d->one('ram:ProcessConditionCode', $reference);
        $code = $element === null ? '' : trim($element->textContent);
        if (isset(self::OTHER_FLOWS[$code])) {
            throw new UnsupportedDocument(sprintf(
                'Status %s is a public portal status of %s, not an invoice lifecycle status.',
                $code,
                self::OTHER_FLOWS[$code],
            ));
        }

        return $d->requiredCode(LifecycleStatus::class, $element, null, 'MDT-105', 'ram:ProcessConditionCode', $reference);
    }

    private function invoice(CdarDocument $d, DOMElement $reference): InvoiceReference
    {
        $issuer = $d->one('ram:IssuerTradeParty', $reference);

        return new InvoiceReference(
            number: $d->requiredText('ram:IssuerAssignedID', $reference, 'MDT-87'),
            type: $d->requiredCode(InvoiceTypeCode::class, $d->one('ram:TypeCode', $reference), null, 'MDT-91', 'ram:TypeCode', $reference),
            receivedAt: $d->requiredTimestamp('ram:ReceiptDateTime/udt:DateTimeString', $reference, 'MDT-95'),
            issueDate: $d->day('ram:FormattedIssueDateTime/qdt:DateTimeString', $reference),
            issuer: $issuer === null ? null : $d->identifier($d->one('ram:GlobalID', $issuer)),
            referenceTypeCode: $d->text('ram:ReferenceTypeCode', $reference),
        );
    }

    private function party(CdarDocument $d, DOMElement $party): LifecycleParty
    {
        return new LifecycleParty(
            globalId: $d->identifier($d->one('ram:GlobalID', $party)),
            role: $d->requiredCode(PartyRole::class, $d->one('ram:RoleCode', $party), null, 'role code', 'ram:RoleCode', $party),
            name: $d->text('ram:Name', $party),
            uri: $d->identifier($d->one('ram:URIUniversalCommunication/ram:URIID', $party)),
        );
    }

    /**
     * @return list<StatusDetail>
     */
    private function details(CdarDocument $d, DOMElement $reference): array
    {
        $details = [];
        foreach ($d->all('ram:SpecifiedDocumentStatus', $reference) as $status) {
            $detail = $this->detail($d, $status);
            if ($detail !== null) {
                $details[] = $detail;
            }
        }

        return $details;
    }

    private function detail(CdarDocument $d, DOMElement $status): ?StatusDetail
    {
        $sequenceElement = $d->one('ram:SequenceNumeric', $status);
        $sequence = $sequenceElement === null ? '' : trim($sequenceElement->textContent);
        if ($sequenceElement === null || preg_match('/^0*[1-9]\d{0,8}$/', $sequence) !== 1) {
            $d->reject($status, $sequenceElement === null
                ? 'dropped: no sequence number (MDT-124-2)'
                : "dropped: invalid sequence number '{$sequence}' (MDT-124-2)");

            return null;
        }
        $d->use($sequenceElement);

        $notes = [];
        foreach ($d->all('ram:IncludedNote', $status) as $note) {
            $notes[] = $this->note($d, $note);
        }
        $characteristics = [];
        foreach ($d->all('ram:SpecifiedDocumentCharacteristic', $status) as $characteristic) {
            $characteristics[] = $this->characteristic($d, $characteristic);
        }

        return new StatusDetail(
            sequence: (int) $sequence,
            reason: $d->code(StatusReason::class, $d->one('ram:ReasonCode', $status)),
            reasonText: $d->text('ram:Reason', $status),
            status: $d->code(LifecycleStatus::class, $d->one('ram:ProcessConditionCode', $status)),
            action: $d->code(RequestedAction::class, $d->one('ram:RequestedActionCode', $status)),
            actionText: $d->text('ram:RequestedAction', $status),
            notes: array_values(array_filter($notes)),
            characteristics: array_values(array_filter($characteristics)),
            referenceDate: $d->timestamp('ram:ReferenceDateTime/udt:DateTimeString', $status),
        );
    }

    private function note(CdarDocument $d, DOMElement $note): ?StatusNote
    {
        $content = $d->text('ram:Content', $note);
        if ($content === null) {
            $d->reject($note, 'dropped: no content (MDT-126)');

            return null;
        }

        return new StatusNote(
            content: $content,
            subject: $d->code(TextSubjectCode::class, $d->one('ram:SubjectCode', $note)),
            contentCode: $d->text('ram:ContentCode', $note),
        );
    }

    private function characteristic(CdarDocument $d, DOMElement $characteristic): ?StatusCharacteristic
    {
        $typeElement = $d->one('ram:TypeCode', $characteristic);
        $code = $typeElement === null ? '' : trim($typeElement->textContent);
        $type = CharacteristicType::tryFrom($code);
        if ($typeElement === null || $type === null) {
            $d->reject($characteristic, $typeElement === null
                ? 'dropped: no type code (MDT-207)'
                : "dropped: invalid type code '{$code}' (MDT-207)");

            return null;
        }
        $d->use($typeElement);
        $amount = $d->one('ram:ValueAmount', $characteristic);

        return new StatusCharacteristic(
            type: $type,
            amount: $amount === null ? null : $d->decimalOf($amount),
            currency: $d->code(CurrencyCode::class, $amount, 'currencyID'),
            percent: $d->decimal('ram:ValuePercent', $characteristic),
            date: $d->day('ram:ValueDateTime/udt:DateTimeString', $characteristic),
            changed: $this->indicator($d, 'ram:ValueChangedIndicator/udt:IndicatorString', $characteristic),
            id: $d->text('ram:ID', $characteristic),
            name: $d->text('ram:Name', $characteristic),
            location: $d->text('ram:Location', $characteristic),
        );
    }

    /**
     * An xsd:boolean indicator; an unrecognised value is reported and read as null.
     */
    private function indicator(CdarDocument $d, string $expression, DOMElement $context): ?bool
    {
        $element = $d->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $value = trim($element->textContent);
        $indicator = match ($value) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };
        if ($indicator === null) {
            $d->reject($element, "invalid indicator '{$value}'");

            return null;
        }
        $d->use($element);

        return $indicator;
    }
}

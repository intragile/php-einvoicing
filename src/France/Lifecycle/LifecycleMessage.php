<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A French invoice lifecycle message (compte rendu de vie, CDV) reporting one status of one
 * invoice.
 *
 * The constructor checks structure only (identifier length, at least one recipient, detail
 * sequences); the French CDAR rules are the validator's job. The message type and the status code
 * default to those of the status when not given.
 */
final readonly class LifecycleMessage
{
    /** Longest message identifier (MDT-4) the rules accept. */
    public const MAX_ID_LENGTH = 50;

    /**
     * @param  list<LifecycleParty>  $recipients  Recipients, at least one.
     * @param  list<StatusDetail>  $details  Status details, in ascending sequence order.
     * @param  list<LifecycleParty>  $invoiceRecipients  Recipients of the referenced invoice (MDT-158).
     */
    public function __construct(
        /** Message identifier (MDT-4), at most 50 characters. */
        public string $id,
        /** Time the message was created (MDT-8). */
        public DateTimeImmutable $issuedAt,
        /** Sender (role MDT-21). */
        public LifecycleParty $sender,
        /** Issuer (role MDT-40). */
        public LifecycleParty $issuer,
        public array $recipients,
        /** Status (MDT-105). */
        public LifecycleStatus $status,
        /** The invoice reported on. */
        public InvoiceReference $invoice,
        /** Time the status was set (MDT-78). */
        public DateTimeImmutable $statusAt,
        /** Guideline (MDT-3). */
        public CdvProfile $profile = CdvProfile::Invoice,
        /** Regulatory scope (MDT-2); absent in the messages sent to the public portal only. */
        public ?RegulatoryScope $scope = RegulatoryScope::Regulated,
        /** Message name (MDT-5). */
        public ?string $name = null,
        /** Status label (MDT-106). */
        public ?string $statusLabel = null,
        private ?string $statusCode = null,
        private ?AcknowledgementType $acknowledgementType = null,
        public array $details = [],
        public array $invoiceRecipients = [],
        /** Whether this is a test message. */
        public bool $test = false,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Lifecycle message identifier (MDT-4) must not be empty.');
        }

        if (mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Lifecycle message identifier (MDT-4) must be at most %d characters, %d given.',
                self::MAX_ID_LENGTH,
                mb_strlen($id),
            ));
        }

        if ($recipients === []) {
            throw new InvalidArgumentException('A lifecycle message needs at least one recipient.');
        }

        $previous = 0;
        foreach ($details as $detail) {
            if ($detail->sequence <= $previous) {
                throw new InvalidArgumentException(sprintf(
                    'Status detail sequences (MDT-124-2) must be unique and ascending; %d follows %d.',
                    $detail->sequence,
                    $previous,
                ));
            }
            $previous = $detail->sequence;
        }
    }

    /**
     * Message type (MDT-77): the one given, or else the status's own.
     */
    public function acknowledgementType(): AcknowledgementType
    {
        return $this->acknowledgementType ?? $this->status->acknowledgementType();
    }

    /**
     * Referenced document status code (MDT-88): the one given, or else the status's own.
     */
    public function statusCode(): ?string
    {
        return $this->statusCode ?? $this->status->statusCode();
    }

    public function withId(string $id): self
    {
        return $this->copy(['id' => $id]);
    }

    public function withIssuedAt(DateTimeImmutable $issuedAt): self
    {
        return $this->copy(['issuedAt' => $issuedAt]);
    }

    public function withSender(LifecycleParty $sender): self
    {
        return $this->copy(['sender' => $sender]);
    }

    public function withIssuer(LifecycleParty $issuer): self
    {
        return $this->copy(['issuer' => $issuer]);
    }

    /**
     * @param  list<LifecycleParty>  $recipients
     */
    public function withRecipients(array $recipients): self
    {
        return $this->copy(['recipients' => $recipients]);
    }

    public function withStatus(LifecycleStatus $status): self
    {
        return $this->copy(['status' => $status]);
    }

    public function withInvoice(InvoiceReference $invoice): self
    {
        return $this->copy(['invoice' => $invoice]);
    }

    public function withStatusAt(DateTimeImmutable $statusAt): self
    {
        return $this->copy(['statusAt' => $statusAt]);
    }

    public function withProfile(CdvProfile $profile): self
    {
        return $this->copy(['profile' => $profile]);
    }

    public function withScope(?RegulatoryScope $scope): self
    {
        return $this->copy(['scope' => $scope]);
    }

    public function withName(?string $name): self
    {
        return $this->copy(['name' => $name]);
    }

    public function withStatusLabel(?string $statusLabel): self
    {
        return $this->copy(['statusLabel' => $statusLabel]);
    }

    /**
     * @param  string|null  $statusCode  Null restores the status's own code.
     */
    public function withStatusCode(?string $statusCode): self
    {
        return $this->copy(['statusCode' => $statusCode]);
    }

    /**
     * @param  AcknowledgementType|null  $acknowledgementType  Null restores the status's own type.
     */
    public function withAcknowledgementType(?AcknowledgementType $acknowledgementType): self
    {
        return $this->copy(['acknowledgementType' => $acknowledgementType]);
    }

    /**
     * @param  list<StatusDetail>  $details
     */
    public function withDetails(array $details): self
    {
        return $this->copy(['details' => $details]);
    }

    /**
     * @param  list<LifecycleParty>  $invoiceRecipients
     */
    public function withInvoiceRecipients(array $invoiceRecipients): self
    {
        return $this->copy(['invoiceRecipients' => $invoiceRecipients]);
    }

    public function withTest(bool $test): self
    {
        return $this->copy(['test' => $test]);
    }

    /**
     * @param  array{
     *     id?: string, issuedAt?: DateTimeImmutable, sender?: LifecycleParty, issuer?: LifecycleParty,
     *     recipients?: list<LifecycleParty>, status?: LifecycleStatus, invoice?: InvoiceReference,
     *     statusAt?: DateTimeImmutable, profile?: CdvProfile, scope?: RegulatoryScope|null,
     *     name?: string|null, statusLabel?: string|null, statusCode?: string|null,
     *     acknowledgementType?: AcknowledgementType|null, details?: list<StatusDetail>,
     *     invoiceRecipients?: list<LifecycleParty>, test?: bool
     * }  $changes
     */
    private function copy(array $changes): self
    {
        return new self(
            id: $changes['id'] ?? $this->id,
            issuedAt: $changes['issuedAt'] ?? $this->issuedAt,
            sender: $changes['sender'] ?? $this->sender,
            issuer: $changes['issuer'] ?? $this->issuer,
            recipients: $changes['recipients'] ?? $this->recipients,
            status: $changes['status'] ?? $this->status,
            invoice: $changes['invoice'] ?? $this->invoice,
            statusAt: $changes['statusAt'] ?? $this->statusAt,
            profile: $changes['profile'] ?? $this->profile,
            scope: array_key_exists('scope', $changes) ? $changes['scope'] : $this->scope,
            name: array_key_exists('name', $changes) ? $changes['name'] : $this->name,
            statusLabel: array_key_exists('statusLabel', $changes) ? $changes['statusLabel'] : $this->statusLabel,
            statusCode: array_key_exists('statusCode', $changes) ? $changes['statusCode'] : $this->statusCode,
            acknowledgementType: array_key_exists('acknowledgementType', $changes) ? $changes['acknowledgementType'] : $this->acknowledgementType,
            details: $changes['details'] ?? $this->details,
            invoiceRecipients: $changes['invoiceRecipients'] ?? $this->invoiceRecipients,
            test: $changes['test'] ?? $this->test,
        );
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterErrors;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Timestamp;

/**
 * A VERI*FACTU tax report B2Brouter keeps for an issued Spanish invoice: the billing record
 * B2Brouter generated and sent to the AEAT as the invoicing system, with its fingerprint (Huella)
 * and the QR image of the invoice.
 *
 * "registered" is Delivered, "refused" is Failed (Validation) and "error" is Failed (Other); every
 * other state, "processing", "signed" and states this version does not know included, is Pending.
 */
final readonly class TaxReport
{
    /** The state of a report the AEAT registered. */
    private const REGISTERED = 'registered';

    /** Final states that are failures, with their category. */
    private const FAILED_STATES = [
        'refused' => FailureCategory::Validation,
        'error' => FailureCategory::Other,
    ];

    /** Bytes of a failure message kept from the report's errors. */
    private const MESSAGE_BYTES = 300;

    /**
     * @param  string  $id  the B2Brouter tax report id (digits)
     * @param  string  $state  B2Brouter's report state as received, for example "registered"
     * @param  string|null  $fingerprint  the record's fingerprint (Huella) as received
     * @param  string|null  $previousId  B2Brouter's previous_id as received; it is not a tax
     *                                   report id, so it must not be read as one
     * @param  string|null  $qrPng  the QR image, decoded from the qr field (PNG bytes)
     * @param  string|null  $recordXml  the billing record, decoded from the xml_base64 field
     * @param  array<string, mixed>  $raw  the report object as received
     */
    public function __construct(
        public string $id,
        public string $state,
        public ?string $fingerprint,
        public ?string $previousId,
        public ?string $qrPng,
        public ?string $recordXml,
        public array $raw,
    ) {}

    /**
     * Reads a tax report from a decoded B2Brouter response: the object under "tax_report" when
     * present, the body itself otherwise. Unknown fields are ignored.
     *
     * @param  array<mixed>  $body
     *
     * @throws UnsupportedDocument when the report has no positive integer id or no state, or the
     *                             qr or xml_base64 field is not Base64
     */
    public static function fromArray(array $body): self
    {
        $report = $body['tax_report'] ?? $body;
        if (! is_array($report) || ($report !== [] && array_is_list($report))) {
            throw new UnsupportedDocument('The B2Brouter tax report is not an object.');
        }
        /** @var array<string, mixed> $report */
        $id = B2BrouterErrors::id($report['id'] ?? null)
            ?? throw new UnsupportedDocument('The B2Brouter tax report has no positive integer id.');
        $state = B2BrouterErrors::text($report['state'] ?? null)
            ?? throw new UnsupportedDocument(sprintf('The B2Brouter tax report "%s" has no state.', $id));

        return new self(
            $id,
            $state,
            B2BrouterErrors::text($report['fingerprint'] ?? null),
            self::verbatim($report['previous_id'] ?? null),
            self::decoded($report, 'qr', $id),
            self::decoded($report, 'xml_base64', $id),
            $report,
        );
    }

    /**
     * Whether B2Brouter has finished with the report: registered, refused or in error.
     */
    public function isFinal(): bool
    {
        return $this->state === self::REGISTERED || isset(self::FAILED_STATES[$this->state]);
    }

    /**
     * Whether the AEAT registered the record.
     */
    public function isRegistered(): bool
    {
        return $this->state === self::REGISTERED;
    }

    /**
     * The delivery status the report's state stands for, keyed by the report id. A failure carries
     * the report's errors as its message.
     */
    public function deliveryStatus(): DeliveryStatus
    {
        $category = self::FAILED_STATES[$this->state] ?? null;
        $state = match (true) {
            $this->isRegistered() => DeliveryState::Delivered,
            $category !== null => DeliveryState::Failed,
            default => DeliveryState::Pending,
        };

        return new DeliveryStatus(
            $this->id,
            $state,
            $category === null ? null : new DeliveryFailure($category, $this->errorMessage()),
            Timestamp::parse($this->raw['updated_at'] ?? null),
        );
    }

    /**
     * The billing record B2Brouter generated, read from the decoded xml_base64 field.
     *
     * @throws UnsupportedDocument when the report carries no record XML or the XML is not a
     *                             VERI*FACTU record
     * @throws UnsafeXml when the XML carries a DOCTYPE declaration or declares entities
     * @throws InvalidRecord when a value of the record is refused by the specification
     */
    public function record(): BillingRecord
    {
        if ($this->recordXml === null) {
            throw new UnsupportedDocument(sprintf('The B2Brouter tax report "%s" carries no record XML (xml_base64).', $this->id));
        }

        return (new RecordReader)->read($this->recordXml);
    }

    /**
     * An id-like value as received: an integer as its digits, a string as given, null otherwise.
     */
    private static function verbatim(mixed $value): ?string
    {
        return match (true) {
            is_int($value) => (string) $value,
            default => B2BrouterErrors::text($value),
        };
    }

    /**
     * The decoded bytes of a Base64 field, or null when the field is absent or empty.
     *
     * @param  array<string, mixed>  $report
     *
     * @throws UnsupportedDocument when the field is not a Base64 string
     */
    private static function decoded(array $report, string $field, string $id): ?string
    {
        $value = $report[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        $bytes = is_string($value) ? base64_decode($value, true) : false;
        if ($bytes === false || $bytes === '') {
            throw new UnsupportedDocument(sprintf('The %s field of the B2Brouter tax report "%s" is not Base64.', $field, $id));
        }

        return $bytes;
    }

    /**
     * The report's errors joined with "; " and cut to MESSAGE_BYTES, or null without any. An
     * entry is a string or an object with a string "message" or "error".
     */
    private function errorMessage(): ?string
    {
        $errors = $this->raw['errors'] ?? null;
        if (! is_array($errors)) {
            return null;
        }
        $messages = [];
        foreach ($errors as $entry) {
            $text = is_array($entry)
                ? (B2BrouterErrors::text($entry['message'] ?? null) ?? B2BrouterErrors::text($entry['error'] ?? null))
                : B2BrouterErrors::text($entry);
            if ($text !== null) {
                $messages[] = $text;
            }
        }
        if ($messages === []) {
            return null;
        }
        $message = implode('; ', $messages);

        return strlen($message) > self::MESSAGE_BYTES ? mb_strcut($message, 0, self::MESSAGE_BYTES, 'UTF-8').'...' : $message;
    }
}

<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\Nip;
use InvalidArgumentException;

/**
 * The filters of a KSeF invoice metadata query or export (InvoiceQueryFilters schema): the subject
 * type, the date range (InvoiceQueryDateRange) and optionally an exact KSeF number or seller NIP.
 *
 * Incremental synchronisation must filter on the PermanentStorage date; only then does KSeF
 * report the high water mark (permanentStorageHwmDate) and honour restrictToHwm.
 */
final readonly class InvoiceQueryFilters
{
    /** Podmiot 1, the seller. */
    public const SUBJECT_SELLER = 'Subject1';

    /** Podmiot 2, the buyer. */
    public const SUBJECT_BUYER = 'Subject2';

    /** Podmiot 3, a third party named on the invoice. */
    public const SUBJECT_THIRD = 'Subject3';

    /** Podmiot upoważniony, an authorised subject. */
    public const SUBJECT_AUTHORIZED = 'SubjectAuthorized';

    public const DATE_ISSUE = 'Issue';

    public const DATE_INVOICING = 'Invoicing';

    public const DATE_PERMANENT_STORAGE = 'PermanentStorage';

    private const SUBJECT_TYPES = [self::SUBJECT_SELLER, self::SUBJECT_BUYER, self::SUBJECT_THIRD, self::SUBJECT_AUTHORIZED];

    private const DATE_TYPES = [self::DATE_ISSUE, self::DATE_INVOICING, self::DATE_PERMANENT_STORAGE];

    /** ISO 8601 with microseconds and offset: a boundary is never rounded. */
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @param  string  $subjectType  Subject1, Subject2, Subject3 or SubjectAuthorized
     * @param  string  $dateType  Issue, Invoicing or PermanentStorage
     * @param  DateTimeImmutable|null  $to  the end of the range; KSeF uses the current time when null
     * @param  bool  $restrictToHwm  limit the range to the high water mark (PermanentStorage only)
     * @param  string|null  $ksefNumber  an exact KSeF number
     * @param  string|null  $sellerNip  an exact seller NIP
     *
     * @throws InvalidArgumentException when a value is not allowed
     */
    public function __construct(
        public string $subjectType,
        public string $dateType,
        public DateTimeImmutable $from,
        public ?DateTimeImmutable $to = null,
        public bool $restrictToHwm = false,
        public ?string $ksefNumber = null,
        public ?string $sellerNip = null,
    ) {
        if (! in_array($subjectType, self::SUBJECT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The KSeF subject type must be one of %s, "%s" given.', implode(', ', self::SUBJECT_TYPES), $subjectType));
        }
        if (! in_array($dateType, self::DATE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('The KSeF date type must be one of %s, "%s" given.', implode(', ', self::DATE_TYPES), $dateType));
        }
        if ($to !== null && $to < $from) {
            throw new InvalidArgumentException('The end of the KSeF query date range is before its start.');
        }
        if ($restrictToHwm && $dateType !== self::DATE_PERMANENT_STORAGE) {
            throw new InvalidArgumentException('Restricting a KSeF query to the high water mark needs the PermanentStorage date type.');
        }
        if ($ksefNumber !== null && ! KsefNumber::isValid($ksefNumber)) {
            throw new InvalidArgumentException('The KSeF number filter is not a valid KSeF number.');
        }
        if ($sellerNip !== null && ! Nip::isWellFormed($sellerNip)) {
            throw new InvalidArgumentException('The seller NIP filter is not a ten digit NIP.');
        }
    }

    /**
     * The incremental filter of the buyer's inbox: PermanentStorage dates from the given point,
     * restricted to the high water mark.
     */
    public static function incoming(DateTimeImmutable $from): self
    {
        return new self(self::SUBJECT_BUYER, self::DATE_PERMANENT_STORAGE, $from, restrictToHwm: true);
    }

    /**
     * A copy starting at another point, as used to continue after a truncated result.
     *
     * @throws InvalidArgumentException when the new start is after the end of the range
     */
    public function withFrom(DateTimeImmutable $from): self
    {
        return new self($this->subjectType, $this->dateType, $from, $this->to, $this->restrictToHwm, $this->ksefNumber, $this->sellerNip);
    }

    /**
     * The request body (InvoiceQueryFilters).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $range = ['dateType' => $this->dateType, 'from' => $this->from->format(self::DATE_FORMAT)];
        if ($this->to !== null) {
            $range['to'] = $this->to->format(self::DATE_FORMAT);
        }
        if ($this->restrictToHwm) {
            $range['restrictToPermanentStorageHwmDate'] = true;
        }

        $json = ['subjectType' => $this->subjectType, 'dateRange' => $range];
        if ($this->ksefNumber !== null) {
            $json['ksefNumber'] = $this->ksefNumber;
        }
        if ($this->sellerNip !== null) {
            $json['sellerNip'] = $this->sellerNip;
        }

        return $json;
    }
}

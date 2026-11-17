<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use InvalidArgumentException;

/**
 * Builds the lifecycle messages of the invoice statuses.
 *
 * The message identifier defaults to the pattern of the FNFE examples,
 * "{invoice number}_{status}_{receipt time YmdHis}#{invoice type}_{issue date Ymd}", with the
 * receipt time in French local time; the issue date part is left out when the reference has no
 * issue date. When that identifier would exceed the 50 characters allowed (an invoice number of
 * more than 18 characters when the issue date part is present), the fallback
 * "{status}_{receipt time YmdHis}_{hash}" is used instead, where the hash is the first 30
 * hexadecimal characters of the SHA-256 of the full pattern; it is 49 characters long and
 * deterministic. The message creation time and the status time are both set to the given time;
 * use the withers to change them, the name or any other field.
 */
final class Lifecycle
{
    private const TIME_ZONE = 'Europe/Paris';

    private const HASH_LENGTH = 30;

    private function __construct() {}

    /**
     * Status 200 (Déposée), sent by the seller's platform to the seller and the public portal.
     */
    public static function deposited(
        InvoiceReference $invoice,
        LifecycleParty $platform,
        LifecycleParty $seller,
        DateTimeImmutable $at,
        ?string $id = null,
    ): LifecycleMessage {
        return self::status(LifecycleStatus::Deposited, $invoice, $platform, $platform, [$seller, LifecycleParty::ppf()], $at, id: $id);
    }

    /**
     * Status 213 (Rejetée), sent by a platform; the comment, when given, becomes a note.
     *
     * @param  list<LifecycleParty>  $recipients  At least one.
     */
    public static function rejected(
        InvoiceReference $invoice,
        LifecycleParty $platform,
        array $recipients,
        StatusReason $reason,
        ?string $comment,
        DateTimeImmutable $at,
        ?string $id = null,
    ): LifecycleMessage {
        $notes = $comment === null || trim($comment) === '' ? [] : [new StatusNote($comment, TextSubjectCode::AHN)];
        $detail = new StatusDetail(1, $reason, $reason->label(), notes: $notes);

        return self::status(LifecycleStatus::Rejected, $invoice, $platform, $platform, $recipients, $at, [$detail], $id);
    }

    /**
     * Status 210 (Refusée), issued by the buyer to the seller and the public portal. A comment
     * explaining the refusal is required; it becomes a note.
     *
     * @throws InvalidArgumentException When the comment is blank.
     */
    public static function refused(
        InvoiceReference $invoice,
        LifecycleParty $buyer,
        LifecycleParty $seller,
        StatusReason $reason,
        string $comment,
        DateTimeImmutable $at,
        ?RequestedAction $action = null,
        ?string $id = null,
    ): LifecycleMessage {
        if (trim($comment) === '') {
            throw new InvalidArgumentException('A refusal (status 210) needs a comment explaining it (MDT-126).');
        }

        $detail = new StatusDetail(1, $reason, $reason->label(), action: $action, notes: [new StatusNote($comment, TextSubjectCode::AHN)]);

        return self::status(LifecycleStatus::Refused, $invoice, self::anyPlatform(), $buyer, [$seller, LifecycleParty::ppf()], $at, [$detail], $id);
    }

    /**
     * Status 212 (Encaissée), issued by the seller to the buyer and the public portal, with one
     * collected amount (MEN) per VAT rate.
     *
     * @param  list<CollectedAmount>  $amounts  At least one, such as the result of Collection::allocate().
     *
     * @throws InvalidArgumentException When no amount is given.
     */
    public static function collected(
        InvoiceReference $invoice,
        LifecycleParty $seller,
        LifecycleParty $buyer,
        array $amounts,
        CurrencyCode $currency,
        DateTimeImmutable $at,
        ?string $id = null,
    ): LifecycleMessage {
        if ($amounts === []) {
            throw new InvalidArgumentException('A collection (status 212) needs at least one collected amount.');
        }

        $characteristics = array_map(
            static fn (CollectedAmount $amount): StatusCharacteristic => StatusCharacteristic::collected($amount, $currency),
            $amounts,
        );

        return self::status(
            LifecycleStatus::Collected,
            $invoice,
            self::anyPlatform(),
            $seller,
            [$buyer, LifecycleParty::ppf()],
            $at,
            [new StatusDetail(1, characteristics: $characteristics)],
            $id,
        );
    }

    /**
     * Any status.
     *
     * @param  list<LifecycleParty>  $recipients  At least one.
     * @param  list<StatusDetail>  $details
     */
    public static function status(
        LifecycleStatus $status,
        InvoiceReference $invoice,
        LifecycleParty $sender,
        LifecycleParty $issuer,
        array $recipients,
        DateTimeImmutable $at,
        array $details = [],
        ?string $id = null,
    ): LifecycleMessage {
        return new LifecycleMessage(
            id: $id ?? self::defaultId($status, $invoice),
            issuedAt: $at,
            sender: $sender,
            issuer: $issuer,
            recipients: $recipients,
            status: $status,
            invoice: $invoice,
            statusAt: $at,
            statusLabel: $status->label(),
            details: $details,
        );
    }

    /**
     * The sending platform without identification, as in the FNFE examples.
     */
    private static function anyPlatform(): LifecycleParty
    {
        return new LifecycleParty(null, PartyRole::Platform);
    }

    private static function defaultId(LifecycleStatus $status, InvoiceReference $invoice): string
    {
        $receivedAt = $invoice->receivedAt->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('YmdHis');
        $id = sprintf(
            '%s_%s_%s#%s%s',
            $invoice->number,
            $status->value,
            $receivedAt,
            $invoice->type->value,
            $invoice->issueDate === null ? '' : '_'.$invoice->issueDate->format('Ymd'),
        );

        if (mb_strlen($id) <= LifecycleMessage::MAX_ID_LENGTH) {
            return $id;
        }

        return sprintf('%s_%s_%s', $status->value, $receivedAt, substr(hash('sha256', $id), 0, self::HASH_LENGTH));
    }
}
